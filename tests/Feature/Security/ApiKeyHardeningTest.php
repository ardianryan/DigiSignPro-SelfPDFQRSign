<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->plainKey = 'digi_test_plain_secret_1234567890123456789012';
    $this->user = User::create([
        'name' => 'API Hardened User',
        'email' => 'api.user@example.com',
        'password' => Hash::make('password123'),
        'role' => 'user',
        'signature_prefix' => 'AH',
        'api_key' => hash('sha256', $this->plainKey),
        'api_key_created_at' => now(),
    ]);
});

test('api rejects credentials passed via url query parameter', function () {
    // Attempting authentication via ?api_key= should fail with 401 missing_api_key
    $response = $this->getJson('/api/v1/me?api_key='.$this->plainKey);

    $response->assertStatus(401)
        ->assertJsonPath('error', 'missing_api_key');
});

test('api authenticates successfully via Authorization Bearer header using sha256 hash', function () {
    $response = $this->withHeader('Authorization', 'Bearer '.$this->plainKey)
        ->getJson('/api/v1/me');

    $response->assertOk()
        ->assertJsonPath('data.email', 'api.user@example.com');
});

test('api authenticates successfully via X-API-Key header using sha256 hash', function () {
    $response = $this->withHeader('X-API-Key', $this->plainKey)
        ->getJson('/api/v1/me');

    $response->assertOk()
        ->assertJsonPath('data.email', 'api.user@example.com');
});

test('legacy plaintext api keys are automatically migrated to sha256 on first request', function () {
    $legacyPlain = 'digi_legacy_plain_key_09876543210987654321';
    $legacyUser = User::create([
        'name' => 'Legacy Key User',
        'email' => 'legacy.user@example.com',
        'password' => Hash::make('password123'),
        'role' => 'user',
        'signature_prefix' => 'LG',
        'api_key' => $legacyPlain,
        'api_key_created_at' => now(),
    ]);

    // First request with plaintext key
    $response = $this->withHeader('X-API-Key', $legacyPlain)
        ->getJson('/api/v1/me');

    $response->assertOk();

    // Verify the database row is upgraded to SHA-256 hash
    $legacyUser->refresh();
    expect($legacyUser->api_key)->toBe(hash('sha256', $legacyPlain));
});
