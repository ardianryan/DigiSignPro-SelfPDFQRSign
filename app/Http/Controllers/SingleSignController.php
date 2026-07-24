<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Signature;
use App\Models\AppSetting;
use App\Helpers\StorageHelper;
use Inertia\Inertia;
use setasign\FpdiProtection\FpdiProtection;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Throwable;
use Illuminate\Support\Facades\Log;

class SingleSignController extends Controller
{
    public function create()
    {
        $settings = AppSetting::first();
        return Inertia::render('Sign/Single', [
            'max_upload_size' => $settings ? $settings->max_upload_size : 10485760, // default 10MB
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'pdf_file' => 'required|file|mimes:pdf',
            'x' => 'required|numeric',
            'y' => 'required|numeric',
            'page' => 'required|integer|min:1',
            'document_number' => 'nullable|string|max:255',
            'document_subject' => 'nullable|string',
            'document_attachment' => 'nullable|string|max:255',
            'signed_date' => 'nullable|date',
            'show_qr_caption' => 'nullable|boolean',
            'qr_caption_position' => 'nullable|string|in:bottom,right',
        ]);

        try {
            $user = $request->user();
            $settings = AppSetting::first();
            $max_size = $settings ? $settings->max_upload_size : 10485760;

            if ($request->file('pdf_file')->getSize() > $max_size) {
                $mb = $max_size / 1024 / 1024;
                return response()->json(['status' => 'error', 'message' => "Ukuran file melebihi batas ($mb MB)"], 422);
            }

            $x = floatval($request->input('x'));
            $y = floatval($request->input('y'));
            $pageToSign = intval($request->input('page'));
            $docNumber = $request->input('document_number') ?? '';
            $docSubject = $request->input('document_subject') ?? '';
            $docAttachment = $request->input('document_attachment') ?? '';
            $signedDate = $request->input('signed_date') ?? date('Y-m-d');
            $showQrCaption = $request->boolean('show_qr_caption');
            $qrCaptionPosition = $request->input('qr_caption_position') ?? 'bottom';

            $signerName = $user->name;
            $signerPosition = $user->position ?? '';
            $userPrefix = $user->signature_prefix ?: 'DS';

            // Generate Verify Code
            $code = $userPrefix . '-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
            
            // Dynamic Verification URL
            $verifyUrl = route('verify', ['code' => $code]);

            // 2. Generate QR Code
            $options = new QROptions([
                'version'    => QRCode::VERSION_AUTO,
                'outputType' => QRCode::OUTPUT_IMAGE_PNG,
                'eccLevel'   => QRCode::ECC_L,
                'scale'      => 5,
            ]);
            
            $qrOutputInterface = new QRCode($options);
            $qrImage = $qrOutputInterface->render($verifyUrl);
            
            // Save QR base64 to temporary local file
            $base64 = explode(',', $qrImage)[1];
            $tempDir = storage_path('app/temp');
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0777, true);
            }
            $qrTempFile = $tempDir . '/qr_' . uniqid() . '.png';
            file_put_contents($qrTempFile, base64_decode($base64));

            // 3. Process PDF with FPDI
            $pdf = new FpdiProtection('P', 'mm', 'A4', true);
            $uploadFile = $request->file('pdf_file')->getRealPath();
            
            try {
                $pageCount = $pdf->setSourceFile($uploadFile);
            } catch (Throwable $e) {
                if (strpos($e->getMessage(), 'compression technique') !== false) {
                     return response()->json([
                         'status' => 'error', 
                         'message' => 'PDF menggunakan kompresi yang tidak didukung. Silakan "Print to PDF" atau simpan ulang sebagai PDF versi 1.4 agar kompatibel.'
                     ], 422);
                }
                return response()->json(['status' => 'error', 'message' => 'Gagal membaca PDF: ' . $e->getMessage()], 422);
            }

            // Loop and render pages
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $templateId = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($templateId);
                
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($templateId);

                if ($pageNo === $pageToSign) {
                    $qrSize = 25; 
                    $pdf->Image($qrTempFile, $x, $y, $qrSize, $qrSize);
                    
                    if ($showQrCaption) {
                        $lh_small = 3;
                        $lh_large = 4;
                        
                        $totalHeight = $lh_small + $lh_small + $lh_large;
                        if (!empty($signerPosition)) {
                            $totalHeight += $lh_small;
                        }
                        
                        if ($qrCaptionPosition === 'right') {
                            $startY = $y + ($qrSize - $totalHeight) / 2;
                            $startX = $x + $qrSize + 1;
                            
                            $pdf->SetFont('Arial', '', 6);
                            $pdf->SetXY($startX, $startY);
                            $pdf->Cell(50, $lh_small, "ID : " . $code, 0, 1, 'L');
                            
                            $pdf->SetX($startX);
                            $pdf->Cell(50, $lh_small, "Ditandatangani secara elektronik oleh", 0, 1, 'L');
                            
                            $pdf->SetFont('Arial', 'B', 10);
                            $pdf->SetX($startX);
                            $pdf->Cell(50, $lh_large, $signerName, 0, 1, 'L');
                            
                            if (!empty($signerPosition)) {
                                $pdf->SetFont('Arial', 'B', 6);
                                $pdf->SetX($startX);
                                $pdf->Cell(50, $lh_small, $signerPosition, 0, 1, 'L');
                            }
                        } else {
                            $textBoxWidth = 60;
                            $startX = $x + ($qrSize - $textBoxWidth) / 2;
                            $startY = $y + $qrSize + 0.5;
                            
                            $pdf->SetFont('Arial', '', 6);
                            $pdf->SetXY($startX, $startY);
                            $pdf->Cell($textBoxWidth, $lh_small, "ID : " . $code, 0, 1, 'C');
                            
                            $pdf->SetX($startX);
                            $pdf->Cell($textBoxWidth, $lh_small, "Ditandatangani secara elektronik oleh", 0, 1, 'C');
                            
                            $pdf->SetFont('Arial', 'B', 10);
                            $pdf->SetX($startX);
                            $pdf->Cell($textBoxWidth, $lh_large, $signerName, 0, 1, 'C');
                            
                            if (!empty($signerPosition)) {
                                $pdf->SetFont('Arial', 'B', 6);
                                $pdf->SetX($startX);
                                $pdf->Cell($textBoxWidth, $lh_small, $signerPosition, 0, 1, 'C');
                            }
                        }
                    }
                }
            }

            // Encrypt and protect PDF
            $pdf_pass = $user->signature_prefix ?: 'DS';
            $permissions = FpdiProtection::PERM_PRINT | FpdiProtection::PERM_COPY | FpdiProtection::PERM_ACCESSIBILITY;
            $pdf->setProtection($permissions, '', $pdf_pass);

            // Output to local temp file
            $tempSigned = $tempDir . '/signed_' . uniqid() . '.pdf';
            $pdf->Output('F', $tempSigned);

            // 4. Upload via StorageHelper
            $safe_subject = preg_replace('/[^A-Za-z0-9_-]/', '_', $docSubject);
            if (empty($safe_subject)) {
                $safe_subject = 'document';
            }
            $filename = $safe_subject . '_' . $code . '_signed.pdf';
            
            $outputPath = StorageHelper::upload($tempSigned, $filename);
            
            // Clean up temps
            if (file_exists($tempSigned)) {
                unlink($tempSigned);
            }
            if (file_exists($qrTempFile)) {
                unlink($qrTempFile);
            }

            // 5. Database Save
            $docName = $request->file('pdf_file')->getClientOriginalName();
            $signedTimestamp = $signedDate . ' ' . date('H:i:s');

            Signature::create([
                'user_id' => $user->id,
                'document_name' => $docName,
                'document_number' => $docNumber,
                'document_subject' => $docSubject,
                'document_attachment' => $docAttachment,
                'file_path' => $outputPath,
                'verify_code' => $code,
                'signature_type' => 'digital',
                'signed_at' => $signedTimestamp
            ]);

            return response()->json([
                'status' => 'success',
                'file_path' => StorageHelper::getFileUrl($outputPath),
                'verify_code' => $code
            ]);

        } catch (Throwable $e) {
            Log::error("PDF Signing Exception: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}
