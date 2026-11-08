<?php

namespace App\Livewire;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** ACC-3: Kasir accounts. The Owner's own account is reset from the server, not here. */
class Staff extends Component
{
    public string $name = '';

    public string $username = '';

    public string $password = '';

    #[Locked]
    public bool $showForm = false;

    #[Locked]
    public ?int $editingId = null;

    #[Locked]
    public ?int $confirmingId = null;

    /** Runs on mount and on every action, on top of the route's can:owner. */
    public function boot(): void
    {
        Gate::authorize('owner');
    }

    public function create(): void
    {
        $this->cancel();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $kasir = $this->kasir($id);

        $this->cancel();
        $this->fill($kasir->only('name', 'username'));
        $this->editingId = $kasir->id;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'alpha_dash', 'max:64', Rule::unique('users')->ignore($this->editingId)],
            'password' => [$this->editingId ? 'nullable' : 'required', 'string', 'min:8', 'max:72'],
        ]);

        $newPassword = $data['password'] !== '';
        if (! $newPassword) {
            unset($data['password']);
        }

        if ($this->editingId) {
            $kasir = $this->kasir($this->editingId);
            $kasir->update($data);

            if ($newPassword) {
                // Old sessions and remember-me cookies must not survive a password change.
                $kasir->forceFill(['remember_token' => Str::random(60)])->save();
                DB::table('sessions')->where('user_id', $kasir->id)->delete();
            }
        } else {
            $kasir = new User($data);
            $kasir->role = Role::Kasir;
            $kasir->save();
        }

        $this->cancel();
    }

    public function cancel(): void
    {
        $this->reset();
        $this->resetValidation();
    }

    public function confirmDeactivate(int $id): void
    {
        $this->confirmingId = $this->kasir($id)->id;
    }

    public function deactivate(int $id): void
    {
        $this->setActive($id, false);
        $this->confirmingId = null;
    }

    public function activate(int $id): void
    {
        $this->setActive($id, true);
    }

    public function render(): View
    {
        return view('livewire.staff', [
            'kasirs' => User::where('role', Role::Kasir)->orderBy('name')->get(),
            'confirming' => $this->confirmingId ? $this->kasir($this->confirmingId) : null,
        ])->title(__('Staff'));
    }

    private function setActive(int $id, bool $active): void
    {
        $kasir = $this->kasir($id);
        $kasir->active = $active;
        $kasir->save();
    }

    /** Only Kasir accounts are reachable, so the Owner cannot edit, demote or deactivate themselves here. */
    private function kasir(int $id): User
    {
        return User::where('role', Role::Kasir)->findOrFail($id);
    }
}
