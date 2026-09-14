<?php

namespace App\Services\User;

use App\Models\UnspscCode;
use App\Support\UnspscCodeLevel;

class UnspscCodeService
{
    private const SEARCH_LIMIT = 25;

    /**
     * @return list<array{id: int, code: string, title: string, path: string, is_curated: bool}>
     */
    public function search(string $query, bool $curatedOnly = true): array
    {
        $codes = UnspscCode::query()
            ->where('level', UnspscCodeLevel::Commodity)
            ->when($curatedOnly, fn ($builder) => $builder->where('is_curated', true))
            ->when($query !== '', function ($builder) use ($query): void {
                $needle = '%'.$query.'%';
                $builder->where(function ($inner) use ($needle): void {
                    $inner->where('code', 'like', $needle)
                        ->orWhere('title', 'like', $needle);
                });
            })
            ->orderBy('code')
            ->limit(self::SEARCH_LIMIT)
            ->get(['id', 'code', 'title', 'segment_code', 'family_code', 'class_code', 'is_curated']);

        $ancestorCodes = $codes
            ->flatMap(fn (UnspscCode $code): array => array_filter([
                $code->segment_code,
                $code->family_code,
                $code->class_code,
            ]))
            ->unique()
            ->values();

        $titles = $ancestorCodes->isEmpty()
            ? collect()
            : UnspscCode::query()
                ->whereIn('code', $ancestorCodes)
                ->pluck('title', 'code');

        return $codes
            ->map(function (UnspscCode $code) use ($titles): array {
                $pathParts = array_values(array_filter([
                    $titles[$code->segment_code] ?? null,
                    $titles[$code->family_code] ?? null,
                    $titles[$code->class_code] ?? null,
                    $code->title,
                ]));

                return [
                    'id' => $code->id,
                    'code' => $code->code,
                    'title' => $code->title,
                    'path' => implode(' > ', $pathParts),
                    'is_curated' => (bool) $code->is_curated,
                ];
            })
            ->values()
            ->all();
    }
}
