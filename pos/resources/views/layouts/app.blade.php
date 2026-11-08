{{-- Owner shell: app bar and bottom navigation on phones, a 240 px sidebar from 1024 px. --}}
@props(['title'])

<!doctype html>
<html lang="id">
<head>
    @include('partials.head')
</head>
<body class="bg-canvas font-sans text-body text-fg antialiased">
<div class="flex h-dvh flex-col lg:flex-row">
    <aside class="hidden w-60 shrink-0 flex-col border-r border-line bg-surface lg:flex">
        <p class="flex h-16 shrink-0 items-center px-6 text-title font-extrabold text-primary">{{ config('app.name') }}</p>

        <nav aria-label="{{ __('Main menu') }}" class="flex min-h-0 flex-1 flex-col overflow-y-auto">
            <x-nav-link :href="route('home')" :active="request()->routeIs('home')" class="flex h-12 items-center gap-3 px-6">
                <x-icon.house class="size-6" />{{ __('Home') }}
            </x-nav-link>

            <p class="px-6 pt-6 pb-2 text-body-sm text-fg-muted">{{ __('Settings') }}</p>
            <x-nav-link :href="route('staff')" :active="request()->routeIs('staff')" class="flex h-12 items-center gap-3 px-6">
                <x-icon.users class="size-6" />{{ __('Staff') }}
            </x-nav-link>
        </nav>

        <form method="POST" action="{{ route('logout') }}" class="border-t border-line">
            @csrf
            <button type="submit" class="flex h-12 w-full items-center gap-3 px-6 text-body-sm text-fg-muted">
                <x-icon.log-out class="size-6" />{{ __('Sign out') }}
            </button>
        </form>
    </aside>

    <div class="flex min-h-0 flex-1 flex-col">
        <header class="flex h-14 shrink-0 items-center border-b border-line bg-surface px-4 lg:h-16 lg:px-6">
            <h1 class="text-title lg:text-headline">{{ $title }}</h1>
        </header>

        <main class="min-h-0 flex-1 overflow-y-auto p-4 lg:p-6">
            <div class="mx-auto max-w-180 lg:max-w-300">
                {{ $slot }}
            </div>
        </main>

        <nav aria-label="{{ __('Main menu') }}" class="shrink-0 border-t border-line bg-surface pb-safe shadow-sheet lg:hidden">
            <div class="grid h-16 grid-cols-3">
                <x-nav-link :href="route('home')" :active="request()->routeIs('home')" class="flex flex-col items-center justify-center gap-1">
                    <x-icon.house class="size-6" />{{ __('Home') }}
                </x-nav-link>
                <x-nav-link :href="route('staff')" :active="request()->routeIs('staff')" class="flex flex-col items-center justify-center gap-1">
                    <x-icon.users class="size-6" />{{ __('Staff') }}
                </x-nav-link>
                <form method="POST" action="{{ route('logout') }}" class="contents">
                    @csrf
                    <button type="submit" class="flex flex-col items-center justify-center gap-1 text-body-sm text-fg-muted">
                        <x-icon.log-out class="size-6" />{{ __('Sign out') }}
                    </button>
                </form>
            </div>
        </nav>
    </div>
</div>
</body>
</html>
