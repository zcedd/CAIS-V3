<?php

namespace App\Actions\User;

use App\Models\Program;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StoreProgramAttachments
{
    /**
     * @param  list<UploadedFile>  $files
     */
    public function store(Program $program, User $user, array $files): void
    {
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $extension = $file->getClientOriginalExtension() ?: $file->extension() ?: 'bin';
            $path = $file->storeAs(
                'program-attachments/'.$program->id,
                Str::uuid()->toString().'.'.$extension,
                'local',
            );

            if ($path === false) {
                throw ValidationException::withMessages([
                    'attachments' => 'The attachment could not be stored.',
                ]);
            }

            $program->attachments()->create([
                'uploaded_by' => $user->id,
                'original_name' => $file->getClientOriginalName(),
                'disk' => 'local',
                'path' => $path,
                'mime_type' => $file->getMimeType() ?: $file->getClientMimeType(),
                'size' => $file->getSize() ?: 0,
            ]);
        }
    }

    /**
     * @param  list<int>  $ids
     */
    public function remove(Program $program, array $ids): void
    {
        $attachments = $program->attachments()
            ->whereIn('id', $ids)
            ->get();

        foreach ($attachments as $attachment) {
            Storage::disk($attachment->disk)->delete($attachment->path);
            $attachment->delete();
        }
    }
}
