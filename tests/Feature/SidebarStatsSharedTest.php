<?php

test('the root path redirects guests to login', function () {
    $this->get('/')->assertRedirect(route('login'));
});
