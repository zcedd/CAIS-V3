<?php

use App\Support\QrCodeSvg;

test('it generates an svg qr code for a url', function () {
    $svg = QrCodeSvg::fromString('https://cais.example/dept/programs/1/assistances/2');

    expect($svg)
        ->toStartWith('<svg')
        ->not->toContain('<?xml')
        ->toContain('viewBox="')
        ->toContain('</svg>');
});

test('it generates an svg qr code for a utf-8 payload', function () {
    $svg = QrCodeSvg::fromString('https://cais.example/assistances/ñaño');

    expect($svg)
        ->toBeString()
        ->toContain('<svg')
        ->toContain('</svg>');
});
