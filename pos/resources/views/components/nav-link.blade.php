@props(['active'])

<a {{ $attributes->class(['text-body-sm', 'bg-primary-subtle text-primary-strong' => $active, 'text-fg-muted' => ! $active]) }} @if ($active) aria-current="page" @endif>{{ $slot }}</a>
