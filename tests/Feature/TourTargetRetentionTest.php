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
