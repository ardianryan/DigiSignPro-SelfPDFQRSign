<?php

use App\Models\Signature;
use App\Models\User;

test('regular user cannot access admin users list', function () {
    $regularUser = User::factory()->create(['role' => 'user']);

    $response = $this->actingAs($regularUser)->get('/admin/users');

    $response->assertStatus(403);
});

test('regular user cannot access admin settings', function () {
    $regularUser = User::factory()->create(['role' => 'user']);

    $response = $this->actingAs($regularUser)->get('/admin/settings');

    $response->assertStatus(403);
});

test('admin can access admin management routes', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->get('/admin/users');
    $response->assertStatus(200);

    $settingsResponse = $this->actingAs($admin)->get('/admin/settings');
    $settingsResponse->assertStatus(200);
});

test('admin cannot delete their own active account', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->delete("/admin/users/{$admin->id}");

    $response->assertSessionHasErrors(['error']);
    $this->assertDatabaseHas('users', ['id' => $admin->id]);
});

test('user cannot delete other users signatures in history', function () {
    $userA = User::factory()->create(['role' => 'user']);
    $userB = User::factory()->create(['role' => 'user']);

    $sigB = Signature::create([
        'user_id' => $userB->id,
        'document_number' => 'DOC-B-001',
        'document_name' => 'doc_b.pdf',
        'document_subject' => 'Secret Document B',
        'verify_code' => 'DS-TEST-B001',
        'file_path' => 'uploads/signatures/test_b.pdf',
        'signed_at' => now(),
    ]);

    $response = $this->actingAs($userA)->delete("/history/{$sigB->id}");

    $response->assertStatus(403);
    $this->assertDatabaseHas('signatures', ['id' => $sigB->id]);
});
