<?php

use App\Models\Assistance;
use App\Models\AssistanceItem;
use App\Models\Beneficiary;
use App\Models\Department;
use App\Models\Item;
use App\Models\ItemUnitMeasurement;
use App\Models\Program;
use App\Models\User;
use App\Support\AssistanceItemOrigin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('authenticated users can view a printable acknowledgment receipt', function () {
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
    $beneficiary = Beneficiary::create([
        'cais_number' => 'CAIS-100',
        'name' => 'Juan Dela Cruz',
        'beneficiable_type' => 'App\\Models\\Individual',
        'beneficiable_id' => 1,
    ]);
    $assistance = Assistance::create([
        'program_id' => $program->id,
        'beneficiary_id' => $beneficiary->id,
        'date_requested' => '2026-03-01',
        'date_delivered' => '2026-03-10',
        'user_id' => $user->id,
    ]);
    $unit = ItemUnitMeasurement::create(['name' => 'kg']);
    $item = Item::create([
        'name' => 'Rice',
        'department_id' => $department->id,
        'item_unit_measurement_id' => $unit->id,
    ]);
    AssistanceItem::create([
        'assistance_id' => $assistance->id,
        'item_id' => $item->id,
        'origin' => AssistanceItemOrigin::Requested,
        'quantity' => 2,
        'requested_quantity' => 2,
        'specification' => '25 kg',
        'is_received' => true,
    ]);

    $oilItem = Item::create([
        'name' => 'Cooking oil',
        'department_id' => $department->id,
        'item_unit_measurement_id' => $unit->id,
    ]);

    AssistanceItem::create([
        'assistance_id' => $assistance->id,
        'item_id' => $oilItem->id,
        'origin' => AssistanceItemOrigin::Additional,
        'quantity' => 1,
        'requested_quantity' => 0,
        'fulfillment_reason' => 'leftover pack',
        'is_received' => true,
    ]);

    $this->actingAs($user)
        ->get(route('user.assistances.receipt', [
            'department' => $department->slug,
            'program' => $program->id,
            'assistance' => $assistance->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('user/assistances/receipt')
            ->where('receipt.cais_number', 'CAIS-100')
            ->where('receipt.beneficiary_name', 'Juan Dela Cruz')
            ->where('receipt.released_items.0.name', 'Rice')
            ->where('receipt.released_items.0.kind', 'goods')
            ->where('receipt.released_items.0.quantity', 2)
            ->where('receipt.released_items.0.origin', AssistanceItemOrigin::Requested)
            ->where('receipt.released_items.1.name', 'Cooking oil')
            ->where('receipt.released_items.1.origin', AssistanceItemOrigin::Additional)
            ->where('receipt.released_items.1.fulfillment_reason', 'leftover pack')
            ->where('receipt.requested_items', fn (Collection $items): bool => $items->count() === 1)
            ->where('receipt.requested_items.0.requested_quantity', 2)
            ->where('receipt.requested_items.0.released_quantity', 2)
            ->where('receipt.item_variance.additional_quantity', 1)
            ->where('receipt.item_variance.has_variance', true)
            ->where('receipt.qr_svg', fn (string $svg): bool => str_contains($svg, '<svg') && str_contains($svg, '</svg>'))
            ->has('receipt.profile_url'));
});

test('guests cannot view an acknowledgment receipt', function () {
    $department = Department::create(['name' => 'Department A']);
    $program = Program::create([
        'name' => 'Relief Program',
        'descriptions' => 'Details',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);
    $user = User::factory()->create(['department_id' => $department->id]);
    $assistance = Assistance::create([
        'program_id' => $program->id,
        'date_requested' => now()->toDateString(),
        'user_id' => $user->id,
    ]);

    $this->get(route('user.assistances.receipt', [
        'department' => $department->slug,
        'program' => $program->id,
        'assistance' => $assistance->id,
    ]))->assertRedirect(route('login'));
});
