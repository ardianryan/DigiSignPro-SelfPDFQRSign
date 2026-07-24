<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Signature;
use App\Helpers\StorageHelper;
use App\Helpers\QrCodeHelper;
use Inertia\Inertia;
use setasign\FpdiProtection\FpdiProtection;
use Throwable;
use Illuminate\Support\Facades\Log;

class QrSignController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Signature::where('signature_type', 'qr_manual');

        if ($user->role !== 'admin') {
            $query->where('user_id', $user->id);
        }

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('document_number', 'like', "%{$search}%")
                  ->orWhere('document_subject', 'like', "%{$search}%")
                  ->orWhere('verify_code', 'like', "%{$search}%");
            });
        }

        $signatures = $query->latest()->paginate(10)->through(function ($sig) {
            $signedAt = $sig->signed_at ?? $sig->created_at;

            return [
                'id' => $sig->id,
                'document_number' => $sig->document_number,
                'document_subject' => $sig->document_subject,
                'document_attachment' => $sig->document_attachment,
                'verify_code' => $sig->verify_code,
                'file_path' => $sig->file_path,
                'file_url' => $sig->file_path ? StorageHelper::getFileUrl($sig->file_path) : null,
                'signed_at' => $signedAt ? $signedAt->format('d M Y H:i') : null,
            ];
        });

        return Inertia::render('Sign/QrList', [
            'signatures' => $signatures,
            'filters' => $request->only(['search'])
        ]);
    }

    public function create(Request $request)
    {
        $step = intval($request->query('step', 1));
        $id = intval($request->query('id', 0));
        $user = $request->user();

        $data = null;
        $qrImage = null;

        if ($step >= 2 && $id > 0) {
            $data = Signature::where('id', $id);
            if ($user->role !== 'admin') {
                $data->where('user_id', $user->id);
            }
            $data = $data->first();

            if (!$data) {
                return redirect()->route('sign.qr.index')->withErrors(['error' => 'Data TTE QR tidak ditemukan.']);
            }

            $verifyUrl = route('verify', ['code' => $data->verify_code]);
            // Generate QR Code as data-URI PNG (chillerlan v6)
            $qrImage = QrCodeHelper::toDataUri($verifyUrl, 10);
        }

        return Inertia::render('Sign/QrCreate', [
            'step' => $step,
            'id' => $id,
            'data' => $data,
            'qrImage' => $qrImage,
        ]);
    }

    public function store(Request $request)
    {
        $step = intval($request->input('step', 1));

        if ($step === 1) {
            $request->validate([
                'document_number' => 'required|string|max:255',
                'subject' => 'required|string',
                'attachment' => 'nullable|string|max:255',
                'signed_at' => 'required|date',
                'pdf_password' => 'required|string',
            ]);

            $user = $request->user();
            $prefix = $user->signature_prefix ?: 'DS';
            $verifyCode = $prefix . '-TTE-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
            
            $signedTimestamp = $request->input('signed_at') . ' ' . date('H:i:s');

            session(['tte_qr_password' => $request->input('pdf_password')]);

            $subject = $request->input('subject');
            $signature = Signature::create([
                'user_id' => $user->id,
                'document_name' => $subject ?: ('TTE-QR-'.$request->input('document_number')),
                'document_number' => $request->input('document_number'),
                'document_subject' => $subject,
                'document_attachment' => $request->input('attachment'),
                'verify_code' => $verifyCode,
                'signature_type' => 'qr_manual',
                'signed_at' => $signedTimestamp,
            ]);

            return redirect()->route('sign.qr.create', ['step' => 2, 'id' => $signature->id]);
        }

        if ($step === 3) {
            $request->validate([
                'id' => 'required|integer',
                'pdf_file' => 'required|file|mimes:pdf',
                'pdf_password' => 'nullable|string',
            ]);

            $id = $request->input('id');
            $user = $request->user();

            $signature = Signature::where('id', $id);
            if ($user->role !== 'admin') {
                $signature->where('user_id', $user->id);
            }
            $signature = $signature->first();

            if (!$signature) {
                return redirect()->route('sign.qr.index')->withErrors(['error' => 'Data TTE QR tidak ditemukan.']);
            }

            $pdfPass = session('tte_qr_password') ?: $request->input('pdf_password');
            if (empty($pdfPass)) {
                return redirect()->back()->withErrors(['pdf_password' => 'Password Parafrase dibutuhkan. Silakan isi kembali.']);
            }

            try {
                $file = $request->file('pdf_file');
                $safeSubject = preg_replace('/[^A-Za-z0-9_-]/', '_', $signature->document_subject);
                if (empty($safeSubject)) {
                    $safeSubject = 'document';
                }
                $filename = $safeSubject . '_' . $signature->verify_code . '_signed.pdf';

                $tempDir = storage_path('app/temp');
                if (!file_exists($tempDir)) {
                    mkdir($tempDir, 0777, true);
                }
                $tempSigned = $tempDir . '/signed_qr_' . uniqid() . '.pdf';

                $pdf = new FpdiProtection('P', 'mm', 'A4', true);
                $pageCount = $pdf->setSourceFile($file->getRealPath());
                
                for ($p = 1; $p <= $pageCount; $p++) {
                    $tplId = $pdf->importPage($p);
                    $size = $pdf->getTemplateSize($tplId);
                    $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                    $pdf->useTemplate($tplId);
                }

                $permissions = FpdiProtection::PERM_PRINT | FpdiProtection::PERM_COPY | FpdiProtection::PERM_ACCESSIBILITY;
                $pdf->setProtection($permissions, '', $pdfPass);
                $pdf->Output('F', $tempSigned);

                $systemPath = StorageHelper::upload($tempSigned, $filename);

                if (file_exists($tempSigned)) {
                    unlink($tempSigned);
                }

                $signature->update([
                    'file_path' => $systemPath,
                ]);

                session()->forget('tte_qr_password');

                return redirect()->route('sign.qr.index')->with('success', 'Dokumen manual berhasil diunggah.');

            } catch (Throwable $e) {
                Log::error("Manual QR PDF Upload Error: " . $e->getMessage());
                return redirect()->back()->withErrors(['error' => 'Gagal memproses proteksi PDF: ' . $e->getMessage()]);
            }
        }

        return redirect()->route('sign.qr.index');
    }
}
