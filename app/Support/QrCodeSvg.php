<?php

namespace App\Support;

use Zcedd\QrCode\Generator;

class QrCodeSvg
{
    /**
     * Render a URL or other payload as an SVG QR code for the acknowledgment receipt.
     */
    public static function fromString(string $payload): string
    {
        $svg = (string) (new Generator)
            ->format('svg')
            ->size(112)
            ->margin(1)
            ->errorCorrection('M')
            ->encoding('UTF-8')
            ->generate($payload);

        return preg_replace('/^<\?xml[^>]*>\s*/', '', $svg) ?? $svg;
    }
}
