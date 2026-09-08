<?php

use App\Models\Assistance;
use App\Models\Beneficiary;
use App\Models\Department;
use App\Models\Program;
use App\Models\User;
use App\Notifications\StaleAssistanceReminderNotification;
use App\Support\RequestSubStatusCode;
use Illuminate\Support\Facades\DB;

test('it creates database notifications for stale open assistances', function () {
    $department = Department::create(['name' => 'Social Welfare']);
    $encoder = User::factory()->create(['department_id' => $department->id]);
    $assignee = User::factory()->create(['department_id' => $department->id]);

    $program = Program::create([
        'name' => 'Aid Program',
        'descriptions' => 'Assistance support program',
        'start_at' => now()->subYears(8)->toDateString(),
        'end_at' => null,
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    $beneficiary = Beneficiary::create([
        'cais_number' => 'CAIS-001',
        'name' => 'Juan Dela Cruz',
        'beneficiable_type' => 'App\\Models\\Individual',
        'beneficiable_id' => 1,
    ]);

    $openSubStatusId = catalogReasonId(RequestSubStatusCode::AwaitingReview);
    $closedSubStatusId = catalogReasonId(RequestSubStatusCode::Closed);

    $staleOpenAssistance = Assistance::query()->create([
        'program_id' => $program->id,
        'mode_of_request_id' => null,
        'beneficiary_id' => $beneficiary->id,
        'date_requested' => now()->subYears(8)->toDateString(),
        'date_delivered' => null,
        'user_id' => $encoder->id,
        'assigned_to_id' => $assignee->id,
        'updated_at' => now()->subDays(8),
    ]);

    $staleClosedAssistance = Assistance::query()->create([
        'program_id' => $program->id,
        'mode_of_request_id' => null,
        'beneficiary_id' => null,
        'date_requested' => now()->subYears(8)->toDateString(),
        'date_delivered' => now()->subYears(8)->toDateString(),
        'user_id' => $encoder->id,
        'updated_at' => now()->subDays(8),
    ]);

    $recentOpenAssistance = Assistance::query()->create([
        'program_id' => $program->id,
        'mode_of_request_id' => null,
        'beneficiary_id' => null,
        'date_requested' => now()->subYears(3)->toDateString(),
        'date_delivered' => null,
        'user_id' => $encoder->id,
        'assigned_to_id' => $assignee->id,
        'updated_at' => now()->subDays(3),
    ]);

    DB::table('assistance_request_sub_status')->insert([
        [
            'assistance_id' => $staleOpenAssistance->id,
            'request_sub_status_id' => $openSubStatusId,
            'remark' => null,
            'recorded_at' => now()->subDays(8),
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ],
        [
            'assistance_id' => $staleClosedAssistance->id,
            'request_sub_status_id' => $closedSubStatusId,
            'remark' => null,
            'recorded_at' => now()->subDays(8),
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ],
        [
            'assistance_id' => $recentOpenAssistance->id,
            'request_sub_status_id' => $openSubStatusId,
            'remark' => null,
            'recorded_at' => now()->subDays(3),
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ],
    ]);

    DB::table('assistances')->where('id', $staleOpenAssistance->id)->update([
        'current_request_sub_status_id' => $openSubStatusId,
        'current_status_recorded_at' => now()->subDays(8),
        'assigned_to_id' => $assignee->id,
        'was_delivered' => false,
    ]);
    DB::table('assistances')->where('id', $staleClosedAssistance->id)->update([
        'current_request_sub_status_id' => $closedSubStatusId,
        'current_status_recorded_at' => now()->subDays(8),
        'was_delivered' => false,
    ]);
    DB::table('assistances')->where('id', $recentOpenAssistance->id)->update([
        'current_request_sub_status_id' => $openSubStatusId,
        'current_status_recorded_at' => now()->subDays(3),
        'was_delivered' => false,
    ]);

    $this->artisan('assistances:notify-stale')
        ->expectsOutput('Dispatched 1 stale assistance notification(s).')
        ->assertSuccessful();

    $notification = $assignee->notifications()->first();

    expect($notification)->not->toBeNull();
    expect($notification->type)->toBe(StaleAssistanceReminderNotification::class);
    expect($notification->data['month_key'])->toBe(now()->startOfMonth()->format('Y-m'));
    expect($notification->data['assistance_id'])->toBe($staleOpenAssistance->id);
    expect($notification->data['url'])->toBe(route('user.assistances.show', [
        'department' => $department->slug,
        'program' => $program->id,
        'assistance' => $staleOpenAssistance->id,
    ]));
    expect($notification->data['message'])->toContain('Juan Dela Cruz');
    expect($notification->data['message'])->toContain('CAIS-001');
    expect($notification->data['message'])->toContain('Aid Program');
    expect($notification->data['message'])->toContain('Submitted — Awaiting Review');
    expect($notification->data['message'])->toContain('View request profile');
    expect($encoder->notifications()->count())->toBe(0);

    $this->artisan('assistances:notify-stale')
        ->expectsOutput('Dispatched 0 stale assistance notification(s).')
        ->assertSuccessful();

    expect($assignee->notifications()->count())->toBe(1);
});
