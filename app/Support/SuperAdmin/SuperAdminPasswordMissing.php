<?php

namespace App\Support\SuperAdmin;

use RuntimeException;

/**
 * The super administrator cannot be created, or have its password reset, without a password
 * from the environment. The repository ships none: it is public.
 */
final class SuperAdminPasswordMissing extends RuntimeException
{
    public static function unset(): self
    {
        return new self(
            'SUPER_ADMIN_PASSWORD is not set, so the super administrator cannot be created. '
            .'Set it in .env (at least 10 characters, with letters and digits) and run '
            .'`php artisan admin:ensure-super-admin`.'
        );
    }

    public static function weak(): self
    {
        return new self('SUPER_ADMIN_PASSWORD is too weak: use at least 10 characters, with letters and digits.');
    }
}
