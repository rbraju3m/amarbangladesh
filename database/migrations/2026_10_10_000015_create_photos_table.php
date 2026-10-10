<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Photos on posts and top-level answers (Phase 6). Uploaded first (photoable_* null), attached when
 * the post or answer is saved; unattached ones are pruned after a day. Files live on the public disk
 * under `path` (+ "-full.webp" / "-thumb.webp"), re-encoded without metadata. See App\Community\Photos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('photoable_type', 16)->nullable(); // post | answer
            $table->unsignedBigInteger('photoable_id')->nullable();
            $table->unsignedTinyInteger('position')->default(0);
            $table->string('path', 64);
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->unsignedInteger('bytes');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['photoable_type', 'photoable_id', 'position']);
            $table->index(['member_id', 'photoable_id']);
            $table->index(['photoable_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photos');
    }
};
