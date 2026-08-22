<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Signature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use setasign\FpdiProtection\FpdiProtection;
use Tests\TestCase;

class ApiV1Test extends TestCase
{
    use RefreshDatabase;

    private function seedUsers(): array
    {
        AppSetting::create([
            'app_name' => 'DigiSign Pro',
            'maintenance_mode' => false,
            'registration_open' => true,
            'max_upload_size' => 20 * 1024 * 1024,
            'storage_mode' => 'local',
        ]);

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'position' => 'IT',
            'signature_prefix' => 'AD',
            'api_key' => 'digi_admin_test_key_'.str_repeat('a', 20),
            'api_key_created_at' => now(),
        ]);

        $user = User::create([
            'name' => 'User',
            'email' => 'user@example.com',
            'password' => Hash::make('password'),
            'role' => 'user',
            'position' => 'Staff',
            'signature_prefix' => 'US',
            'api_key' => 'digi_user_test_key_'.str_repeat('b', 20),
            'api_key_created_at' => now(),
        ]);

        return compact('admin', 'user');
    }

    private function makePdf(): UploadedFile
    {
        $path = sys_get_temp_dir().'/api_'.uniqid().'.pdf';
        $pdf = new FpdiProtection('P', 'mm', 'A4', true);
        $pdf->AddPage();
        $pdf->SetFont('Arial', '', 12);
        $pdf->Text(20, 20, 'API Sign Test');
        $pdf->Output('F', $path);

        return new UploadedFile($path, 'api.pdf', 'application/pdf', null, true);
    }

    public function test_health_and_quickapi_are_public(): void
    {
        $this->seedUsers();

        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->get('/api/v1/docs/quickapi.md')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')
            ->assertSee('DigiSign Pro — Quick API Guide', false);
    }

    public function test_api_requires_key(): void
    {
        $this->seedUsers();

        $this->getJson('/api/v1/me')
            ->assertStatus(401)
            ->assertJsonPath('error', 'missing_api_key');
    }

    public function test_me_and_signatures_with_bearer(): void
    {
        ['user' => $user] = $this->seedUsers();

        Signature::create([
            'user_id' => $user->id,
            'document_name' => 'a.pdf',
            'document_number' => 'N-1',
            'document_subject' => 'Subjek',
            'verify_code' => 'US-TEST-001',
            'signature_type' => 'digital',
            'signed_at' => now(),
        ]);

        $this->withHeader('Authorization', 'Bearer '.$user->api_key)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'user@example.com');

        $this->withHeader('X-API-Key', $user->api_key)
            ->getJson('/api/v1/signatures')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.verify_code', 'US-TEST-001');
    }

    public function test_single_sign_via_api(): void
    {
        ['user' => $user] = $this->seedUsers();
        $pdf = $this->makePdf();

        $response = $this->withHeader('Authorization', 'Bearer '.$user->api_key)
            ->post('/api/v1/sign/single', [
                'pdf_file' => $pdf,
                'x' => 40,
                'y' => 40,
                'page' => 1,
                'document_number' => 'API-001',
                'document_subject' => 'API Subject',
                'pdf_password' => 'secret',
                'show_qr_caption' => 1,
                'qr_caption_position' => 'bottom',
            ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['id', 'verify_code', 'file_url']]);

        $this->assertDatabaseHas('signatures', [
            'document_number' => 'API-001',
            'user_id' => $user->id,
            'signature_type' => 'digital',
        ]);

        $code = $response->json('data.verify_code');
        $this->getJson('/api/v1/verify/'.$code)
            ->assertOk()
            ->assertJsonPath('data.valid', true);
    }

    public function test_user_cannot_delete_others_signature(): void
    {
        ['admin' => $admin, 'user' => $user] = $this->seedUsers();
        $sig = Signature::create([
            'user_id' => $admin->id,
            'document_name' => 'admin.pdf',
            'verify_code' => 'AD-ONLY-1',
            'signature_type' => 'digital',
            'signed_at' => now(),
        ]);

        $this->withHeader('X-API-Key', $user->api_key)
            ->deleteJson('/api/v1/signatures/'.$sig->id)
            ->assertStatus(403);
    }

    public function test_stats_endpoint_returns_user_and_tool_statistics(): void
    {
        ['user' => $user] = $this->seedUsers();

        \App\Models\ToolUsage::create([
            'user_id' => $user->id,
            'tool_name' => 'editor',
            'files_count' => 3,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$user->api_key)
            ->getJson('/api/v1/stats')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.pdf_tools.editor.uses', 1)
            ->assertJsonPath('data.pdf_tools.editor.files', 3);
    }

    public function test_qr_manual_sign_via_api(): void
    {
        ['user' => $user] = $this->seedUsers();

        $response = $this->withHeader('X-API-Key', $user->api_key)
            ->postJson('/api/v1/sign/qr-manual', [
                'document_number' => 'MANUAL-001',
                'subject' => 'Manual TTE Subject',
                'pdf_password' => 'passphrase123',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['id', 'verify_code', 'verify_url', 'qr_image_data_uri']]);

        $this->assertDatabaseHas('signatures', [
            'document_number' => 'MANUAL-001',
            'user_id' => $user->id,
            'signature_type' => 'qr_manual',
        ]);
    }

    public function test_invalid_api_key_returns_unauthorized(): void
    {
        $this->seedUsers();

        $this->withHeader('Authorization', 'Bearer digi_fake_invalid_key')
            ->getJson('/api/v1/me')
            ->assertStatus(401)
            ->assertJsonPath('error', 'invalid_api_key');
    }
}
