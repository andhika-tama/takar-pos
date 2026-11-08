<?php

namespace Tests\Feature;

use App\Livewire\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_sign_in_page_shows_the_form(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee(__('Sign in'))
            ->assertSee('name="username"', false)
            ->assertSee('name="password"', false);
    }

    public function test_a_kasir_lands_on_the_till_after_sign_in(): void
    {
        $kasir = User::factory()->kasir()->create(['username' => 'sari', 'password' => 'sandi-sari-1']);

        $this->post('/login', ['username' => 'sari', 'password' => 'sandi-sari-1'])->assertRedirect('/pos');

        $this->assertAuthenticatedAs($kasir);
    }

    public function test_the_owner_lands_on_home_after_sign_in(): void
    {
        $owner = User::factory()->owner()->create(['username' => 'pemilik', 'password' => 'sandi-pemilik']);

        $this->post('/login', ['username' => 'pemilik', 'password' => 'sandi-pemilik'])->assertRedirect('/');

        $this->assertAuthenticatedAs($owner);
    }

    public function test_an_intended_url_is_honoured(): void
    {
        User::factory()->owner()->create(['username' => 'pemilik', 'password' => 'sandi-pemilik']);

        $this->get('/staff')->assertRedirect('/login');

        $this->post('/login', ['username' => 'pemilik', 'password' => 'sandi-pemilik'])->assertRedirect('/staff');
    }

    public function test_a_kasir_ignores_an_owner_page_left_as_the_intended_url(): void
    {
        User::factory()->kasir()->create(['username' => 'sari', 'password' => 'sandi-sari-1']);

        // On a shared device an Owner session expired on /staff.
        $this->get('/staff')->assertRedirect('/login');

        $this->post('/login', ['username' => 'sari', 'password' => 'sandi-sari-1'])->assertRedirect('/pos');
        $this->assertNull(session('url.intended'));
    }

    public function test_a_refused_page_answers_in_indonesian(): void
    {
        $this->actingAs(User::factory()->kasir()->create())
            ->get('/staff')
            ->assertForbidden()
            ->assertSee('Anda tidak punya akses untuk tindakan ini.')
            ->assertDontSee('This action is unauthorized.');
    }

    public function test_sign_in_regenerates_the_session_id(): void
    {
        User::factory()->kasir()->create(['username' => 'sari', 'password' => 'sandi-sari-1']);
        $fixated = Str::random(40);

        $response = $this->withCookie(config('session.cookie'), $fixated)
            ->post('/login', ['username' => 'sari', 'password' => 'sandi-sari-1']);

        $this->assertNotSame($fixated, $response->getCookie(config('session.cookie'))->getValue());
    }

    public function test_a_wrong_password_an_unknown_user_and_an_inactive_user_get_the_same_answer(): void
    {
        User::factory()->kasir()->create(['username' => 'sari', 'password' => 'sandi-sari-1']);
        User::factory()->kasir()->inactive()->create(['username' => 'budi', 'password' => 'sandi-budi-1']);

        foreach ([['sari', 'salah'], ['tidakada', 'sandi-sari-1'], ['budi', 'sandi-budi-1']] as [$username, $password]) {
            $this->post('/login', ['username' => $username, 'password' => $password])
                ->assertSessionHasErrors(['username' => __('The username or password is wrong.')]);

            $this->assertGuest();
        }
    }

    public function test_sign_in_is_throttled_after_five_failures_per_username_and_ip(): void
    {
        $this->freezeTime();
        User::factory()->kasir()->create(['username' => 'sari', 'password' => 'sandi-sari-1']);
        User::factory()->kasir()->create(['username' => 'budi', 'password' => 'sandi-budi-1']);

        foreach (range(1, 5) as $attempt) {
            $this->post('/login', ['username' => 'sari', 'password' => 'salah-'.$attempt])
                ->assertSessionHasErrors(['username' => __('The username or password is wrong.')]);
        }

        // The 6th attempt is refused even with the right password, and says when to try again.
        $this->post('/login', ['username' => 'SARI', 'password' => 'sandi-sari-1'])
            ->assertSessionHasErrors(['username' => __('Too many sign-in attempts. Try again in :seconds seconds.', ['seconds' => 60])]);
        $this->assertGuest();

        // Another username from the same address is not locked out.
        $this->post('/login', ['username' => 'budi', 'password' => 'sandi-budi-1'])->assertRedirect('/pos');
        $this->post('/logout');

        $this->travel(61)->seconds();

        $this->post('/login', ['username' => 'sari', 'password' => 'sandi-sari-1'])->assertRedirect('/pos');
    }

    public function test_a_successful_sign_in_clears_the_failure_count(): void
    {
        User::factory()->kasir()->create(['username' => 'sari', 'password' => 'sandi-sari-1']);

        foreach (range(1, 4) as $attempt) {
            $this->post('/login', ['username' => 'sari', 'password' => 'salah']);
        }
        $this->post('/login', ['username' => 'sari', 'password' => 'sandi-sari-1'])->assertRedirect('/pos');
        $this->post('/logout');

        foreach (range(1, 4) as $attempt) {
            $this->post('/login', ['username' => 'sari', 'password' => 'salah']);
        }
        $this->post('/login', ['username' => 'sari', 'password' => 'sandi-sari-1'])->assertRedirect('/pos');
    }

    public function test_a_deactivated_user_is_signed_out_on_the_next_request(): void
    {
        $kasir = User::factory()->kasir()->create();

        $this->actingAs($kasir)->get('/pos')->assertOk();

        $kasir->forceFill(['active' => false])->save();

        $this->get('/pos')
            ->assertRedirect('/login')
            ->assertSessionHas('status', __('Your account has been deactivated. Ask the Owner to activate it again.'));
        $this->assertGuest();

        $this->get('/login')->assertSee(__('Your account has been deactivated. Ask the Owner to activate it again.'));
    }

    public function test_sign_out_ends_the_session(): void
    {
        $this->actingAs(User::factory()->kasir()->create())->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_passwords_are_hashed_and_never_flashed(): void
    {
        $kasir = User::factory()->kasir()->create(['username' => 'sari', 'password' => 'sandi-sari-1']);

        $this->assertNotSame('sandi-sari-1', $kasir->getRawOriginal('password'));
        $this->assertTrue(Hash::check('sandi-sari-1', $kasir->getRawOriginal('password')));

        $this->post('/login', ['username' => 'sari', 'password' => 'tebakan-rahasia'])
            ->assertSessionHasInput('username', 'sari')
            ->assertSessionMissing('_old_input.password');
    }

    public function test_passwords_never_reach_the_log(): void
    {
        $log = tempnam(sys_get_temp_dir(), 'takar-log-');
        config([
            'logging.default' => 'capture',
            'logging.channels.capture' => ['driver' => 'single', 'path' => $log, 'level' => 'debug'],
        ]);

        // The seeder creates the Owner from OWNER_PASSWORD without printing it.
        config(['app.owner_password' => 'sandi-pemilik-lama']);
        Artisan::call('db:seed', ['--force' => true]);
        $console = Artisan::output();
        $owner = User::firstWhere('username', 'owner');
        User::factory()->kasir()->create(['username' => 'sari', 'password' => 'sandi-sari-benar']);

        // Failed sign-ins up to the lockout, then a throttled one.
        foreach (range(1, 6) as $attempt) {
            $this->post('/login', ['username' => 'sari', 'password' => 'tebakan-salah-'.$attempt]);
        }

        // A successful sign-in.
        $this->post('/login', ['username' => 'owner', 'password' => 'sandi-pemilik-lama'])->assertRedirect('/');

        // A staff form that fails validation while holding a password.
        Livewire::actingAs($owner)->test(Staff::class)
            ->call('create')
            ->set('username', 'kasir baru')
            ->set('password', 'sandi-gagal-validasi')
            ->call('save')
            ->assertHasErrors(['name', 'username']);

        // The reset command.
        $this->artisan('takar:reset-owner-password')
            ->expectsQuestion(__('New Owner password (leave empty to generate one)'), 'sandi-pemilik-baru')
            ->assertSuccessful();

        $logged = file_get_contents($log);
        unlink($log);

        $this->assertStringContainsString('sari', $logged, 'The lockout was logged, so the capture works.');
        foreach (['sandi-pemilik-lama', 'sandi-sari-benar', 'tebakan-salah', 'sandi-gagal-validasi', 'sandi-pemilik-baru'] as $secret) {
            $this->assertStringNotContainsString($secret, $logged);
        }
        $this->assertStringNotContainsString('sandi-pemilik-lama', $console);
    }
}
