<?php

use Livewire\Attributes\Validate;
use Illuminate\Validation\ValidationException;

class Bad
{
    #[Validate('required', message: 'Nama wajib')]
    public string $name = '';

    protected $messages = ['code.required' => 'Kode wajib'];

    public function save(): void
    {
        session()->flash('status', 'Tersimpan');
        session()->flash('ok', __('Tersimpan'));
        $this->addError('name', 'Nama wajib diisi');
        $this->dispatch('toast', message: 'Berhasil');
        $this->validate(['name' => 'required|in:Aktif'], ['name.required' => 'Teks bebas satu']);
        throw ValidationException::withMessages(['name' => 'Gagal total']);
    }

    protected function messages(): array { return ['a' => 'Teks bebas']; }

    public function log(): void
    {
        Log::info('Order saved');
        $x = ['k' => 'Not a message'];
    }

    protected function attributes(): array
    {
        return ['name' => 'Nama barang', 'qty' => __('Jumlah')];
    }

    #[Title('Judul atribut')]
    #[Title(__('Judul bersih'))]
    public function render()
    {
        $a = $this->view()->title('Judul render');
        $b = $this->view()->title(__('Judul bersih'));
        redirect('/x')->with('status', 'Pesan redirect');
        redirect('/x')->with('status', __('Pesan bersih'));
        view('x')->with('rows', $rows)->with(['k' => 'Not a message']);
    }
}
