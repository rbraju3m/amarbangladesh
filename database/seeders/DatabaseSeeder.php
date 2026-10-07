<?php

namespace Database\Seeders;

use App\Support\SuperAdmin\SuperAdminPasswordMissing;
use App\Support\SuperAdmin\SuperAdminProvisioner;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(QuizContentSeeder::class);

        // Every user is an admin; there is no public registration. The super administrator
        // (config/admin.php, password from SUPER_ADMIN_PASSWORD) is the first one.
        try {
            $user = (new SuperAdminProvisioner)->ensure();
            $this->command?->info("Super administrator: {$user?->email}");
        } catch (SuperAdminPasswordMissing $e) {
            $this->command?->warn($e->getMessage());
        }
    }
}
