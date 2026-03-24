<?php

namespace App\Enums;

enum QuestionType: string
{
    case MCQ = 'mcq';
    case MultiSelect = 'multi_select';
    case TrueFalse = 'true_false';
    case ShortAnswer = 'short_answer';
    case LongAnswer = 'long_answer';
    case FillBlank = 'fill_blank';
    case MatchColumn = 'match_column';

    public function label(): string
    {
        return match ($this) {
            self::MCQ => 'Multiple Choice',
            self::MultiSelect => 'Multi Select',
            self::TrueFalse => 'True / False',
            self::ShortAnswer => 'Short Answer',
            self::LongAnswer => 'Long Answer',
            self::FillBlank => 'Fill in the Blank',
            self::MatchColumn => 'Match the Column',
        };
    }

    public function hasOptions(): bool
    {
        return in_array($this, [self::MCQ, self::MultiSelect, self::TrueFalse]);
    }

    public function hasBlanks(): bool
    {
        return $this === self::FillBlank;
    }

    public function hasMatchPairs(): bool
    {
        return $this === self::MatchColumn;
    }

    public function hasExpectedAnswer(): bool
    {
        return in_array($this, [self::ShortAnswer, self::LongAnswer]);
    }
}
