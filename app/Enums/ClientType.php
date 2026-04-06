<?php

namespace App\Enums;

enum ClientType: string
{
    case Invoicly = 'invoicly';
    case External = 'external';
}
