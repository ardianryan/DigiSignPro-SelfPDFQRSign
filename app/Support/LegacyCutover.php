<?php

namespace App\Support;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * One-time cutover from DigiSign PHP-native → Laravel.
 *
 * - Schema adapt (columns/tables)
 * - Laravel migrations
 * - API keys for all users
 * - Lock so it never runs again (unless --force)
 *
 * Ongoing deploys use CI/CD + regular "Migrasi Database" only.
 */
class LegacyCutover
{
    public function lockPath(): string
    {
        return storage_path('app/legacy_cutover.lock');
    }

    public function isCompleted(): bool
    {
        if (File::exists($this->lockPath())) {
            return true;
        }

        try {
            $settings = AppSetting::query()->first();
            if ($settings && ! empty($settings->legacy_cutover_at)) {
                return true;
            }
        } catch (Throwable) {
            // table/column may not exist yet
        }

        return false;
    }

    /**
     * Heuristic: does this look like a DB that came from legacy DigiSign?
     */
    public function looksLegacy(): bool
    {
        try {
            if (Schema::hasTable('users') && Schema::hasColumn('users', 'reset_token')) {
                return true;
            }
            if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'api_key')) {
                return true;
            }
            if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'remember_token')) {
                return true;
            }
            if (Schema::hasTable('signatures') && ! Schema::hasColumn('signatures', 'updated_at')) {
                return true;
            }
        } catch (Throwable) {
            return false;
        }

        return false;
    }

    /**
     * Whether the one-time UI/CLI should still offer cutover.
     */
    public function shouldOffer(): bool
    {
        return ! $this->isCompleted();
    }

    /**
     * @return array{completed: bool, already_done: bool, steps: list<string>, message: string}
     */
    public function run(bool $force = false, ?string $triggeredBy = null): array
    {
        if ($this->isCompleted() && ! $force) {
            return [
                'completed' => true,
                'already_done' => true,
                'steps' => [],
                'message' => 'Cutover legacy sudah pernah dijalankan. Gunakan Migrasi Database biasa + CI/CD untuk update berikutnya.',
            ];
        }

        $steps = [];

        // 1) Schema adapt (idempotent)
        $schemaSteps = (new LegacyDigisignSchema)->adapt();
        $steps = array_merge($steps, array_map(fn ($s) => "[schema] {$s}", $schemaSteps));

        // 2) Laravel migrations
        try {
            Artisan::call('migrate', ['--force' => true]);
            $out = trim(Artisan::output());
            $steps[] = '[migrate] '.($out !== '' ? preg_replace('/\s+/', ' ', $out) : 'OK');
        } catch (Throwable $e) {
            $steps[] = '[migrate] ERROR: '.$e->getMessage();
            throw $e;
        }

        // 3) Ensure every user has API key (new session world + REST)
        $generated = 0;
        try {
            User::query()
                ->where(function ($q) {
                    $q->whereNull('api_key')->orWhere('api_key', '');
                })
                ->each(function (User $user) use (&$generated) {
                    $user->forceFill([
                        'api_key' => User::generateApiKey(),
                        'api_key_created_at' => now(),
                    ])->save();
                    $generated++;
                });
            $steps[] = "[api_key] {$generated} user mendapat API key baru.";
        } catch (Throwable $e) {
            $steps[] = '[api_key] skip: '.$e->getMessage();
        }

        // 4) Session note — PHP native sessions are NOT portable to Laravel
        $steps[] = '[session] Session PHP native tidak dipindahkan. Semua user harus login ulang di Laravel.';
        $steps[] = '[session] Reset password lama (reset_token) diganti tabel password_reset_tokens Laravel.';

        // 5) Mark completed (DB + lock file)
        $note = 'Cutover selesai'.($triggeredBy ? " oleh {$triggeredBy}" : '').' @ '.now()->toDateTimeString();
        try {
            $settings = AppSetting::query()->first();
            if (! $settings) {
                $settings = new AppSetting(['app_name' => 'DigiSign Pro']);
            }
            $settings->legacy_cutover_at = now();
            $settings->legacy_cutover_note = $note;
            $settings->save();
            $steps[] = '[lock] app_settings.legacy_cutover_at diset.';
        } catch (Throwable $e) {
            $steps[] = '[lock] DB flag gagal: '.$e->getMessage().' (tetap tulis file lock)';
        }

        File::ensureDirectoryExists(dirname($this->lockPath()));
        File::put($this->lockPath(), json_encode([
            'completed_at' => now()->toIso8601String(),
            'triggered_by' => $triggeredBy,
            'steps' => $steps,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $steps[] = '[lock] '.$this->lockPath();

        return [
            'completed' => true,
            'already_done' => false,
            'steps' => $steps,
            'message' => "Cutover legacy → Laravel selesai (sekali jalan).\n\n".implode("\n", $steps),
        ];
    }
}
