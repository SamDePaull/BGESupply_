<x-filament::page>
    <x-filament::section>
        <x-slot name="heading">Default Location</x-slot>
        {{ $this->form }}
        <div class="mt-4">
            <x-filament::button wire:click="save">
                Simpan
            </x-filament::button>
        </div>
    </x-filament::section>
</x-filament::page>
