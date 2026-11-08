<div class="flex flex-col gap-4">
    <div>
        <button type="button" wire:click="create" class="h-12 rounded-md bg-primary px-4 text-label text-on-primary active:bg-primary-strong">
            {{ __('Add kasir') }}
        </button>
    </div>

    @if ($kasirs->isEmpty())
        <p class="text-body text-fg-muted">{{ __('No kasir accounts yet.') }}</p>
    @else
        {{-- Stacked rows on phones, a real table from 1024 px. --}}
        <table class="w-full bg-surface">
            <thead class="sr-only lg:not-sr-only">
                <tr class="bg-surface-muted text-left text-body-sm">
                    <th scope="col" class="h-11 px-4">{{ __('Name') }}</th>
                    <th scope="col" class="px-4">{{ __('Username') }}</th>
                    <th scope="col" class="px-4">{{ __('Status') }}</th>
                    <th scope="col" class="px-4"><span class="sr-only">{{ __('Actions') }}</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @foreach ($kasirs as $kasir)
                    <tr wire:key="kasir-{{ $kasir->id }}" class="flex flex-wrap items-center gap-x-3 gap-y-2 p-4 lg:table-row lg:p-0">
                        <td class="w-full text-label lg:h-11 lg:w-auto lg:px-4">{{ $kasir->name }}</td>
                        <td class="text-body-sm text-fg-muted lg:px-4">{{ $kasir->username }}</td>
                        <td class="lg:px-4">
                            @if ($kasir->active)
                                <span class="inline-flex items-center gap-1 rounded-sm bg-success-subtle px-2 py-0.5 text-body-sm text-success">
                                    <x-icon.circle-check class="size-4" />{{ __('Active') }}
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 rounded-sm bg-surface-muted px-2 py-0.5 text-body-sm text-fg">
                                    <x-icon.info class="size-4" />{{ __('Inactive') }}
                                </span>
                            @endif
                        </td>
                        <td class="w-full lg:w-auto lg:px-4 lg:py-1">
                            <div class="flex gap-2 lg:justify-end">
                                <button type="button" wire:click="edit({{ $kasir->id }})"
                                    class="h-12 rounded-md border border-line-strong bg-surface px-4 text-label text-fg active:bg-surface-muted lg:h-10">
                                    {{ __('Edit') }}
                                </button>
                                @if ($kasir->active)
                                    <button type="button" wire:click="confirmDeactivate({{ $kasir->id }})"
                                        class="h-12 rounded-md border border-line-strong bg-surface px-4 text-label text-fg active:bg-surface-muted lg:h-10">
                                        {{ __('Deactivate') }}
                                    </button>
                                @else
                                    <button type="button" wire:click="activate({{ $kasir->id }})"
                                        class="h-12 rounded-md border border-line-strong bg-surface px-4 text-label text-fg active:bg-surface-muted lg:h-10">
                                        {{ __('Activate') }}
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Create or edit: a full-screen view on phones, a 480 px panel on the right from 1024 px. --}}
    @if ($showForm)
        <div class="fixed inset-0 z-30 hidden bg-scrim lg:block"></div>
        <form wire:submit="save" role="dialog" aria-modal="true" aria-labelledby="staff-form-title"
            x-trap.noscroll="true" x-on:keydown.escape.window="$wire.cancel()"
            class="fixed inset-0 z-40 flex flex-col bg-surface lg:left-auto lg:w-120 lg:border-l lg:border-line-strong">
            <div class="flex h-14 shrink-0 items-center gap-2 border-b border-line px-2">
                <button type="button" wire:click="cancel" aria-label="{{ __('Back') }}" class="grid size-11 place-items-center rounded-md">
                    <x-icon.arrow-left class="size-6" />
                </button>
                <h2 id="staff-form-title" class="text-title">{{ $editingId ? __('Edit kasir') : __('Add kasir') }}</h2>
            </div>

            <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto p-4">
                <x-field name="name" :label="__('Name')" wire:model="name" autocomplete="off" />
                <x-field name="username" :label="__('Username')" wire:model="username" autocomplete="off" autocapitalize="none" />
                <x-field name="password" type="password" :label="$editingId ? __('New password (optional)') : __('Password')"
                    wire:model="password" autocomplete="new-password" />
            </div>

            <div class="shrink-0 border-t border-line bg-surface pb-safe shadow-sheet">
                <div class="flex h-20 items-center px-4">
                    <button type="submit" class="h-12 w-full rounded-md bg-primary px-4 text-label text-on-primary active:bg-primary-strong">
                        {{ __('Save') }}
                    </button>
                </div>
            </div>
        </form>
    @endif

    {{-- Deactivation is destructive, so it asks first; Escape and the scrim do not close it. --}}
    @if ($confirming)
        <div class="fixed inset-0 z-30 bg-scrim"></div>
        <div role="dialog" aria-modal="true" aria-labelledby="deactivate-title" x-trap.noscroll="true"
            class="fixed inset-x-0 bottom-0 z-40 max-h-sheet overflow-y-auto rounded-t-lg bg-surface pb-safe shadow-sheet sm:inset-x-auto sm:top-1/2 sm:bottom-auto sm:left-1/2 sm:w-120 sm:-translate-x-1/2 sm:-translate-y-1/2 sm:rounded-lg">
            <div class="flex items-start justify-between gap-2 px-4 pt-4">
                <h2 id="deactivate-title" class="pt-2 text-title">{{ __('Deactivate :name?', ['name' => $confirming->name]) }}</h2>
                <button type="button" wire:click="cancel" aria-label="{{ __('Close') }}" class="grid size-11 shrink-0 place-items-center rounded-md">
                    <x-icon.x class="size-6" />
                </button>
            </div>
            <p class="px-4 pt-2 text-body">
                {{ __(':name is signed out and can no longer sign in. You can activate the account again later.', ['name' => $confirming->name]) }}
            </p>
            <div class="flex gap-2 p-4">
                <button type="button" wire:click="cancel"
                    class="h-12 flex-1 rounded-md border border-line-strong bg-surface px-4 text-label text-fg active:bg-surface-muted">
                    {{ __('Cancel') }}
                </button>
                <button type="button" wire:click="deactivate({{ $confirming->id }})"
                    class="h-12 flex-1 rounded-md bg-danger px-4 text-label text-on-primary">
                    {{ __('Deactivate') }}
                </button>
            </div>
        </div>
    @endif
</div>
