<?php

namespace App\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Brings a DigiSign PHP-native (legacy) database up to the Laravel app schema.
 * Safe to run repeatedly (idempotent): only adds missing tables/columns.
 */
class LegacyDigisignSchema
{
    /**
     * @return list<string> Human-readable steps applied
     */
    public function adapt(): array
    {
        $steps = [];

        $steps = array_merge($steps, $this->ensureUsers());
        $steps = array_merge($steps, $this->ensureAppSettings());
        $steps = array_merge($steps, $this->ensureSignatures());
        $steps = array_merge($steps, $this->ensurePasswordResetTokens());
        $steps = array_merge($steps, $this->ensureSessions());
        $steps = array_merge($steps, $this->ensureCacheTables());
        $steps = array_merge($steps, $this->ensureJobsTables());

        if ($steps === []) {
            $steps[] = 'Struktur database sudah selaras (tidak ada perubahan).';
        }

        return $steps;
    }

    private function ensureUsers(): array
    {
        $steps = [];

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->enum('role', ['admin', 'user'])->default('user');
                $table->string('position')->nullable();
                $table->string('signature_path')->nullable();
                $table->string('signature_prefix', 9)->default('DS');
                $table->rememberToken();
                $table->timestamps();
            });
            $steps[] = "Tabel 'users' dibuat (skema Laravel).";

            return $steps;
        }

        Schema::table('users', function (Blueprint $table) use (&$steps) {
            if (! Schema::hasColumn('users', 'email_verified_at')) {
                $table->timestamp('email_verified_at')->nullable()->after('email');
                $steps[] = 'Kolom users.email_verified_at ditambahkan.';
            }
            if (! Schema::hasColumn('users', 'remember_token')) {
                $table->rememberToken();
                $steps[] = 'Kolom users.remember_token ditambahkan.';
            }
            if (! Schema::hasColumn('users', 'role')) {
                $table->enum('role', ['admin', 'user'])->default('user');
                $steps[] = 'Kolom users.role ditambahkan.';
            }
            if (! Schema::hasColumn('users', 'position')) {
                $table->string('position')->nullable();
                $steps[] = 'Kolom users.position ditambahkan.';
            }
            if (! Schema::hasColumn('users', 'signature_path')) {
                $table->string('signature_path')->nullable();
                $steps[] = 'Kolom users.signature_path ditambahkan.';
            }
            if (! Schema::hasColumn('users', 'signature_prefix')) {
                $table->string('signature_prefix', 9)->default('DS');
                $steps[] = 'Kolom users.signature_prefix ditambahkan.';
            }
            if (! Schema::hasColumn('users', 'created_at')) {
                $table->timestamp('created_at')->nullable();
                $steps[] = 'Kolom users.created_at ditambahkan.';
            }
            if (! Schema::hasColumn('users', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
                $steps[] = 'Kolom users.updated_at ditambahkan.';
            }
            if (! Schema::hasColumn('users', 'api_key')) {
                $table->string('api_key', 64)->nullable()->unique();
                $steps[] = 'Kolom users.api_key ditambahkan.';
            }
            if (! Schema::hasColumn('users', 'api_key_created_at')) {
                $table->timestamp('api_key_created_at')->nullable();
                $steps[] = 'Kolom users.api_key_created_at ditambahkan.';
            }
        });

        // Legacy reset_token columns may remain; Laravel uses password_reset_tokens instead.
        // Backfill updated_at for old rows
        try {
            if (Schema::hasColumn('users', 'updated_at')) {
                DB::table('users')->whereNull('updated_at')->update([
                    'updated_at' => DB::raw('COALESCE(created_at, CURRENT_TIMESTAMP)'),
                ]);
            }
        } catch (Throwable) {
            // ignore driver differences
        }

        return $steps;
    }

    private function ensureAppSettings(): array
    {
        $steps = [];

        if (! Schema::hasTable('app_settings')) {
            Schema::create('app_settings', function (Blueprint $table) {
                $table->id();
                $table->string('app_name')->default('DigiSign Pro');
                $table->string('app_logo')->nullable();
                $table->boolean('maintenance_mode')->default(false);
                $table->boolean('registration_open')->default(true);
                $table->integer('max_upload_size')->default(10485760);
                $table->integer('max_upload_size_bulk')->default(52428800);
                $table->integer('max_prefix_length')->default(3);
                $table->string('timezone', 64)->default('Asia/Jakarta');
                $table->enum('storage_mode', ['local', 's3', 'both'])->default('local');
                $table->string('s3_endpoint')->nullable();
                $table->string('s3_region', 50)->default('us-east-1');
                $table->string('s3_bucket')->nullable();
                $table->string('s3_access_key')->nullable();
                $table->string('s3_secret_key')->nullable();
                $table->string('s3_directory')->default('digisign/');
                $table->string('s3_public_url')->nullable();
                $table->timestamps();
            });
            $steps[] = "Tabel 'app_settings' dibuat.";

            return $steps;
        }

        $columns = [
            'app_name' => fn (Blueprint $t) => $t->string('app_name')->default('DigiSign Pro'),
            'app_logo' => fn (Blueprint $t) => $t->string('app_logo')->nullable(),
            'maintenance_mode' => fn (Blueprint $t) => $t->boolean('maintenance_mode')->default(false),
            'registration_open' => fn (Blueprint $t) => $t->boolean('registration_open')->default(true),
            'max_upload_size' => fn (Blueprint $t) => $t->integer('max_upload_size')->default(10485760),
            'max_upload_size_bulk' => fn (Blueprint $t) => $t->integer('max_upload_size_bulk')->default(52428800),
            'max_prefix_length' => fn (Blueprint $t) => $t->integer('max_prefix_length')->default(3),
            'timezone' => fn (Blueprint $t) => $t->string('timezone', 64)->default('Asia/Jakarta'),
            'storage_mode' => fn (Blueprint $t) => $t->enum('storage_mode', ['local', 's3', 'both'])->default('local'),
            's3_endpoint' => fn (Blueprint $t) => $t->string('s3_endpoint')->nullable(),
            's3_region' => fn (Blueprint $t) => $t->string('s3_region', 50)->default('us-east-1'),
            's3_bucket' => fn (Blueprint $t) => $t->string('s3_bucket')->nullable(),
            's3_access_key' => fn (Blueprint $t) => $t->string('s3_access_key')->nullable(),
            's3_secret_key' => fn (Blueprint $t) => $t->string('s3_secret_key')->nullable(),
            's3_directory' => fn (Blueprint $t) => $t->string('s3_directory')->default('digisign/'),
            's3_public_url' => fn (Blueprint $t) => $t->string('s3_public_url')->nullable(),
            'legacy_cutover_at' => fn (Blueprint $t) => $t->timestamp('legacy_cutover_at')->nullable(),
            'legacy_cutover_note' => fn (Blueprint $t) => $t->string('legacy_cutover_note', 500)->nullable(),
            'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
            'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
        ];

        foreach ($columns as $name => $adder) {
            if (! Schema::hasColumn('app_settings', $name)) {
                Schema::table('app_settings', function (Blueprint $table) use ($adder, $name, &$steps) {
                    $adder($table);
                    $steps[] = "Kolom app_settings.{$name} ditambahkan.";
                });
            }
        }

        return $steps;
    }

    private function ensureSignatures(): array
    {
        $steps = [];

        if (! Schema::hasTable('signatures')) {
            Schema::create('signatures', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('batch_id', 50)->nullable();
                $table->string('document_name')->nullable();
                $table->string('document_number')->nullable();
                $table->text('document_subject')->nullable();
                $table->string('document_attachment')->nullable();
                $table->string('file_path')->nullable();
                $table->string('verify_code', 100)->unique();
                $table->enum('signature_type', ['digital', 'qr_manual'])->default('digital');
                $table->timestamp('signed_at')->useCurrent();
                $table->timestamps();
            });
            $steps[] = "Tabel 'signatures' dibuat.";

            return $steps;
        }

        $columns = [
            'batch_id' => fn (Blueprint $t) => $t->string('batch_id', 50)->nullable(),
            'document_name' => fn (Blueprint $t) => $t->string('document_name')->nullable(),
            'document_number' => fn (Blueprint $t) => $t->string('document_number')->nullable(),
            'document_subject' => fn (Blueprint $t) => $t->text('document_subject')->nullable(),
            'document_attachment' => fn (Blueprint $t) => $t->string('document_attachment')->nullable(),
            'file_path' => fn (Blueprint $t) => $t->string('file_path')->nullable(),
            'verify_code' => fn (Blueprint $t) => $t->string('verify_code', 100),
            'signature_type' => fn (Blueprint $t) => $t->enum('signature_type', ['digital', 'qr_manual'])->default('digital'),
            'signed_at' => fn (Blueprint $t) => $t->timestamp('signed_at')->nullable(),
            'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
            'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
        ];

        foreach ($columns as $name => $adder) {
            if (! Schema::hasColumn('signatures', $name)) {
                Schema::table('signatures', function (Blueprint $table) use ($adder, $name, &$steps) {
                    $adder($table);
                    $steps[] = "Kolom signatures.{$name} ditambahkan.";
                });
            }
        }

        // Legacy rows may have NULL document_name; fill so app UI never breaks
        try {
            $rows = DB::table('signatures')
                ->where(function ($q) {
                    $q->whereNull('document_name')->orWhere('document_name', '');
                })
                ->get(['id', 'document_name', 'document_subject', 'document_number']);

            foreach ($rows as $row) {
                $name = $row->document_name
                    ?: ($row->document_subject ?: ($row->document_number ?: 'Dokumen'));
                DB::table('signatures')->where('id', $row->id)->update(['document_name' => $name]);
            }

            if ($rows->count() > 0) {
                $steps[] = "Nilai document_name kosong diisi fallback ({$rows->count()} baris).";
            }
        } catch (Throwable) {
            // ignore
        }

        return $steps;
    }

    private function ensurePasswordResetTokens(): array
    {
        if (Schema::hasTable('password_reset_tokens')) {
            return [];
        }

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        return ["Tabel 'password_reset_tokens' dibuat (pengganti reset_token di users lama)."];
    }

    private function ensureSessions(): array
    {
        if (Schema::hasTable('sessions')) {
            return [];
        }

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        return ["Tabel 'sessions' dibuat."];
    }

    private function ensureCacheTables(): array
    {
        $steps = [];

        if (! Schema::hasTable('cache')) {
            Schema::create('cache', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->integer('expiration');
            });
            $steps[] = "Tabel 'cache' dibuat.";
        }

        if (! Schema::hasTable('cache_locks')) {
            Schema::create('cache_locks', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->string('owner');
                $table->integer('expiration');
            });
            $steps[] = "Tabel 'cache_locks' dibuat.";
        }

        return $steps;
    }

    private function ensureJobsTables(): array
    {
        $steps = [];

        if (! Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table) {
                $table->id();
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
            $steps[] = "Tabel 'jobs' dibuat.";
        }

        if (! Schema::hasTable('job_batches')) {
            Schema::create('job_batches', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('name');
                $table->integer('total_jobs');
                $table->integer('pending_jobs');
                $table->integer('failed_jobs');
                $table->longText('failed_job_ids');
                $table->mediumText('options')->nullable();
                $table->integer('cancelled_at')->nullable();
                $table->integer('created_at');
                $table->integer('finished_at')->nullable();
            });
            $steps[] = "Tabel 'job_batches' dibuat.";
        }

        if (! Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('uuid')->unique();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
            $steps[] = "Tabel 'failed_jobs' dibuat.";
        }

        return $steps;
    }
}
