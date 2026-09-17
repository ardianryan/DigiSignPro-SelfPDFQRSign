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

test('public verify web endpoint is throttled after 30 requests', function () {
    for ($i = 0; $i < 30; $i++) {
        $this->get('/verify/DS-RATE-LIMIT-CHECK');
    }

    $response = $this->get('/verify/DS-RATE-LIMIT-CHECK');
    $response->assertStatus(429);
});

test('public verify api endpoint is throttled after 30 requests', function () {
    for ($i = 0; $i < 30; $i++) {
        $this->getJson('/api/v1/verify/DS-RATE-LIMIT-CHECK');
    }

    $response = $this->getJson('/api/v1/verify/DS-RATE-LIMIT-CHECK');
    $response->assertStatus(429);
});
