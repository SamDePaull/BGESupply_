<x-filament::section>
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <x-filament::avatar
                :src="Auth::user()?->profile_photo_url ?? null"
                :alt="Auth::user()?->name ?? 'User'"
                class="w-10 h-10"
            />
            <div>
                <div class="text-sm text-gray-500">Welcome</div>
                <div class="font-semibold">{{ Auth::user()?->name ?? 'User' }}</div>
            </div>
        </div>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <x-filament::button color="gray" type="submit" icon="heroicon-o-arrow-right-start-on-rectangle">
                Sign out
            </x-filament::button>
        </form>
    </div>
</x-filament::section>
