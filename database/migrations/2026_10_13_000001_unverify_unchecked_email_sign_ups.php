<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Email sign-ups made before 2026-10-13 never proved the address: the account was made straight from the
 * typed email. They lose the "email verified" badge (and, unless Google or Facebook gave an address, the
 * notification address) until a password reset proves the inbox. Sign-ups now confirm a code first.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('member_identities')->where('provider', 'email')->update(['verified_at' => null]);

        $otherSource = DB::table('member_identities')->whereIn('provider', ['google', 'facebook'])->select('member_id');
        DB::table('members')->whereNotNull('email')
            ->whereIn('id', DB::table('member_identities')->where('provider', 'email')->select('member_id'))
            ->whereNotIn('id', $otherSource)
            ->update(['email' => null]);
    }

    public function down(): void
    {
        // The addresses weren't checked; nothing to put back.
    }
};
