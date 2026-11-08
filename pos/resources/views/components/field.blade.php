{{-- Label above, field, then the error with circle-x. Extra attributes go to the <input>; the slot sits inside the field on the right. --}}
@props(['name', 'label'])

<div class="flex flex-col gap-1">
    <label for="{{ $name }}" class="text-label">{{ $label }}</label>
    <div class="relative">
        <input id="{{ $name }}" name="{{ $name }}"
            @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
            {{ $attributes->class([
                'h-12 w-full rounded-md bg-surface pl-3 text-body',
                $slot->isEmpty() ? 'pr-3' : 'pr-12',
                $errors->has($name) ? 'border-2 border-danger' : 'border border-line-strong',
            ]) }}>
        {{ $slot }}
    </div>
    @error($name)
        <p id="{{ $name }}-error" class="flex items-start gap-1 text-body-sm text-danger">
            <x-icon.circle-x class="mt-0.5 size-4 shrink-0" />{{ $message }}
        </p>
    @enderror
</div>
