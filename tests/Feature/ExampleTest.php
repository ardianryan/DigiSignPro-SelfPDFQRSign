<?php

it('returns a successful response', function () {
    $response = $this->get('/');

    // Root redirects guests to the login page
    $response->assertRedirect(route('login'));
});
