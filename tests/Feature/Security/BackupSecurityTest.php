<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->admin = User::create([
        'name' => 'Admin Backup',
        'email' => 'admin.backup@example.com',
        'password' => Hash::make('password123'),
        'role' => 'admin',
        'signature_prefix' => 'BK',
        'api_key' => 'digi_secret_key_1234567890',
        'api_key_created_at' => now(),
    ]);
});

test('backup restore rejects zip archives containing zip slip traversal entries', function () {
    $zipPath = sys_get_temp_dir().'/zip_slip_'.uniqid().'.zip';
    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::CREATE);
    $zip->addFromString('../../public/evil.txt', 'malicious content');
    $zip->close();

    $file = new UploadedFile($zipPath, 'backup.zip', 'application/zip', null, true);

    $response = $this->actingAs($this->admin)
        ->postJson(route('admin.backup.restore'), [
            'backup_file' => $file,
            'restore_db' => true,
        ]);

    $response->assertStatus(422)
        ->assertJson([
            'status' => 'error',
            'message' => 'Berkas cadangan mengandung jalur tidak aman (Zip Slip terdeteksi).',
        ]);

    if (file_exists($zipPath)) {
        unlink($zipPath);
    }
});

test('backup run masks user api key in exported users.json', function () {
    $response = $this->actingAs($this->admin)
        ->post(route('admin.backup.run'), [
            'backup_db' => true,
            'backup_media' => false,
        ]);

    $response->assertOk();

    // Verify downloaded file is a zip containing masked api_key
    $tempZip = sys_get_temp_dir().'/test_export_'.uniqid().'.zip';
    file_put_contents($tempZip, $response->streamedContent());

    $zip = new ZipArchive;
    expect($zip->open($tempZip))->toBeTrue();

    $jsonContent = $zip->getFromName('database/users.json');
    expect($jsonContent)->not->toBeFalse();

    $users = json_decode($jsonContent, true);
    expect($users)->toBeArray();

    foreach ($users as $user) {
        expect($user['api_key'])->toBe('[PROTECTED_IN_BACKUP]');
    }

    $zip->close();
    unlink($tempZip);
});
