<?php

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function createDatabaseNotification(
    User $user,
    array $data,
    ?string $readAt = null,
    string $type = 'App\\Notifications\\TestNotification',
): void {
    DB::table('notifications')->insert([
        'id' => (string) Str::uuid(),
        'type' => $type,
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => json_encode($data, JSON_THROW_ON_ERROR),
        'read_at' => $readAt,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('guests cannot view the notifications page', function () {
    $department = Department::create(['name' => 'Social Welfare']);

    $this->get(route('user.notifications.index', ['department' => $department->slug]))
        ->assertRedirect(route('login'));
});

test('department users can view their notifications', function () {
    $department = Department::create(['name' => 'Social Welfare']);
    $user = User::factory()->create(['department_id' => $department->id]);

    createDatabaseNotification($user, [
        'title' => 'Personal update',
        'message' => 'First notification',
        'category' => 'personal',
    ]);
    createDatabaseNotification($user, [
        'title' => 'System update',
        'message' => 'System notification',
        'category' => 'system',
    ]);

    $this->actingAs($user)
        ->get(route('user.notifications.index', ['department' => $department->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('user/notifications/index')
            ->where('department.slug', $department->slug)
            ->has('notifications.data', 2)
            ->where('notifications.data.0.title', 'System update')
            ->where('notifications.data.1.title', 'Personal update'));
});

test('notification messages decode html entities', function () {
    $department = Department::create(['name' => 'Social Welfare']);
    $user = User::factory()->create(['department_id' => $department->id]);

    createDatabaseNotification($user, [
        'title' => 'Assistance &amp; Support',
        'message' => '<p>Request approved for &quot;Rice&quot; &amp; supplies.</p>',
    ]);

    $this->actingAs($user)
        ->get(route('user.notifications.index', ['department' => $department->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('notifications.data.0.title', 'Assistance & Support')
            ->where('notifications.data.0.message', '<p>Request approved for "Rice" & supplies.</p>'));
});

test('notifications page only returns notifications for the authenticated user', function () {
    $department = Department::create(['name' => 'Social Welfare']);
    $user = User::factory()->create(['department_id' => $department->id]);
    $otherUser = User::factory()->create(['department_id' => $department->id]);

    createDatabaseNotification($user, ['message' => 'My notification']);
    createDatabaseNotification($otherUser, ['message' => 'Other user notification']);

    $this->actingAs($user)
        ->get(route('user.notifications.index', ['department' => $department->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('notifications.data', 1)
            ->where('notifications.data.0.message', 'My notification'));
});

test('authenticated users cannot view notifications for another department', function () {
    $departmentA = Department::create(['name' => 'Social Welfare']);
    $departmentB = Department::create(['name' => 'Health']);
    $user = User::factory()->create(['department_id' => $departmentA->id]);

    $this->actingAs($user)
        ->get(route('user.notifications.index', ['department' => $departmentB->slug]))
        ->assertForbidden();
});

test('department users can view a single notification', function () {
    $department = Department::create(['name' => 'Social Welfare']);
    $user = User::factory()->create(['department_id' => $department->id]);
    $notificationId = (string) Str::uuid();

    DB::table('notifications')->insert([
        'id' => $notificationId,
        'type' => 'App\\Notifications\\TestNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => json_encode([
            'title' => 'Assistance approved',
            'message' => 'Your assistance request was approved.',
            'category' => 'personal',
            'action_url' => '/programs/1',
        ], JSON_THROW_ON_ERROR),
        'read_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('user.notifications.show', [
            'department' => $department->slug,
            'notification' => $notificationId,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('user/notifications/show')
            ->where('notification.id', $notificationId)
            ->where('notification.title', 'Assistance approved')
            ->where('notification.message', 'Your assistance request was approved.')
            ->where('notification.url', '/programs/1')
            ->where('notification.category', 'personal'));
});

test('users cannot view another users notification', function () {
    $department = Department::create(['name' => 'Social Welfare']);
    $user = User::factory()->create(['department_id' => $department->id]);
    $otherUser = User::factory()->create(['department_id' => $department->id]);
    $notificationId = (string) Str::uuid();

    DB::table('notifications')->insert([
        'id' => $notificationId,
        'type' => 'App\\Notifications\\TestNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $otherUser->id,
        'data' => json_encode(['message' => 'Private notification'], JSON_THROW_ON_ERROR),
        'read_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('user.notifications.show', [
            'department' => $department->slug,
            'notification' => $notificationId,
        ]))
        ->assertForbidden();
});
