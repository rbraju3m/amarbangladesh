<?php

namespace App\Quiz;

final class ScoreResult
{
    /**
     * @param  array<string, float>  $vector  trait key => raw points
     * @param  array<string, int>  $traitScores  trait key => display % (sorted, highest first)
     * @param  list<string>  $reasons  answer fragments that best explain the result (Bangla)
     * @param  array<string, float>  $ranking  location slug => score (highest first)
     * @param  list<string>  $reasonsEn  the same fragments in English (Bangla where none is written yet)
     */
    public function __construct(
        public readonly string $locationSlug,
        public readonly int $matchPct,
        public readonly string $secondSlug,
        public readonly int $secondPct,
        public readonly array $vector,
        public readonly array $traitScores,
        public readonly array $reasons,
        public readonly array $ranking,
        public readonly array $reasonsEn = [],
    ) {}
}
