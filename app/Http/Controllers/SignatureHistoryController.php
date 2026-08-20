<?php

namespace App\Http\Controllers;

use App\Helpers\StorageHelper;
use App\Models\Signature;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SignatureHistoryController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Signature::with('user');

        if ($user->role !== 'admin') {
            $query->where('user_id', $user->id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('document_name', 'like', "%{$search}%")
                    ->orWhere('document_number', 'like', "%{$search}%")
                    ->orWhere('document_subject', 'like', "%{$search}%")
                    ->orWhere('verify_code', 'like', "%{$search}%")
                    ->orWhere('batch_id', 'like', "%{$search}%");
            });
        }

        $signatures = $query->orderByDesc('signed_at')->orderByDesc('id')->get()->map(function ($sig) {
            $signedAt = $sig->signed_at ?? $sig->created_at;

            return [
                'id' => $sig->id,
                'user_id' => $sig->user_id,
                'user_name' => $sig->user ? $sig->user->name : 'N/A',
                'user_position' => $sig->user ? ($sig->user->position ?: 'Staff') : 'N/A',
                'document_name' => $sig->document_name,
                'document_number' => $sig->document_number,
                'document_subject' => $sig->document_subject,
                'file_path' => $sig->file_path,
                'file_url' => $sig->file_path ? StorageHelper::getFileUrl($sig->file_path) : null,
                'verification_code' => $sig->verify_code,
                'verify_code' => $sig->verify_code,
                'signature_type' => $sig->signature_type,
                'batch_id' => $sig->batch_id,
                'created_at' => $signedAt ? $signedAt->format('d M Y H:i') : null,
                'signed_at' => $signedAt ? $signedAt->format('d M Y H:i') : null,
            ];
        });

        return Inertia::render('History', [
            'signatures' => $signatures,
            'filters' => $request->only(['search']),
        ]);
    }

    public function destroy(Signature $signature)
    {
        if (auth()->user()->role !== 'admin' && $signature->user_id !== auth()->id()) {
            abort(403);
        }

        if ($signature->file_path) {
            StorageHelper::delete($signature->file_path);
        }

        $signature->delete();

        return redirect()->back()->with('success', 'Riwayat tanda tangan berhasil dihapus.');
    }

    public function destroyBatch(Request $request, string $batchId)
    {
        $user = auth()->user();
        $query = Signature::where('batch_id', $batchId);

        if ($user->role !== 'admin') {
            $query->where('user_id', $user->id);
        }

        $signatures = $query->get();

        if ($signatures->isEmpty()) {
            return redirect()->back()->withErrors(['error' => 'Batch tidak ditemukan atau akses ditolak.']);
        }

        // Non-admin may only delete if they own every item in the batch
        if ($user->role !== 'admin') {
            $foreign = Signature::where('batch_id', $batchId)->where('user_id', '!=', $user->id)->exists();
            if ($foreign) {
                abort(403);
            }
        }

        $deletedFiles = 0;
        foreach ($signatures as $signature) {
            if ($signature->file_path) {
                StorageHelper::delete($signature->file_path);
                $deletedFiles++;
            }
            $signature->delete();
        }

        return redirect()->back()->with(
            'success',
            "Berhasil menghapus batch ({$deletedFiles} dokumen)."
        );
    }
}
