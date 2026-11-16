<?php

return [

    // Dates are stored in UTC and displayed in this timezone.
    'display_timezone' => env('DOCUMIND_DISPLAY_TIMEZONE', 'Africa/Nairobi'),

    'default_currency' => env('DOCUMIND_DEFAULT_CURRENCY', 'KES'),

    // Used from Week 4.
    'uploads' => [
        'disk' => env('DOCUMIND_DOCUMENTS_DISK', 'documents'),
        'max_kb' => (int) env('DOCUMIND_MAX_UPLOAD_KB', 10240),
        'max_files' => 10,
        'allowed_extensions' => ['pdf', 'jpg', 'jpeg', 'png'],
    ],
    'extraction' => [
        'pdftotext' => env('DOCUMIND_PDFTOTEXT_PATH', 'pdftotext'),
        'pdftoppm' => env('DOCUMIND_PDFTOPPM_PATH', 'pdftoppm'),
        'tesseract' => env('DOCUMIND_TESSERACT_PATH', 'tesseract'),
        'ocr_languages' => env('DOCUMIND_OCR_LANGUAGES', 'eng'),
        'ocr_max_pages' => 5,     // OCR is slow; scanned PDFs longer than this are partly read
        'ocr_dpi' => 300,         // resolution pages are rendered at before OCR
        'min_pdf_text_chars' => 40, // fewer real characters than this → treat the PDF as a scan
        'tool_timeout' => 90,     // seconds for any single tool run
    ],
    'ai' => [
        'enabled' => (bool) env('DOCUMIND_AI_ENABLED', true),
        'max_input_chars' => 30000,      // longer documents are cut, to control cost
        'max_output_tokens' => 4096,     // includes the model's reasoning ("thinking") tokens
        'request_timeout' => 60,         // seconds
        'thinking_level' => env('DOCUMIND_AI_THINKING_LEVEL') ?: null,
        'daily_calls_per_business' => (int) env('DOCUMIND_AI_DAILY_LIMIT', 200), // 0 = no limit

        // Estimates only – copy current prices (USD per million tokens) from the provider's pricing page.
        'pricing' => [
            'gemini' => [
                'input_per_million' => (float) env('GEMINI_PRICE_INPUT_PER_MILLION', 0),
                'output_per_million' => (float) env('GEMINI_PRICE_OUTPUT_PER_MILLION', 0),
            ],
        ],
    ],

    // Sidebar items. An item becomes a link as soon as its route exists.
    'navigation' => [
        ['label' => 'Dashboard', 'icon' => 'home', 'route' => 'dashboard', 'active' => 'dashboard', 'week' => null],
        ['label' => 'Documents', 'icon' => 'documents', 'route' => 'documents.index', 'active' => 'documents.*', 'week' => 4],
        ['label' => 'Categories', 'icon' => 'categories', 'route' => 'categories.index', 'active' => 'categories.*', 'week' => 5],
        ['label' => 'Invoices', 'icon' => 'invoices', 'route' => 'invoices.index', 'active' => 'invoices.*', 'week' => 10],
        ['label' => 'Tasks', 'icon' => 'tasks', 'route' => 'tasks.index', 'active' => 'tasks.*', 'week' => 11],
        ['label' => 'Insights', 'icon' => 'insights', 'route' => 'insights.index', 'active' => 'insights.*', 'week' => 14],
        ['label' => 'Team', 'icon' => 'team', 'route' => 'team.index', 'active' => 'team.*', 'week' => 3],
    ],

];
