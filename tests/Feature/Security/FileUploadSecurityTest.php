<?php

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use setasign\FpdiProtection\FpdiProtection;

function generateTestPdf(): string
{
    $path = sys_get_temp_dir().'/sec_test_'.uniqid().'.pdf';
    $pdf = new FpdiProtection('P', 'mm', 'A4', true);
    $pdf->AddPage();
    $pdf->SetFont('Arial', '', 12);
    $pdf->Text(20, 20, 'Security Audit PDF Sample');
    $pdf->Output('F', $path);

    return $path;
}

test('rejects non-pdf files disguised with .pdf extension on single sign', function () {
    $user = User::factory()->create();

    // Fake payload masquerading as a PDF
    $fakePdf = UploadedFile::fake()->createWithContent('shell.pdf', '<?php phpinfo(); ?>');

    $response = $this->actingAs($user)->post('/sign/single', [
        'pdf_file' => $fakePdf,
        'x' => 100,
        'y' => 100,
        'page' => 1,
        'pdf_password' => 'secret123',
    ]);

    $response->assertStatus(422);
    $response->assertJson([
        'status' => 'error',
    ]);
});

test('accepts valid PDF with %PDF- magic bytes on single sign', function () {
    AppSetting::firstOrCreate(
        ['id' => 1],
        [
            'app_name' => 'DigiSign Pro',
            'maintenance_mode' => false,
            'registration_open' => true,
            'max_upload_size' => 20 * 1024 * 1024,
            'max_upload_size_bulk' => 50 * 1024 * 1024,
            'max_prefix_length' => 3,
            'timezone' => 'Asia/Jakarta',
            'storage_mode' => 'local',
            's3_directory' => 'digisign/',
        ]
    );

    $user = User::factory()->create();
    $pdfPath = generateTestPdf();
    $validPdf = new UploadedFile($pdfPath, 'valid_audit.pdf', 'application/pdf', null, true);

    $response = $this->actingAs($user)->post('/sign/single', [
        'pdf_file' => $validPdf,
        'x' => 50,
        'y' => 50,
        'page' => 1,
        'document_number' => 'TEST-001',
        'document_subject' => 'Security Audit Valid Document',
        'pdf_password' => 'safe_password_123',
    ]);

    $response->assertStatus(200);
    $response->assertJson(['status' => 'success']);

    @unlink($pdfPath);
});

test('rejects non-zip files disguised with .zip extension on bulk preview', function () {
    $user = User::factory()->create();

    $fakeZip = UploadedFile::fake()->createWithContent('fake.zip', 'NOT_A_ZIP_HEADER_CONTENT');

    $response = $this->actingAs($user)->post('/sign/preview-bulk', [
        'zip_file' => $fakeZip,
    ]);

    $response->assertStatus(422);
});

test('api rejects disguised non-pdf file on /api/v1/sign/single', function () {
    $user = User::factory()->create([
        'api_key' => 'dg_sec_test_api_key_123',
    ]);

    $fakePdf = UploadedFile::fake()->createWithContent('exploit.pdf', 'MALICIOUS_SCRIPT_CONTENT');

    $response = $this->withHeaders([
        'Authorization' => 'Bearer dg_sec_test_api_key_123',
    ])->post('/api/v1/sign/single', [
        'pdf_file' => $fakePdf,
        'x' => 10,
        'y' => 10,
        'page' => 1,
        'pdf_password' => 'secret123',
    ]);

    $response->assertStatus(422);
    $response->assertJson([
        'success' => false,
        'error' => 'invalid_pdf_format',
    ]);
});
