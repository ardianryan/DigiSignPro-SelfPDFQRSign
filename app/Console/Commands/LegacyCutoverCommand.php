<?php

namespace App\Console\Commands;

use App\Support\LegacyCutover;
use Illuminate\Console\Command;
use Throwable;

/**
 * One-time cutover from DigiSign PHP-native DB → Laravel.
 * Preferred "outside" entrypoint for ops/CI first deploy after switch.
 *
 *   php artisan digisign:legacy-cutover
 *   php artisan digisign:legacy-cutover --force   # re-run (ops only)
 */
class LegacyCutoverCommand extends Command
{
    protected $signature = 'digisign:legacy-cutover
                            {--force : Jalankan ulang meski cutover sudah pernah selesai}
                            {--dry-run : Hanya tampilkan status tanpa mengeksekusi}';

    protected $description = 'One-time cutover skema/data DigiSign legacy ke Laravel (bukan update harian)';

    public function handle(LegacyCutover $cutover): int
    {
        $this->info('=== DigiSign Legacy → Laravel Cutover ===');
        $this->line('Deploy kode harian: CI/CD. Migrasi harian: Admin → Migrasi Database.');
        $this->newLine();

        if ($cutover->isCompleted() && ! $this->option('force')) {
            $this->warn('Cutover sudah selesai. Tidak dijalankan ulang.');
            $this->line('Lock: '.$cutover->lockPath());
            $this->line('Gunakan --force hanya jika Anda yakin (ops).');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->table(['Check', 'Value'], [
                ['completed', $cutover->isCompleted() ? 'yes' : 'no'],
                ['looks_legacy', $cutover->looksLegacy() ? 'yes' : 'no'],
                ['should_offer', $cutover->shouldOffer() ? 'yes' : 'no'],
                ['lock', $cutover->lockPath()],
            ]);

            return self::SUCCESS;
        }

        if ($cutover->looksLegacy()) {
            $this->warn('DB terdeteksi mirip DigiSign legacy (kolom lama / belum lengkap Laravel).');
        }

        if (! $this->option('force') && ! $this->confirm('Lanjutkan cutover sekali jalan? User harus login ulang setelah ini.', true)) {
            $this->info('Dibatalkan.');

            return self::SUCCESS;
        }

        try {
            $result = $cutover->run(
                force: (bool) $this->option('force'),
                triggeredBy: 'cli:'.(get_current_user() ?: 'artisan')
            );

            foreach ($result['steps'] as $step) {
                $this->line('  · '.$step);
            }

            $this->newLine();
            $this->info($result['already_done'] ? $result['message'] : 'Cutover BERHASIL.');
            $this->comment('Session legacy tidak dipindah — minta semua user login ulang.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Cutover gagal: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
