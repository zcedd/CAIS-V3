<?php

use App\Enums\AssistanceItemOrigin;
use App\Enums\DocumentRequirementMilestone;
use App\Enums\DocumentTypeSlug;
use App\Enums\ItemKind;
use App\Enums\ProgramFieldType;
use App\Enums\ProgramKind;
use App\Enums\RoleName;
use App\Enums\SlaState;
use App\Enums\StockMovementType;
use App\Enums\UnspscCodeLevel;
use App\Support\EmptyCell;

test('program kinds expose creatable and encodable cases', function () {
    expect(ProgramKind::creatable())->toBe([ProgramKind::Standalone, ProgramKind::Scheme])
        ->and(ProgramKind::encodable())->toBe([ProgramKind::Standalone, ProgramKind::Batch])
        ->and(ProgramKind::Standalone->isEncodable())->toBeTrue()
        ->and(ProgramKind::Batch->isEncodable())->toBeTrue()
        ->and(ProgramKind::Scheme->isEncodable())->toBeFalse()
        ->and(ProgramKind::Scheme->label())->toBe('Program with batches');
});

test('item kinds describe inventory and cash formatting', function () {
    expect(ItemKind::Goods->tracksInventory())->toBeTrue()
        ->and(ItemKind::Cash->tracksInventory())->toBeFalse()
        ->and(ItemKind::Service->isCash())->toBeFalse()
        ->and(ItemKind::Cash->isCash())->toBeTrue()
        ->and(ItemKind::formatQuantity(null, null, ItemKind::Goods))->toBe(EmptyCell::VALUE)
        ->and(ItemKind::formatQuantity(1500, 'php', ItemKind::Cash))->toBe('₱1,500.00');
});

test('stock movement types identify receipts and on-hand increases', function () {
    expect(StockMovementType::receipts())->toBe([
        StockMovementType::OpeningBalance,
        StockMovementType::Receipt,
    ])
        ->and(StockMovementType::Receipt->increasesOnHand())->toBeTrue()
        ->and(StockMovementType::Issue->increasesOnHand())->toBeFalse();
});

test('sla states and assistance origins keep closed catalogs', function () {
    expect(SlaState::DueSoon->label())->toBe('Due soon')
        ->and(SlaState::None->label())->toBe('No SLA')
        ->and(AssistanceItemOrigin::unrequested())->toBe([
            AssistanceItemOrigin::Additional,
            AssistanceItemOrigin::Substitute,
        ])
        ->and(DocumentRequirementMilestone::values())->toBe(['verified', 'delivered'])
        ->and(DocumentTypeSlug::ValidId->value)->toBe('valid_id')
        ->and(ProgramFieldType::Select->value)->toBe('select')
        ->and(UnspscCodeLevel::ItemClass->value)->toBe('class');
});

test('role names expose labels for administration', function () {
    expect(RoleName::SuperAdmin->label())->toBe('Super admin')
        ->and(RoleName::Workflow->label())->toBe('Workflow')
        ->and(RoleName::values())->toContain(RoleName::Head->value);
});
