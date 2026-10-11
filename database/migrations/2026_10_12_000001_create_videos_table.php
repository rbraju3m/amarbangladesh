<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Videos on posts and top-level answers. Uploaded in pieces (status uploading, a private part file),
 * checked with ffprobe (waiting), converted by `community:process-videos` to a metadata-free MP4 with
 * posters on the public disk (`path` + ".mp4" / "-poster.webp" / "-poster.jpg"; ready), then attached
 * when the post or answer is saved. See App\Community\Videos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('videoable_type', 16)->nullable(); // post | answer
            $table->unsignedBigInteger('videoable_id')->nullable();
            $table->string('status', 12)->default('uploading'); // uploading | waiting | processing | ready | failed
            $table->unsignedInteger('size'); // bytes the browser said it would send
            $table->unsignedInteger('received')->default(0);
            $table->string('path', 64)->nullable(); // set once converted
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->unsignedInteger('bytes')->nullable(); // the converted MP4
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('error', 32)->nullable(); // a code, never a message
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();

            $table->index(['videoable_type', 'videoable_id']);
            $table->index(['member_id', 'videoable_id']);
            $table->index(['status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};
