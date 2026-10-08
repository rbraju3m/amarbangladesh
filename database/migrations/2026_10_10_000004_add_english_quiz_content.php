<?php

use App\Quiz\QuizConfig;
use Database\Seeders\QuizEnglishSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** English columns for the quiz content, filled by QuizEnglishSeeder (which the content seeder also runs). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('traits', fn (Blueprint $t) => $t->string('label_en', 60)->nullable()->after('label_bn'));
        Schema::table('locations', function (Blueprint $t) {
            $t->string('title_en', 100)->nullable()->after('title_bn');
            $t->string('tagline_en', 255)->nullable()->after('tagline_bn');
            $t->text('description_en')->nullable()->after('description_bn');
            $t->string('reason_tail_en', 255)->nullable()->after('reason_tail_bn');
            $t->json('badges_en')->nullable()->after('badges');
        });
        Schema::table('questions', function (Blueprint $t) {
            $t->string('prompt_en', 255)->nullable()->after('prompt_bn');
            $t->string('subtitle_en', 255)->nullable()->after('subtitle_bn');
        });
        Schema::table('question_options', function (Blueprint $t) {
            $t->string('label_en', 120)->nullable()->after('label_bn');
            $t->string('reason_en', 120)->nullable()->after('reason_bn');
        });
        Schema::table('quiz_results', fn (Blueprint $t) => $t->string('reason_en', 255)->nullable()->after('reason_bn'));

        (new QuizEnglishSeeder)->run();

        QuizConfig::forget();
    }

    public function down(): void
    {
        Schema::table('quiz_results', fn (Blueprint $t) => $t->dropColumn('reason_en'));
        Schema::table('question_options', fn (Blueprint $t) => $t->dropColumn(['label_en', 'reason_en']));
        Schema::table('questions', fn (Blueprint $t) => $t->dropColumn(['prompt_en', 'subtitle_en']));
        Schema::table('locations', fn (Blueprint $t) => $t->dropColumn(['title_en', 'tagline_en', 'description_en', 'reason_tail_en', 'badges_en']));
        Schema::table('traits', fn (Blueprint $t) => $t->dropColumn('label_en'));
        QuizConfig::forget();
    }
};
