<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Signature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class FeatureParitySmokeTest extends TestCase
{
    use RefreshDatabase;

    private function seedApp(): array
    {
        $settings = AppSetting::create([
            'app_name' => 'DigiSign Pro',
            'maintenance_mode' => false,
            'registration_open' => true,
            'max_upload_size' => 10485760,
            'max_upload_size_bulk' => 52428800,
            'max_prefix_length' => 3,
            'timezone' => 'Asia/Jakarta',
            'storage_mode' => 'local',
            's3_directory' => 'digisign/',
        ]);

        $admin = User::create([
            'name' => 'Administrator',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'position' => 'IT Administrator',
            'signature_prefix' => 'AD',
        ]);

        $user = User::create([
            'name' => 'Demo User',
            'email' => 'user@example.com',
            'password' => Hash::make('password'),
            'role' => 'user',
            'position' => 'Staff',
            'signature_prefix' => 'DS',
        ]);

        return compact('settings', 'admin', 'user');
    }

    public function test_guest_pages_render(): void
    {
        $this->seedApp();

        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
        $this->get('/forgot-password')->assertOk();
        $this->get('/verify')->assertOk();
        $this->get('/verify/UNKNOWN')->assertOk();
    }

    public function test_login_and_admin_pages(): void
    {
        ['admin' => $admin] = $this->seedApp();

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Dashboard')->has('stats'));

        $pages = [
            '/history' => 'History',
            '/profile' => 'Profile/Edit',
            '/sign/single' => 'Sign/Single',
            '/sign/bulk' => 'Sign/Bulk',
            '/sign/qr-manual' => 'Sign/QrList',
            '/sign/qr-manual/create' => 'Sign/QrCreate',
            '/admin/users' => 'Admin/Users',
            '/admin/settings' => 'Admin/Settings',
            '/admin/storage' => 'Admin/Storage',
            '/admin/backup' => 'Admin/Backup',
            '/admin/updater' => 'Admin/Updater',
        ];

        foreach ($pages as $uri => $component) {
            $this->actingAs($admin)
                ->get($uri)
                ->assertOk()
                ->assertInertia(fn ($page) => $page->component($component));
        }
    }

    public function test_user_cannot_access_admin_routes(): void
    {
        ['user' => $user] = $this->seedApp();

        foreach (['/admin/users', '/admin/settings', '/admin/storage', '/admin/backup', '/admin/updater'] as $uri) {
            $this->actingAs($user)->get($uri)->assertForbidden();
        }
    }

    public function test_user_core_pages(): void
    {
        ['user' => $user] = $this->seedApp();

        foreach (['/dashboard', '/history', '/profile', '/sign/single', '/sign/bulk', '/sign/qr-manual'] as $uri) {
            $this->actingAs($user)->get($uri)->assertOk();
        }
    }

    public function test_dashboard_prefix_update(): void
    {
        ['user' => $user] = $this->seedApp();

        $this->actingAs($user)
            ->post('/dashboard/prefix', ['signature_prefix' => 'xyz'])
            ->assertRedirect();

        $this->assertEquals('XYZ', $user->fresh()->signature_prefix);
    }

    public function test_profile_update_position_and_prefix(): void
    {
        ['user' => $user] = $this->seedApp();

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => 'Updated Name',
                'email' => 'user@example.com',
                'position' => 'Manager',
                'signature_prefix' => 'MG',
            ])
            ->assertRedirect(route('profile.edit'));

        $user->refresh();
        $this->assertEquals('Updated Name', $user->name);
        $this->assertEquals('Manager', $user->position);
        $this->assertEquals('MG', $user->signature_prefix);
    }

    public function test_history_and_batch_delete(): void
    {
        ['admin' => $admin, 'user' => $user] = $this->seedApp();

        $batchId = 'BATCHTEST01';
        $sig1 = Signature::create([
            'user_id' => $user->id,
            'batch_id' => $batchId,
            'document_name' => 'a.pdf',
            'document_number' => 'N-1',
            'document_subject' => 'Subjek A',
            'verify_code' => 'DS-20260724-AAAAAA',
            'signature_type' => 'digital',
            'signed_at' => now(),
        ]);
        $sig2 = Signature::create([
            'user_id' => $user->id,
            'batch_id' => $batchId,
            'document_name' => 'b.pdf',
            'document_number' => 'N-2',
            'document_subject' => 'Subjek B',
            'verify_code' => 'DS-20260724-BBBBBB',
            'signature_type' => 'digital',
            'signed_at' => now(),
        ]);
        $single = Signature::create([
            'user_id' => $user->id,
            'document_name' => 'single.pdf',
            'document_subject' => 'Single',
            'verify_code' => 'DS-20260724-CCCCCC',
            'signature_type' => 'digital',
            'signed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get('/history')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('History')
                ->has('signatures', 3)
                ->where('signatures.0.verification_code', fn ($v) => is_string($v) && $v !== '')
            );

        $this->actingAs($admin)
            ->delete('/history/batch/'.$batchId)
            ->assertRedirect();

        $this->assertDatabaseMissing('signatures', ['id' => $sig1->id]);
        $this->assertDatabaseMissing('signatures', ['id' => $sig2->id]);
        $this->assertDatabaseHas('signatures', ['id' => $single->id]);

        $this->actingAs($admin)
            ->delete('/history/'.$single->id)
            ->assertRedirect();

        $this->assertDatabaseMissing('signatures', ['id' => $single->id]);
    }

    public function test_verify_public_page_with_signature(): void
    {
        ['user' => $user] = $this->seedApp();

        Signature::create([
            'user_id' => $user->id,
            'document_name' => 'doc.pdf',
            'document_number' => 'NO-99',
            'document_subject' => 'Perihal Test',
            'verify_code' => 'DS-VERIFY-OK',
            'signature_type' => 'digital',
            'signed_at' => now(),
        ]);

        $this->get('/verify/DS-VERIFY-OK')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Verify')
                ->where('status', 'success')
                ->where('signature.verify_code', 'DS-VERIFY-OK')
                ->where('signature.document_number', 'NO-99')
            );
    }

    public function test_settings_update_timezone_and_prefix_length(): void
    {
        ['admin' => $admin] = $this->seedApp();

        $this->actingAs($admin)
            ->put('/admin/settings', [
                'app_name' => 'DigiSign Pro Updated',
                'maintenance_mode' => 0,
                'registration_open' => 1,
                'max_upload_size_mb' => 15,
                'max_upload_size_bulk_mb' => 60,
                'max_prefix_length' => 5,
                'timezone' => 'Asia/Makassar',
                'storage_mode' => 'local',
                's3_bucket' => '',
                's3_region' => 'us-east-1',
                's3_access_key' => '',
                's3_secret_key' => '',
                's3_endpoint' => '',
                's3_public_url' => '',
                's3_directory' => 'digisign/',
            ])
            ->assertRedirect();

        $settings = AppSetting::first();
        $this->assertEquals('DigiSign Pro Updated', $settings->app_name);
        $this->assertEquals(5, $settings->max_prefix_length);
        $this->assertEquals('Asia/Makassar', $settings->timezone);
        $this->assertEquals(15 * 1024 * 1024, $settings->max_upload_size);
    }

    public function test_admin_user_crud(): void
    {
        ['admin' => $admin] = $this->seedApp();

        $this->actingAs($admin)
            ->post('/admin/users', [
                'name' => 'New Staff',
                'email' => 'staff@example.com',
                'password' => 'password123',
                'role' => 'user',
                'position' => 'Staff',
                'signature_prefix' => 'ST',
            ])
            ->assertRedirect();

        $created = User::where('email', 'staff@example.com')->first();
        $this->assertNotNull($created);

        $this->actingAs($admin)
            ->patch('/admin/users/'.$created->id, [
                'name' => 'Staff Updated',
                'email' => 'staff@example.com',
                'role' => 'user',
                'position' => 'Senior Staff',
                'signature_prefix' => 'SU',
            ])
            ->assertRedirect();

        $this->assertEquals('Staff Updated', $created->fresh()->name);

        $this->actingAs($admin)
            ->delete('/admin/users/'.$created->id)
            ->assertRedirect();

        $this->assertDatabaseMissing('users', ['email' => 'staff@example.com']);
    }

    public function test_registration_open_gate(): void
    {
        $this->seedApp();
        AppSetting::query()->update(['registration_open' => false]);

        $this->get('/register')->assertForbidden();
    }

    public function test_maintenance_blocks_non_admin_login(): void
    {
        $this->seedApp();
        AppSetting::query()->update(['maintenance_mode' => true]);

        $this->post('/login', [
            'email' => 'user@example.com',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();

        $this->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticated();
    }

    public function test_qr_manual_create_flow_session(): void
    {
        ['user' => $user] = $this->seedApp();

        $this->actingAs($user)
            ->post('/sign/qr-manual', [
                'step' => 1,
                'document_number' => 'QR-001',
                'subject' => 'QR Test',
                'attachment' => '',
                'signed_at' => now()->toDateString(),
                'pdf_password' => 'secret123',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('signatures', [
            'document_number' => 'QR-001',
            'document_subject' => 'QR Test',
            'signature_type' => 'qr_manual',
        ]);
    }

    public function test_updater_analyze_invalid_zip(): void
    {
        ['admin' => $admin] = $this->seedApp();

        $tmp = sys_get_temp_dir().'/bad_'.uniqid().'.zip';
        file_put_contents($tmp, 'not-a-zip-content');

        $file = new UploadedFile($tmp, 'bad.zip', 'application/zip', null, true);

        $response = $this->actingAs($admin)
            ->postJson('/admin/updater/analyze', [
                'update_file' => $file,
            ]);

        // Validation (mimes) or open-failure path should not be 200 success
        $this->assertNotEquals(200, $response->status());
        if ($response->status() === 422) {
            $json = $response->json();
            $this->assertTrue(
                isset($json['message']) || isset($json['errors']) || ($json['status'] ?? null) === 'error'
            );
        }

        @unlink($tmp);
    }

    public function test_updater_analyze_valid_package(): void
    {
        ['admin' => $admin] = $this->seedApp();

        $tmpZip = sys_get_temp_dir().'/upd_test_'.uniqid().'.zip';
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($tmpZip, ZipArchive::CREATE) === true);
        $manifest = json_encode([
            'version' => '2.0.1',
            'release_date' => '2026-07-24',
            'description' => 'Test update package',
            'author' => 'QA',
        ]);
        $zip->addFromString('manifest.json', $manifest);
        $zip->addFromString('files/README_UPDATE.md', '# updated');
        $zip->close();

        $file = new UploadedFile($tmpZip, 'update.zip', 'application/zip', null, true);

        $response = $this->actingAs($admin)
            ->post('/admin/updater/analyze', [
                'update_file' => $file,
            ], ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('manifest.version', '2.0.1');

        $tempId = $response->json('temp_id');
        $this->assertNotEmpty($tempId);

        $exec = $this->actingAs($admin)
            ->post('/admin/updater/execute', [
                'temp_id' => $tempId,
            ], ['Accept' => 'application/json']);

        $exec->assertOk()->assertJsonPath('status', 'success');
        $this->assertEquals('2.0.1', trim((string) file_get_contents(base_path('version.lock'))));

        // restore version for other tests / workspace
        file_put_contents(base_path('version.lock'), "2.0.0\n");
        @unlink(base_path('README_UPDATE.md'));
        @unlink($tmpZip);
    }

    public function test_database_migrate_endpoint(): void
    {
        ['admin' => $admin] = $this->seedApp();

        $this->actingAs($admin)
            ->postJson('/admin/database/migrate')
            ->assertOk()
            ->assertJsonPath('status', 'success');
    }

    public function test_settings_clear_temp(): void
    {
        ['admin' => $admin] = $this->seedApp();

        $dir = storage_path('app/temp');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($dir.'/junk.txt', 'temp');

        $this->actingAs($admin)
            ->post('/admin/settings/clear-temp')
            ->assertRedirect();

        $this->assertFileDoesNotExist($dir.'/junk.txt');
    }

    public function test_csrf_meta_present_in_html(): void
    {
        $this->seedApp();
        $html = $this->get('/login')->assertOk()->getContent();
        $this->assertStringContainsString('csrf-token', $html);
    }
}
