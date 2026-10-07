<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\SuperAdmin\SuperAdminIsProtected;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    // is_super_admin is deliberately not fillable: only SuperAdminProvisioner raises it.
    protected $fillable = ['name', 'email', 'password'];

    protected $attributes = ['is_super_admin' => false];

    protected $hidden = ['password', 'remember_token'];

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** The super administrator can't be deleted, demoted or re-addressed through the model. */
    protected static function booted(): void
    {
        static::deleting(function (User $user) {
            if ($user->is_super_admin) {
                throw SuperAdminIsProtected::cannotDelete();
            }
        });

        static::updating(function (User $user) {
            if (! $user->getOriginal('is_super_admin')) {
                return;
            }
            if ($user->isDirty('is_super_admin') && ! $user->is_super_admin) {
                throw SuperAdminIsProtected::cannotDemote();
            }
            if ($user->isDirty('email')) {
                throw SuperAdminIsProtected::cannotChangeEmail();
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
        ];
    }
}
