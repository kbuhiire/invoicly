<?php

namespace App\Enums;

enum CreditNoteStatus: string
{
    case Issued = 'issued';
    case Applied = 'applied';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Issued => 'Issued',
            self::Applied => 'Applied',
            self::Void => 'Void',
        };
    }
}
