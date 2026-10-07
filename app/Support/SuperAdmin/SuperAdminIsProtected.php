<?php

namespace App\Support\SuperAdmin;

use Symfony\Component\HttpKernel\Exception\HttpException;

/** Raised when something tries to delete, demote or re-address the super administrator. */
final class SuperAdminIsProtected extends HttpException
{
    public static function cannotDelete(): self
    {
        return new self(403, 'The super administrator account cannot be deleted.');
    }

    public static function cannotDemote(): self
    {
        return new self(403, 'The super administrator cannot have its privileges removed.');
    }

    public static function cannotChangeEmail(): self
    {
        return new self(403, 'The super administrator email address cannot be changed.');
    }
}
