<?php

namespace App\Enums;

enum WorkflowStepType: string
{
    case Start = 'start';
    case Verification = 'verification';
    case Evaluation = 'evaluation';
    case Approval = 'approval';
    case Preparation = 'preparation';
    case Release = 'release';
    case Completion = 'completion';
    case Rejection = 'rejection';
    case Hold = 'hold';
    case Custom = 'custom';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Start => 'Start',
            self::Verification => 'Verification',
            self::Evaluation => 'Evaluation',
            self::Approval => 'Approval',
            self::Preparation => 'Preparation',
            self::Release => 'Release',
            self::Completion => 'Completion',
            self::Rejection => 'Rejection',
            self::Hold => 'Hold',
            self::Custom => 'Custom',
        };
    }

    public static function fromStatusCode(?RequestStatusCode $code, bool $isStart = false, bool $isEnd = false): self
    {
        if ($isStart) {
            return self::Start;
        }

        if ($isEnd || $code === RequestStatusCode::Closed) {
            return self::Completion;
        }

        return match ($code) {
            RequestStatusCode::Review => self::Verification,
            RequestStatusCode::Approved => self::Approval,
            RequestStatusCode::Delivered => self::Release,
            RequestStatusCode::Denied => self::Rejection,
            RequestStatusCode::OnHold => self::Hold,
            default => self::Custom,
        };
    }
}
