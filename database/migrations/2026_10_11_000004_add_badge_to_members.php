<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A professional badge ("Doctor", "Lawyer"…) that only admins give, after checking. The label is public;
 * the note (how it was checked), who gave it and when are admin-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('badge_bn', 40)->nullable()->after('locale');
            $table->string('badge_en', 40)->nullable()->after('badge_bn');
            $table->string('badge_note', 300)->nullable()->after('badge_en');
            $table->foreignId('badge_by')->nullable()->after('badge_note')->constrained('users')->nullOnDelete();
            $table->timestamp('badge_at')->nullable()->after('badge_by');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropConstrainedForeignId('badge_by');
            $table->dropColumn(['badge_bn', 'badge_en', 'badge_note', 'badge_at']);
        });
    }
};
