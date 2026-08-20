<?php

use App\Models\User;

test('authenticated user can view pdf tools hub', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/tools');

    $response->assertStatus(200);
});

test('authenticated user can view individual tool pages', function () {
    $user = User::factory()->create();

    $routes = [
        '/tools/merge',
        '/tools/split',
        '/tools/organize',
        '/tools/image-to-pdf',
        '/tools/watermark',
        '/tools/page-number',
        '/tools/protect',
        '/tools/editor',
    ];

    foreach ($routes as $route) {
        $response = $this->actingAs($user)->get($route);
        $response->assertStatus(200);
    }
});

test('unauthenticated guest is redirected to login from tools', function () {
    $response = $this->get('/tools');
    $response->assertRedirect('/login');

    $mergeResponse = $this->get('/tools/merge');
    $mergeResponse->assertRedirect('/login');
});
