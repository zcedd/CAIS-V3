<?php

namespace App\Services\Disbursement;

use App\Enums\DisbursementEvidenceType;
use App\Enums\DisbursementTransactionStatus;
use App\Models\DisbursementEvidence;
use App\Models\DisbursementTransaction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DisbursementEvidenceService
{
    public function __construct(private DisbursementAudit $audit) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function store(
        DisbursementTransaction $transaction,
        User $actor,
        UploadedFile $file,
        DisbursementEvidenceType $type,
        array $metadata = [],
    ): DisbursementEvidence {
        if ($transaction->status !== DisbursementTransactionStatus::Draft) {
            throw ValidationException::withMessages([
                'evidence' => 'Evidence can be replaced only before the release is finalized.',
            ]);
        }

        if ($type === DisbursementEvidenceType::LivePhoto && ($metadata['capture_source'] ?? null) !== 'camera') {
            throw ValidationException::withMessages([
                'capture_source' => 'A live photo must be captured from the device camera. A gallery upload is not accepted as live evidence.',
            ]);
        }

        $contents = $file->getContent();
        $this->assertImage($contents, $file->getSize(), $type);

        $disk = (string) config('disbursement.evidence_disk', 'local');
        $extension = $type === DisbursementEvidenceType::Signature ? 'png' : $this->extension($contents);
        $path = 'disbursements/'.$transaction->disbursement_batch_id.'/'.$transaction->id.'/'.Str::uuid()->toString().'.'.$extension;
        $mime = $this->mime($contents);

        if (! Storage::disk($disk)->put($path, $contents)) {
            throw ValidationException::withMessages([
                'file' => 'The evidence file could not be stored.',
            ]);
        }

        $existing = $transaction->evidence()->where('evidence_type', $type)->first();
        $previousPath = $existing?->path;
        $previousDisk = $existing?->disk;

        $attributes = [
            'disk' => $disk,
            'path' => $path,
            'mime_type' => $mime,
            'file_size' => strlen($contents),
            'sha256_hash' => hash('sha256', $contents),
            'captured_at' => now(),
            'received_at' => now(),
            'captured_by' => $actor->id,
            'metadata' => [
                'capture_source' => $metadata['capture_source'] ?? null,
            ],
        ];

        if ($existing instanceof DisbursementEvidence) {
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
                'disbursement_transaction_id' => $transaction->id,
                'evidence_id' => $evidence->id,
                'evidence_type' => $type->value,
                'sha256_hash' => $evidence->sha256_hash,
                'file_size' => $evidence->file_size,
            ]);
        }

        return $evidence;
    }

    public function assertHash(DisbursementEvidence $evidence): bool
    {
        $contents = Storage::disk($evidence->disk)->get($evidence->path);

        if (! is_string($contents)) {
            return false;
        }

        return hash_equals($evidence->sha256_hash, hash('sha256', $contents));
    }

    private function assertImage(string $contents, int|false $size, DisbursementEvidenceType $type): void
    {
        $maxKilobytes = $type === DisbursementEvidenceType::LivePhoto
            ? (int) config('disbursement.live_photo_max_kilobytes', 5120)
            : (int) config('disbursement.signature_max_kilobytes', 2048);

        if ($size !== false && $size > ($maxKilobytes * 1024)) {
            throw ValidationException::withMessages([
                'file' => 'The evidence file is larger than the allowed size.',
            ]);
        }

        $mime = $this->mime($contents);
        $allowed = $type === DisbursementEvidenceType::LivePhoto
            ? config('disbursement.photo_mime_types', [])
            : config('disbursement.signature_mime_types', []);

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
