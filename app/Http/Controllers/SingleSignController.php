<?php

namespace App\Http\Controllers;

use App\Helpers\QrCodeHelper;
use App\Helpers\StorageHelper;
use App\Models\AppSetting;
use App\Models\Signature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use setasign\FpdiProtection\FpdiProtection;
use Throwable;

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
            'pdf_password' => 'required|string',
        ]);

        try {
            $user = $request->user();
            $settings = AppSetting::first();
            $max_size = $settings ? $settings->max_upload_size : 10485760;

            if ($request->file('pdf_file')->getSize() > $max_size) {
                $mb = $max_size / 1024 / 1024;

                return response()->json(['status' => 'error', 'message' => "Ukuran file melebihi batas ($mb MB)"], 422);
            }

            // Security Hardening: Validate PDF Magic Bytes (%PDF-)
            $pdfMagic = file_get_contents($request->file('pdf_file')->getRealPath(), false, null, 0, 5);
            if (strpos($pdfMagic, '%PDF-') !== 0) {
                return response()->json(['status' => 'error', 'message' => 'Berkas yang diunggah bukan format PDF yang valid.'], 422);
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
            $code = $userPrefix.'-'.date('Ymd').'-'.strtoupper(substr(md5(uniqid()), 0, 6));

            // Dynamic Verification URL
            $verifyUrl = route('verify', ['code' => $code]);

            // 2. Generate QR Code (PNG via chillerlan v6 API)
            $tempDir = storage_path('app/temp');
            if (! file_exists($tempDir)) {
                mkdir($tempDir, 0777, true);
            }
            $qrTempFile = QrCodeHelper::toTempFile($verifyUrl, $tempDir, 5);

            // 3. Process PDF with FPDI
            $pdf = new FpdiProtection('P', 'mm', 'A4', true);
            $uploadFile = $request->file('pdf_file')->getRealPath();

            try {
                $pageCount = $pdf->setSourceFile($uploadFile);
            } catch (Throwable $e) {
                if (strpos($e->getMessage(), 'compression technique') !== false) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'PDF menggunakan kompresi yang tidak didukung. Silakan "Print to PDF" atau simpan ulang sebagai PDF versi 1.4 agar kompatibel.',
                    ], 422);
                }

                return response()->json(['status' => 'error', 'message' => 'Gagal membaca PDF: '.$e->getMessage()], 422);
            }

            // Loop and render pages
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $templateId = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($templateId);
                $orientation = ($size['width'] > $size['height']) ? 'L' : 'P';

                $pdf->AddPage($orientation, [$size['width'], $size['height']]);
                $pdf->useTemplate($templateId, 0, 0, $size['width'], $size['height'], true);

                if ($pageNo === $pageToSign) {
                    $qrSize = 25;
                    $pdf->Image($qrTempFile, $x, $y, $qrSize, $qrSize);

                    if ($showQrCaption) {
                        $lh_small = 3;
                        $lh_large = 4;

                        $totalHeight = $lh_small + $lh_small + $lh_large;
                        if (! empty($signerPosition)) {
                            $totalHeight += $lh_small;
                        }

                        if ($qrCaptionPosition === 'right') {
                            $startY = $y + ($qrSize - $totalHeight) / 2;
                            $startX = $x + $qrSize + 1;

                            $pdf->SetFont('Arial', '', 6);
                            $pdf->SetXY($startX, $startY);
                            $pdf->Cell(50, $lh_small, 'ID : '.$code, 0, 1, 'L');

                            $pdf->SetX($startX);
                            $pdf->Cell(50, $lh_small, 'Ditandatangani secara elektronik oleh', 0, 1, 'L');

                            $pdf->SetFont('Arial', 'B', 10);
                            $pdf->SetX($startX);
                            $pdf->Cell(50, $lh_large, $signerName, 0, 1, 'L');

                            if (! empty($signerPosition)) {
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
                            $pdf->Cell($textBoxWidth, $lh_small, 'ID : '.$code, 0, 1, 'C');

                            $pdf->SetX($startX);
                            $pdf->Cell($textBoxWidth, $lh_small, 'Ditandatangani secara elektronik oleh', 0, 1, 'C');

                            $pdf->SetFont('Arial', 'B', 10);
                            $pdf->SetX($startX);
                            $pdf->Cell($textBoxWidth, $lh_large, $signerName, 0, 1, 'C');

                            if (! empty($signerPosition)) {
                                $pdf->SetFont('Arial', 'B', 6);
                                $pdf->SetX($startX);
                                $pdf->Cell($textBoxWidth, $lh_small, $signerPosition, 0, 1, 'C');
                            }
                        }
                    }
                }
            }

            // Encrypt and protect PDF with user-provided parafrase password
            $pdf_pass = $request->input('pdf_password');
            $permissions = FpdiProtection::PERM_PRINT | FpdiProtection::PERM_COPY | FpdiProtection::PERM_ACCESSIBILITY;
            $pdf->setProtection($permissions, '', $pdf_pass);

            // Output to local temp file
            $tempSigned = $tempDir.'/signed_'.uniqid().'.pdf';
            $pdf->Output('F', $tempSigned);

            // 4. Upload via StorageHelper
            $safe_subject = preg_replace('/[^A-Za-z0-9_-]/', '_', $docSubject);
            if (empty($safe_subject)) {
                $safe_subject = 'document';
            }
            $filename = $safe_subject.'_'.$code.'_signed.pdf';

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
            $signedTimestamp = $signedDate.' '.date('H:i:s');

            Signature::create([
                'user_id' => $user->id,
                'document_name' => $docName,
                'document_number' => $docNumber,
                'document_subject' => $docSubject,
                'document_attachment' => $docAttachment,
                'file_path' => $outputPath,
                'verify_code' => $code,
                'signature_type' => 'digital',
                'signed_at' => $signedTimestamp,
            ]);

            return response()->json([
                'status' => 'success',
                'file_path' => StorageHelper::getFileUrl($outputPath),
                'verify_code' => $code,
            ]);

        } catch (Throwable $e) {
            Log::error('PDF Signing Exception: '.$e->getMessage());

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}
