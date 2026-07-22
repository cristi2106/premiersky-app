<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed the initial administrator account with a freshly generated password.
     */
    public function run(): void
    {
        $name = env('ADMIN_NAME', 'Admin');
        $email = env('ADMIN_EMAIL', 'admin@premiersky.ro');
        $password = Str::password(20);

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]
        );

        $this->command->newLine();
        $this->command->info('Admin user created:');
        $this->command->line("  Email:    {$email}");
        $this->command->line("  Password: {$password}");
        $this->command->newLine();
        $this->command->warn('Store this password securely — it will not be shown again.');
    }
}
