<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->admin = User::create([
        'name' => 'Admin S3',
        'email' => 'admin.s3@example.com',
        'password' => Hash::make('password123'),
        'role' => 'admin',
        'signature_prefix' => 'S3',
    ]);
});

test('testS3 rejects loopback addresses as ssrf mitigation', function () {
    $response = $this->actingAs($this->admin)
        ->postJson(route('admin.settings.test_s3'), [
            's3_bucket' => 'test-bucket',
            's3_region' => 'us-east-1',
            's3_access_key' => 'AKIATEST',
            's3_secret_key' => 'secret123',
            's3_endpoint' => 'http://127.0.0.1:9000',
        ]);

    $response->assertStatus(422)
        ->assertJson([
            'status' => 'error',
            'message' => 'Endpoint S3 tidak diizinkan merujuk ke alamat lokal atau metadata cloud.',
        ]);
});

test('testS3 rejects cloud metadata service address', function () {
    $response = $this->actingAs($this->admin)
        ->postJson(route('admin.settings.test_s3'), [
            's3_bucket' => 'test-bucket',
            's3_region' => 'us-east-1',
            's3_access_key' => 'AKIATEST',
            's3_secret_key' => 'secret123',
            's3_endpoint' => 'http://169.254.169.254/latest/meta-data',
        ]);

    $response->assertStatus(422)
        ->assertJson([
            'status' => 'error',
            'message' => 'Endpoint S3 tidak diizinkan merujuk ke alamat lokal atau metadata cloud.',
        ]);
});

test('testS3 rejects invalid url formats', function () {
    $response = $this->actingAs($this->admin)
        ->postJson(route('admin.settings.test_s3'), [
            's3_bucket' => 'test-bucket',
            's3_region' => 'us-east-1',
            's3_access_key' => 'AKIATEST',
            's3_secret_key' => 'secret123',
            's3_endpoint' => 'invalid-not-a-url',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['s3_endpoint']);
});
