<?php

namespace App\Support\SuperAdmin;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Guarantees the app always has its super administrator (mirrors hospital-management).
 *
 * Idempotent and self-healing: creates the account when missing and restores the flag when it
 * was removed behind the model's back. Repairing needs no password; creating it, or an explicit
 * reset, takes the password from SUPER_ADMIN_PASSWORD. A password the owner changed later (on
 * the admin Password page) is left alone.
 */
final class SuperAdminProvisioner
{
    public function __construct(private readonly bool $resetPassword = false) {}

    /** Null while the schema can't hold the account yet (during the very first migrate). */
    public function ensure(): ?User
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'is_super_admin')) {
            return null;
        }

        $name = (string) config('admin.super_admin.name');
        $email = (string) config('admin.super_admin.email');
        if ($email === '') {
            throw new RuntimeException('admin.super_admin.email must be configured.');
        }

        return DB::transaction(function () use ($name, $email): User {
            $user = User::where('email', $email)->first();

            if ($user === null) {
                $user = new User;
                $user->email = $email;
                $user->name = $name;
                $user->password = Hash::make($this->password());
            } elseif ($this->resetPassword) {
                $user->password = Hash::make($this->password());
            }

            // Assigned directly: the flag isn't mass-assignable, and only this class raises it.
            $user->is_super_admin = true;
            $user->save();

            return $user;
        });
    }

    private function password(): string
    {
        $password = (string) config('admin.super_admin.password');

        if ($password === '') {
            throw SuperAdminPasswordMissing::unset();
        }
        if (mb_strlen($password) < 10 || ! preg_match('/\pL/u', $password) || ! preg_match('/\d/', $password)) {
            throw SuperAdminPasswordMissing::weak();
        }

        return $password;
    }
}
