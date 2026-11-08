<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** The seeded Owner account and `takar:reset-owner-password`. */
class OwnerPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_seeder_creates_the_owner_with_the_configured_password(): void
    {
        config(['app.owner_password' => 'sandi-dari-env']);

        Artisan::call('db:seed', ['--force' => true]);

        $owner = User::sole();
        $this->assertSame(['owner', Role::Owner, true], [$owner->username, $owner->role, $owner->active]);
        $this->assertTrue(Hash::check('sandi-dari-env', $owner->password));
        $this->assertStringNotContainsString('sandi-dari-env', Artisan::output());
    }

    public function test_without_a_configured_password_the_seeder_prints_no_password_only_how_to_set_one(): void
    {
        config(['app.owner_password' => null]);

        Artisan::call('db:seed', ['--force' => true]);
        $output = Artisan::output();
        $owner = User::sole();

        $this->assertSame(['owner', Role::Owner], [$owner->username, $owner->role]);
        $this->assertStringContainsString('takar:reset-owner-password', $output);
        // Seeder output lands in `docker compose logs`: no word of it may be the password.
        foreach (preg_split('/\s+/', $output, flags: PREG_SPLIT_NO_EMPTY) as $word) {
            $this->assertFalse(Hash::check(trim($word, '"().:'), $owner->password), 'The seeder printed the Owner password.');
        }

        // Re-running keeps the existing Owner and password, and prints nothing about it.
        Artisan::call('db:seed', ['--force' => true]);

        $this->assertSame($owner->password, User::sole()->password);
        $this->assertStringNotContainsString('takar:reset-owner-password', Artisan::output());
    }

    public function test_the_reset_command_sets_the_typed_password(): void
    {
        $owner = User::factory()->owner()->create(['password' => 'sandi-lama-1']);

        $this->artisan('takar:reset-owner-password')
            ->expectsQuestion(__('New Owner password (leave empty to generate one)'), 'sandi-baru-1')
            ->doesntExpectOutputToContain('sandi-baru-1')
            ->assertSuccessful();

        $this->assertTrue(Hash::check('sandi-baru-1', $owner->refresh()->password));
    }

    public function test_the_reset_command_generates_and_prints_a_password_when_none_is_typed(): void
    {
        $owner = User::factory()->owner()->create(['password' => 'sandi-lama-1']);

        Artisan::call('takar:reset-owner-password', ['--no-interaction' => true]);

        $this->assertSame(1, preg_match('/'.preg_quote(__('Owner password: :password', ['password' => '']), '/').'(\S+)/', Artisan::output(), $match));
        $this->assertTrue(Hash::check($match[1], $owner->refresh()->password));
    }

    public function test_the_reset_command_refuses_a_short_password(): void
    {
        $owner = User::factory()->owner()->create(['password' => 'sandi-lama-1']);

        $this->artisan('takar:reset-owner-password')
            ->expectsQuestion(__('New Owner password (leave empty to generate one)'), 'pendek')
            ->assertFailed();

        $this->assertTrue(Hash::check('sandi-lama-1', $owner->refresh()->password));
    }

    public function test_the_reset_command_fails_without_an_owner(): void
    {
        User::factory()->kasir()->create();

        $this->artisan('takar:reset-owner-password')->assertFailed();
    }

    public function test_the_reset_command_ends_the_owners_sessions_and_remember_token(): void
    {
        $owner = User::factory()->owner()->create(['password' => 'sandi-lama-1', 'remember_token' => 'token-lama']);
        DB::table('sessions')->insert(['id' => 'sesi-owner', 'user_id' => $owner->id, 'payload' => '', 'last_activity' => time()]);
        DB::table('sessions')->insert(['id' => 'sesi-lain', 'user_id' => null, 'payload' => '', 'last_activity' => time()]);

        $this->artisan('takar:reset-owner-password')
            ->expectsQuestion(__('New Owner password (leave empty to generate one)'), 'sandi-baru-1')
            ->assertSuccessful();

        $this->assertDatabaseMissing('sessions', ['id' => 'sesi-owner']);
        $this->assertDatabaseHas('sessions', ['id' => 'sesi-lain']);
        $this->assertNotSame('token-lama', $owner->refresh()->remember_token);
    }
}
