<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Helpline directory (feature plan chunk 12): public numbers, managed by admins at /admin/helplines.
 * Never deleted: turned off instead, and every change is logged. The starter list below ships switched
 * off with no "last checked" date: an admin confirms each number before it can be turned on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('helplines', function (Blueprint $table) {
            $table->id();
            $table->string('name_bn', 120);
            $table->string('name_en', 120)->nullable();
            $table->string('number', 20);
            $table->string('kind', 20); // App\Models\Helpline::KINDS
            $table->foreignId('area_id')->nullable()->constrained('areas'); // null = the whole country
            $table->string('note_bn', 200)->nullable();
            $table->string('note_en', 200)->nullable();
            $table->date('last_checked_at')->nullable();
            $table->string('source', 300)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        $now = now();
        $order = 0;
        DB::table('helplines')->insert(array_map(fn ($row) => $row + [
            'area_id' => null, 'last_checked_at' => null, 'source' => null, 'sort_order' => ++$order,
            'is_active' => false, 'created_at' => $now, 'updated_at' => $now,
        ], [
            ['number' => '999', 'kind' => 'emergency', 'name_bn' => 'জাতীয় জরুরি সেবা', 'name_en' => 'National Emergency Service', 'note_bn' => 'পুলিশ, ফায়ার সার্ভিস, অ্যাম্বুলেন্স', 'note_en' => 'Police, fire service, ambulance'],
            ['number' => '109', 'kind' => 'women_children', 'name_bn' => 'নারী ও শিশু নির্যাতন প্রতিরোধ', 'name_en' => 'Violence against women and children', 'note_bn' => null, 'note_en' => null],
            ['number' => '1098', 'kind' => 'women_children', 'name_bn' => 'চাইল্ড হেল্পলাইন', 'name_en' => 'Child Helpline', 'note_bn' => 'বিপদে পড়া শিশুর জন্য', 'note_en' => 'For children in danger'],
            ['number' => '16263', 'kind' => 'health', 'name_bn' => 'স্বাস্থ্য বাতায়ন', 'name_en' => 'Shasthya Batayon', 'note_bn' => 'ডাক্তারের পরামর্শ', 'note_en' => 'Advice from a doctor'],
            ['number' => '16430', 'kind' => 'legal', 'name_bn' => 'জাতীয় আইনগত সহায়তা', 'name_en' => 'National Legal Aid', 'note_bn' => 'বিনা খরচে আইনি পরামর্শ', 'note_en' => 'Free legal advice'],
            ['number' => '1090', 'kind' => 'disaster', 'name_bn' => 'দুর্যোগের আগাম বার্তা', 'name_en' => 'Disaster early warning', 'note_bn' => 'ঝড়, বন্যার খবর', 'note_en' => 'Storm and flood updates'],
            ['number' => '333', 'kind' => 'government', 'name_bn' => 'জাতীয় কল সেন্টার', 'name_en' => 'National Call Centre', 'note_bn' => 'সরকারি তথ্য ও সেবা', 'note_en' => 'Government information and services'],
            ['number' => '106', 'kind' => 'government', 'name_bn' => 'দুর্নীতি দমন কমিশন', 'name_en' => 'Anti-Corruption Commission', 'note_bn' => 'দুর্নীতির অভিযোগ', 'note_en' => 'Report corruption'],
        ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('helplines');
    }
};
