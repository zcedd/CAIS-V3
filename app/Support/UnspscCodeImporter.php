<?php

namespace App\Support;

use App\Models\UnspscCode;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class UnspscCodeImporter
{
    /**
     * Import UNSPSC rows from a CSV with headers:
     * code,title,level,parent_code,is_curated,version
     *
     * UNSPSC codes are maintained by GS1. Do not bundle the commercial full catalog
     * unless usage terms for the chosen version are confirmed. This importer accepts
     * a licensed or internally curated file.
     *
     * @return int Number of rows upserted
     */
    public function importFromCsv(string $path, bool $markCurated = false): int
    {
        if (! is_readable($path)) {
            throw new InvalidArgumentException("UNSPSC CSV is not readable: {$path}");
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException("Unable to open UNSPSC CSV: {$path}");
        }

        $header = fgetcsv($handle);

        if (! is_array($header)) {
            fclose($handle);
            throw new RuntimeException('UNSPSC CSV is empty.');
        }

        $header = array_map(static fn (mixed $column): string => strtolower(trim((string) $column)), $header);
        $rows = [];

        while (($data = fgetcsv($handle)) !== false) {
            if ($data === [null] || $data === false) {
                continue;
            }

            $row = [];

            foreach ($header as $index => $column) {
                $row[$column] = isset($data[$index]) ? trim((string) $data[$index]) : '';
            }

            $code = $this->normalizeCode($row['code'] ?? '');

            if ($code === null) {
                continue;
            }

            $rows[] = [
                'code' => $code,
                'title' => $row['title'] !== '' ? $row['title'] : $code,
                'level' => $this->normalizeLevel($row['level'] ?? '', $code),
                'parent_code' => $this->normalizeCode($row['parent_code'] ?? '') ?? $this->parentCodeFor($code),
                'is_curated' => $markCurated || $this->toBoolean($row['is_curated'] ?? '0'),
                'version' => $row['version'] !== '' ? $row['version'] : 'curated-2026',
            ];
        }

        fclose($handle);

        return $this->upsertRows($rows);
    }

    /**
     * @param  list<array{code: string, title: string, level: string, parent_code: string|null, is_curated: bool, version: string}>  $rows
     */
    public function upsertRows(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        $now = now();

        $ordered = collect($rows)->sortBy(fn (array $row): int => match ($row['level']) {
            UnspscCodeLevel::Segment => 1,
            UnspscCodeLevel::Family => 2,
            UnspscCodeLevel::ItemClass => 3,
            default => 4,
        })->values();

        return DB::transaction(function () use ($ordered, $now): int {
            $count = 0;

            foreach ($ordered as $row) {
                UnspscCode::query()->updateOrCreate(
                    ['code' => $row['code']],
                    [
                        'title' => $row['title'],
                        'level' => $row['level'],
                        'parent_id' => null,
                        'segment_code' => substr($row['code'], 0, 2).'000000',
                        'family_code' => $row['level'] === UnspscCodeLevel::Segment
                            ? null
                            : substr($row['code'], 0, 4).'0000',
                        'class_code' => in_array($row['level'], [UnspscCodeLevel::Segment, UnspscCodeLevel::Family], true)
                            ? null
                            : substr($row['code'], 0, 6).'00',
                        'is_curated' => $row['is_curated'],
                        'version' => $row['version'],
                        'updated_at' => $now,
                        'created_at' => $now,
                    ],
                );
                $count++;
            }

            $idsByCode = UnspscCode::query()->pluck('id', 'code');

            foreach ($ordered as $row) {
                $parentCode = $row['parent_code'];

                if ($parentCode === null || $parentCode === $row['code']) {
                    continue;
                }

                $parentId = $idsByCode[$parentCode] ?? null;

                if ($parentId === null) {
                    continue;
                }

                UnspscCode::query()
                    ->where('code', $row['code'])
                    ->update(['parent_id' => $parentId]);
            }

            return $count;
        });
    }

    public function normalizeCode(string $code): ?string
    {
        $digits = preg_replace('/\D+/', '', $code) ?? '';

        if ($digits === '') {
            return null;
        }

        return str_pad(substr($digits, 0, 8), 8, '0');
    }

    private function parentCodeFor(string $code): ?string
    {
        $level = $this->normalizeLevel('', $code);

        return match ($level) {
            UnspscCodeLevel::Commodity => substr($code, 0, 6).'00',
            UnspscCodeLevel::ItemClass => substr($code, 0, 4).'0000',
            UnspscCodeLevel::Family => substr($code, 0, 2).'000000',
            default => null,
        };
    }

    private function normalizeLevel(string $level, string $code): string
    {
        $level = strtolower(trim($level));

        if (in_array($level, UnspscCodeLevel::values(), true)) {
            return $level;
        }

        if (str_ends_with($code, '000000')) {
            return UnspscCodeLevel::Segment;
        }

        if (str_ends_with($code, '0000')) {
            return UnspscCodeLevel::Family;
        }

        if (str_ends_with($code, '00')) {
            return UnspscCodeLevel::ItemClass;
        }

        return UnspscCodeLevel::Commodity;
    }

    private function toBoolean(string $value): bool
    {
        return in_array(strtolower($value), ['1', 'true', 'yes'], true);
    }
}
