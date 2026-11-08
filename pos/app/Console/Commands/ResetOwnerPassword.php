<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Signature('takar:reset-owner-password')]
#[Description('Reset the Owner password from the server')]
class ResetOwnerPassword extends Command
{
    public function handle(): int
    {
        $owner = User::where('role', Role::Owner)->first();

        if (! $owner) {
            $this->error(__('No Owner account found. Run php artisan db:seed first.'));

            return self::FAILURE;
        }

        // Hidden prompt, so the password never lands in the shell history or a log.
        $password = (string) $this->secret(__('New Owner password (leave empty to generate one)'));
        $generated = $password === '';

        if ($generated) {
            $password = Str::password(16, symbols: false);
        } elseif (mb_strlen($password) < 8) {
            $this->error(__('The password must be at least 8 characters.'));

            return self::FAILURE;
        }

        // Old sessions and remember-me cookies must not survive a password change.
        $owner->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
        DB::table('sessions')->where('user_id', $owner->id)->delete();

        $this->info(__('Owner password updated.'));

        if ($generated) {
            $this->warn(__('Owner password: :password', ['password' => $password]));
            $this->line(__('Write it down now. It is not shown again.'));
        }

        return self::SUCCESS;
    }
}
