<?php

namespace App\Actions\User;

use App\Models\AssistanceDocument;
use Illuminate\Support\Facades\Storage;

class DeleteAssistanceDocument
{
    public function __invoke(AssistanceDocument $document): void
    {
        Storage::disk($document->disk)->delete($document->path);

        $document->delete();
    }
}
