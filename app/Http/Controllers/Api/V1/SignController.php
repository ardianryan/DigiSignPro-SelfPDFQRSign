<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\QrCodeHelper;
use App\Helpers\StorageHelper;
use App\Models\AppSetting;
use App\Models\Signature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use setasign\FpdiProtection\FpdiProtection;
use Throwable;

class SignController extends BaseApiController
{
    /**
     * POST /api/v1/sign/single
     * multipart: pdf_file, x, y, page, document_number, document_subject,
     * document_attachment?, signed_date?, show_qr_caption?, qr_caption_position?, pdf_password
     */
    public function single(Request $request)
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

        $user = $this->apiUser($request);
        $settings = AppSetting::first();
        $maxSize = $settings?->max_upload_size ?: 10485760;

        if ($request->file('pdf_file')->getSize() > $maxSize) {
            $mb = $maxSize / 1024 / 1024;

            return $this->fail("Ukuran file melebihi batas ({$mb} MB)", 422, 'file_too_large');
        }

        try {
            $x = (float) $request->input('x');
            $y = (float) $request->input('y');
            $pageToSign = (int) $request->input('page');
            $docNumber = $request->input('document_number') ?? '';
            $docSubject = $request->input('document_subject') ?? '';
            $docAttachment = $request->input('document_attachment') ?? '';
            $signedDate = $request->input('signed_date') ?? date('Y-m-d');
            $showQrCaption = $request->boolean('show_qr_caption', true);
            $qrCaptionPosition = $request->input('qr_caption_position') ?? 'bottom';

            $signerName = $user->name;
            $signerPosition = $user->position ?? '';
            $userPrefix = $user->signature_prefix ?: 'DS';
            $code = $userPrefix.'-'.date('Ymd').'-'.strtoupper(substr(md5(uniqid()), 0, 6));
            $verifyUrl = route('verify', ['code' => $code]);

            $tempDir = storage_path('app/temp');
            if (! is_dir($tempDir)) {
                mkdir($tempDir, 0775, true);
            }

            $qrTempFile = QrCodeHelper::toTempFile($verifyUrl, $tempDir, 8);
            $pdf = new FpdiProtection('P', 'mm', 'A4', true);
            $uploadFile = $request->file('pdf_file')->getRealPath();

            try {
                $pageCount = $pdf->setSourceFile($uploadFile);
            } catch (Throwable $e) {
                if (str_contains($e->getMessage(), 'compression technique')) {
                    return $this->fail(
                        'PDF menggunakan kompresi yang tidak didukung. Simpan ulang sebagai PDF 1.4.',
                        422,
                        'unsupported_pdf'
                    );
                }

                return $this->fail('Gagal membaca PDF: '.$e->getMessage(), 422, 'pdf_read_error');
            }

            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $templateId = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($templateId);
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($templateId);

                if ($pageNo === $pageToSign) {
                    $qrSize = 25;
                    $pdf->Image($qrTempFile, $x, $y, $qrSize, $qrSize);

                    if ($showQrCaption) {
                        $lhSmall = 3;
                        $lhLarge = 4;
                        $totalHeight = $lhSmall + $lhSmall + $lhLarge;
                        if (! empty($signerPosition)) {
                            $totalHeight += $lhSmall;
                        }

                        if ($qrCaptionPosition === 'right') {
                            $startY = $y + ($qrSize - $totalHeight) / 2;
                            $startX = $x + $qrSize + 1;
                            $pdf->SetFont('Arial', '', 6);
                            $pdf->SetXY($startX, $startY);
                            $pdf->Cell(50, $lhSmall, 'ID : '.$code, 0, 1, 'L');
                            $pdf->SetX($startX);
                            $pdf->Cell(50, $lhSmall, 'Ditandatangani secara elektronik oleh', 0, 1, 'L');
                            $pdf->SetFont('Arial', 'B', 10);
                            $pdf->SetX($startX);
                            $pdf->Cell(50, $lhLarge, $signerName, 0, 1, 'L');
                            if (! empty($signerPosition)) {
                                $pdf->SetFont('Arial', 'B', 6);
                                $pdf->SetX($startX);
                                $pdf->Cell(50, $lhSmall, $signerPosition, 0, 1, 'L');
                            }
                        } else {
                            $textBoxWidth = 60;
                            $startX = $x + ($qrSize - $textBoxWidth) / 2;
                            $startY = $y + $qrSize + 0.5;
                            $pdf->SetFont('Arial', '', 6);
                            $pdf->SetXY($startX, $startY);
                            $pdf->Cell($textBoxWidth, $lhSmall, 'ID : '.$code, 0, 1, 'C');
                            $pdf->SetX($startX);
                            $pdf->Cell($textBoxWidth, $lhSmall, 'Ditandatangani secara elektronik oleh', 0, 1, 'C');
                            $pdf->SetFont('Arial', 'B', 10);
                            $pdf->SetX($startX);
                            $pdf->Cell($textBoxWidth, $lhLarge, $signerName, 0, 1, 'C');
                            if (! empty($signerPosition)) {
                                $pdf->SetFont('Arial', 'B', 6);
                                $pdf->SetX($startX);
                                $pdf->Cell($textBoxWidth, $lhSmall, $signerPosition, 0, 1, 'C');
                            }
                        }
                    }
                }
            }

            $pdfPass = $request->input('pdf_password');
            $permissions = FpdiProtection::PERM_PRINT | FpdiProtection::PERM_COPY | FpdiProtection::PERM_ACCESSIBILITY;
            $pdf->setProtection($permissions, '', $pdfPass);

            $tempSigned = $tempDir.'/signed_'.uniqid().'.pdf';
            $pdf->Output('F', $tempSigned);

            $safeSubject = preg_replace('/[^A-Za-z0-9_-]/', '_', $docSubject) ?: 'document';
            $filename = $safeSubject.'_'.$code.'_signed.pdf';
            $outputPath = StorageHelper::upload($tempSigned, $filename);

            @unlink($tempSigned);
            @unlink($qrTempFile);

            $signature = Signature::create([
                'user_id' => $user->id,
                'document_name' => $request->file('pdf_file')->getClientOriginalName(),
                'document_number' => $docNumber,
                'document_subject' => $docSubject,
                'document_attachment' => $docAttachment,
                'file_path' => $outputPath,
                'verify_code' => $code,
                'signature_type' => 'digital',
                'signed_at' => $signedDate.' '.date('H:i:s'),
            ]);

            return $this->ok([
                'id' => $signature->id,
                'verify_code' => $code,
                'verify_url' => $verifyUrl,
                'file_path' => $outputPath,
                'file_url' => StorageHelper::getFileUrl($outputPath),
            ], 'Dokumen berhasil ditandatangani', 201);
        } catch (Throwable $e) {
            Log::error('API single sign: '.$e->getMessage());

            return $this->fail('Gagal menandatangani: '.$e->getMessage(), 500, 'sign_failed');
        }
    }

    /**
     * POST /api/v1/sign/qr-manual
     * Create TTE QR manual record (+ optional QR data URI).
     */
    public function qrManual(Request $request)
    {
        $request->validate([
            'document_number' => 'required|string|max:255',
            'subject' => 'required|string',
            'attachment' => 'nullable|string|max:255',
            'signed_at' => 'nullable|date',
            'pdf_password' => 'required|string',
        ]);

        $user = $this->apiUser($request);
        $prefix = $user->signature_prefix ?: 'DS';
        $verifyCode = $prefix.'-TTE-'.date('Ymd').'-'.strtoupper(substr(md5(uniqid()), 0, 6));
        $signedAt = ($request->input('signed_at') ?: date('Y-m-d')).' '.date('H:i:s');
        $subject = $request->input('subject');

        $signature = Signature::create([
            'user_id' => $user->id,
            'document_name' => $subject ?: ('TTE-QR-'.$request->input('document_number')),
            'document_number' => $request->input('document_number'),
            'document_subject' => $subject,
            'document_attachment' => $request->input('attachment'),
            'verify_code' => $verifyCode,
            'signature_type' => 'qr_manual',
            'signed_at' => $signedAt,
        ]);

        // Store password in session is not useful for API; just return QR image
        $verifyUrl = route('verify', ['code' => $verifyCode]);
        $qrDataUri = QrCodeHelper::toDataUri($verifyUrl, 10);

        return $this->ok([
            'id' => $signature->id,
            'verify_code' => $verifyCode,
            'verify_url' => $verifyUrl,
            'qr_image_data_uri' => $qrDataUri,
            'document_number' => $signature->document_number,
            'document_subject' => $signature->document_subject,
            'signed_at' => $signature->signed_at?->toIso8601String(),
            'note' => 'pdf_password diterima untuk kompatibilitas klien; gunakan password yang sama saat proteksi PDF manual.',
        ], 'TTE QR manual dibuat', 201);
    }
}
