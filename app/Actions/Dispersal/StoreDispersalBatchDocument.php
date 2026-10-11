<?php

namespace App\Actions\Dispersal;

use App\Models\DispersalBatch;
use App\Models\DispersalBatchDocument;
use App\Models\User;
use App\Services\Dispersal\DispersalAudit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StoreDispersalBatchDocument
{
    public function __construct(private DispersalAudit $audit) {}

    /**
     * @param  array{file: UploadedFile, document_type_id?: int|null, notes?: string|null}  $data
     */
    public function __invoke(DispersalBatch $batch, User $actor, array $data): DispersalBatchDocument
    {
        /** @var UploadedFile $file */
        $file = $data['file'];
        $extension = $file->getClientOriginalExtension() ?: $file->extension() ?: 'bin';
        $path = $file->storeAs(
            'dispersal-documents/'.$batch->id,
            Str::uuid()->toString().'.'.$extension,
            'local',
        );

        if ($path === false) {
            throw ValidationException::withMessages([
                'file' => 'The document could not be stored.',
            ]);
        }

        $document = $batch->documents()->create([
            'document_type_id' => $data['document_type_id'] ?? null,
            'uploaded_by' => $actor->id,
            'original_name' => $file->getClientOriginalName(),
            'disk' => 'local',
            'path' => $path,
            'mime_type' => $file->getMimeType() ?: $file->getClientMimeType(),
            'size' => $file->getSize() ?: 0,
            'notes' => $data['notes'] ?? null,
        ]);

        $this->audit->record($actor, $batch, 'document_added', [
            'revision' => $batch->revision,
            'document_id' => $document->id,
            'original_name' => $document->original_name,
        ]);

        return $document;
    }
}
