<?php

namespace Tests\Unit;

use App\Support\Bangla;
use PHPUnit\Framework\TestCase;

class BanglaTest extends TestCase
{
    public function test_digits(): void
    {
        $this->assertSame('৮৭%', Bangla::digits('87%'));
    }

    public function test_possessive_follows_bangla_grammar(): void
    {
        $this->assertSame('রাশেদের', Bangla::possessive('রাশেদ'));
        $this->assertSame('রিয়ার', Bangla::possessive('রিয়া'));
        $this->assertSame('Rashed-এর', Bangla::possessive('Rashed'));
    }

    public function test_clean_name_strips_links_and_symbols(): void
    {
        $this->assertSame('রাশেদ', Bangla::cleanName('  রাশেদ <script> '));
        $this->assertNull(Bangla::cleanName('https://evil.com'));
        $this->assertSame('রাশেদ', Bangla::cleanName('রাশেদ www.x.com'));
        $this->assertSame('Md. Rashed', Bangla::cleanName('Md. Rashed'));
        $this->assertNull(Bangla::cleanName('!!!'));
        $this->assertSame(20, mb_strlen(Bangla::cleanName(str_repeat('ক', 50))));
    }
}
