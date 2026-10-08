<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The community: members (a device-held identity, no login), posts of a few types, answers,
 * "helpful" marks and reports. Areas are one hierarchy (division → district → later upazila),
 * so a post can sit at any level and a district feed can later include its upazilas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->string('type', 12); // division | district (upazila later)
            $table->string('slug', 60)->unique();
            $table->string('name_bn', 60);
            $table->string('name_en', 60);
            $table->unsignedSmallInteger('sort_order')->default(0);
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 40)->unique();
            $table->string('name_bn', 60);
            $table->string('emoji', 16);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('code', 12)->unique();
            $table->string('name', 20)->nullable();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('blocked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained();
            $table->string('type', 16)->default('question');
            $table->string('title', 200);
            $table->text('body')->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 12)->default('published'); // published | hidden | removed | deleted
            $table->unsignedInteger('answers_count')->default(0);
            $table->unsignedInteger('helpful_count')->default(0);
            $table->unsignedInteger('reports_count')->default(0);
            $table->unsignedBigInteger('accepted_answer_id')->nullable();
            $table->timestamps();

            // The feed is keyset-paginated by id within a status, optionally narrowed.
            $table->index(['status', 'id']);
            $table->index(['status', 'category_id', 'id']);
            $table->index(['status', 'area_id', 'id']);
            $table->index(['status', 'answers_count', 'id']);
            $table->index(['member_id', 'id']);
            $table->index(['status', 'reports_count']);
        });

        Schema::create('answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained();
            $table->text('body');
            $table->string('status', 12)->default('published');
            $table->unsignedInteger('helpful_count')->default(0);
            $table->unsignedInteger('reports_count')->default(0);
            $table->timestamps();

            $table->index(['post_id', 'status']);
            $table->index(['member_id', 'id']);
            $table->index(['status', 'reports_count']);
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->foreign('accepted_answer_id')->references('id')->on('answers')->nullOnDelete();
        });

        // One mark per member per post/answer. "Helpful", not "like": it is the seed of a trust score.
        Schema::create('helpful_marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('markable_type', 16);
            $table->unsignedBigInteger('markable_id');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['markable_type', 'markable_id', 'member_id']);
            $table->index('member_id');
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('reportable_type', 16);
            $table->unsignedBigInteger('reportable_id');
            $table->string('reason', 20);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['reportable_type', 'reportable_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
        Schema::dropIfExists('helpful_marks');
        Schema::table('posts', fn (Blueprint $table) => $table->dropForeign(['accepted_answer_id']));
        Schema::dropIfExists('answers');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('members');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('areas');
    }
};
