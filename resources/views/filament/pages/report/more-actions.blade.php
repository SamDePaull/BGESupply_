{{-- resources/views/filament/pages/report/more-actions.blade.php --}}
<div class="space-y-2">
    <a href="{{ $previewUrl }}" target="_blank" rel="noopener"
       class="inline-flex items-center gap-2 rounded-lg border border-yellow-300 bg-yellow-50 px-3 py-2 text-sm font-medium text-yellow-800 hover:bg-yellow-100">
        <x-filament::icon name="heroicon-o-eye" class="h-4 w-4" />
        <span>Preview PDF</span>
    </a>

    <a href="{{ $downloadUrl }}" target="_blank" rel="noopener"
       class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
        <x-filament::icon name="heroicon-o-arrow-down-tray" class="h-4 w-4" />
        <span>Unduh PDF</span>
    </a>
</div>
