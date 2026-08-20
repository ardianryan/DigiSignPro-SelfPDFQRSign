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
use ZipArchive;

class BulkSignController extends Controller
{
    public function create()
    {
        $settings = AppSetting::first();

        return Inertia::render('Sign/Bulk', [
            'max_upload_size_bulk' => $settings ? $settings->max_upload_size_bulk : 52428800, // default 50MB
        ]);
    }

    public function previewBulk(Request $request)
    {
        $request->validate([
            'zip_file' => 'required|file|mimes:zip',
        ]);

        try {
            $settings = AppSetting::first();
            $max_size = $settings ? $settings->max_upload_size_bulk : 52428800;

            if ($request->file('zip_file')->getSize() > $max_size) {
                $mb = round($max_size / 1024 / 1024);

                return response()->json(['status' => 'error', 'message' => "Ukuran file melebihi batas ($mb MB)"], 422);
            }

            // Security Hardening: Validate ZIP Magic Bytes (PK\x03\x04 / PK\x05\x06 / PK\x07\x08)
            $zipMagic = file_get_contents($request->file('zip_file')->getRealPath(), false, null, 0, 4);
            if (! str_starts_with($zipMagic, "PK\x03\x04") && ! str_starts_with($zipMagic, "PK\x05\x06") && ! str_starts_with($zipMagic, "PK\x07\x08")) {
                return response()->json(['status' => 'error', 'message' => 'Berkas yang diunggah bukan format ZIP yang valid.'], 422);
            }

            // Setup Temp Dir inside public disk for client previewing
            $batchId = uniqid('bulk_', true);
            $tempDir = storage_path('app/public/uploads/temp/'.$batchId);
            if (! file_exists($tempDir)) {
                mkdir($tempDir, 0777, true);
            }

            $zipPath = $tempDir.'/original.zip';
            if (! copy($request->file('zip_file')->getRealPath(), $zipPath)) {
                return response()->json(['status' => 'error', 'message' => 'Gagal menyimpan file ZIP'], 500);
            }

            // Extract First PDF for Preview
            $zip = new ZipArchive;
            if ($zip->open($zipPath) === true) {
                $firstPdf = null;

                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $filename = $zip->getNameIndex($i);
                    // Security Hardening: Prevent Zip Slip / Directory Traversal attacks
                    if (str_contains($filename, '..') || str_starts_with($filename, '/') || str_starts_with($filename, '\\')) {
                        continue;
                    }
                    if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'pdf') {
                        if (strpos($filename, '__MACOSX') === false) {
                            $firstPdf = $filename;
                            break;
                        }
                    }
                }

                if ($firstPdf) {
                    $zip->extractTo($tempDir, $firstPdf);
                    $zip->close();

                    $previewUrl = url('storage/uploads/temp/'.$batchId.'/'.$firstPdf);

                    return response()->json([
                        'status' => 'success',
                        'batch_id' => $batchId,
                        'preview_url' => $previewUrl,
                        'filename' => pathinfo($firstPdf, PATHINFO_BASENAME),
                    ]);
                } else {
                    $zip->close();

                    return response()->json(['status' => 'error', 'message' => 'Tidak ditemukan file PDF di dalam ZIP'], 422);
                }
            } else {
                return response()->json(['status' => 'error', 'message' => 'Gagal membaca ZIP archive'], 422);
            }
        } catch (Throwable $e) {
            Log::error('Bulk Sign Preview Error: '.$e->getMessage());

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'batch_id' => 'required|string',
            'x' => 'required|numeric',
            'y' => 'required|numeric',
            'page' => 'required|integer|min:1',
            'base_number' => 'nullable|string',
            'subject' => 'nullable|string',
            'pdf_password' => 'required|string',
            'show_qr_caption' => 'nullable|boolean',
            'qr_caption_position' => 'nullable|string|in:bottom,right',
        ]);

        // Auto-cleanup old temp directories (older than 1 hour)
        $cleanupDir = storage_path('app/public/uploads/temp');
        if (is_dir($cleanupDir)) {
            $now = time();
            $files = scandir($cleanupDir);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                $filePath = $cleanupDir.'/'.$file;
                if (file_exists($filePath) && filemtime($filePath) < ($now - 3600)) {
                    if (is_dir($filePath)) {
                        $batchFiles = scandir($filePath);
                        foreach ($batchFiles as $bf) {
                            if ($bf === '.' || $bf === '..') {
                                continue;
                            }
                            @unlink($filePath.'/'.$bf);
                        }
                        @rmdir($filePath);
                    } else {
                        @unlink($filePath);
                    }
                }
            }
        }

        try {
            $user = $request->user();
            $batchId = $request->input('batch_id');
            $x = floatval($request->input('x'));
            $y = floatval($request->input('y'));
            $pageToSign = intval($request->input('page'));
            $baseNumber = $request->input('base_number') ?? '';
            $subject = $request->input('subject') ?? '';
            $pdf_pass = $request->input('pdf_password');
            $showQrCaption = $request->boolean('show_qr_caption');
            $qrCaptionPosition = $request->input('qr_caption_position') ?? 'bottom';

            $signerName = $user->name;
            $signerPosition = $user->position ?? '';
            $userPrefix = $user->signature_prefix ?: 'DS';

            $tempDir = storage_path('app/public/uploads/temp/'.$batchId);
            $originalZipPath = $tempDir.'/original.zip';

            if (! file_exists($originalZipPath)) {
                return response()->json(['status' => 'error', 'message' => 'File batch tidak ditemukan. Silakan upload kembali.'], 422);
            }

            $outputZipName = 'Bulk_Signed_'.date('Ymd_Hi').'.zip';
            $outputZipPath = $tempDir.'/'.$outputZipName;

            $outputZip = new ZipArchive;
            if ($outputZip->open($outputZipPath, ZipArchive::CREATE) !== true) {
                return response()->json(['status' => 'error', 'message' => 'Gagal membuat file ZIP hasil'], 500);
            }

            $inputZip = new ZipArchive;
            if ($inputZip->open($originalZipPath) !== true) {
                return response()->json(['status' => 'error', 'message' => 'Gagal membuka file ZIP sumber'], 422);
            }

            $processedCount = 0;
            $errorCount = 0;
            $errors = [];

            for ($i = 0; $i < $inputZip->numFiles; $i++) {
                $filename = $inputZip->getNameIndex($i);
                $fileInfo = pathinfo($filename);

                if (substr($filename, -1) === '/' || strtolower($fileInfo['extension'] ?? '') !== 'pdf' || strpos($filename, '__MACOSX') !== false || str_contains($filename, '..')) {
                    continue;
                }

                $tempPdfPath = $tempDir.'/temp_'.$i.'.pdf';
                copy('zip://'.$originalZipPath.'#'.$filename, $tempPdfPath);

                try {
                    $verifyCode = $userPrefix.'-BLK-'.date('Ymd').'-'.strtoupper(substr(md5(uniqid()), 0, 6));
                    $verifyUrl = route('verify', ['code' => $verifyCode]);
                    $qrTempFile = QrCodeHelper::toTempFile($verifyUrl, $tempDir, 5);

                    $pdf = new FpdiProtection('P', 'mm', 'A4', true);

                    try {
                        $pageCount = $pdf->setSourceFile($tempPdfPath);
                    } catch (Throwable $e) {
                        throw new \Exception('Kompresi PDF/Versi tidak didukung: '.$e->getMessage());
                    }

                    $targetPage = ($pageToSign > $pageCount) ? $pageCount : $pageToSign;

                    for ($p = 1; $p <= $pageCount; $p++) {
                        $tplId = $pdf->importPage($p);
                        $size = $pdf->getTemplateSize($tplId);
                        $orientation = ($size['width'] > $size['height']) ? 'L' : 'P';

                        $pdf->AddPage($orientation, [$size['width'], $size['height']]);
                        $pdf->useTemplate($tplId, 0, 0, $size['width'], $size['height'], true);

                        if ($p === $targetPage) {
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
                                    $pdf->Cell(50, $lh_small, 'ID : '.$verifyCode, 0, 1, 'L');

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
                                    $pdf->Cell($textBoxWidth, $lh_small, 'ID : '.$verifyCode, 0, 1, 'C');

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

                    $permissions = FpdiProtection::PERM_PRINT | FpdiProtection::PERM_COPY | FpdiProtection::PERM_ACCESSIBILITY;
                    $pdf->setProtection($permissions, '', $pdf_pass);

                    $tempSigned = $tempDir.'/signed_'.$processedCount.'.pdf';
                    $pdf->Output('F', $tempSigned);

                    $safe_subject = preg_replace('/[^A-Za-z0-9_-]/', '_', $subject);
                    if (empty($safe_subject)) {
                        $safe_subject = 'bulk';
                    }
                    $signedFilename = $safe_subject.'_'.$verifyCode.'_signed.pdf';

                    $systemPath = StorageHelper::upload($tempSigned, $signedFilename);

                    // Add to Output ZIP
                    $outputZip->addFile($tempSigned, 'Signed_'.$fileInfo['basename']);

                    // DB Insert
                    $docNumber = $baseNumber.' - '.($processedCount + 1);
                    $docName = $fileInfo['basename'];
                    $signedAt = date('Y-m-d H:i:s');

                    Signature::create([
                        'user_id' => $user->id,
                        'batch_id' => $batchId,
                        'document_name' => $docName,
                        'document_number' => $docNumber,
                        'document_subject' => $subject,
                        'file_path' => $systemPath,
                        'verify_code' => $verifyCode,
                        'signature_type' => 'digital',
                        'signed_at' => $signedAt,
                    ]);

                    $processedCount++;

                    if (file_exists($qrTempFile)) {
                        unlink($qrTempFile);
                    }

                } catch (Throwable $e) {
                    $errorMsg = 'Gagal memproses '.$filename.': '.$e->getMessage();
                    $outputZip->addFromString('ERROR_'.$fileInfo['basename'].'.txt', $errorMsg);
                    $errors[] = $errorMsg;
                    $errorCount++;
                }

                if (file_exists($tempPdfPath)) {
                    unlink($tempPdfPath);
                }
            }

            $inputZip->close();
            if (! $outputZip->close()) {
                return response()->json(['status' => 'error', 'message' => 'Gagal merampungkan output ZIP'], 500);
            }

            // Cleanup temp individual PDFs
            for ($j = 0; $j < $processedCount; $j++) {
                $tempSigned = $tempDir.'/signed_'.$j.'.pdf';
                if (file_exists($tempSigned)) {
                    @unlink($tempSigned);
                }
            }

            $zipUrl = url('storage/uploads/temp/'.$batchId.'/'.$outputZipName);

            if (file_exists($originalZipPath)) {
                unlink($originalZipPath);
            }

            return response()->json([
                'status' => 'success',
                'processed' => $processedCount,
                'failed' => $errorCount,
                'errors' => $errors,
                'zip_url' => $zipUrl,
            ]);

        } catch (Throwable $e) {
            Log::error('Bulk Sign Processing Error: '.$e->getMessage());

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}
