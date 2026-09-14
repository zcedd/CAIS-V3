<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('appearance cookie values are whitelisted before they reach javascript', function () {
    $this->withUnencryptedCookie('appearance', "';alert(document.domain)//")
        ->get(route('login'))
        ->assertOk()
        ->assertSee('const appearance = "system"', false)
        ->assertDontSee('alert(document.domain)', false)
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

test('valid appearance cookie is reflected as json', function () {
    $this->withUnencryptedCookie('appearance', 'dark')
        ->get(route('login'))
        ->assertOk()
        ->assertSee('const appearance = "dark"', false);
});
