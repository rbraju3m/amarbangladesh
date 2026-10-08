<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * English for the quiz content, run by QuizContentSeeder and by the migration that added the
 * English columns. Rows are matched by their Bangla text and only empty English fields are
 * filled, so content edited in admin keeps whatever the admin wrote. Reasons are lower-case
 * phrases: two are joined into "X and Y — <reason tail>".
 */
class QuizEnglishSeeder extends Seeder
{
    private const TRAITS = [
        'nature' => 'Nature', 'adventure' => 'Adventure', 'calm' => 'Calm', 'social' => 'Social',
        'foodie' => 'Foodie', 'heritage' => 'Heritage', 'urban' => 'City energy', 'water' => 'Love of water',
    ];

    private const LOCATIONS = [
        'sylhet' => ['The calm mind of the tea gardens', "You're calm, but never boring.",
            'Nature pulls you in and rain makes you thoughtful. A cup of tea and a bit of green, and your day is set. Even in a crowd, you know how to find your own quiet corner.',
            'Where else but Sylhet would you find that mix?', ['🌿 Nature lover', '☕ Tea lover', '🌧️ Rain romantic', '🧭 Calm explorer']],
        'coxs-bazar' => ['Child of the waves', 'Your heart is like the sea — open and vast.',
            'Friends, music and a sunset — with those three you need nothing else. Wherever you go, the hangout comes alive. The plan can be small; the fun will be huge.',
            'Your place is right by the sea!', ['🌊 Sea lover', '👫 Life of the party', '🌅 Sunset chaser', '🎶 Open heart']],
        'bandarban' => ['Explorer of the clouds', 'The harder the road, the keener you are.',
            'You\'re the one who says “Let\'s climb up and see!” Touching clouds, walking trails, heading off down unknown roads — adventure is in your blood.',
            'The land of clouds is calling you.', ['🏔️ Hill lover', '🧗 Brave', '☁️ Head in the clouds', '🎒 Bag always packed']],
        'rangamati' => ['The artist by the water', "You're quiet, but there's a whole story inside you.",
            'The stillness of a lake ringed by hills is just like you — calm, deep and beautiful. You watch more than you speak, and your imagination paints in deeper colours than most.',
            'Your reflection is in the lake.', ['🛶 Lake lover', '🎨 Artist at heart', '🌙 Dreamer', '😌 Calm and deep']],
        'rajshahi' => ['The mango-orchard nostalgic', "You don't rush — you savour.",
            'Afternoons by the Padma, stories of old buildings and tree-ripened mangoes — simple things make you happiest. Your bag of memories is always full.',
            'The city on the Padma feels made for you.', ['🥭 Mango lover', '🏛️ Heritage fan', '🌇 Easygoing', '📜 Keeper of memories']],
        'puran-dhaka' => ['The foodie of the alleys', "Crowds, lights, noise — that's where you come alive.",
            "One whiff of kacchi and your feet start walking on their own. Alley after alley, shop after shop — you love the city's heartbeat, and in a hangout you're unstoppable.",
            "Old Dhaka's alleys know you.", ['🍛 Food lover', '⚡ City soul', '🏮 King of the alleys', '👫 Born to hang out']],
        'sundarbans' => ['The mangrove mystery', "You're not easy to read — and that's your beauty.",
            'Silent creeks, dense forest and the call of the unknown — you want places without crowds but full of mystery. You have both courage and patience, like the tiger.',
            'Your home is in the land of the tiger.', ["🐅 Tiger's courage", '🌳 Wild at heart', '🔦 Mystery lover', '🤫 Loves solitude']],
        'barishal' => ['The river poet', 'A river flows inside you.',
            "Wind on the launch deck, hot hilsa on your plate and green paddy fields in your eyes — in Jibanananda's land you're right at home. You're simple, warm and a little bit of a poet.",
            'Your heart is on the banks of the Dhansiri.', ['🚢 Launch lover', '🐟 Hilsa fan', '✍️ Poet at heart', '🌾 Down to earth']],
        'chattogram' => ['The big-hearted host', 'Your heart is big, and your invitations are even bigger.',
            "You want both hills and sea, plus a table full of people and mezbani beef. You're bold, big-hearted, and wherever you go you make your own place.",
            "You've got the Chattogram spirit.", ['🍖 Mezbani lover', '💪 Big-hearted', '⛰️ Hills and sea', '🎉 Great host']],
    ];

    private const QUESTIONS = [
        '৩ দিনের ছুটি! ব্যাগ গুছিয়ে কোথায় যাবে?' => 'A 3-day holiday! Where are you packing your bag for?',
        'কোন খাবারের সামনে ডায়েট শেষ?' => 'Which food ends your diet?',
        'বৃষ্টি নামলো! তুমি…' => "It's started raining! You…",
        'বন্ধুরা বললো, "কাল ভোরে বের হচ্ছি!"' => 'Your friends say, "We\'re leaving at dawn tomorrow!"',
        'সারা বছর কোন ঋতুর অপেক্ষায় থাকো?' => 'Which season do you wait for all year?',
        'কোন দৃশ্যে ঢুকে পড়তে চাও?' => 'Which scene would you step into?',
        'রাত ১১টা। তুমি…' => '11 pm. You…',
        'বন্ধুরা তোমাকে কী বলে?' => 'What do your friends call you?',
    ];

    private const LABELS = [
        'পাহাড়ে' => 'The hills', 'সমুদ্রে' => 'The sea', 'নদী-গ্রামে' => 'A river village', 'শহরের অলিগলিতে' => "The city's back alleys",
        'কাচ্চি' => 'Kacchi', 'ইলিশ ভাজা' => 'Fried hilsa', 'মেজবানি মাংস' => 'Mezbani beef', 'ভর্তা-ভাত' => 'Bhorta and rice',
        'চা হাতে জানালায়' => 'Tea in hand at the window', 'ভিজতে বের হই' => 'Head out and get soaked', 'খিচুড়ি-ডিম ভাজা' => 'Khichuri and fried egg', 'কাঁথা মুড়ি দিয়ে ঘুম' => 'Nap under a kantha',
        'চল! কোথায় যাচ্ছি?' => "Let's go! Where to?", 'আগে প্ল্যানটা দেখি' => 'Let me see the plan first', 'জায়গা ভালো হলে যাবো' => "I'll go if the place is good", 'বাসাই বেস্ট' => 'Home is best',
        'বর্ষা' => 'Monsoon', 'গ্রীষ্ম, আম-কাঁঠাল!' => 'Summer — mangoes and jackfruit!', 'শীত, পিঠা-পুলি' => 'Winter — pitha and puli', 'বসন্ত, ফুলের রং' => 'Spring — colourful flowers',
        'মেঘে ঢাকা পাহাড়' => 'Cloud-covered hills', 'পাহাড়ঘেরা লেকে নৌকা' => 'A boat on a lake among hills', 'পুরোনো জমিদারবাড়ি' => 'An old zamindar mansion', 'ম্যানগ্রোভের নিস্তব্ধ খাল' => 'A silent mangrove creek',
        'টং দোকানে আড্ডা' => 'Hanging out at a tea stall', 'ছাদে গান-গল্প' => 'Songs and stories on the roof', 'সিরিজ আর ঘুম' => 'A series, then sleep', 'পরের ট্রিপের প্ল্যান' => 'Planning the next trip',
        'পাগলা অভিযাত্রী' => 'The crazy explorer', 'শান্ত দার্শনিক' => 'The calm philosopher', 'আড্ডাবাজ' => 'The social butterfly', 'পেটুক' => 'The foodie',
    ];

    private const REASONS = [
        'পাহাড়ের ডাক' => 'the call of the hills', 'সমুদ্রের টান' => 'the pull of the sea', 'নদী-গ্রামের ছুটি' => 'a river-village getaway', 'শহরের অলিগলি' => "the city's back alleys",
        'কাচ্চির প্রেম' => 'your love of kacchi', 'গরম ইলিশ ভাজা' => 'hot fried hilsa', 'মেজবানি মাংস' => 'mezbani beef', 'ভর্তা-ভাতের সরলতা' => 'the simplicity of bhorta and rice',
        'বৃষ্টিতে জানালার পাশে চা' => 'tea by the window in the rain', 'বৃষ্টিতে ভেজার পাগলামি' => 'the joy of getting soaked in the rain', 'বৃষ্টির দিনের খিচুড়ি' => 'rainy-day khichuri', 'কাঁথা মুড়ি দেওয়া আলসেমি' => 'cosy laziness under a kantha',
        'এক কথায় ব্যাগ গোছানো' => 'packing your bag in a heartbeat', 'সব গুছিয়ে প্ল্যান করা' => 'planning everything properly', 'ভালো জায়গার খোঁজ' => 'hunting for good places', 'ঘরকুনো শান্তি' => 'homebody peace',
        'বর্ষার প্রেম' => 'your love of the monsoon', 'আম-কাঁঠালের গ্রীষ্ম' => 'a summer of mangoes and jackfruit', 'শীতের পিঠা-পুলি' => 'winter pitha', 'বসন্তের রং' => 'the colours of spring',
        'মেঘে ঢাকা পাহাড়' => 'cloud-covered hills', 'পাহাড়ঘেরা লেকে নৌকা' => 'a boat on a lake among hills', 'পুরোনো জমিদারবাড়ির গল্প' => 'stories of an old zamindar mansion', 'ম্যানগ্রোভের নিস্তব্ধতা' => 'the stillness of the mangroves',
        'টং দোকানের আড্ডা' => 'tea-stall hangouts', 'ছাদের গান-গল্প' => 'songs and stories on the roof', 'সিরিজ আর ঘুম' => 'a series, then sleep', 'পরের ট্রিপের প্ল্যান' => 'planning the next trip',
        'অভিযাত্রী মন' => "an explorer's spirit", 'শান্ত দার্শনিক মন' => 'a calm, thoughtful mind', 'আড্ডাবাজ স্বভাব' => 'a love of hanging out', 'খাদ্যরসিক মন' => "a foodie's heart",
    ];

    public function run(): void
    {
        foreach (self::TRAITS as $key => $label) {
            DB::table('traits')->where('key', $key)->whereNull('label_en')->update(['label_en' => $label]);
        }
        foreach (self::LOCATIONS as $slug => [$title, $tagline, $description, $tail, $badges]) {
            DB::table('locations')->where('slug', $slug)->whereNull('title_en')->update([
                'title_en' => $title, 'tagline_en' => $tagline, 'description_en' => $description,
                'reason_tail_en' => $tail, 'badges_en' => json_encode($badges, JSON_UNESCAPED_UNICODE),
            ]);
        }
        foreach (self::QUESTIONS as $bn => $en) {
            DB::table('questions')->where('prompt_bn', $bn)->whereNull('prompt_en')->update(['prompt_en' => $en]);
        }
        foreach (self::LABELS as $bn => $en) {
            DB::table('question_options')->where('label_bn', $bn)->whereNull('label_en')->update(['label_en' => $en]);
        }
        foreach (self::REASONS as $bn => $en) {
            DB::table('question_options')->where('reason_bn', $bn)->whereNull('reason_en')->update(['reason_en' => $en]);
        }

    }
}
