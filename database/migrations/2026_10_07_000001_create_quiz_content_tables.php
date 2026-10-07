<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('traits', function (Blueprint $table) {
            $table->id();
            $table->string('key', 30)->unique();
            $table->string('label_bn', 60);
            $table->string('emoji', 16);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->string('name_bn', 60);
            $table->string('name_en', 60);
            $table->string('emoji', 16);
            $table->string('title_bn', 100);
            $table->string('tagline_bn', 255);
            $table->text('description_bn');
            $table->string('reason_tail_bn', 255);
            $table->json('badges');
            $table->json('profile');
            $table->string('accent_color', 9);
            $table->decimal('map_x', 5, 2);
            $table->decimal('map_y', 5, 2);
            $table->string('illustration')->nullable();
            $table->string('og_image')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->string('prompt_bn', 255);
            $table->string('subtitle_bn', 255)->nullable();
            $table->string('kind', 10)->default('emoji');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->string('label_bn', 120);
            $table->string('emoji', 16)->nullable();
            $table->string('image')->nullable();
            $table->string('reason_bn', 120);
            $table->json('trait_weights');
            $table->json('location_bonus')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_options');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('traits');
    }
};
