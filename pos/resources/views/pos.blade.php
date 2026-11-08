{{-- Placeholder until the till page (Task 21). --}}
<x-layouts::guest :title="__('Sell')">
    <p class="text-headline font-extrabold text-primary">{{ config('app.name') }}</p>
    <h1 class="mt-6 text-title">{{ __('Sell') }}</h1>
    <p class="mt-2 text-body text-fg-muted">{{ __('The till screen is being prepared.') }}</p>

    <p class="mt-6 text-label">{{ auth()->user()->name }}</p>
    <form method="POST" action="{{ route('logout') }}" class="mt-2">
        @csrf
        <button type="submit" class="flex h-12 items-center gap-2 rounded-md border border-line-strong bg-surface px-4 text-label text-fg active:bg-surface-muted">
            <x-icon.log-out class="size-5" />{{ __('Sign out') }}
        </button>
    </form>
</x-layouts::guest>
