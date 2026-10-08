<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Accounts for community members. A member can sign in several ways (identities) and stay signed
 * in on several devices (tokens). The device token that used to live on `members` moves to
 * `member_tokens`, so members created before sign-in existed keep their posts and their session.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 16); // google | facebook | phone | email
            $table->string('identifier', 191); // provider user id, +8801…, lower-cased email
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'identifier']);
            $table->index('member_id');
        });

        Schema::create('member_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::table('members', function (Blueprint $table) {
            $table->string('password')->nullable()->after('name');
        });

        DB::table('members')->orderBy('id')->each(function ($m) {
            DB::table('member_tokens')->insert(['member_id' => $m->id, 'token_hash' => $m->token_hash, 'created_at' => $m->created_at]);
        });

        Schema::table('members', function (Blueprint $table) {
            $table->dropUnique(['token_hash']);
            $table->dropColumn('token_hash');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->char('token_hash', 64)->nullable()->unique();
        });
        DB::table('member_tokens')->orderBy('id')->each(function ($t) {
            DB::table('members')->where('id', $t->member_id)->whereNull('token_hash')->update(['token_hash' => $t->token_hash]);
        });
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('password');
        });
        Schema::dropIfExists('member_tokens');
        Schema::dropIfExists('member_identities');
    }
};
