<?php

namespace App\Http\Controllers\User;

use App\Actions\User\DeleteAssistanceDocument;
use App\Actions\User\StoreAssistanceDocument;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\Assistance\DestroyDocumentRequest;
use App\Http\Requests\User\Assistance\DownloadDocumentRequest;
use App\Http\Requests\User\Assistance\StoreDocumentRequest;
use App\Models\Assistance;
use App\Models\AssistanceDocument;
use App\Models\Department;
use App\Models\Program;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssistanceDocumentController extends Controller
{
    /**
     * Store a document attached to the assistance request.
     */
    public function store(
        StoreDocumentRequest $request,
        Department $department,
        Program $program,
        Assistance $assistance,
        StoreAssistanceDocument $storeAssistanceDocument,
    ): RedirectResponse {
        $storeAssistanceDocument($assistance, $request->user(), $request->validated());

        return redirect()
            ->back()
            ->with('success', 'Document uploaded successfully.');
    }

    /**
     * Download or preview an assistance document.
     */
    public function show(
        DownloadDocumentRequest $request,
        Department $department,
        Program $program,
        Assistance $assistance,
        AssistanceDocument $document,
    ): StreamedResponse {
        $disk = Storage::disk($document->disk);

        abort_unless($disk->exists($document->path), 404);

        return $disk->response(
            $document->path,
            $document->original_name,
            [
                'Content-Type' => $document->mime_type,
            ],
        );
    }

    /**
     * Remove an assistance document.
     */
    public function destroy(
        DestroyDocumentRequest $request,
        Department $department,
        Program $program,
        Assistance $assistance,
        AssistanceDocument $document,
        DeleteAssistanceDocument $deleteAssistanceDocument,
    ): RedirectResponse {
        $deleteAssistanceDocument($document);

        return redirect()
            ->back()
            ->with('success', 'Document removed successfully.');
    }
}
