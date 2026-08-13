<?php

test('program assistance toolbar tour target exists in the toolbar source', function () {
    $source = file_get_contents(resource_path('js/pages/user/programs/assistance-toolbar.tsx'));

    expect($source)->toContain('data-tour="program-assistance-toolbar"');
});

test('program assistance table tour target exists in the table source', function () {
    $source = file_get_contents(resource_path('js/pages/user/programs/program-assistance-table.tsx'));

    expect($source)->toContain('data-tour="program-assistance-table"');
});

test('app tour retains deferred assistance targets until WhenVisible content mounts', function () {
    $source = file_get_contents(resource_path('js/components/app-tour.tsx'));

    expect($source)
        ->toContain('DEFERRED_ASSISTANCE_TOUR_TARGETS')
        ->toContain('waitForDeferredAssistanceTourTarget')
        ->toContain('[data-tour="program-assistance-toolbar"]')
        ->toContain('Keep WhenVisible-deferred targets while their parent section exists');
});

test('program create form tour target exists on the programs index drawer', function () {
    $source = file_get_contents(resource_path('js/pages/user/programs/index.tsx'));

    expect($source)
        ->toContain('data-tour="programs-create-form"')
        ->toContain('data-tour="programs-create-name"')
        ->toContain('data-tour="programs-create-submit"');
});

test('item create form tour target exists on the items toolbar drawer', function () {
    $source = file_get_contents(resource_path('js/pages/user/items/item-toolbar.tsx'));

    expect($source)
        ->toContain('data-tour="items-create-form"')
        ->toContain('data-tour="items-create-name"')
        ->toContain('data-tour="items-create-submit"');
});

test('fund create form tour target exists on the funds toolbar drawer', function () {
    $source = file_get_contents(resource_path('js/pages/user/funds/fund-toolbar.tsx'));

    expect($source)
        ->toContain('data-tour="funds-create-form"')
        ->toContain('data-tour="funds-create-name"')
        ->toContain('data-tour="funds-create-submit"');
});

test('app tour opens create drawers before highlighting their forms', function () {
    $source = file_get_contents(resource_path('js/components/app-tour.tsx'));

    expect($source)
        ->toContain('CREATE_DRAWER_TOURS')
        ->toContain('createDrawerTourSteps')
        ->toContain('createDrawerFieldStep')
        ->toContain('disableFocusTrap: true')
        ->toContain('lockCreateDrawerTour')
        ->toContain('unlockCreateDrawerTour')
        ->toContain('[data-tour="programs-create-form"]')
        ->toContain('[data-tour="programs-create-name"]')
        ->toContain('[data-tour="items-create-form"]')
        ->toContain('[data-tour="items-create-name"]')
        ->toContain('[data-tour="funds-create-form"]')
        ->toContain('[data-tour="funds-create-name"]');
});

test('create drawers ignore dismiss while the tour lock is active', function () {
    $lockSource = file_get_contents(resource_path('js/lib/tour-create-drawer.ts'));
    $programSource = file_get_contents(resource_path('js/pages/user/programs/index.tsx'));
    $itemSource = file_get_contents(resource_path('js/pages/user/items/item-toolbar.tsx'));
    $fundSource = file_get_contents(resource_path('js/pages/user/funds/fund-toolbar.tsx'));

    expect($lockSource)
        ->toContain('lockCreateDrawerTour')
        ->toContain('applyCreateDrawerOpenChange')
        ->toContain('useCreateDrawerTourLock')
        ->toContain('react-joyride-portal')
        ->toContain('restoreTourLayerInteractivity');

    expect($programSource)
        ->toContain('dismissible={!createDrawerTourLocked}')
        ->not->toContain('modal={!createDrawerTourLocked}');

    expect($itemSource)
        ->toContain('dismissible={!createDrawerTourLocked}')
        ->not->toContain('modal={!createDrawerTourLocked}');

    expect($fundSource)
        ->toContain('dismissible={!createDrawerTourLocked}')
        ->not->toContain('modal={!createDrawerTourLocked}');
});
