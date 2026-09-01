<?php

namespace App\Support;

class DocumentTypeSlug
{
    public const ValidId = 'valid_id';

    public const IndigencyCertificate = 'indigency_certificate';

    public const DeliveryPhoto = 'delivery_photo';

    public const SignedAcknowledgment = 'signed_acknowledgment';

    public const Other = 'other';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::ValidId,
            self::IndigencyCertificate,
            self::DeliveryPhoto,
            self::SignedAcknowledgment,
            self::Other,
        ];
    }
}
