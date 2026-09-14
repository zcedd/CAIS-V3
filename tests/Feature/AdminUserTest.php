<?php

use App\Enums\RoleName;
use App\Models\Department;
use App\Models\User;
use App\Notifications\UserInvitedNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

test('guests cannot view the admin users page', function () {
    $this->get(route('admin.users.index'))
        ->assertRedirect(route('login'));
});

test('staff cannot view the admin users page', function () {
    $department = Department::create(['name' => 'Department A']);
    $user = User::factory()->create([
        'department_id' => $department->id,
    ]);

    $this->actingAs($user)
        ->get(route('admin.users.index'))
        ->assertForbidden();
});

test('department heads cannot view the admin users page', function () {
    seedRolesAndPermissions();

    $user = User::factory()->create();
    $user->syncRoles([RoleName::Head->value]);

    $this->actingAs($user)
        ->get(route('admin.users.index'))
        ->assertForbidden();
});

test('super admin is redirected from admin index to users', function () {
    $admin = assignSuperAdminRole(User::factory()->create());

    $this->actingAs($admin)
        ->get(route('admin.index'))
        ->assertRedirect(route('admin.users.index'));
});

test('super admin can view the users page', function () {
    $department = Department::create(['name' => 'Department A']);
    $admin = assignSuperAdminRole(User::factory()->create([
        'firstName' => 'Zced',
        'lastName' => 'Admin',
        'email' => 'admin@example.com',
    ]));
    $staff = User::factory()->create([
        'firstName' => 'Ana',
        'lastName' => 'Santos',
        'email' => 'ana@example.com',
        'department_id' => $department->id,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/index')
            ->has('users.data', 2)
            ->where('auth.is_super_admin', true)
            ->has('role_options')
            ->has('departments', 1)
            ->where('departments.0.id', $department->id));

    expect($staff->hasRole(RoleName::Assistance))->toBeTrue();
});

test('super admin can create a user with a department and roles', function () {
    Notification::fake();

    $department = Department::create(['name' => 'Social Welfare']);
    $admin = assignSuperAdminRole(User::factory()->create());

    $this->actingAs($admin)
        ->from(route('admin.users.index'))
        ->post(route('admin.users.store'), [
            'firstName' => 'Ana',
            'middleName' => 'Cruz',
            'lastName' => 'Santos',
            'email' => 'ana@example.com',
            'department_id' => $department->id,
            'roles' => [RoleName::Assistance->value, RoleName::Program->value],
        ])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('success');

    $user = User::query()->where('email', 'ana@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->firstName)->toBe('Ana')
        ->and($user->department_id)->toBe($department->id)
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->hasRole(RoleName::Assistance))->toBeTrue()
        ->and($user->hasRole(RoleName::Program))->toBeTrue()
        ->and($user->isSuperAdmin())->toBeFalse()
        ->and(Hash::check('password', $user->password))->toBeFalse();

    Notification::assertSentTo($user, UserInvitedNotification::class);
});

test('an invited user can set a password from the invite link and log in', function () {
    Notification::fake();

    $admin = assignSuperAdminRole(User::factory()->create());

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'firstName' => 'Ana',
            'lastName' => 'Santos',
            'email' => 'ana@example.com',
            'roles' => [RoleName::Assistance->value],
        ])
        ->assertRedirect();

    $user = User::query()->where('email', 'ana@example.com')->first();

    expect($user)->not->toBeNull();

    $this->post(route('logout'));

    Notification::assertSentTo($user, UserInvitedNotification::class, function (UserInvitedNotification $notification) use ($user): bool {
        $this->get(route('password.reset', [
            'token' => $notification->token,
            'email' => $user->email,
            'invite' => 1,
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/reset-password')
                ->where('isInvite', true)
                ->where('email', $user->email));

        $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        return true;
    });

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

test('super admin can update a user department and roles', function () {
    $department = Department::create(['name' => 'Social Welfare']);
    $admin = assignSuperAdminRole(User::factory()->create());
    $user = withoutResourceRoles(User::factory()->create([
        'firstName' => 'Ana',
        'lastName' => 'Santos',
        'email' => 'ana@example.com',
    ]));

    $this->actingAs($admin)
        ->from(route('admin.users.index'))
        ->put(route('admin.users.update', $user), [
            'firstName' => 'Ana',
            'middleName' => null,
            'lastName' => 'Reyes',
            'email' => 'ana@example.com',
            'department_id' => $department->id,
            'roles' => [RoleName::Workflow->value],
        ])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('success');

    $user->refresh();

    expect($user->lastName)->toBe('Reyes')
        ->and($user->department_id)->toBe($department->id)
        ->and($user->hasRole(RoleName::Workflow))->toBeTrue()
        ->and($user->roles)->toHaveCount(1);
});

test('super admin cannot remove their own super admin role', function () {
    $admin = assignSuperAdminRole(User::factory()->create());

    $this->actingAs($admin)
        ->from(route('admin.users.index'))
        ->put(route('admin.users.update', $admin), [
            'firstName' => $admin->firstName,
            'lastName' => $admin->lastName,
            'email' => $admin->email,
            'roles' => [RoleName::Head->value],
        ])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHasErrors('roles');

    expect($admin->fresh()?->isSuperAdmin())->toBeTrue();
});

test('the last super admin cannot be demoted', function () {
    $admin = assignSuperAdminRole(User::factory()->create());
    $otherAdmin = assignSuperAdminRole(User::factory()->create());

    $this->actingAs($admin)
        ->put(route('admin.users.update', $otherAdmin), [
            'firstName' => $otherAdmin->firstName,
            'lastName' => $otherAdmin->lastName,
            'email' => $otherAdmin->email,
            'roles' => [RoleName::Head->value],
        ])
        ->assertRedirect();

    expect($otherAdmin->fresh()?->isSuperAdmin())->toBeFalse();

    $this->actingAs($admin)
        ->from(route('admin.users.index'))
        ->put(route('admin.users.update', $admin), [
            'firstName' => $admin->firstName,
            'lastName' => $admin->lastName,
            'email' => $admin->email,
            'roles' => [RoleName::Head->value],
        ])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHasErrors('roles');

    expect($admin->fresh()?->isSuperAdmin())->toBeTrue();
});

test('super admin cannot delete their own account', function () {
    $admin = assignSuperAdminRole(User::factory()->create());

    $this->actingAs($admin)
        ->from(route('admin.users.index'))
        ->delete(route('admin.users.destroy', $admin))
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHasErrors('user');

    $this->assertDatabaseHas('users', [
        'id' => $admin->id,
        'deleted_at' => null,
    ]);
});

test('super admin can delete another user', function () {
    $admin = assignSuperAdminRole(User::factory()->create());
    $user = withoutResourceRoles(User::factory()->create());

    $this->actingAs($admin)
        ->from(route('admin.users.index'))
        ->delete(route('admin.users.destroy', $user))
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('success');

    $this->assertSoftDeleted('users', [
        'id' => $user->id,
    ]);
});
