<?php

namespace App\Enums;

enum DistributionVerificationMethod: string
{
    case ControlNumber = 'control_number';
    case RequestReference = 'request_reference';
    case OfficerAttestation = 'officer_attestation';

    public function label(): string
    {
        return match ($this) {
            self::ControlNumber => 'Control number',
            self::RequestReference => 'Assistance request reference',
            self::OfficerAttestation => 'Officer attestation',
        };
    }
}
