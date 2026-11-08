<x-layouts::guest :title="__('Sign in')">
    <h1 class="text-headline font-extrabold text-primary">{{ config('app.name') }}</h1>

    @if (session('status'))
        <p role="alert" class="mt-4 flex gap-2 rounded-md bg-warning-subtle p-3 text-body text-warning">
            <x-icon.triangle-alert class="size-5 shrink-0" />{{ session('status') }}
        </p>
    @endif

    <form method="POST" action="{{ route('login') }}" class="mt-4 flex flex-col gap-4 rounded-lg bg-surface p-4">
        @csrf

        <x-field name="username" :label="__('Username')" :value="old('username')"
            required autofocus autocomplete="username" autocapitalize="none" />

        <x-field name="password" type="password" :label="__('Password')" required autocomplete="current-password">
            <button type="button" data-password-toggle aria-controls="password" aria-pressed="false" aria-label="{{ __('Show password') }}"
                class="group absolute inset-y-0 right-0 grid w-11 place-items-center rounded-md text-fg-muted">
                <x-icon.eye class="size-5 group-aria-pressed:hidden" />
                <x-icon.eye-off class="hidden size-5 group-aria-pressed:block" />
            </button>
        </x-field>

        <button type="submit" class="h-12 w-full rounded-md bg-primary px-4 text-label text-on-primary active:bg-primary-strong">
            {{ __('Sign in') }}
        </button>
    </form>
</x-layouts::guest>
