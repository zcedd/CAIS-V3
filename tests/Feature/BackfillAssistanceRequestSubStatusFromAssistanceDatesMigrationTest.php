<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

const BACKFILL_MIGRATION_PATH = 'database/migrations/2026_05_20_070518_backfill_assistance_request_sub_status_from_assistance_dates.php';

function seedRequestStatusFixtures(): array
{
    DB::table('request_statuses')->insert([
        ['id' => 2, 'name' => 'Submitted'],
        ['id' => 13, 'name' => 'Closed'],
        ['id' => 4, 'name' => 'Verification'],
        ['id' => 11, 'name' => 'Delivered'],
        ['id' => 12, 'name' => 'Denied'],
    ]);

    $subStatusIds = [];

    foreach (
        [
            'Awaiting Review' => 2,
            'Verified' => 4,
            'Successfully Delivered' => 11,
            'Eligibility Denied' => 12,
            'Closed after Resolution' => 13,
            'Closed after Denial' => 13,
        ] as $name => $requestStatusId
    ) {
        $subStatusIds[$name] = DB::table('request_sub_statuses')->insertGetId([
            'request_status_id' => $requestStatusId,
            'name' => $name,
            'description' => null,
        ]);
    }

    return $subStatusIds;
}

function createAssistanceWithDates(array $dates): int
{
    $departmentId = DB::table('departments')->insertGetId([
        'name' => 'Backfill Department '.uniqid(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $userId = DB::table('users')->insertGetId([
        'name' => 'Backfill User',
        'email' => 'backfill-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'department_id' => $departmentId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $programId = DB::table('programs')->insertGetId([
        'name' => 'Backfill Program',
        'descriptions' => null,
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $departmentId,
        'is_closed' => false,
        'is_organization' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return DB::table('assistances')->insertGetId(array_merge([
        'program_id' => $programId,
        'beneficiary_id' => null,
        'individual_id' => null,
        'organization_id' => null,
        'mode_of_request_id' => null,
        'date_requested' => null,
        'date_verified' => null,
        'date_denied' => null,
        'date_delivered' => null,
        'user_id' => $userId,
        'remark' => null,
        'created_at' => now(),
        'updated_at' => now(),
        'deleted_at' => null,
    ], $dates));
}

test('backfill creates a pivot row for every non-null assistance date column', function () {
    Artisan::call('migrate:rollback', ['--path' => BACKFILL_MIGRATION_PATH]);

    $subStatusIds = seedRequestStatusFixtures();

    $assistanceId = createAssistanceWithDates([
        'date_requested' => '2024-01-10',
        'date_verified' => '2024-01-15',
        'date_delivered' => '2024-01-20',
        'date_denied' => '2024-01-25',
    ]);

    expect(DB::table('assistance_request_sub_status')->count())->toBe(0);

    Artisan::call('migrate', ['--path' => BACKFILL_MIGRATION_PATH]);

    $pivots = DB::table('assistance_request_sub_status')
        ->where('assistance_id', $assistanceId)
        ->get()
        ->map(static fn ($pivot): array => [
            'request_sub_status_id' => (int) $pivot->request_sub_status_id,
            'created_at' => Carbon::parse($pivot->created_at)->toDateString(),
        ])
        ->all();

    expect($pivots)->toHaveCount(6);
    expect($pivots)->toContain([
        'request_sub_status_id' => $subStatusIds['Awaiting Review'],
        'created_at' => '2024-01-10',
    ]);
    expect($pivots)->toContain([
        'request_sub_status_id' => $subStatusIds['Verified'],
        'created_at' => '2024-01-15',
    ]);
    expect($pivots)->toContain([
        'request_sub_status_id' => $subStatusIds['Successfully Delivered'],
        'created_at' => '2024-01-20',
    ]);
    expect($pivots)->toContain([
        'request_sub_status_id' => $subStatusIds['Closed after Resolution'],
        'created_at' => '2024-01-20',
    ]);
    expect($pivots)->toContain([
        'request_sub_status_id' => $subStatusIds['Eligibility Denied'],
        'created_at' => '2024-01-25',
    ]);
    expect($pivots)->toContain([
        'request_sub_status_id' => $subStatusIds['Closed after Denial'],
        'created_at' => '2024-01-25',
    ]);
});

test('backfill skips null date columns', function () {
    Artisan::call('migrate:rollback', ['--path' => BACKFILL_MIGRATION_PATH]);

    $subStatusIds = seedRequestStatusFixtures();

    $assistanceId = createAssistanceWithDates([
        'date_requested' => '2024-02-01',
    ]);

    Artisan::call('migrate', ['--path' => BACKFILL_MIGRATION_PATH]);

    $pivots = DB::table('assistance_request_sub_status')
        ->where('assistance_id', $assistanceId)
        ->get();

    expect($pivots)->toHaveCount(1);
    expect($pivots[0]->request_sub_status_id)->toBe($subStatusIds['Awaiting Review']);
});

test('backfill is idempotent and does not duplicate existing pivot rows for the same sub-status', function () {
    Artisan::call('migrate:rollback', ['--path' => BACKFILL_MIGRATION_PATH]);

    $subStatusIds = seedRequestStatusFixtures();

    $assistanceId = createAssistanceWithDates([
        'date_requested' => '2024-03-01',
    ]);

    DB::table('assistance_request_sub_status')->insert([
        'assistance_id' => $assistanceId,
        'request_sub_status_id' => $subStatusIds['Awaiting Review'],
        'remark' => 'Recorded through the new flow',
        'created_at' => Carbon::parse('2024-03-05 10:00:00'),
        'updated_at' => Carbon::parse('2024-03-05 10:00:00'),
        'deleted_at' => null,
    ]);

    Artisan::call('migrate', ['--path' => BACKFILL_MIGRATION_PATH]);

    $pivots = DB::table('assistance_request_sub_status')
        ->where('assistance_id', $assistanceId)
        ->where('request_sub_status_id', $subStatusIds['Awaiting Review'])
        ->get();

    expect($pivots)->toHaveCount(1);
    expect($pivots[0]->remark)->toBe('Recorded through the new flow');
});

test('rollback removes only the backfilled pivot rows', function () {
    Artisan::call('migrate:rollback', ['--path' => BACKFILL_MIGRATION_PATH]);

    $subStatusIds = seedRequestStatusFixtures();

    $assistanceId = createAssistanceWithDates([
        'date_requested' => '2024-04-01',
    ]);

    DB::table('assistance_request_sub_status')->insert([
        'assistance_id' => $assistanceId,
        'request_sub_status_id' => $subStatusIds['Verified'],
        'remark' => 'Manual entry preserved on rollback',
        'created_at' => now(),
        'updated_at' => now(),
        'deleted_at' => null,
    ]);

    Artisan::call('migrate', ['--path' => BACKFILL_MIGRATION_PATH]);

    expect(DB::table('assistance_request_sub_status')->where('assistance_id', $assistanceId)->count())->toBe(2);

    Artisan::call('migrate:rollback', ['--path' => BACKFILL_MIGRATION_PATH]);

    $remaining = DB::table('assistance_request_sub_status')
        ->where('assistance_id', $assistanceId)
        ->get();

    expect($remaining)->toHaveCount(1);
    expect($remaining[0]->request_sub_status_id)->toBe($subStatusIds['Verified']);
    expect($remaining[0]->remark)->toBe('Manual entry preserved on rollback');
});
