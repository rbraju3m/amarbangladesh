<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(QuizContentSeeder::class);

        // Every user is an admin; there is no public registration.
        $email = env('ADMIN_EMAIL', 'admin@example.com');
        if (! User::where('email', $email)->exists()) {
            $password = env('ADMIN_PASSWORD') ?: Str::password(16);
            User::create(['name' => 'Admin', 'email' => $email, 'password' => $password]);
            $this->command?->info("Admin user: {$email} / {$password}");
        }
    }
}
