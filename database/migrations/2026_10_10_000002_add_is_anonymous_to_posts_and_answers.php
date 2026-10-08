<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A signed-in member can post or answer anonymously: the public sees "বেনামী", admins see who. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['posts', 'answers'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->boolean('is_anonymous')->default(false)->after('member_id'));
        }
    }

    public function down(): void
    {
        foreach (['posts', 'answers'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('is_anonymous'));
        }
    }
};
