<?php

use App\Models\Department;
use App\Models\DocumentType;
use App\Models\Fund;
use App\Models\Item;
use App\Models\ItemUnitMeasurement;
use App\Models\Program;
use App\Models\User;
use App\Support\DocumentRequirementMilestone;
use App\Support\DocumentTypeSlug;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{fund: Fund, item: Item}
 */
function createProgramDocumentRequirementFixtures(Department $department): array
{
    $fund = Fund::create([
        'name' => 'General Fund',
        'year' => '2026',
        'amount' => 1000,
        'is_active' => true,
        'department_id' => $department->id,
    ]);

    $unit = ItemUnitMeasurement::create(['name' => 'pc']);

    $item = Item::create([
        'name' => 'Kit',
        'department_id' => $department->id,
        'item_unit_measurement_id' => $unit->id,
    ]);

    return ['fund' => $fund, 'item' => $item];
}

test('program create can define a document checklist', function () {
    $department = Department::create(['name' => 'Department A']);
    $user = User::factory()->create(['department_id' => $department->id]);
    ['fund' => $fund, 'item' => $item] = createProgramDocumentRequirementFixtures($department);
    $idType = DocumentType::query()->where('slug', DocumentTypeSlug::ValidId)->firstOrFail();
    $photoType = DocumentType::query()->where('slug', DocumentTypeSlug::DeliveryPhoto)->firstOrFail();

    $response = $this->actingAs($user)->post(route('user.programs.store', [
        'department' => $department->slug,
    ]), [
        'name' => 'Livelihood Program',
        'descriptions' => 'Support details',
        'start_at' => '2026-01-01',
        'end_at' => null,
        'fund_ids' => [$fund->id],
        'item_ids' => [$item->id],
        'document_requirements' => [
            [
                'document_type_id' => $idType->id,
                'is_required' => true,
                'required_before' => DocumentRequirementMilestone::Verified,
                'sort_order' => 0,
            ],
            [
                'document_type_id' => $photoType->id,
                'is_required' => true,
                'required_before' => DocumentRequirementMilestone::Delivered,
                'sort_order' => 1,
            ],
        ],
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $program = Program::query()->where('name', 'Livelihood Program')->first();
    expect($program)->not->toBeNull();

    $this->assertDatabaseHas('program_document_requirements', [
        'program_id' => $program->id,
        'document_type_id' => $idType->id,
        'is_required' => 1,
        'required_before' => DocumentRequirementMilestone::Verified,
    ]);

    $this->assertDatabaseHas('program_document_requirements', [
        'program_id' => $program->id,
        'document_type_id' => $photoType->id,
        'required_before' => DocumentRequirementMilestone::Delivered,
    ]);
});

test('program update can replace the document checklist', function () {
    $department = Department::create(['name' => 'Department A']);
    $user = User::factory()->create(['department_id' => $department->id]);
    ['fund' => $fund, 'item' => $item] = createProgramDocumentRequirementFixtures($department);
    $idType = DocumentType::query()->where('slug', DocumentTypeSlug::ValidId)->firstOrFail();
    $indigencyType = DocumentType::query()->where('slug', DocumentTypeSlug::IndigencyCertificate)->firstOrFail();

    $program = Program::create([
        'name' => 'Livelihood Program',
        'descriptions' => 'Support details',
        'start_at' => '2026-01-01',
        'end_at' => null,
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);
    $program->fund()->attach($fund->id);
    $program->item()->attach($item->id);
    $existing = $program->documentRequirements()->create([
        'document_type_id' => $idType->id,
        'is_required' => true,
        'required_before' => DocumentRequirementMilestone::Verified,
        'sort_order' => 0,
    ]);

    $this->actingAs($user)->put(route('user.programs.update', [
        'department' => $department->slug,
        'program' => $program->id,
    ]), [
        'name' => 'Livelihood Program',
        'descriptions' => 'Support details',
        'start_at' => '2026-01-01',
        'end_at' => null,
        'fund_ids' => [$fund->id],
        'item_ids' => [$item->id],
        'document_requirements' => [
            [
                'document_type_id' => $indigencyType->id,
                'is_required' => false,
                'required_before' => DocumentRequirementMilestone::Delivered,
                'sort_order' => 0,
            ],
        ],
    ])->assertRedirect();

    $this->assertDatabaseMissing('program_document_requirements', [
        'id' => $existing->id,
    ]);

    $this->assertDatabaseHas('program_document_requirements', [
        'program_id' => $program->id,
        'document_type_id' => $indigencyType->id,
        'is_required' => 0,
        'required_before' => DocumentRequirementMilestone::Delivered,
    ]);
});

test('program document checklist rejects duplicate document types', function () {
    $department = Department::create(['name' => 'Department A']);
    $user = User::factory()->create(['department_id' => $department->id]);
    ['fund' => $fund, 'item' => $item] = createProgramDocumentRequirementFixtures($department);
    $idType = DocumentType::query()->where('slug', DocumentTypeSlug::ValidId)->firstOrFail();

    $this->actingAs($user)->post(route('user.programs.store', [
        'department' => $department->slug,
    ]), [
        'name' => 'Livelihood Program',
        'descriptions' => 'Support details',
        'start_at' => '2026-01-01',
        'end_at' => null,
        'fund_ids' => [$fund->id],
        'item_ids' => [$item->id],
        'document_requirements' => [
            [
                'document_type_id' => $idType->id,
                'is_required' => true,
                'required_before' => DocumentRequirementMilestone::Verified,
                'sort_order' => 0,
            ],
            [
                'document_type_id' => $idType->id,
                'is_required' => true,
                'required_before' => DocumentRequirementMilestone::Delivered,
                'sort_order' => 1,
            ],
        ],
    ])->assertSessionHasErrors('document_requirements.1.document_type_id');
});
