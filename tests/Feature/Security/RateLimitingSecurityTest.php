<?php

test('api public docs rate limit triggers under heavy traffic', function () {
    // Docs endpoint is throttled to 30 requests per minute
    for ($i = 0; $i < 30; $i++) {
        $this->get('/api/v1/docs/quickapi.md');
    }

    // 31st request should be throttled
    $response = $this->get('/api/v1/docs/quickapi.md');
    $response->assertStatus(429);
});

test('public verify endpoint responds properly under normal traffic', function () {
    $response = $this->getJson('/api/v1/verify/DS-NONEXISTENT-CODE');
    $response->assertStatus(404);
});
