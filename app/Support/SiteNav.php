<?php

namespace App\Support;

/** The site's main sections, shared by the header, the phone tab bar and the footer. */
final class SiteNav
{
    /** @return list<array{key: string, href: string, label: string, icon: string}> */
    public static function items(): array
    {
        return [
            ['key' => 'home', 'href' => lroute('home', [], false), 'label' => 'হোম', 'icon' => 'M3 11l9-7 9 7v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1v-9Z'],
            ['key' => 'feed', 'href' => lroute('feed', [], false), 'label' => 'আলোচনা', 'icon' => 'M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.4A8 8 0 1 1 21 12Z'],
            ['key' => 'ask', 'href' => lroute('ask', [], false), 'label' => 'জিজ্ঞেস করুন', 'icon' => 'M12 5v14M5 12h14'],
            ['key' => 'quiz', 'href' => lroute('quiz', [], false), 'label' => 'কুইজ', 'icon' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm3.5-12.5-2 5-5 2 2-5 5-2Z'],
            ['key' => 'me', 'href' => lroute('me', [], false), 'label' => 'আমি', 'icon' => 'M20 21a8 8 0 0 0-16 0M12 13a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z'],
        ];
    }

    public static function icon(string $path, string $class = 'size-6'): string
    {
        return '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="'.$path.'"/></svg>';
    }
}
