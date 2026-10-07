<?php

namespace App\Support;

use App\Enums\RoleName;

class OfficeCatalog
{
    /**
     * @return array{
     *     value: string,
     *     kicker: string,
     *     title: string,
     *     office: string,
     *     summary: string,
     *     powers: list<string>,
     *     pending_count: int|null,
     *     pending_label: string|null,
     *     requires_department: bool
     * }
     */
    public static function card(RoleName $role, ?int $pendingCount): array
    {
        $details = self::details($role);

        return [
            'value' => $role->value,
            'kicker' => $details['kicker'],
            'title' => $details['title'],
            'office' => $details['office'],
            'summary' => $details['summary'],
            'powers' => $details['powers'],
            'pending_count' => $pendingCount,
            'pending_label' => $pendingCount === null ? null : $details['pending_label'],
            'requires_department' => $role->requiresDepartment(),
        ];
    }

    /**
     * @return array{
     *     kicker: string,
     *     title: string,
     *     office: string,
     *     summary: string,
     *     powers: list<string>,
     *     pending_label: string|null
     * }
     */
    private static function details(RoleName $role): array
    {
        return match ($role) {
            RoleName::SuperAdmin => [
                'kicker' => 'Super admin',
                'title' => 'Provincial IT Administrator',
                'office' => 'Provincial Information Technology',
                'summary' => 'Highest authority in the system. Configures users, workflows, and every office.',
                'powers' => [
                    'User clearances and office assignment',
                    'Workflow publishing and task overrides',
                    'Enter any office without losing super admin access',
                ],
                'pending_label' => null,
            ],
            RoleName::Governor => [
                'kicker' => 'Governor',
                'title' => 'Provincial Governor',
                'office' => 'Office of the Provincial Governor',
                'summary' => 'Final approval of assistance programs across departments.',
                'powers' => [
                    'Review programs awaiting gubernatorial approval',
                    'Approve or return a program with a comment',
                    'View programs, funds, assistances, and beneficiaries across departments',
                ],
                'pending_label' => 'Program(s) awaiting gubernatorial approval',
            ],
            RoleName::DepartmentHead => [
                'kicker' => 'Department head',
                'title' => 'Department Head',
                'office' => 'Provincial Social Welfare and Development Office',
                'summary' => 'Formulates assistance programs and endorses them for governor approval.',
                'powers' => [
                    'Create and edit programs, funds, and items',
                    'Submit, endorse, and revise proposed programs',
                    'View beneficiaries and assistance performance',
                ],
                'pending_label' => 'Proposed program(s) for review',
            ],
            RoleName::ReleasingOfficer => [
                'kicker' => 'Releasing officer',
                'title' => 'Releasing Officer',
                'office' => 'Field operations and payout',
                'summary' => 'Enrolls beneficiaries and releases assistance on approved programs.',
                'powers' => [
                    'Enroll and update beneficiaries',
                    'Encode, claim, and release assistance',
                    'View approved programs, items, and the queue',
                ],
                'pending_label' => null,
            ],
            default => [
                'kicker' => $role->label(),
                'title' => $role->label(),
                'office' => '',
                'summary' => '',
                'powers' => [],
                'pending_label' => null,
            ],
        };
    }
}
