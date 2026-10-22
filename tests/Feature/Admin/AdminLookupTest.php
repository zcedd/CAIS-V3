<?php

use App\Enums\DocumentTypeSlug;
use App\Models\Assistance;
use App\Models\Department;
use App\Models\DocumentType;
use App\Models\Item;
use App\Models\ItemUnitMeasurement;
use App\Models\ModeOfRequest;
use App\Models\Program;
use App\Models\ProgramDocumentRequirement;
use App\Models\Sector;
use App\Models\User;
use App\Services\User\AssistanceDocumentService;
use App\Services\User\AssistanceService;
use App\Services\User\ItemService;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array<string, array{0: string, 1: string}>
 */
function lookupPages(): array
{
    return [
        'modes of request' => ['admin.modes-of-request.index', 'admin/modes-of-request/index'],
        'document types' => ['admin.document-types.index', 'admin/document-types/index'],
        'unit measurements' => ['admin.unit-measurements.index', 'admin/unit-measurements/index'],
        'sectors' => ['admin.sectors.index', 'admin/sectors/index'],
    ];
}

function lookupAdmin(): User
{
    return assignSuperAdminRole(User::factory()->create());
}

test('guests cannot view lookup pages', function (string $route) {
    $this->get(route($route))
        ->assertRedirect(route('login'));
})->with(fn () => collect(lookupPages())->map(fn (array $page) => [$page[0]])->all());

test('staff cannot view lookup pages', function (string $route) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route($route))
        ->assertForbidden();
})->with(fn () => collect(lookupPages())->map(fn (array $page) => [$page[0]])->all());

test('super admin can open each lookup page', function (string $route, string $component) {
    $this->actingAs(lookupAdmin())
        ->get(route($route))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component($component));
})->with(fn () => lookupPages());

test('super admin can save and deactivate a mode of request', function () {
    $admin = lookupAdmin();

    $this->actingAs($admin)
        ->post(route('admin.modes-of-request.store'), [
            'name' => 'Radio',
            'is_active' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Mode of request saved.');

    $mode = ModeOfRequest::query()->where('name', 'Radio')->first();

    expect($mode)->not->toBeNull()
        ->and($mode->is_active)->toBeTrue();

    $this->actingAs($admin)
        ->put(route('admin.modes-of-request.update', $mode), [
            'name' => 'Radio call',
            'is_active' => false,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $fresh = $mode->fresh();

    expect($fresh)->not->toBeNull()
        ->and($fresh->name)->toBe('Radio call')
        ->and($fresh->is_active)->toBeFalse();
});

test('a mode of request rejects a duplicate name regardless of case', function () {
    $admin = lookupAdmin();
    ModeOfRequest::factory()->create(['name' => 'Walk In']);

    $this->actingAs($admin)
        ->from(route('admin.modes-of-request.index'))
        ->post(route('admin.modes-of-request.store'), [
            'name' => '  walk in  ',
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.modes-of-request.index'))
        ->assertSessionHasErrors([
            'name' => 'The name has already been taken.',
        ]);

    expect(ModeOfRequest::query()->count())->toBe(1);
});

test('a mode of request requires a name', function () {
    $this->actingAs(lookupAdmin())
        ->post(route('admin.modes-of-request.store'), [
            'is_active' => true,
        ])
        ->assertSessionHasErrors('name');
});

test('super admin can delete an unused mode of request', function () {
    $admin = lookupAdmin();
    $mode = ModeOfRequest::factory()->create(['name' => 'Fax']);

    $this->actingAs($admin)
        ->delete(route('admin.modes-of-request.destroy', $mode))
        ->assertRedirect()
        ->assertSessionHas('success', 'Mode of request deleted.');

    $this->assertModelMissing($mode);
});

test('a mode of request used by an assistance cannot be deleted', function () {
    $admin = lookupAdmin();
    $mode = ModeOfRequest::factory()->create(['name' => 'Fax']);
    $program = Program::factory()->create();
    Assistance::query()->create([
        'program_id' => $program->id,
        'mode_of_request_id' => $mode->id,
        'date_requested' => now()->toDateString(),
        'user_id' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.modes-of-request.destroy', $mode))
        ->assertSessionHasErrors([
            'lookup' => 'This mode of request is used by 1 record. Deactivate it instead of deleting it.',
        ]);

    $this->assertModelExists($mode);
});

test('the online mode cannot be renamed, deactivated, or deleted', function () {
    $admin = lookupAdmin();
    $mode = ModeOfRequest::factory()->create([
        'name' => ModeOfRequest::PUBLIC_INTAKE_NAME,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.modes-of-request.update', $mode), [
            'name' => 'Web',
            'is_active' => true,
        ])
        ->assertSessionHasErrors([
            'name' => 'The Online mode is required by public intake and cannot be changed.',
        ]);

    $this->actingAs($admin)
        ->put(route('admin.modes-of-request.update', $mode), [
            'name' => ModeOfRequest::PUBLIC_INTAKE_NAME,
            'is_active' => false,
        ])
        ->assertSessionHasErrors('is_active');

    $this->actingAs($admin)
        ->delete(route('admin.modes-of-request.destroy', $mode))
        ->assertSessionHasErrors([
            'lookup' => 'The Online mode is required by public intake and cannot be deleted.',
        ]);

    $fresh = $mode->fresh();

    expect($fresh)->not->toBeNull()
        ->and($fresh->name)->toBe(ModeOfRequest::PUBLIC_INTAKE_NAME)
        ->and($fresh->is_active)->toBeTrue();
});

test('a deactivated mode stays on a program that already uses it', function () {
    $admin = lookupAdmin();
    $mode = ModeOfRequest::factory()->create(['name' => 'Radio']);
    $program = Program::factory()->create();
    Assistance::query()->create([
        'program_id' => $program->id,
        'mode_of_request_id' => $mode->id,
        'date_requested' => now()->toDateString(),
        'user_id' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.modes-of-request.update', $mode), [
            'name' => 'Radio',
            'is_active' => false,
        ])
        ->assertSessionHasNoErrors();

    $service = app(AssistanceService::class);

    expect(collect($service->modesOfRequestForSelect())->pluck('id'))->not->toContain($mode->id)
        ->and(collect($service->modesOfRequestForSelect($program))->pluck('id'))->toContain($mode->id);
});

test('super admin can save a document type with a generated slug', function () {
    $admin = lookupAdmin();

    $this->actingAs($admin)
        ->post(route('admin.document-types.store'), [
            'name' => 'Barangay Certificate',
            'is_active' => true,
            'slug' => 'hacked',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $type = DocumentType::query()->where('name', 'Barangay Certificate')->first();

    expect($type)->not->toBeNull()
        ->and($type->slug)->toBe('barangay_certificate');
});

test('a system document type can be renamed but not deactivated or deleted', function () {
    $admin = lookupAdmin();
    $type = DocumentType::query()
        ->where('slug', DocumentTypeSlug::ValidId->value)
        ->firstOrFail();

    $this->actingAs($admin)
        ->put(route('admin.document-types.update', $type), [
            'name' => 'Government ID',
            'is_active' => true,
            'slug' => 'hacked',
        ])
        ->assertSessionHasNoErrors();

    $renamed = $type->fresh();

    expect($renamed)->not->toBeNull()
        ->and($renamed->name)->toBe('Government ID')
        ->and($renamed->slug)->toBe(DocumentTypeSlug::ValidId->value);

    $this->actingAs($admin)
        ->put(route('admin.document-types.update', $type), [
            'name' => 'Government ID',
            'is_active' => false,
        ])
        ->assertSessionHasErrors([
            'is_active' => 'This document type is required by the system and cannot be deactivated.',
        ]);

    $this->actingAs($admin)
        ->delete(route('admin.document-types.destroy', $type))
        ->assertSessionHasErrors([
            'lookup' => 'This document type is required by the system and cannot be deleted.',
        ]);

    expect($type->fresh()?->is_active)->toBeTrue();
});

test('a document type used by a program cannot be deleted', function () {
    $admin = lookupAdmin();
    $type = DocumentType::factory()->create([
        'name' => 'Sketch',
        'slug' => 'sketch',
    ]);
    ProgramDocumentRequirement::factory()->create([
        'document_type_id' => $type->id,
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.document-types.destroy', $type))
        ->assertSessionHasErrors([
            'lookup' => 'This document type is used by 1 record. Deactivate it instead of deleting it.',
        ]);

    $this->assertModelExists($type);
});

test('a deactivated document type stays on a program that already requires it', function () {
    $admin = lookupAdmin();
    $type = DocumentType::factory()->create([
        'name' => 'Sketch',
        'slug' => 'sketch',
    ]);
    $requirement = ProgramDocumentRequirement::factory()->create([
        'document_type_id' => $type->id,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.document-types.update', $type), [
            'name' => 'Sketch',
            'is_active' => false,
        ])
        ->assertSessionHasNoErrors();

    $service = app(AssistanceDocumentService::class);
    $program = $requirement->program;

    expect(collect($service->documentTypesForSelect())->pluck('id'))->not->toContain($type->id)
        ->and(collect($service->documentTypesForProgram($program))->pluck('id'))->toContain($type->id);
});

test('the php unit cannot be renamed, deactivated, or deleted', function () {
    $admin = lookupAdmin();
    $unit = ItemUnitMeasurement::factory()->create([
        'name' => ItemUnitMeasurement::CASH_NAME,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.unit-measurements.update', $unit), [
            'name' => 'peso',
            'is_active' => true,
        ])
        ->assertSessionHasErrors([
            'name' => 'The php unit is required by cash items and cannot be changed.',
        ]);

    $this->actingAs($admin)
        ->put(route('admin.unit-measurements.update', $unit), [
            'name' => ItemUnitMeasurement::CASH_NAME,
            'is_active' => false,
        ])
        ->assertSessionHasErrors('is_active');

    $this->actingAs($admin)
        ->delete(route('admin.unit-measurements.destroy', $unit))
        ->assertSessionHasErrors([
            'lookup' => 'The php unit is required by cash items and cannot be deleted.',
        ]);

    $fresh = $unit->fresh();

    expect($fresh)->not->toBeNull()
        ->and($fresh->name)->toBe(ItemUnitMeasurement::CASH_NAME)
        ->and($fresh->is_active)->toBeTrue();
});

test('a unit used by an item cannot be deleted', function () {
    $admin = lookupAdmin();
    $unit = ItemUnitMeasurement::factory()->create(['name' => 'sack']);
    Item::factory()->create([
        'item_unit_measurement_id' => $unit->id,
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.unit-measurements.destroy', $unit))
        ->assertSessionHasErrors([
            'lookup' => 'This unit is used by 1 record. Deactivate it instead of deleting it.',
        ]);

    $this->assertModelExists($unit);
});

test('a deactivated unit stays on items in the department that uses it', function () {
    $admin = lookupAdmin();
    $department = Department::query()->create(['name' => 'Relief']);
    $unit = ItemUnitMeasurement::factory()->create(['name' => 'sack']);
    Item::factory()->create([
        'department_id' => $department->id,
        'item_unit_measurement_id' => $unit->id,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.unit-measurements.update', $unit), [
            'name' => 'sack',
            'is_active' => false,
        ])
        ->assertSessionHasNoErrors();

    $service = app(ItemService::class);

    expect(collect($service->unitMeasurementsForSelect())->pluck('id'))->not->toContain($unit->id)
        ->and(collect($service->unitMeasurementsForSelect($department))->pluck('id'))->toContain($unit->id);
});

test('super admin can save and delete an unused sector', function () {
    $admin = lookupAdmin();

    $this->actingAs($admin)
        ->post(route('admin.sectors.store'), [
            'name' => 'Social governance',
            'is_active' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Sector saved.');

    $sector = Sector::query()->where('name', 'Social governance')->first();

    expect($sector)->not->toBeNull();

    $this->actingAs($admin)
        ->delete(route('admin.sectors.destroy', $sector))
        ->assertRedirect()
        ->assertSessionHas('success', 'Sector deleted.');

    $this->assertSoftDeleted($sector);
});

test('a sector linked to a department cannot be deleted', function () {
    $admin = lookupAdmin();
    $sector = Sector::factory()->create(['name' => 'Social governance']);
    $department = Department::query()->create(['name' => 'Social Welfare']);
    $department->sector()->attach($sector->id);

    $this->actingAs($admin)
        ->delete(route('admin.sectors.destroy', $sector))
        ->assertSessionHasErrors([
            'lookup' => 'This sector is used by 1 record. Deactivate it instead of deleting it.',
        ]);

    $this->assertModelExists($sector);
});
