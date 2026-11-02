@props(['name'])

@switch($name)
    @case('home')
        <flux:icon.home class="size-5 shrink-0" />
        @break
    @case('documents')
        <flux:icon.document-text class="size-5 shrink-0" />
        @break
    @case('invoices')
        <flux:icon.receipt-percent class="size-5 shrink-0" />
        @break
    @case('tasks')
        <flux:icon.clipboard-document-check class="size-5 shrink-0" />
        @break
    @case('insights')
        <flux:icon.sparkles class="size-5 shrink-0" />
        @break
            @case('team')
        <flux:icon.users class="size-5 shrink-0" />
        @break
    @default
        <flux:icon.square-2-stack class="size-5 shrink-0" />
@endswitch
