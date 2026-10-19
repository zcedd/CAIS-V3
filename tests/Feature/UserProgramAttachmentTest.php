<?php

use App\Enums\RoleName;
use App\Models\Department;
use App\Models\Fund;
use App\Models\Item;
use App\Models\ItemUnitMeasurement;
use App\Models\Program;
use App\Models\ProgramAttachment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array{department: Department, user: User, fund: Fund, item: Item}
 */
function programAttachmentContext(): array
{
    $department = Department::create(['name' => 'Department A']);
    $user = User::factory()->create([
        'department_id' => $department->id,
    ]);
    $fund = Fund::create([
        'name' => 'General Fund',
        'amount' => '10000',
        'year' => '2026',
        'is_active' => true,
        'department_id' => $department->id,
    ]);
    $unit = ItemUnitMeasurement::create(['name' => 'kg']);
    $item = Item::create([
        'name' => 'Rice',
        'department_id' => $department->id,
        'item_unit_measurement_id' => $unit->id,
    ]);

    return compact('department', 'user', 'fund', 'item');
}

/**
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function programAttachmentPayload(Fund $fund, Item $item, array $extra = []): array
{
    return [
        'name' => 'Relief Program',
        'descriptions' => 'Household relief',
        'start_at' => '2026-01-01',
        'fund_ids' => [$fund->id],
        'item_ids' => [$item->id],
        ...$extra,
    ];
}

test('a program can be created with several attachments', function () {
    Storage::fake('local');
    ['department' => $department, 'user' => $user, 'fund' => $fund, 'item' => $item] = programAttachmentContext();

    $this->actingAs($user)
        ->post(route('user.programs.store', ['department' => $department->slug]), programAttachmentPayload($fund, $item, [
            'attachments' => [
                UploadedFile::fake()->create('proposal.pdf', 80, 'application/pdf'),
                UploadedFile::fake()->image('map.png'),
            ],
        ]))
        ->assertRedirect()
        ->assertSessionHas('success');

    $program = Program::query()->where('name', 'Relief Program')->firstOrFail();
    $attachments = $program->attachments()->orderBy('id')->get();

    expect($attachments)->toHaveCount(2)
        ->and($attachments->pluck('original_name')->all())->toBe(['proposal.pdf', 'map.png'])
        ->and($attachments->first()->uploaded_by)->toBe($user->id);

    Storage::disk('local')->assertExists($attachments[0]->path);
    Storage::disk('local')->assertExists($attachments[1]->path);

    $this->actingAs($user)
        ->get(route('user.programs.show', [
            'department' => $department->slug,
            'program' => $program,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('program.attachments.0.original_name', 'proposal.pdf')
            ->where('program.attachments.1.original_name', 'map.png'));
});

test('a program can be created without attachments', function () {
    ['department' => $department, 'user' => $user, 'fund' => $fund, 'item' => $item] = programAttachmentContext();

    $this->actingAs($user)
        ->post(route('user.programs.store', ['department' => $department->slug]), programAttachmentPayload($fund, $item))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(ProgramAttachment::query()->count())->toBe(0);
});

test('program attachments reject a disallowed file type', function () {
    Storage::fake('local');
    ['department' => $department, 'user' => $user, 'fund' => $fund, 'item' => $item] = programAttachmentContext();

    $this->actingAs($user)
        ->from(route('user.programs.index', ['department' => $department->slug]))
        ->post(route('user.programs.store', ['department' => $department->slug]), programAttachmentPayload($fund, $item, [
            'attachments' => [
                UploadedFile::fake()->create('notes.exe', 20, 'application/x-msdownload'),
            ],
        ]))
        ->assertRedirect()
        ->assertSessionHasErrors('attachments.0');

    expect(Program::query()->count())->toBe(0);
});

test('department staff and executives can download a program attachment', function () {
    Storage::fake('local');
    ['department' => $department, 'user' => $user, 'fund' => $fund, 'item' => $item] = programAttachmentContext();

    $this->actingAs($user)
        ->post(route('user.programs.store', ['department' => $department->slug]), programAttachmentPayload($fund, $item, [
            'attachments' => [
                UploadedFile::fake()->create('proposal.pdf', 40, 'application/pdf'),
            ],
        ]));

    $program = Program::query()->where('name', 'Relief Program')->firstOrFail();
    $attachment = $program->attachments()->firstOrFail();

    $this->actingAs($user)
        ->get(route('programs.attachments.show', [
            'program' => $program,
            'attachment' => $attachment,
        ]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $this->seed(RolePermissionSeeder::class);
    $executive = User::factory()->create([
        'department_id' => Department::create(['name' => 'Office Of The Governor'])->id,
    ]);
    $executive->syncRoles([RoleName::Executive->value]);

    $this->actingAs($executive)
        ->get(route('programs.attachments.show', [
            'program' => $program,
            'attachment' => $attachment,
        ]))
        ->assertOk();
});

test('guests and other departments cannot download a program attachment', function () {
    Storage::fake('local');
    ['department' => $department, 'user' => $user, 'fund' => $fund, 'item' => $item] = programAttachmentContext();

    $this->actingAs($user)
        ->post(route('user.programs.store', ['department' => $department->slug]), programAttachmentPayload($fund, $item, [
            'attachments' => [
                UploadedFile::fake()->image('map.png'),
            ],
        ]));

    $program = Program::query()->where('name', 'Relief Program')->firstOrFail();
    $attachment = $program->attachments()->firstOrFail();

    auth()->logout();

    $this->get(route('programs.attachments.show', [
        'program' => $program,
        'attachment' => $attachment,
    ]))->assertRedirect(route('login'));

    $other = User::factory()->create([
        'department_id' => Department::create(['name' => 'Department B'])->id,
    ]);
    $other->syncRoles([RoleName::Encoder->value]);

    $this->actingAs($other)
        ->get(route('programs.attachments.show', [
            'program' => $program,
            'attachment' => $attachment,
        ]))
        ->assertForbidden();
});

test('a program attachment cannot be downloaded through another program', function () {
    Storage::fake('local');
    ['department' => $department, 'user' => $user, 'fund' => $fund, 'item' => $item] = programAttachmentContext();

    $this->actingAs($user)
        ->post(route('user.programs.store', ['department' => $department->slug]), programAttachmentPayload($fund, $item, [
            'name' => 'First Program',
            'attachments' => [
                UploadedFile::fake()->image('map.png'),
            ],
        ]));

    $this->actingAs($user)
        ->post(route('user.programs.store', ['department' => $department->slug]), programAttachmentPayload($fund, $item, [
            'name' => 'Second Program',
        ]));

    $first = Program::query()->where('name', 'First Program')->firstOrFail();
    $second = Program::query()->where('name', 'Second Program')->firstOrFail();
    $attachment = $first->attachments()->firstOrFail();

    $this->actingAs($user)
        ->get(route('programs.attachments.show', [
            'program' => $second,
            'attachment' => $attachment,
        ]))
        ->assertNotFound();
});

test('updating a program can add and remove attachments', function () {
    Storage::fake('local');
    ['department' => $department, 'user' => $user, 'fund' => $fund, 'item' => $item] = programAttachmentContext();

    $this->actingAs($user)
        ->post(route('user.programs.store', ['department' => $department->slug]), programAttachmentPayload($fund, $item, [
            'attachments' => [
                UploadedFile::fake()->create('proposal.pdf', 40, 'application/pdf'),
                UploadedFile::fake()->image('map.png'),
            ],
        ]));

    $program = Program::query()->where('name', 'Relief Program')->firstOrFail();
    $remove = $program->attachments()->where('original_name', 'proposal.pdf')->firstOrFail();
    $kept = $program->attachments()->where('original_name', 'map.png')->firstOrFail();
    $removedPath = $remove->path;

    $this->actingAs($user)
        ->put(route('user.programs.update', [
            'department' => $department->slug,
            'program' => $program,
        ]), programAttachmentPayload($fund, $item, [
            'remove_attachment_ids' => [$remove->id],
            'attachments' => [
                UploadedFile::fake()->image('site.jpg'),
            ],
        ]))
        ->assertRedirect()
        ->assertSessionHas('success');

    $names = $program->attachments()->orderBy('id')->pluck('original_name')->all();

    expect($names)->toBe(['map.png', 'site.jpg'])
        ->and($program->attachments()->whereKey($kept->id)->exists())->toBeTrue();

    Storage::disk('local')->assertMissing($removedPath);
    $this->assertModelMissing($remove);
});

test('a program cannot remove an attachment that belongs to another program', function () {
    Storage::fake('local');
    ['department' => $department, 'user' => $user, 'fund' => $fund, 'item' => $item] = programAttachmentContext();

    $this->actingAs($user)
        ->post(route('user.programs.store', ['department' => $department->slug]), programAttachmentPayload($fund, $item, [
            'name' => 'First Program',
            'attachments' => [
                UploadedFile::fake()->image('map.png'),
            ],
        ]));

    $this->actingAs($user)
        ->post(route('user.programs.store', ['department' => $department->slug]), programAttachmentPayload($fund, $item, [
            'name' => 'Second Program',
        ]));

    $first = Program::query()->where('name', 'First Program')->firstOrFail();
    $second = Program::query()->where('name', 'Second Program')->firstOrFail();
    $foreign = $first->attachments()->firstOrFail();

    $this->actingAs($user)
        ->from(route('user.programs.show', [
            'department' => $department->slug,
            'program' => $second,
        ]))
        ->put(route('user.programs.update', [
            'department' => $department->slug,
            'program' => $second,
        ]), programAttachmentPayload($fund, $item, [
            'name' => 'Second Program',
            'remove_attachment_ids' => [$foreign->id],
        ]))
        ->assertRedirect()
        ->assertSessionHasErrors('remove_attachment_ids.0');

    $this->assertModelExists($foreign);
});
