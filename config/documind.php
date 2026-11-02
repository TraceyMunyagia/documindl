<?php

return [

    // Dates are stored in UTC and displayed in this timezone.
    'display_timezone' => env('DOCUMIND_DISPLAY_TIMEZONE', 'Africa/Nairobi'),

    'default_currency' => env('DOCUMIND_DEFAULT_CURRENCY', 'KES'),

    // Used from Week 4.
    'uploads' => [
        'max_kb' => (int) env('DOCUMIND_MAX_UPLOAD_KB', 10240),
        'allowed_extensions' => ['pdf', 'jpg', 'jpeg', 'png'],
    ],

    // Sidebar items. An item becomes a link as soon as its route exists.
    'navigation' => [
        ['label' => 'Dashboard', 'icon' => 'home', 'route' => 'dashboard', 'active' => 'dashboard', 'week' => null],
        ['label' => 'Documents', 'icon' => 'documents', 'route' => 'documents.index', 'active' => 'documents.*', 'week' => 4],
        ['label' => 'Invoices', 'icon' => 'invoices', 'route' => 'invoices.index', 'active' => 'invoices.*', 'week' => 10],
        ['label' => 'Tasks', 'icon' => 'tasks', 'route' => 'tasks.index', 'active' => 'tasks.*', 'week' => 11],
        ['label' => 'Insights', 'icon' => 'insights', 'route' => 'insights.index', 'active' => 'insights.*', 'week' => 14],
    ],

];
