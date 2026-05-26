<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeAdminUser extends Command
{
    protected $signature = 'cardora:make-admin
        {email : Email for the admin account}
        {--name= : Full name}
        {--nickname= : Public nickname / display name}
        {--handle= : Public handle}
        {--password= : Password to set}
        {--role=super_admin : Admin role}';

    protected $description = 'Create a new Cardora admin user or promote an existing user to admin';

    public function handle(): int
    {
        $email = Str::lower((string) $this->argument('email'));
        $name = $this->option('name') ?: 'Cardora Admin';
        $nickname = $this->option('nickname') ?: 'CardoraAdmin';
        $handle = $this->option('handle') ?: Str::slug($nickname ?: $name, '');
        $password = $this->option('password') ?: Str::password(16);
        $role = $this->option('role') ?: 'super_admin';

        /** @var User|null $user */
        $user = User::query()->where('email', $email)->first();

        if ($user) {
            $user->fill([
                'name' => $name ?: $user->name,
                'display_name' => $nickname ?: $user->display_name,
                'handle' => $handle ?: $user->handle,
                'is_admin' => true,
                'admin_role' => $role,
                'trust_status' => $user->trust_status ?: 'trusted',
                'is_verified_seller' => true,
            ]);

            if ($this->option('password')) {
                $user->password = $password;
            }

            if (! $user->email_verified_at) {
                $user->email_verified_at = now();
            }

            $user->save();

            $this->info("Promoted existing user [{$email}] to admin.");

            return self::SUCCESS;
        }

        User::query()->create([
            'name' => $name,
            'display_name' => $nickname,
            'handle' => $handle,
            'email' => $email,
            'phone' => null,
            'city' => 'Athens',
            'bio' => 'Cardora administrator account.',
            'collector_tagline' => 'Administrative operator',
            'profile_visibility' => 'private',
            'locale' => 'el',
            'favorite_categories' => ['cards'],
            'trust_status' => 'trusted',
            'is_verified_seller' => true,
            'is_admin' => true,
            'admin_role' => $role,
            'password' => $password,
            'email_verified_at' => now(),
        ]);

        $this->info("Created admin user [{$email}].");
        $this->line("Nickname: {$nickname}");
        $this->line("Handle: @{$handle}");
        $this->line("Password: {$password}");

        return self::SUCCESS;
    }
}
