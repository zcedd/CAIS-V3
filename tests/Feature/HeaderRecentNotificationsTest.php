<?php

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function insertHeaderNotification(
    User $user,
    array $data,
    mixed $createdAt = null,
): void {
    DB::table('notifications')->insert([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\TestNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => json_encode($data, JSON_THROW_ON_ERROR),
        'read_at' => null,
        'created_at' => $createdAt ?? now(),
        'updated_at' => now(),
    ]);
}

test('a normal page load omits recent notifications', function () {
    $department = Department::create(['name' => 'Social Welfare']);
    $user = User::factory()->create(['department_id' => $department->id]);

    insertHeaderNotification($user, [
        'title' => 'Unread notice',
        'message' => 'Still waiting',
    ]);

    $this->actingAs($user)
        ->get(route('user.notifications.index', ['department' => $department->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->missing('recentNotifications'));
});

test('a partial reload returns the five newest notifications for the current user', function () {
    $department = Department::create(['name' => 'Social Welfare']);
    $user = User::factory()->create(['department_id' => $department->id]);
    $other = User::factory()->create(['department_id' => $department->id]);

    foreach (range(1, 6) as $index) {
        insertHeaderNotification($user, [
            'title' => "Notice {$index}",
            'message' => "Message {$index}",
        ], now()->subMinutes(6 - $index));
    }

    insertHeaderNotification($other, [
        'title' => 'Other user notice',
        'message' => 'Hidden',
    ]);

    $response = $this->actingAs($user)
        ->get(
            route('user.notifications.index', ['department' => $department->slug]),
            inertiaPartialHeaders('user/notifications/index', ['recentNotifications']),
        )
        ->assertOk()
        ->assertJsonCount(5, 'props.recentNotifications')
        ->assertJsonPath('props.recentNotifications.0.title', 'Notice 6')
        ->assertJsonPath('props.recentNotifications.0.message', 'Message 6')
        ->assertJsonPath('props.recentNotifications.0.read_at', null)
        ->assertJsonPath('props.recentNotifications.4.title', 'Notice 2');

    $titles = collect($response->json('props.recentNotifications'))->pluck('title');

    expect($titles)->not->toContain('Notice 1')
        ->and($titles)->not->toContain('Other user notice');

    expect($response->json('props.recentNotifications.0.created_at'))->not->toBeNull()
        ->and($response->json('props.recentNotifications.0.id'))->not->toBeEmpty();
});
