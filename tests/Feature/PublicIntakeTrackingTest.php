<?php

use App\Models\Assistance;
use App\Models\Individual;
use App\Services\Public\PublicIntakeService;
use Inertia\Testing\AssertableInertia as Assert;

test('save for later can be tracked and resumed with cais and last name', function () {
    ['program' => $program, 'item' => $item, 'barangayId' => $barangayId] = createPublicIntakeContext();

    $this->post(route('public.apply.store', $program), [
        ...publicIntakeIdentityPayload($barangayId),
        'intent' => 'save',
        'create_new' => true,
        'item_details' => [
            ['item_id' => $item->id, 'quantity' => 1],
        ],
    ])->assertRedirect(route('public.apply.confirmation', $program));

    $individual = Individual::query()->where('last_name', 'Cruz')->first();
    $assistance = Assistance::query()->with('currentRequestSubStatus')->first();

    expect($individual)->not->toBeNull()
        ->and($assistance->currentRequestSubStatus?->name)->toBe('Saved For Later');

    $tracked = app(PublicIntakeService::class)->track($individual->cais_number, 'Cruz');

    expect($tracked['cais_number'])->toBe($individual->cais_number)
        ->and($tracked['requests'][0]['can_resume'])->toBeTrue();

    $this->get(route('public.track.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('public/track/index'));

    $resume = app(PublicIntakeService::class)->resumePayload(
        $program,
        $individual->cais_number,
        'Cruz',
    );

    expect($resume)->not->toBeNull()
        ->and($resume['confirmed_beneficiary_id'])->toBe($assistance->beneficiary_id);

    $this->get(route('public.apply.show', [
        'program' => $program->id,
        'cais_number' => $individual->cais_number,
        'last_name' => 'Cruz',
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('public/apply/show'));
});

test('tracking with the wrong last name does not reveal a cais number', function () {
    ['program' => $program, 'item' => $item, 'barangayId' => $barangayId] = createPublicIntakeContext();

    $this->post(route('public.apply.store', $program), [
        ...publicIntakeIdentityPayload($barangayId),
        'intent' => 'submit',
        'consent' => true,
        'create_new' => true,
        'item_details' => [
            ['item_id' => $item->id, 'quantity' => 1],
        ],
    ])->assertRedirect();

    $individual = Individual::query()->first();

    $this->post(route('public.track.lookup'), [
        'cais_number' => $individual->cais_number,
        'last_name' => 'SomeoneElse',
    ])->assertSessionHasErrors('cais_number');
});
