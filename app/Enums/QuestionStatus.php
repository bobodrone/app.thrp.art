<?php

namespace App\Enums;

enum QuestionStatus: string
{
    case Asked    = 'asked';
    case Claimed  = 'claimed';
    case Answered = 'answered';

    public function label(): string
    {
        return match ($this) {
            self::Asked    => 'Asked',
            self::Claimed  => 'In progress',
            self::Answered => 'Responded',
        };
    }
}
