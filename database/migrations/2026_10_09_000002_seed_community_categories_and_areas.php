<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reference data the community needs in every environment (deploys run `migrate`, not the seeder):
 * the starting categories and Bangladesh's 8 divisions with their 64 districts.
 */
return new class extends Migration
{
    private const CATEGORIES = [
        ['education', 'শিক্ষা', '📚'],
        ['career', 'চাকরি ও ক্যারিয়ার', '💼'],
        ['health', 'স্বাস্থ্য', '🩺'],
        ['technology', 'প্রযুক্তি', '💻'],
        ['business', 'ব্যবসা', '📈'],
        ['lifestyle', 'জীবনযাপন', '🏡'],
        ['travel', 'ভ্রমণ', '🧳'],
        ['local-issues', 'স্থানীয় সমস্যা', '📍'],
        ['shopping', 'কেনাকাটা ও পণ্য', '🛍️'],
        ['government', 'সরকারি সেবা', '🏛️'],
        ['other', 'অন্যান্য', '💬'],
    ];

    /** [division slug, bn, en] => [[district slug, bn, en], ...] */
    private const DIVISIONS = [
        'barishal|বরিশাল|Barishal' => [
            ['barguna', 'বরগুনা', 'Barguna'], ['barishal', 'বরিশাল', 'Barishal'], ['bhola', 'ভোলা', 'Bhola'],
            ['jhalokati', 'ঝালকাঠি', 'Jhalokati'], ['patuakhali', 'পটুয়াখালী', 'Patuakhali'], ['pirojpur', 'পিরোজপুর', 'Pirojpur'],
        ],
        'chattogram|চট্টগ্রাম|Chattogram' => [
            ['bandarban', 'বান্দরবান', 'Bandarban'], ['brahmanbaria', 'ব্রাহ্মণবাড়িয়া', 'Brahmanbaria'], ['chandpur', 'চাঁদপুর', 'Chandpur'],
            ['chattogram', 'চট্টগ্রাম', 'Chattogram'], ['cumilla', 'কুমিল্লা', 'Cumilla'], ['coxs-bazar', 'কক্সবাজার', "Cox's Bazar"],
            ['feni', 'ফেনী', 'Feni'], ['khagrachhari', 'খাগড়াছড়ি', 'Khagrachhari'], ['lakshmipur', 'লক্ষ্মীপুর', 'Lakshmipur'],
            ['noakhali', 'নোয়াখালী', 'Noakhali'], ['rangamati', 'রাঙামাটি', 'Rangamati'],
        ],
        'dhaka|ঢাকা|Dhaka' => [
            ['dhaka', 'ঢাকা', 'Dhaka'], ['faridpur', 'ফরিদপুর', 'Faridpur'], ['gazipur', 'গাজীপুর', 'Gazipur'],
            ['gopalganj', 'গোপালগঞ্জ', 'Gopalganj'], ['kishoreganj', 'কিশোরগঞ্জ', 'Kishoreganj'], ['madaripur', 'মাদারীপুর', 'Madaripur'],
            ['manikganj', 'মানিকগঞ্জ', 'Manikganj'], ['munshiganj', 'মুন্সিগঞ্জ', 'Munshiganj'], ['narayanganj', 'নারায়ণগঞ্জ', 'Narayanganj'],
            ['narsingdi', 'নরসিংদী', 'Narsingdi'], ['rajbari', 'রাজবাড়ী', 'Rajbari'], ['shariatpur', 'শরীয়তপুর', 'Shariatpur'],
            ['tangail', 'টাঙ্গাইল', 'Tangail'],
        ],
        'khulna|খুলনা|Khulna' => [
            ['bagerhat', 'বাগেরহাট', 'Bagerhat'], ['chuadanga', 'চুয়াডাঙ্গা', 'Chuadanga'], ['jashore', 'যশোর', 'Jashore'],
            ['jhenaidah', 'ঝিনাইদহ', 'Jhenaidah'], ['khulna', 'খুলনা', 'Khulna'], ['kushtia', 'কুষ্টিয়া', 'Kushtia'],
            ['magura', 'মাগুরা', 'Magura'], ['meherpur', 'মেহেরপুর', 'Meherpur'], ['narail', 'নড়াইল', 'Narail'],
            ['satkhira', 'সাতক্ষীরা', 'Satkhira'],
        ],
        'mymensingh|ময়মনসিংহ|Mymensingh' => [
            ['jamalpur', 'জামালপুর', 'Jamalpur'], ['mymensingh', 'ময়মনসিংহ', 'Mymensingh'], ['netrokona', 'নেত্রকোনা', 'Netrokona'],
            ['sherpur', 'শেরপুর', 'Sherpur'],
        ],
        'rajshahi|রাজশাহী|Rajshahi' => [
            ['bogura', 'বগুড়া', 'Bogura'], ['chapai-nawabganj', 'চাঁপাইনবাবগঞ্জ', 'Chapai Nawabganj'], ['joypurhat', 'জয়পুরহাট', 'Joypurhat'],
            ['naogaon', 'নওগাঁ', 'Naogaon'], ['natore', 'নাটোর', 'Natore'], ['pabna', 'পাবনা', 'Pabna'],
            ['rajshahi', 'রাজশাহী', 'Rajshahi'], ['sirajganj', 'সিরাজগঞ্জ', 'Sirajganj'],
        ],
        'rangpur|রংপুর|Rangpur' => [
            ['dinajpur', 'দিনাজপুর', 'Dinajpur'], ['gaibandha', 'গাইবান্ধা', 'Gaibandha'], ['kurigram', 'কুড়িগ্রাম', 'Kurigram'],
            ['lalmonirhat', 'লালমনিরহাট', 'Lalmonirhat'], ['nilphamari', 'নীলফামারী', 'Nilphamari'], ['panchagarh', 'পঞ্চগড়', 'Panchagarh'],
            ['rangpur', 'রংপুর', 'Rangpur'], ['thakurgaon', 'ঠাকুরগাঁও', 'Thakurgaon'],
        ],
        'sylhet|সিলেট|Sylhet' => [
            ['habiganj', 'হবিগঞ্জ', 'Habiganj'], ['moulvibazar', 'মৌলভীবাজার', 'Moulvibazar'], ['sunamganj', 'সুনামগঞ্জ', 'Sunamganj'],
            ['sylhet', 'সিলেট', 'Sylhet'],
        ],
    ];

    public function up(): void
    {
        $now = now();
        foreach (self::CATEGORIES as $i => [$slug, $name, $emoji]) {
            DB::table('categories')->insertOrIgnore(['slug' => $slug, 'name_bn' => $name, 'emoji' => $emoji, 'sort_order' => $i, 'created_at' => $now, 'updated_at' => $now]);
        }

        $d = 0;
        foreach (self::DIVISIONS as $division => $districts) {
            [$slug, $bn, $en] = explode('|', $division);
            DB::table('areas')->insertOrIgnore(['type' => 'division', 'slug' => "{$slug}-division", 'name_bn' => $bn, 'name_en' => $en, 'sort_order' => $d++]);
            $parent = DB::table('areas')->where('slug', "{$slug}-division")->value('id');
            foreach ($districts as $i => [$dSlug, $dBn, $dEn]) {
                DB::table('areas')->insertOrIgnore(['parent_id' => $parent, 'type' => 'district', 'slug' => $dSlug, 'name_bn' => $dBn, 'name_en' => $dEn, 'sort_order' => $i]);
            }
        }
    }

    public function down(): void
    {
        DB::table('areas')->whereNotNull('parent_id')->delete();
        DB::table('areas')->delete();
        DB::table('categories')->delete();
    }
};
