<?php

namespace App\Console\Commands;

use App\Support\SuperAdmin\SuperAdminPasswordMissing;
use App\Support\SuperAdmin\SuperAdminProvisioner;
use Illuminate\Console\Command;

/** Manual entry point to the provisioner; --reset-password is the account-recovery path. */
class EnsureSuperAdmin extends Command
{
    protected $signature = 'admin:ensure-super-admin {--reset-password : Also reset the password to SUPER_ADMIN_PASSWORD}';

    protected $description = 'Create or repair the permanent super administrator account';

    public function handle(): int
    {
        try {
            $user = (new SuperAdminProvisioner(resetPassword: (bool) $this->option('reset-password')))->ensure();
        } catch (SuperAdminPasswordMissing $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        if ($user === null) {
            $this->components->warn('Schema is not migrated yet; nothing to do.');

            return self::SUCCESS;
        }

        $this->components->info("Super administrator ready: {$user->email} (#{$user->id})".($this->option('reset-password') ? ', password reset.' : '.'));

        return self::SUCCESS;
    }
}
