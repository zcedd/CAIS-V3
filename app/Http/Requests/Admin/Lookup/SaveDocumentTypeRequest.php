<?php

namespace App\Http\Requests\Admin\Lookup;

use App\Models\DocumentType;

class SaveDocumentTypeRequest extends SaveLookupRequest
{
    protected function lookupTable(): string
    {
        return 'document_types';
    }

    protected function ignoredId(): ?int
    {
        $documentType = $this->route('documentType');

        return $documentType instanceof DocumentType ? $documentType->id : null;
    }
}
