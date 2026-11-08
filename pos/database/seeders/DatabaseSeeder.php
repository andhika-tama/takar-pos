<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database. Runs on every `docker compose up`, so it never touches an existing Owner.
     */
    public function run(): void
    {
        if (User::where('role', Role::Owner)->exists()) {
            return;
        }

        $configured = config('app.owner_password');

        $owner = new User(['name' => 'Owner', 'username' => 'owner', 'password' => $configured ?: Str::password(32)]);
        $owner->role = Role::Owner;
        $owner->save();

        // Console output ends up in `docker compose logs`, so no password is ever printed (ACC-4).
        if (! $configured) {
            $this->command?->warn(__('The Owner account "owner" has no known password yet. Set one with: php artisan takar:reset-owner-password (in Docker: docker compose exec app php artisan takar:reset-owner-password)'));
        }
    }
}
