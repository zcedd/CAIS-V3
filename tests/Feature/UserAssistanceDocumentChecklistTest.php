<?php

use App\Enums\DocumentRequirementMilestone;
use App\Enums\DocumentTypeSlug;
use App\Enums\RequestSubStatusCode;
use App\Models\Assistance;
use App\Models\AssistanceItem;
use App\Models\AssistanceRequestSubStatus;
use App\Models\Department;
use App\Models\DocumentType;
use App\Models\Item;
use App\Models\ItemUnitMeasurement;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * @return array{in_progress: int, verified: int, delivered: int}
 */
function seedDocumentChecklistStatusCatalog(): array
{
    return [
        'in_progress' => catalogReasonId(RequestSubStatusCode::AwaitingReview),
        'verified' => catalogReasonId(RequestSubStatusCode::Verified),
        'delivered' => catalogReasonId(RequestSubStatusCode::Delivered),
    ];
}

/**
 * @return array{department: Department, user: User, program: Program, assistance: Assistance, documentType: DocumentType}
 */
function createDocumentChecklistContext(): array
{
    $department = Department::create(['name' => 'Department A']);
    $user = User::factory()->create(['department_id' => $department->id]);
    $program = Program::create([
        'name' => 'Relief Program',
        'descriptions' => 'Details',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);
    $assistance = Assistance::create([
        'program_id' => $program->id,
        'date_requested' => now()->toDateString(),
        'user_id' => $user->id,
        'assigned_to_id' => $user->id,
        'assigned_at' => now(),
    ]);
    $documentType = DocumentType::query()->where('slug', DocumentTypeSlug::ValidId)->firstOrFail();

    $program->documentRequirements()->create([
        'document_type_id' => $documentType->id,
        'is_required' => true,
        'required_before' => DocumentRequirementMilestone::Verified->value,
        'sort_order' => 0,
    ]);

    return compact('department', 'user', 'program', 'assistance', 'documentType');
}

test('verified status is blocked until required documents are uploaded', function () {
    $statuses = seedDocumentChecklistStatusCatalog();
    [
        'department' => $department,
        'user' => $user,
        'program' => $program,
        'assistance' => $assistance,
    ] = createDocumentChecklistContext();

    AssistanceRequestSubStatus::query()->create([
        'assistance_id' => $assistance->id,
        'request_sub_status_id' => $statuses['in_progress'],
        'recorded_at' => now(),
    ]);

    $this->actingAs($user)
        ->patch(route('user.programs.assistances.status.update', [
            'department' => $department->slug,
            'program' => $program->id,
            'assistance' => $assistance->id,
        ]), [
            'request_sub_status_id' => $statuses['verified'],
            'recorded_at' => now()->toDateTimeString(),
        ])
        ->assertSessionHasErrors('request_sub_status_id');

    expect(
        AssistanceRequestSubStatus::query()
            ->where('assistance_id', $assistance->id)
            ->where('request_sub_status_id', $statuses['verified'])
            ->exists(),
    )->toBeFalse();
});

test('verified status succeeds after required documents are uploaded', function () {
    Storage::fake('local');
    $statuses = seedDocumentChecklistStatusCatalog();
    [
        'department' => $department,
        'user' => $user,
        'program' => $program,
        'assistance' => $assistance,
        'documentType' => $documentType,
    ] = createDocumentChecklistContext();

    $this->actingAs($user)
        ->post(route('user.assistances.documents.store', [
            'department' => $department->slug,
            'program' => $program->id,
            'assistance' => $assistance->id,
        ]), [
            'document_type_id' => $documentType->id,
            'file' => UploadedFile::fake()->image('id.jpg'),
        ])
        ->assertRedirect();

    $this->actingAs($user)
        ->patch(route('user.programs.assistances.status.update', [
            'department' => $department->slug,
            'program' => $program->id,
            'assistance' => $assistance->id,
        ]), [
            'request_sub_status_id' => $statuses['verified'],
            'recorded_at' => now()->toDateTimeString(),
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(
        AssistanceRequestSubStatus::query()
            ->where('assistance_id', $assistance->id)
            ->where('request_sub_status_id', $statuses['verified'])
            ->exists(),
    )->toBeTrue();
});

test('delivered status requires verified documents as well as delivery documents', function () {
    $statuses = seedDocumentChecklistStatusCatalog();
    [
        'department' => $department,
        'user' => $user,
        'program' => $program,
        'assistance' => $assistance,
    ] = createDocumentChecklistContext();

    $deliveryType = DocumentType::query()
        ->where('slug', DocumentTypeSlug::DeliveryPhoto)
        ->firstOrFail();

    $program->documentRequirements()->create([
        'document_type_id' => $deliveryType->id,
        'is_required' => true,
        'required_before' => DocumentRequirementMilestone::Delivered->value,
        'sort_order' => 1,
    ]);

    $unit = ItemUnitMeasurement::create(['name' => 'kg']);
    $item = Item::create([
        'name' => 'Rice',
        'department_id' => $department->id,
        'item_unit_measurement_id' => $unit->id,
    ]);
    $program->item()->attach($item->id);
    seedProgramStock($program, $item, 10, $user);
    $assistanceItem = AssistanceItem::create([
        'assistance_id' => $assistance->id,
        'item_id' => $item->id,
        'quantity' => 2,
        'is_received' => false,
    ]);

    $this->actingAs($user)
        ->patch(route('user.programs.assistances.status.update', [
            'department' => $department->slug,
            'program' => $program->id,
            'assistance' => $assistance->id,
        ]), [
            'request_sub_status_id' => $statuses['delivered'],
            'recorded_at' => now()->toDateTimeString(),
            'delivered_items' => [
                [
                    'assistance_item_id' => $assistanceItem->id,
                    'quantity' => 2,
                ],
            ],
        ])
        ->assertSessionHasErrors('request_sub_status_id');
});

test('bulk verified is blocked when a selected assistance is missing required documents', function () {
    $statuses = seedDocumentChecklistStatusCatalog();
    [
        'department' => $department,
        'user' => $user,
        'program' => $program,
        'assistance' => $assistance,
    ] = createDocumentChecklistContext();

    $this->actingAs($user)
        ->patch(route('user.programs.assistances.status.bulk-update', [
            'department' => $department->slug,
            'program' => $program->id,
        ]), [
            'assistance_ids' => [$assistance->id],
            'request_sub_status_id' => $statuses['verified'],
            'recorded_at' => now()->toDateTimeString(),
        ])
        ->assertSessionHasErrors('request_sub_status_id');
});
