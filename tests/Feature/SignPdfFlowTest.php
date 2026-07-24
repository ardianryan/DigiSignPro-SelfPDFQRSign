<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Signature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use setasign\FpdiProtection\FpdiProtection;
use Tests\TestCase;

class SignPdfFlowTest extends TestCase
{
    use RefreshDatabase;

    private function seedUser(): User
    {
        AppSetting::create([
            'app_name' => 'DigiSign Pro',
            'maintenance_mode' => false,
            'registration_open' => true,
            'max_upload_size' => 20 * 1024 * 1024,
            'max_upload_size_bulk' => 50 * 1024 * 1024,
            'max_prefix_length' => 3,
            'timezone' => 'Asia/Jakarta',
            'storage_mode' => 'local',
            's3_directory' => 'digisign/',
        ]);

        return User::create([
            'name' => 'Signer',
            'email' => 'signer@example.com',
            'password' => Hash::make('password'),
            'role' => 'user',
            'position' => 'Staff',
            'signature_prefix' => 'SG',
        ]);
    }

    private function makeSamplePdf(): string
    {
        $path = sys_get_temp_dir().'/sample_'.uniqid().'.pdf';
        // 4th arg true = arcfour fallback (OpenSSL 3 without legacy RC4 provider)
        $pdfLib = new FpdiProtection('P', 'mm', 'A4', true);
        $pdfLib->AddPage();
        $pdfLib->SetFont('Arial', '', 12);
        $pdfLib->Text(20, 20, 'Hello DigiSign Sample');
        $pdfLib->Output('F', $path);

        return $path;
    }

    public function test_single_sign_requires_pdf_password_and_signs(): void
    {
        Storage::fake('public');
        $user = $this->seedUser();
        $pdfPath = $this->makeSamplePdf();
        $upload = new UploadedFile($pdfPath, 'sample.pdf', 'application/pdf', null, true);

        // Missing password should fail validation
        try {
            $this->withoutExceptionHandling();
            $this->actingAs($user)->post('/sign/single', [
                'pdf_file' => $upload,
                'x' => 50,
                'y' => 50,
                'page' => 1,
                'document_number' => 'DOC-1',
                'document_subject' => 'Test Single',
                'signed_date' => now()->toDateString(),
                'show_qr_caption' => 1,
                'qr_caption_position' => 'bottom',
            ]);
            $this->fail('Expected ValidationException for missing pdf_password');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('pdf_password', $e->errors());
        }

        // Successful sign
        $pdfPath = $this->makeSamplePdf();
        $upload = new UploadedFile($pdfPath, 'sample.pdf', 'application/pdf', null, true);

        $response = $this->withExceptionHandling()
            ->actingAs($user)
            ->post('/sign/single', [
                'pdf_file' => $upload,
                'x' => 50,
                'y' => 50,
                'page' => 1,
                'document_number' => 'DOC-1',
                'document_subject' => 'Test Single',
                'document_attachment' => '',
                'signed_date' => now()->toDateString(),
                'show_qr_caption' => 1,
                'qr_caption_position' => 'bottom',
                'pdf_password' => 'parafrase123',
            ], [
                'Accept' => 'application/json',
            ]);

        if ($response->status() !== 200) {
            $this->fail('Single sign failed ('.$response->status().'): '.$response->getContent());
        }

        $response->assertOk()->assertJsonPath('status', 'success');
        $this->assertDatabaseHas('signatures', [
            'document_number' => 'DOC-1',
            'document_subject' => 'Test Single',
            'signature_type' => 'digital',
            'user_id' => $user->id,
        ]);

        $sig = Signature::where('document_number', 'DOC-1')->first();
        $this->assertNotEmpty($sig->verify_code);
        $this->assertStringStartsWith('SG-', $sig->verify_code);
        $this->assertNotEmpty($sig->file_path);
        $this->assertNotEmpty($response->json('file_path'));

        @unlink($pdfPath);
    }
}
