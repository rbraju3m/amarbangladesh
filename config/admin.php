<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Permanent super administrator
    |--------------------------------------------------------------------------
    |
    | One account that always exists: created or repaired after every migrate,
    | by the seeder, by `php artisan admin:ensure-super-admin`, and (throttled)
    | on boot. Same identity as the hospital-management project. The password
    | comes from the environment only: this repository is public, so a default
    | here would be a password everyone knows.
    |
    */

    'super_admin' => [
        'name' => env('SUPER_ADMIN_NAME', 'Raju'),
        'email' => env('SUPER_ADMIN_EMAIL', 'rbraju3m@gmail.com'),
        'password' => env('SUPER_ADMIN_PASSWORD'),
        'auto_provision' => (bool) env('SUPER_ADMIN_AUTO_PROVISION', true),
        // Seconds between boot-time checks (one cache lookup per request in between).
        'auto_provision_interval' => (int) env('SUPER_ADMIN_AUTO_PROVISION_INTERVAL', 3600),
    ],

];
