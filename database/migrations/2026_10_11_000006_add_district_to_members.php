<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A member's home district (feature plan chunk 13): optional and private (only they see it), for the
 * "my area" shortcut on the feed and their division on the home map. Districts only, no upazilas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->foreignId('district_id')->nullable()->after('locale')->constrained('areas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropConstrainedForeignId('district_id');
        });
    }
};
