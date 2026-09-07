<?php

test('guests are redirected to the login page', function () {
    $this->get(route('home'))->assertRedirect(route('login'));
});
