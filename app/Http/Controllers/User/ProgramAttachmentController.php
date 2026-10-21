<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\Program\DownloadAttachmentRequest;
use App\Models\Program;
use App\Models\ProgramAttachment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProgramAttachmentController extends Controller
{
    /**
     * Download a file attached to a program.
     */
    public function show(
        DownloadAttachmentRequest $request,
        Program $program,
        ProgramAttachment $attachment,
    ): StreamedResponse {
        $disk = Storage::disk($attachment->disk);

        abort_unless($disk->exists($attachment->path), 404);

        return $disk->response(
            $attachment->path,
            $attachment->original_name,
            [
                'Content-Type' => $attachment->mime_type,
            ],
        );
    }
}
