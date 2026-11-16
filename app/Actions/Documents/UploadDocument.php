<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Exceptions\DuplicateDocumentException;
use App\Jobs\ProcessDocument;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Document;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class UploadDocument
{
    /**
     * Expects a file that has already passed DocumentUploadRules.
     * Queues ProcessDocument to extract text and classify it in the background.
     */
    public function handle(UploadedFile $file, User $uploader, Business $business, ?int $categoryId = null): Document
    {
        $checksum = hash_file('sha256', $file->getRealPath());

        $existing = Document::query()
            ->where('business_id', $business->getKey())
            ->where('checksum', $checksum)
            ->first();

        if ($existing !== null) {
            throw DuplicateDocumentException::matching($existing);
        }

        $disk = config('documind.uploads.disk');
        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension());
        $directory = sprintf('businesses/%d/documents/%s', $business->getKey(), now()->format('Y/m'));
        $filename = Str::uuid()->toString().'.'.$extension;

        $path = $file->storeAs($directory, $filename, $disk);

        if ($path === false) {
            throw new RuntimeException('The file could not be saved.');
        }

        try {
            $document = DB::transaction(function () use ($file, $uploader, $business, $categoryId, $disk, $path, $checksum): Document {
                $document = Document::create([
                    'business_id' => $business->getKey(),
                    'category_id' => $categoryId,
                    'uploaded_by' => $uploader->getKey(),
                    'original_name' => self::cleanName($file->getClientOriginalName()),
                    'disk' => $disk,
                    'path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'size_bytes' => $file->getSize(),
                    'checksum' => $checksum,
                    'type' => DocumentType::Other,       // classified in Week 6
                    'status' => DocumentStatus::Pending, // processed in Week 6
                ]);

                AuditLog::record('document.uploaded', $document, [
                    'name' => $document->original_name,
                    'size' => $document->size_bytes,
                ]);

                return $document;
            });
        } catch (Throwable $e) {
            // The database write failed, so don't leave an orphaned file behind.
            Storage::disk($disk)->delete($path);

            throw $e;
        }

        // Dispatched after the transaction has committed, so the worker can always find the row.
        ProcessDocument::dispatch($document);

        return $document;
    }

    /** Keeps the name readable for display, but strips paths and control characters. */
    public static function cleanName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');

        return Str::limit($name !== '' ? $name : 'document', 200, '');
    }
}
