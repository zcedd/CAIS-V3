<?php

namespace App\Actions\User;

use App\Models\Assistance;
use App\Models\AssistanceDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StoreAssistanceDocument
{
    /**
     * @param  array{
     *     document_type_id: int,
     *     file: UploadedFile,
     *     notes?: string|null
     * }  $validated
     */
    public function __invoke(Assistance $assistance, User $user, array $validated): AssistanceDocument
    {
        /** @var UploadedFile $file */
        $file = $validated['file'];
        $extension = $file->getClientOriginalExtension() ?: $file->extension() ?: 'bin';
        $path = $file->storeAs(
            'assistance-documents/'.$assistance->id,
            Str::uuid()->toString().'.'.$extension,
            'local',
        );

        if ($path === false) {
            throw ValidationException::withMessages([
                'file' => 'The document could not be stored.',
            ]);
        }

        return $assistance->documents()->create([
            'document_type_id' => $validated['document_type_id'],
            'uploaded_by' => $user->id,
            'original_name' => $file->getClientOriginalName(),
            'disk' => 'local',
            'path' => $path,
            'mime_type' => $file->getMimeType() ?: $file->getClientMimeType(),
            'size' => $file->getSize(),
            'notes' => $validated['notes'] ?? null,
        ]);
    }
}
