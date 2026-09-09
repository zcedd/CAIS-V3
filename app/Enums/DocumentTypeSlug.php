<?php

namespace App\Enums;

enum DocumentTypeSlug: string
{
    case ValidId = 'valid_id';
    case IndigencyCertificate = 'indigency_certificate';
    case DeliveryPhoto = 'delivery_photo';
    case SignedAcknowledgment = 'signed_acknowledgment';
    case Other = 'other';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
