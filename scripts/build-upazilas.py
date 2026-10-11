"""
Builds resources/data/bd-upazilas.php: every upazila of Bangladesh under its district, plus the
metropolitan thanas of Dhaka and Chattogram (city areas that aren't upazilas), with Bangla and English
names and a unique slug "{district}-{name}". Also writes a review table (CSV) of every row, marking the
names this script changed from the source and the ones to check.

Source (MIT): nuhil/bangladesh-geocode, built from the district government websites
https://github.com/nuhil/bangladesh-geocode (districts/districts.csv, upazilas/upazilas.csv)
The thanas are listed below (Dhaka Metropolitan Police: 50, Chattogram Metropolitan Police: 15; CMP's
Karnaphuli is left out because it is also an upazila).

Usage: python3 -I scripts/build-upazilas.py path/to/districts.csv path/to/upazilas.csv [review.csv]
"""
import csv
import re
import sys

# The source's district English names -> our area slugs, where lower-casing isn't enough.
DISTRICTS = {
    'Barisal': 'barishal', 'Chapainawabganj': 'chapai-nawabganj', 'Coxsbazar': 'coxs-bazar',
    'Jhalakathi': 'jhalokati',
}

# Our district names (the areas table), so "X Sadar" reads exactly like the district everywhere.
DISTRICT_NAMES = {
    'bagerhat': ('বাগেরহাট', 'Bagerhat'), 'bandarban': ('বান্দরবান', 'Bandarban'), 'barguna': ('বরগুনা', 'Barguna'),
    'barishal': ('বরিশাল', 'Barishal'), 'bhola': ('ভোলা', 'Bhola'), 'bogura': ('বগুড়া', 'Bogura'),
    'brahmanbaria': ('ব্রাহ্মণবাড়িয়া', 'Brahmanbaria'), 'chandpur': ('চাঁদপুর', 'Chandpur'),
    'chapai-nawabganj': ('চাঁপাইনবাবগঞ্জ', 'Chapai Nawabganj'), 'chattogram': ('চট্টগ্রাম', 'Chattogram'),
    'chuadanga': ('চুয়াডাঙ্গা', 'Chuadanga'), 'coxs-bazar': ('কক্সবাজার', "Cox's Bazar"), 'cumilla': ('কুমিল্লা', 'Cumilla'),
    'dhaka': ('ঢাকা', 'Dhaka'), 'dinajpur': ('দিনাজপুর', 'Dinajpur'), 'faridpur': ('ফরিদপুর', 'Faridpur'),
    'feni': ('ফেনী', 'Feni'), 'gaibandha': ('গাইবান্ধা', 'Gaibandha'), 'gazipur': ('গাজীপুর', 'Gazipur'),
    'gopalganj': ('গোপালগঞ্জ', 'Gopalganj'), 'habiganj': ('হবিগঞ্জ', 'Habiganj'), 'jamalpur': ('জামালপুর', 'Jamalpur'),
    'jashore': ('যশোর', 'Jashore'), 'jhalokati': ('ঝালকাঠি', 'Jhalokati'), 'jhenaidah': ('ঝিনাইদহ', 'Jhenaidah'),
    'joypurhat': ('জয়পুরহাট', 'Joypurhat'), 'khagrachhari': ('খাগড়াছড়ি', 'Khagrachhari'), 'khulna': ('খুলনা', 'Khulna'),
    'kishoreganj': ('কিশোরগঞ্জ', 'Kishoreganj'), 'kurigram': ('কুড়িগ্রাম', 'Kurigram'), 'kushtia': ('কুষ্টিয়া', 'Kushtia'),
    'lakshmipur': ('লক্ষ্মীপুর', 'Lakshmipur'), 'lalmonirhat': ('লালমনিরহাট', 'Lalmonirhat'),
    'madaripur': ('মাদারীপুর', 'Madaripur'), 'magura': ('মাগুরা', 'Magura'), 'manikganj': ('মানিকগঞ্জ', 'Manikganj'),
    'meherpur': ('মেহেরপুর', 'Meherpur'), 'moulvibazar': ('মৌলভীবাজার', 'Moulvibazar'),
    'munshiganj': ('মুন্সিগঞ্জ', 'Munshiganj'), 'mymensingh': ('ময়মনসিংহ', 'Mymensingh'), 'naogaon': ('নওগাঁ', 'Naogaon'),
    'narail': ('নড়াইল', 'Narail'), 'narayanganj': ('নারায়ণগঞ্জ', 'Narayanganj'), 'narsingdi': ('নরসিংদী', 'Narsingdi'),
    'natore': ('নাটোর', 'Natore'), 'netrokona': ('নেত্রকোনা', 'Netrokona'), 'nilphamari': ('নীলফামারী', 'Nilphamari'),
    'noakhali': ('নোয়াখালী', 'Noakhali'), 'pabna': ('পাবনা', 'Pabna'), 'panchagarh': ('পঞ্চগড়', 'Panchagarh'),
    'patuakhali': ('পটুয়াখালী', 'Patuakhali'), 'pirojpur': ('পিরোজপুর', 'Pirojpur'), 'rajbari': ('রাজবাড়ী', 'Rajbari'),
    'rajshahi': ('রাজশাহী', 'Rajshahi'), 'rangamati': ('রাঙামাটি', 'Rangamati'), 'rangpur': ('রংপুর', 'Rangpur'),
    'satkhira': ('সাতক্ষীরা', 'Satkhira'), 'shariatpur': ('শরীয়তপুর', 'Shariatpur'), 'sherpur': ('শেরপুর', 'Sherpur'),
    'sirajganj': ('সিরাজগঞ্জ', 'Sirajganj'), 'sunamganj': ('সুনামগঞ্জ', 'Sunamganj'), 'sylhet': ('সিলেট', 'Sylhet'),
    'tangail': ('টাঙ্গাইল', 'Tangail'), 'thakurgaon': ('ঠাকুরগাঁও', 'Thakurgaon'),
}

# Source typos and spellings fixed: English name -> (bn or None to keep, en).
FIXES = {
    'Borhan Sddin': (None, 'Borhanuddin'),
    'Sadarsouth': (None, 'Sadar Dakshin'),
    'Charfesson': (None, 'Char Fasson'),
    'Charrajibpur': (None, 'Char Rajibpur'),
    'Charbhadrasan': (None, 'Char Bhadrasan'),
    'Dakshinsurma': (None, 'Dakshin Surma'),
    'Osmaninagar': (None, 'Osmani Nagar'),
    'Senbug': (None, 'Senbagh'),
    'Sonaimori': (None, 'Sonaimuri'),
    'Louhajanj': (None, 'Lohajang'),
    'Kamolganj': (None, 'Kamalganj'),
    'Nondigram': (None, 'Nandigram'),
    'Dupchanchia': ('দুপচাঁচিয়া', 'Dupchanchia'),
    'Sitakunda': ('সীতাকুণ্ড', 'Sitakunda'),
    'Harinakundu': ('হরিণাকুণ্ডু', 'Harinakundu'),
}

# Kept as in the source, but worth a look (renamed or spelled differently in other lists).
CHECK = {
    'Zianagar': 'renamed Indurkani (ইন্দুরকানী) in other lists?',
    'Nesarabad': 'also known as Swarupkathi (স্বরূপকাঠি)',
    'Laxmichhari': 'Lakshmichhari (লক্ষ্মীছড়ি)?',
    'Kaliganj': 'several districts have one: check each district is right',
}

# Metropolitan thanas (city areas, not upazilas): district -> [(bn, en)].
THANAS = {
    'dhaka': [
        ('আদাবর', 'Adabor'), ('বাড্ডা', 'Badda'), ('বনানী', 'Banani'), ('বংশাল', 'Bangshal'), ('ভাসানটেক', 'Bhashantek'),
        ('ভাটারা', 'Bhatara'), ('বিমানবন্দর', 'Bimanbandar'), ('ক্যান্টনমেন্ট', 'Cantonment'), ('চকবাজার', 'Chawkbazar'),
        ('দক্ষিণখান', 'Dakshinkhan'), ('দারুস সালাম', 'Darus Salam'), ('ডেমরা', 'Demra'), ('ধানমন্ডি', 'Dhanmondi'),
        ('গেন্ডারিয়া', 'Gendaria'), ('গুলশান', 'Gulshan'), ('হাতিরঝিল', 'Hatirjheel'), ('হাজারীবাগ', 'Hazaribagh'),
        ('যাত্রাবাড়ী', 'Jatrabari'), ('কদমতলী', 'Kadamtali'), ('কাফরুল', 'Kafrul'), ('কলাবাগান', 'Kalabagan'),
        ('কামরাঙ্গীরচর', 'Kamrangirchar'), ('খিলগাঁও', 'Khilgaon'), ('খিলক্ষেত', 'Khilkhet'), ('কোতোয়ালী', 'Kotwali'),
        ('লালবাগ', 'Lalbagh'), ('মিরপুর', 'Mirpur'), ('মোহাম্মদপুর', 'Mohammadpur'), ('মতিঝিল', 'Motijheel'),
        ('মুগদা', 'Mugda'), ('নিউমার্কেট', 'New Market'), ('পল্লবী', 'Pallabi'), ('পল্টন', 'Paltan'), ('রমনা', 'Ramna'),
        ('রামপুরা', 'Rampura'), ('রূপনগর', 'Rupnagar'), ('সবুজবাগ', 'Sabujbagh'), ('শাহ আলী', 'Shah Ali'),
        ('শাহবাগ', 'Shahbagh'), ('শাহজাহানপুর', 'Shahjahanpur'), ('শেরেবাংলা নগর', 'Sher-e-Bangla Nagar'),
        ('শ্যামপুর', 'Shyampur'), ('সূত্রাপুর', 'Sutrapur'), ('তেজগাঁও', 'Tejgaon'),
        ('তেজগাঁও শিল্পাঞ্চল', 'Tejgaon Industrial Area'), ('তুরাগ', 'Turag'), ('উত্তরা পূর্ব', 'Uttara East'),
        ('উত্তরা পশ্চিম', 'Uttara West'), ('উত্তরখান', 'Uttarkhan'), ('ওয়ারী', 'Wari'),
    ],
    'chattogram': [
        ('আকবর শাহ', 'Akbar Shah'), ('বাকলিয়া', 'Bakalia'), ('বন্দর', 'Bandar'), ('বায়েজিদ বোস্তামী', 'Bayazid Bostami'),
        ('চান্দগাঁও', 'Chandgaon'), ('চকবাজার', 'Chawkbazar'), ('ডবলমুরিং', 'Double Mooring'), ('ইপিজেড', 'EPZ'),
        ('হালিশহর', 'Halishahar'), ('খুলশী', 'Khulshi'), ('কোতোয়ালী', 'Kotwali'), ('পাহাড়তলী', 'Pahartali'),
        ('পাঁচলাইশ', 'Panchlaish'), ('পতেঙ্গা', 'Patenga'), ('সদরঘাট', 'Sadarghat'),
    ],
}


def slugify(text):
    return re.sub(r'[^a-z0-9]+', '-', text.lower().replace("'", '')).strip('-')


def clean(text):
    return re.sub(r'\s+', ' ', text).strip()


districts = {}
for row in csv.reader(open(sys.argv[1], encoding='utf-8')):
    slug = DISTRICTS.get(row[2], slugify(row[2]))
    assert slug in DISTRICT_NAMES, f'unknown district {row[2]}'
    districts[row[0]] = slug

rows = {slug: [] for slug in DISTRICT_NAMES}
review = []
for row in csv.reader(open(sys.argv[2], encoding='utf-8')):
    district = districts[row[1]]
    src_en, src_bn = clean(row[2]), clean(row[3])
    bn, en, note = src_bn, src_en, ''
    if src_en.endswith(' Sadar'):
        bn, en = DISTRICT_NAMES[district][0] + ' সদর', DISTRICT_NAMES[district][1] + ' Sadar'
    elif src_en in FIXES:
        bn = FIXES[src_en][0] or src_bn
        en = FIXES[src_en][1]
    else:
        en = re.sub(r'gonj$', 'ganj', en)
    if (bn, en) != (src_bn, src_en):
        note = f'changed from {src_bn} / {src_en}'
    if src_en in CHECK:
        note = (note + '; ' if note else '') + 'check: ' + CHECK[src_en]
    sort = 0 if en.endswith(' Sadar') else 1
    rows[district].append([f'{district}-{slugify(en)}', 'upazila', bn, en, sort])
    review.append([DISTRICT_NAMES[district][1], 'upazila', bn, en, note])

for district, thanas in THANAS.items():
    for bn, en in thanas:
        rows[district].append([f'{district}-{slugify(en)}', 'thana', bn, en, 0])
        review.append([DISTRICT_NAMES[district][1], 'thana', bn, en, 'listed by hand: check'])

slugs = [r[0] for rs in rows.values() for r in rs]
assert len(slugs) == len(set(slugs)), 'duplicate slugs'
assert all(rows.values()), 'a district without upazilas'

with open('resources/data/bd-upazilas.php', 'w', encoding='utf-8') as fh:
    fh.write('<?php\n\n// Generated by scripts/build-upazilas.py from nuhil/bangladesh-geocode (MIT) + the metropolitan thanas\n')
    fh.write('// listed in the script. Do not edit by hand: fix the script and run it again.\n')
    fh.write('// district slug => [[slug, type (upazila | thana), name_bn, name_en, sort_order], ...]\n\nreturn [\n')
    for district in sorted(rows):
        fh.write(f"    '{district}' => [\n")
        for slug, kind, bn, en, sort in sorted(rows[district], key=lambda r: (r[1] != 'upazila', r[4], r[3])):
            en_php = en.replace("'", "\\'")
            fh.write(f"        ['{slug}', '{kind}', '{bn}', '{en_php}', {sort}],\n")
        fh.write('    ],\n')
    fh.write('];\n')

if len(sys.argv) > 3:
    with open(sys.argv[3], 'w', encoding='utf-8', newline='') as fh:
        w = csv.writer(fh)
        w.writerow(['district', 'type', 'name_bn', 'name_en', 'note'])
        w.writerows(sorted(review, key=lambda r: (r[0], r[1], r[3])))

kinds = [r[1] for rs in rows.values() for r in rs]
print(kinds.count('upazila'), 'upazilas,', kinds.count('thana'), 'thanas,', len(rows), 'districts')
