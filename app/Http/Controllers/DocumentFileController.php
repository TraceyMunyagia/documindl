<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Document;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The only way to read a stored file. Route model binding already applies
 * the business scope (another business's document is a 404), and the policy
 * is a second check.
 */
class DocumentFileController extends Controller
{
    public function show(Document $document): StreamedResponse
    {
        Gate::authorize('view', $document);

        return $this->respond($document, 'inline');
    }

    public function download(Document $document): StreamedResponse
    {
        Gate::authorize('view', $document);

        AuditLog::record('document.downloaded', $document);

        return $this->respond($document, 'attachment');
    }

    private function respond(Document $document, string $disposition): StreamedResponse
    {
        abort_unless($document->hasFile(), 404);

        $disk = Storage::disk($document->disk);

        abort_unless($disk->exists($document->path), 404);

        return $disk->response($document->path, $document->original_name, [
            'Content-Type' => $document->mime_type ?? 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff', // never let the browser guess a different type
            'Cache-Control' => 'private, no-store',
        ], $disposition);
    }
}
