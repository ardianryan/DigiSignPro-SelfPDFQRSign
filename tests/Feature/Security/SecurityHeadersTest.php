<?php

test('web responses include OWASP security headers', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-XSS-Protection', '1; mode=block');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    $response->assertHeader('Permissions-Policy');
});

test('api responses include security headers', function () {
    $response = $this->getJson('/api/v1/health');

    $response->assertStatus(200);
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
});

test('hsts header is attached on secure or forwarded https requests', function () {
    $response = $this->withHeaders([
        'X-Forwarded-Proto' => 'https',
    ])->get('/login');

    $response->assertStatus(200);
    $response->assertHeader('Strict-Transport-Security');
});
