<?php

namespace App\Livewire\Forms;

use App\Actions\Documents\UploadDocument;
use App\Enums\DocumentType;
use App\Models\AuditLog;
use App\Models\Document;
use Illuminate\Validation\Rule;
use Livewire\Form;

class DocumentDetailsForm extends Form
{
    public string $name = '';

    public string $categoryId = '';

    public string $type = '';

    public string $summary = '';

    public function setFrom(Document $document): void
    {
        // The extension isn't editable: renaming "x.pdf" to "x.exe" must not be possible.
        $this->name = pathinfo($document->original_name, PATHINFO_FILENAME);
        $this->categoryId = (string) ($document->category_id ?? '');
        $this->type = $document->type->value;
        $this->summary = (string) $document->summary;
    }

    public function save(Document $document): Document
    {
        $this->validate([
            'name' => ['required', 'string', 'max:190'],
            'categoryId' => [
                'nullable',
                Rule::exists('categories', 'id')->where('business_id', $document->business_id),
            ],
            'type' => ['required', Rule::enum(DocumentType::class)],
            'summary' => ['nullable', 'string', 'max:2000'],
        ]);

        $extension = pathinfo($document->original_name, PATHINFO_EXTENSION);
        $name = UploadDocument::cleanName($this->name);

        if ($extension !== '' && ! str_ends_with(strtolower($name), '.'.strtolower($extension))) {
            $name .= '.'.$extension;
        }

        $document->fill([
            'original_name' => $name,
            'category_id' => $this->categoryId === '' ? null : (int) $this->categoryId,
            'type' => DocumentType::from($this->type),
            'summary' => trim($this->summary) === '' ? null : trim($this->summary),
        ]);

        $changedFields = array_keys($document->getDirty());
        $document->save();

        if ($changedFields !== []) {
            AuditLog::record('document.updated', $document, ['fields' => $changedFields]);
        }

        return $document;
    }
}
