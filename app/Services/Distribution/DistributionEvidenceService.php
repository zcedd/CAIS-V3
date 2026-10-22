<?php

namespace App\Services\Distribution;

use App\Enums\DistributionEvidenceType;
use App\Enums\DistributionTransactionStatus;
use App\Models\DistributionEvidence;
use App\Models\DistributionTransaction;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DistributionEvidenceService
{
    public function __construct(private DistributionAudit $audit) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function store(
        DistributionTransaction $transaction,
        User $actor,
        UploadedFile $file,
        DistributionEvidenceType $type,
        array $metadata = [],
    ): DistributionEvidence {
        if ($transaction->status !== DistributionTransactionStatus::Draft) {
            throw ValidationException::withMessages([
                'evidence' => 'Evidence can be replaced only before the release is finalized.',
            ]);
        }

        if ($type === DistributionEvidenceType::LivePhoto && ($metadata['capture_source'] ?? null) !== 'camera') {
            throw ValidationException::withMessages([
                'capture_source' => 'A live photo must be captured from the device camera. A gallery upload is not accepted as live evidence.',
            ]);
        }

        $contents = $file->getContent();
        $this->assertImage($contents, $file->getSize(), $type);

        $disk = (string) config('distribution.evidence_disk', 'local');
        $extension = $type === DistributionEvidenceType::Signature ? 'png' : $this->extension($contents);
        $path = 'distributions/'.$transaction->distribution_batch_id.'/'.$transaction->id.'/'.Str::uuid()->toString().'.'.$extension;
        $mime = $this->mime($contents);

        if (! Storage::disk($disk)->put($path, $contents)) {
            throw ValidationException::withMessages([
                'file' => 'The evidence file could not be stored.',
            ]);
        }

        $existing = $transaction->evidence()->where('evidence_type', $type)->first();
        $previousPath = $existing?->path;
        $previousDisk = $existing?->disk;

        $storedMetadata = [
            'capture_source' => $metadata['capture_source'] ?? null,
        ];
        $source = $metadata['source'] ?? null;

        if (is_string($source) && $source !== '') {
            $storedMetadata['source'] = $source;
        }

        $attributes = [
            'disk' => $disk,
            'path' => $path,
            'mime_type' => $mime,
            'file_size' => strlen($contents),
            'sha256_hash' => hash('sha256', $contents),
            'captured_at' => $this->capturedAt($metadata),
            'received_at' => now(),
            'captured_by' => $actor->id,
            'metadata' => $storedMetadata,
        ];

        if ($existing instanceof DistributionEvidence) {
            $existing->update($attributes);
            $evidence = $existing->refresh();
            $event = 'evidence_replaced';
        } else {
            $evidence = $transaction->evidence()->create([
                'evidence_type' => $type,
                ...$attributes,
            ]);
            $event = 'evidence_stored';
        }

        if (is_string($previousPath) && $previousPath !== $path) {
            Storage::disk($previousDisk ?: $disk)->delete($previousPath);
        }

        $batch = $transaction->batch;

        if ($batch !== null) {
            $this->audit->record($actor, $batch, $event, [
                'distribution_transaction_id' => $transaction->id,
                'evidence_id' => $evidence->id,
                'evidence_type' => $type->value,
                'sha256_hash' => $evidence->sha256_hash,
                'file_size' => $evidence->file_size,
            ]);
        }

        return $evidence;
    }

    public function response(DistributionEvidence $evidence): StreamedResponse
    {
        return Storage::disk($evidence->disk)->response($evidence->path, null, [
            'Content-Type' => $evidence->mime_type,
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function dataUri(DistributionEvidence $evidence): ?string
    {
        $contents = Storage::disk($evidence->disk)->get($evidence->path);

        if (! is_string($contents) || $contents === '') {
            return null;
        }

        $mime = is_string($evidence->mime_type) && str_starts_with($evidence->mime_type, 'image/')
            ? $evidence->mime_type
            : 'image/jpeg';

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }

    public function assertHash(DistributionEvidence $evidence): bool
    {
        $contents = Storage::disk($evidence->disk)->get($evidence->path);

        if (! is_string($contents)) {
            return false;
        }

        return hash_equals($evidence->sha256_hash, hash('sha256', $contents));
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function capturedAt(array $metadata): DateTimeInterface
    {
        $value = $metadata['captured_at'] ?? null;

        if (! is_string($value) || trim($value) === '') {
            return now();
        }

        return Carbon::parse($value);
    }

    private function assertImage(string $contents, int|false $size, DistributionEvidenceType $type): void
    {
        $maxKilobytes = $type === DistributionEvidenceType::LivePhoto
            ? (int) config('distribution.live_photo_max_kilobytes', 5120)
            : (int) config('distribution.signature_max_kilobytes', 2048);

        if ($size !== false && $size > ($maxKilobytes * 1024)) {
            throw ValidationException::withMessages([
                'file' => 'The evidence file is larger than the allowed size.',
            ]);
        }

        $mime = $this->mime($contents);
        $allowed = $type === DistributionEvidenceType::LivePhoto
            ? config('distribution.photo_mime_types', [])
            : config('distribution.signature_mime_types', []);

        if (! in_array($mime, $allowed, true) || getimagesizefromstring($contents) === false) {
            throw ValidationException::withMessages([
                'file' => 'The evidence file must be a supported image.',
            ]);
        }
    }

    private function mime(string $contents): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($contents);

        return is_string($mime) ? $mime : 'application/octet-stream';
    }

    private function extension(string $contents): string
    {
        return match ($this->mime($contents)) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }
}
