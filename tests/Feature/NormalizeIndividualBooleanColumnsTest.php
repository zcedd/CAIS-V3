<?php

use App\Models\Assistance;
use App\Models\AssistanceItem;
use App\Models\Department;
use App\Models\Individual;
use App\Models\Item;
use App\Models\ItemUnitMeasurement;
use App\Models\ModeOfRequest;
use App\Models\Program;
use App\Models\User;
use App\Services\User\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * @return array<string, mixed>|null
 */
function individualColumn(string $name): ?array
{
    return collect(Schema::getColumns('individuals'))
        ->first(static fn (array $column): bool => ($column['name'] ?? null) === $name);
}

test('normalize migration backfills null demographic booleans and makes them required', function () {
    Artisan::call('migrate:rollback', [
        '--path' => 'database/migrations/2026_08_04_034258_normalize_individual_boolean_columns.php',
    ]);

    $barangayId = createAddressBarangay();

    $individualId = DB::table('individuals')->insertGetId([
        'cais_number' => 'IND-NULL-BOOL-001',
        'first_name' => 'Null',
        'middle_name' => null,
        'last_name' => 'Flags',
        'suffix' => null,
        'birthday' => null,
        'sex' => 'Male',
        'other_address' => null,
        'mobile_number' => null,
        'indigenous' => null,
        'pwd' => null,
        'is_4ps_beneficiary' => null,
        'is_solo_parent' => null,
        'address_barangay_id' => $barangayId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Artisan::call('migrate', [
        '--path' => 'database/migrations/2026_08_04_034258_normalize_individual_boolean_columns.php',
    ]);

    $row = DB::table('individuals')->where('id', $individualId)->first();

    expect((int) $row->pwd)->toBe(0)
        ->and((int) $row->indigenous)->toBe(0)
        ->and((int) $row->is_4ps_beneficiary)->toBe(0)
        ->and((int) $row->is_solo_parent)->toBe(0)
        ->and(individualColumn('pwd')['nullable'] ?? true)->toBeFalse()
        ->and(individualColumn('indigenous')['nullable'] ?? true)->toBeFalse()
        ->and(individualColumn('is_4ps_beneficiary')['nullable'] ?? true)->toBeFalse()
        ->and(individualColumn('is_solo_parent')['nullable'] ?? true)->toBeFalse();
});

test('dashboard demographics omit unspecified buckets for demographic booleans', function () {
    $department = Department::create(['name' => 'Welfare']);
    $program = Program::create([
        'name' => 'Demo Program',
        'descriptions' => 'Details',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);
    $unit = ItemUnitMeasurement::create(['name' => 'pc']);
    $item = Item::create([
        'name' => 'Goods',
        'department_id' => $department->id,
        'item_unit_measurement_id' => $unit->id,
    ]);
    $individual = Individual::factory()->create([
        'pwd' => false,
        'indigenous' => true,
        'is_4ps_beneficiary' => false,
        'is_solo_parent' => false,
    ]);
    $mode = ModeOfRequest::query()->firstOrCreate(['name' => 'Walk In']);

    $assistance = Assistance::create([
        'program_id' => $program->id,
        'beneficiary_id' => $individual->beneficiaryRecord->id,
        'mode_of_request_id' => $mode->id,
        'date_requested' => now()->toDateString(),
        'user_id' => User::factory()->create(['department_id' => $department->id])->id,
    ]);

    AssistanceItem::create([
        'assistance_id' => $assistance->id,
        'item_id' => $item->id,
        'quantity' => 1,
        'is_received' => false,
    ]);

    $demographics = app(DashboardService::class)->demographics($department, []);

    expect(collect($demographics['pwd'])->pluck('label')->all())->not->toContain('Unspecified')
        ->and(collect($demographics['indigenous'])->pluck('label')->all())->not->toContain('Unspecified')
        ->and(collect($demographics['four_ps'])->pluck('label')->all())->not->toContain('Unspecified')
        ->and(collect($demographics['solo_parent'])->pluck('label')->all())->not->toContain('Unspecified');
});
