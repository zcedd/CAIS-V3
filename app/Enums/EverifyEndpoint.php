<?php

namespace App\Enums;

enum EverifyEndpoint: string
{
    case Auth = 'auth';
    case Query = 'query';
    case QrQuery = 'qr_query';
}
