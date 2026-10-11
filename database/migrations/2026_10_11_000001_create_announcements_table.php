<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Site-wide announcements (feature plan chunk 8): a short bar under the header on every public page,
 * between optional start and end times. Never deleted: turned off instead, and every change is logged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('text_bn', 240);
            $table->string('text_en', 240)->nullable();
            $table->string('link', 500)->nullable();
            $table->string('level', 8)->default('info'); // info | warning
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
