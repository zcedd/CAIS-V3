<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Lookup\DeleteLookupRequest;
use App\Http\Requests\Admin\Lookup\SaveDocumentTypeRequest;
use App\Models\DocumentType;
use App\Services\Admin\LookupService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DocumentTypeController extends Controller
{
    public function __construct(private LookupService $lookups) {}

    public function index(): Response
    {
        return Inertia::render('admin/document-types/index', [
            'document_types' => $this->lookups->documentTypes(),
        ]);
    }

    public function store(SaveDocumentTypeRequest $request): RedirectResponse
    {
        $this->lookups->createDocumentType($request->lookupAttributes());

        return back()->with('success', 'Document type saved.');
    }

    public function update(SaveDocumentTypeRequest $request, DocumentType $documentType): RedirectResponse
    {
        $this->lookups->updateDocumentType($documentType, $request->lookupAttributes());

        return back()->with('success', 'Document type updated.');
    }

    public function destroy(DeleteLookupRequest $request, DocumentType $documentType): RedirectResponse
    {
        $this->lookups->deleteDocumentType($documentType);

        return back()->with('success', 'Document type deleted.');
    }
}
