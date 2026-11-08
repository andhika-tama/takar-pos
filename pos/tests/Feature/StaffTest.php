<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/** ACC-3: the Owner creates, edits, deactivates and reactivates Kasir accounts. */
class StaffTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->owner()->create(['name' => 'Bu Sari']);
    }

    private function staff(): Testable
    {
        return Livewire::actingAs($this->owner)->test(Staff::class);
    }

    public function test_the_screen_lists_kasir_accounts_but_not_the_owner(): void
    {
        User::factory()->kasir()->create(['name' => 'Rina']);

        $this->actingAs($this->owner)->get('/staff')->assertOk()->assertSee('Rina')->assertDontSee('Bu Sari');
    }

    public function test_the_owner_creates_a_kasir_who_can_sign_in(): void
    {
        $this->staff()
            ->call('create')
            ->set('name', 'Rina')
            ->set('username', 'rina')
            ->set('password', 'sandi-rina-1')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('password', '')
            ->assertSet('showForm', false);

        $kasir = User::firstWhere('username', 'rina');
        $this->assertSame(Role::Kasir, $kasir->role);
        $this->assertTrue($kasir->active);
        $this->assertTrue(Hash::check('sandi-rina-1', $kasir->password));

        auth()->logout();
        $this->post('/login', ['username' => 'rina', 'password' => 'sandi-rina-1'])->assertRedirect('/pos');
    }

    public function test_input_is_validated_with_indonesian_messages(): void
    {
        $taken = User::factory()->kasir()->create(['username' => 'rina']);

        $component = $this->staff()
            ->call('create')
            ->set('username', 'rina')
            ->set('password', 'pendek')
            ->call('save')
            ->assertHasErrors(['name' => 'required', 'username' => 'unique', 'password' => 'min'])
            ->assertSeeHtml('aria-invalid="true"');

        $this->assertSame('Nama wajib diisi.', $component->errors()->first('name'));
        $this->assertSame('Nama pengguna sudah dipakai.', $component->errors()->first('username'));
        $this->assertSame('Kata sandi minimal 8 karakter.', $component->errors()->first('password'));

        $this->staff()->call('create')->set('name', 'Budi')->set('username', 'budi santoso')->call('save')
            ->assertHasErrors(['username' => 'alpha_dash', 'password' => 'required']);

        $this->assertSame(2, User::count());
        $this->assertSame('rina', $taken->refresh()->username);
    }

    public function test_editing_without_a_password_keeps_the_old_one(): void
    {
        $kasir = User::factory()->kasir()->create(['username' => 'rina', 'password' => 'sandi-rina-1']);

        $this->staff()
            ->call('edit', $kasir->id)
            ->assertSet('username', 'rina')
            ->set('name', 'Rina Wati')
            ->call('save')
            ->assertHasNoErrors();

        $kasir->refresh();
        $this->assertSame('Rina Wati', $kasir->name);
        $this->assertTrue(Hash::check('sandi-rina-1', $kasir->password));
    }

    public function test_editing_with_a_password_replaces_it(): void
    {
        $kasir = User::factory()->kasir()->create(['username' => 'rina']);

        $this->staff()->call('edit', $kasir->id)->set('password', 'sandi-baru-1')->call('save')->assertHasNoErrors();

        $this->assertTrue(Hash::check('sandi-baru-1', $kasir->refresh()->password));
    }

    public function test_a_deactivated_kasir_is_signed_out_and_cannot_sign_in_until_reactivated(): void
    {
        $kasir = User::factory()->kasir()->create(['username' => 'rina', 'password' => 'sandi-rina-1']);

        $this->staff()
            ->call('confirmDeactivate', $kasir->id)
            ->assertSee(__('Deactivate :name?', ['name' => $kasir->name]))
            ->call('deactivate', $kasir->id)
            ->assertSet('confirmingId', null);

        $this->assertFalse($kasir->refresh()->active);
        $this->actingAs($kasir)->get('/pos')->assertRedirect('/login');
        $this->assertGuest();
        $this->post('/login', ['username' => 'rina', 'password' => 'sandi-rina-1'])->assertSessionHasErrors('username');
        $this->assertGuest();

        $this->staff()->call('activate', $kasir->id);

        $this->assertTrue($kasir->refresh()->active);
        auth()->logout();
        $this->post('/login', ['username' => 'rina', 'password' => 'sandi-rina-1'])->assertRedirect('/pos');
    }

    public function test_the_screen_cannot_touch_the_owner_account(): void
    {
        foreach (['edit', 'confirmDeactivate', 'deactivate', 'activate'] as $action) {
            $this->staff()->call($action, $this->owner->id)->assertNotFound();
        }

        $this->assertTrue($this->owner->refresh()->active);
        $this->assertSame(Role::Owner, $this->owner->role);
    }

    public function test_role_and_active_cannot_be_mass_assigned(): void
    {
        $user = new User(['name' => 'X', 'username' => 'x', 'password' => 'sandi-x-123', 'role' => 'owner', 'active' => false]);

        $this->assertNull($user->role);
        $this->assertNull($user->active);
    }

    public function test_a_new_password_ends_that_kasirs_sessions_and_remember_token(): void
    {
        $rina = User::factory()->kasir()->create(['remember_token' => 'token-lama']);
        DB::table('sessions')->insert(['id' => 'sesi-rina', 'user_id' => $rina->id, 'payload' => '', 'last_activity' => time()]);

        $this->staff()->call('edit', $rina->id)->set('password', 'sandi-baru-1')->call('save')->assertHasNoErrors();

        $this->assertDatabaseMissing('sessions', ['id' => 'sesi-rina']);
        $this->assertNotSame('token-lama', $rina->refresh()->remember_token);
    }

    public function test_an_edit_without_a_new_password_keeps_the_kasirs_sessions(): void
    {
        $rina = User::factory()->kasir()->create(['remember_token' => 'token-lama']);
        DB::table('sessions')->insert(['id' => 'sesi-rina', 'user_id' => $rina->id, 'payload' => '', 'last_activity' => time()]);

        $this->staff()->call('edit', $rina->id)->set('name', 'Rina B')->call('save')->assertHasNoErrors();

        $this->assertDatabaseHas('sessions', ['id' => 'sesi-rina']);
        $this->assertSame('token-lama', $rina->refresh()->remember_token);
    }
}
