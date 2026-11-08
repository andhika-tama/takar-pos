<div>
    <h1>Daftar Barang</h1>
    <p>{{ __('ok.key') }}</p>
    <input type="text" placeholder="Cari barang">
    <button type="submit" value="Simpan">{{ __('ok') }}</button>
    <img src="x.png" alt="Logo toko">
    <script>var a = "Not visible";</script>
    <x-button label="Simpan">{{ __('ok') }}</x-button>
    <x-field heading="Nama" :title="$t" class="mt-2" />
    <div x-show="count > 0">Total</div>
    <span title="Catatan">{{ $item->name }}</span>
    @foreach ($items as $item)
        <li>Barang {{ $item->name }}</li>
    @endforeach
</div>
