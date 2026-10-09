<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tells members when their post is answered, their answer is chosen as the solution, or a question
 * they also wanted answered gets an answer. Shown on the site and (grouped) by email; `members`
 * gets the address to email (never shown), an opt-out and the language to write in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16); // answer | accepted | need
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('answer_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['member_id', 'type', 'answer_id']);
            $table->index(['member_id', 'read_at']);
            $table->index(['emailed_at', 'created_at']);
        });

        Schema::table('members', function (Blueprint $table) {
            $table->string('email', 191)->nullable()->after('password');
            $table->boolean('email_notifications')->default(true)->after('email');
            $table->string('locale', 2)->default('bn')->after('email_notifications');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_notifications');
        Schema::table('members', fn (Blueprint $table) => $table->dropColumn(['email', 'email_notifications', 'locale']));
    }
};
