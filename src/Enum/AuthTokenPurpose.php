<?php

namespace App\Enum;

enum AuthTokenPurpose: string
{
    case MapVerify = 'map_verify';
    case MagicLogin = 'magic_login';
}
