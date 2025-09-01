@php
    /** @var string $baseUrl */
    /** @var array  $counts */
    /** @var string|null $currentStatus */
    /** @var array  $persistedQuery */

    $tabs = [
        ['key' => 'all',      'label' => 'All'],
        ['key' => 'paid',     'label' => 'Paid'],
        ['key' => 'unpaid',   'label' => 'Unpaid'],
        ['key' => 'refunded', 'label' => 'Refunded'],
        ['key' => 'void',     'label' => 'Void'],
    ];
@endphp

{{-- Wrapper full width + center --}}
<div class="mb-2 w-full">
    <div class="flex items-center justify-center">
        <div class="inline-flex flex-wrap items-center gap-1 rounded-xl border border-gray-200 bg-white p-1
                    dark:border-gray-800 dark:bg-gray-900">

            @foreach ($tabs as $t)
                @php
                    // Aktif jika: 'all' & tidak ada status di query, atau tepat sama
                    $isActive = $t['key'] === 'all'
                        ? blank($currentStatus)
                        : ($currentStatus === $t['key']);

                    // Build URL sambil mempertahankan query lain
                    $qs = $persistedQuery;
                    if ($t['key'] !== 'all') {
                        $qs['status'] = $t['key'];
                    }
                    $url = $baseUrl . (empty($qs) ? '' : ('?' . http_build_query($qs)));

                    $badge = $counts[$t['key']] ?? 0;
                @endphp

                <a href="{{ $url }}"
                   wire:navigate
                   @if($isActive) aria-current="page" @endif
                   class="group inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm font-medium
                          transition
                          {{ $isActive
                                ? 'bg-primary-600 text-white shadow-sm'
                                : 'text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-800' }}">
                    <span>{{ $t['label'] }}</span>
                    <span class="inline-flex min-w-[1.25rem] items-center justify-center rounded-full px-1.5 text-xs font-semibold
                                 {{ $isActive ? 'bg-primary-700/70 text-white' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-100' }}">
                        {{ $badge }}
                    </span>
                </a>
            @endforeach

        </div>
    </div>
</div>
