<?php

use App\Models\Department;
use App\Models\User;
use App\Services\User\NotificationService;
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
    mixed $createdAt = null,
): void {
    DB::table('notifications')->insert([
        'id' => (string) Str::uuid(),
        'type' => $type,
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => json_encode($data, JSON_THROW_ON_ERROR),
        'read_at' => $readAt,
        'created_at' => $createdAt ?? now(),
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
    ], createdAt: now()->subMinute());
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

test('notification messages are returned as plain text', function () {
    $department = Department::create(['name' => 'Social Welfare']);
    $user = User::factory()->create(['department_id' => $department->id]);

    createDatabaseNotification($user, [
        'title' => 'Assistance &amp; Support',
        'message' => '<p>Request approved for &quot;Rice&quot; &amp; supplies.</p><img src=x onerror=alert(1)>',
    ]);

    $this->actingAs($user)
        ->get(route('user.notifications.index', ['department' => $department->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('notifications.data.0.title', 'Assistance & Support')
            ->where('notifications.data.0.message', 'Request approved for "Rice" & supplies.'));
});

test('shared unread notifications count reflects unread database notifications', function () {
    $department = Department::create(['name' => 'Social Welfare']);
    $user = User::factory()->create(['department_id' => $department->id]);

    createDatabaseNotification($user, ['message' => 'Unread notification']);
    createDatabaseNotification($user, ['message' => 'Read notification'], now()->toIso8601String());

    $this->actingAs($user)
        ->get(route('user.notifications.index', ['department' => $department->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('unreadNotificationsCount', 1));
});

test('marking a notification as read invalidates the unread count cache', function () {
    $department = Department::create(['name' => 'Social Welfare']);
    $user = User::factory()->create(['department_id' => $department->id]);
    $notificationId = (string) Str::uuid();

    DB::table('notifications')->insert([
        'id' => $notificationId,
        'type' => 'App\\Notifications\\TestNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => json_encode(['message' => 'Unread notification'], JSON_THROW_ON_ERROR),
        'read_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $notificationService = app(NotificationService::class);

    expect($notificationService->unreadCountForUser($user))->toBe(1);

    $notificationService->markAsReadForUser($user, $notificationId);

    expect($notificationService->unreadCountForUser($user))->toBe(0);
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

test('users cannot mark another users notification as read', function () {
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
        ->patch(route('user.notifications.read', [
            'department' => $department->slug,
            'notification' => $notificationId,
        ]))
        ->assertForbidden();
});

test('department users can mark a notification as read', function () {
    $department = Department::create(['name' => 'Social Welfare']);
    $user = User::factory()->create(['department_id' => $department->id]);
    $notificationId = (string) Str::uuid();

    DB::table('notifications')->insert([
        'id' => $notificationId,
        'type' => 'App\\Notifications\\TestNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => json_encode(['message' => 'Unread notification'], JSON_THROW_ON_ERROR),
        'read_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user)
        ->from(route('user.notifications.show', [
            'department' => $department->slug,
            'notification' => $notificationId,
        ]))
        ->patch(route('user.notifications.read', [
            'department' => $department->slug,
            'notification' => $notificationId,
        ]))
        ->assertRedirect()
        ->assertSessionHas('success', 'Notification marked as read.');

    $this->assertDatabaseHas('notifications', [
        'id' => $notificationId,
        'notifiable_id' => $user->id,
    ]);

    expect(DB::table('notifications')->where('id', $notificationId)->value('read_at'))
        ->not->toBeNull();
});

test('department users can mark all notifications as read', function () {
    $department = Department::create(['name' => 'Social Welfare']);
    $user = User::factory()->create(['department_id' => $department->id]);

    createDatabaseNotification($user, ['message' => 'Unread one']);
    createDatabaseNotification($user, ['message' => 'Unread two']);
    createDatabaseNotification($user, ['message' => 'Already read'], now()->toIso8601String());

    $this->actingAs($user)
        ->from(route('user.notifications.index', ['department' => $department->slug]))
        ->patch(route('user.notifications.read-all', ['department' => $department->slug]))
        ->assertRedirect()
        ->assertSessionHas('success', 'All notifications marked as read.');

    expect($user->fresh()->unreadNotifications()->count())->toBe(0);
});

test('users cannot mark all notifications read for another department', function () {
    $departmentA = Department::create(['name' => 'Social Welfare']);
    $departmentB = Department::create(['name' => 'Health']);
    $user = User::factory()->create(['department_id' => $departmentA->id]);

    createDatabaseNotification($user, ['message' => 'Unread notification']);

    $this->actingAs($user)
        ->patch(route('user.notifications.read-all', ['department' => $departmentB->slug]))
        ->assertForbidden();
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
