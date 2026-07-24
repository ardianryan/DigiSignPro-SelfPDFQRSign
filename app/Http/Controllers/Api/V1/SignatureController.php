<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\StorageHelper;
use App\Models\Signature;
use Illuminate\Http\Request;

class SignatureController extends BaseApiController
{
    public function index(Request $request)
    {
        $user = $this->apiUser($request);

        $query = Signature::query()->with('user:id,name,position');

        if ($user->role !== 'admin') {
            $query->where('user_id', $user->id);
        } elseif ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->query('user_id'));
        }

        if ($request->filled('search')) {
            $s = $request->query('search');
            $query->where(function ($q) use ($s) {
                $q->where('document_name', 'like', "%{$s}%")
                    ->orWhere('document_number', 'like', "%{$s}%")
                    ->orWhere('document_subject', 'like', "%{$s}%")
                    ->orWhere('verify_code', 'like', "%{$s}%")
                    ->orWhere('batch_id', 'like', "%{$s}%");
            });
        }

        if ($request->filled('signature_type')) {
            $query->where('signature_type', $request->query('signature_type'));
        }

        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));

        $paginator = $query->orderByDesc('signed_at')->orderByDesc('id')->paginate($perPage);

        $items = $paginator->getCollection()->map(fn (Signature $sig) => $this->transform($sig));

        return $this->ok([
            'items' => $items,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ], 'Daftar riwayat tanda tangan');
    }

    public function show(Request $request, int $id)
    {
        $user = $this->apiUser($request);
        $sig = Signature::with('user:id,name,position')->find($id);

        if (! $sig) {
            return $this->fail('Signature tidak ditemukan.', 404, 'not_found');
        }

        if ($user->role !== 'admin' && $sig->user_id !== $user->id) {
            return $this->fail('Akses ditolak.', 403, 'forbidden');
        }

        return $this->ok($this->transform($sig), 'Detail signature');
    }

    public function destroy(Request $request, int $id)
    {
        $user = $this->apiUser($request);
        $sig = Signature::find($id);

        if (! $sig) {
            return $this->fail('Signature tidak ditemukan.', 404, 'not_found');
        }

        if ($user->role !== 'admin' && $sig->user_id !== $user->id) {
            return $this->fail('Akses ditolak.', 403, 'forbidden');
        }

        if ($sig->file_path) {
            StorageHelper::delete($sig->file_path);
        }
        $sig->delete();

        return $this->ok(null, 'Signature dihapus');
    }

    private function transform(Signature $sig): array
    {
        return [
            'id' => $sig->id,
            'user_id' => $sig->user_id,
            'user_name' => $sig->user?->name,
            'batch_id' => $sig->batch_id,
            'document_name' => $sig->document_name,
            'document_number' => $sig->document_number,
            'document_subject' => $sig->document_subject,
            'document_attachment' => $sig->document_attachment,
            'file_path' => $sig->file_path,
            'file_url' => $sig->file_path ? StorageHelper::getFileUrl($sig->file_path) : null,
            'verify_code' => $sig->verify_code,
            'signature_type' => $sig->signature_type,
            'signed_at' => $sig->signed_at?->toIso8601String(),
            'created_at' => $sig->created_at?->toIso8601String(),
        ];
    }
}
