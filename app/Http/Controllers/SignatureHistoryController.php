<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Signature;
use App\Helpers\StorageHelper;
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
        
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('document_name', 'like', "%{$search}%")
                  ->orWhere('verification_code', 'like', "%{$search}%");
            });
        }
        
        $signatures = $query->latest()->get()->map(function ($sig) {
            return [
                'id' => $sig->id,
                'user_id' => $sig->user_id,
                'user_name' => $sig->user ? $sig->user->name : 'N/A',
                'user_position' => $sig->user ? $sig->user->position : 'N/A',
                'document_name' => $sig->document_name,
                'file_path' => $sig->file_path,
                'file_url' => StorageHelper::getFileUrl($sig->file_path),
                'verification_code' => $sig->verification_code,
                'signature_type' => $sig->signature_type,
                'batch_id' => $sig->batch_id,
                'created_at' => $sig->created_at->format('d M Y H:i'),
            ];
        });
        
        return Inertia::render('History', [
            'signatures' => $signatures,
            'filters' => $request->only(['search'])
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
}
