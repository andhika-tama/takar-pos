{{-- Komentar tidak dihitung --}}
<div>
    <h1>{{ __('Daftar Barang') }}</h1>
    <p>@lang('Halo')</p>
    <input type="text" placeholder="{{ __('Cari barang') }}">
    <button type="submit" value="{{ __('Simpan') }}">{!! trans('Simpan') !!}</button>
    <img src="x.png" alt="{{ __('Logo toko') }}">
    <span>12.500 - |</span>
    <style>.a { color: red; }</style>
    <input value="{{ $item->name }}" class="x">
    <div x-show="count > 0">{{ __('Total') }}</div>
    @foreach ($items as $item)
        <li>Rp{{ $total }}</li>
    @endforeach
    @if ($open) @else {{ __('Tutup') }} @endif
    <x-card :title="$title" x-bind:title="foo">&nbsp;&times;&copy;</x-card>
    <x-input :placeholder="__('Cari')" placeholder="@lang('Cari')" name="q" wire:model="q" />
    <x-button label="{{ __('Simpan') }}" icon="check" variant="primary" class="Save" />
    <span title="@lang('Catatan')">&nbsp;</span>
    @php
        $x = 'Teks dalam php';
    @endphp
    <a href="/x" class="btn">{{ __('Kembali') }}</a>
</div>
