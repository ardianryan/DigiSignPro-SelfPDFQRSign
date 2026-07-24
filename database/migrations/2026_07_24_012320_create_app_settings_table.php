<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Idempotent for legacy DigiSign app_settings tables.
     */
    public function up(): void
    {
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

            return;
        }

        Schema::table('app_settings', function (Blueprint $table) {
            $add = [
                'app_name' => fn () => $table->string('app_name')->default('DigiSign Pro'),
                'app_logo' => fn () => $table->string('app_logo')->nullable(),
                'maintenance_mode' => fn () => $table->boolean('maintenance_mode')->default(false),
                'registration_open' => fn () => $table->boolean('registration_open')->default(true),
                'max_upload_size' => fn () => $table->integer('max_upload_size')->default(10485760),
                'max_upload_size_bulk' => fn () => $table->integer('max_upload_size_bulk')->default(52428800),
                'max_prefix_length' => fn () => $table->integer('max_prefix_length')->default(3),
                'timezone' => fn () => $table->string('timezone', 64)->default('Asia/Jakarta'),
                'storage_mode' => fn () => $table->enum('storage_mode', ['local', 's3', 'both'])->default('local'),
                's3_endpoint' => fn () => $table->string('s3_endpoint')->nullable(),
                's3_region' => fn () => $table->string('s3_region', 50)->default('us-east-1'),
                's3_bucket' => fn () => $table->string('s3_bucket')->nullable(),
                's3_access_key' => fn () => $table->string('s3_access_key')->nullable(),
                's3_secret_key' => fn () => $table->string('s3_secret_key')->nullable(),
                's3_directory' => fn () => $table->string('s3_directory')->default('digisign/'),
                's3_public_url' => fn () => $table->string('s3_public_url')->nullable(),
                'created_at' => fn () => $table->timestamp('created_at')->nullable(),
                'updated_at' => fn () => $table->timestamp('updated_at')->nullable(),
            ];

            foreach ($add as $col => $fn) {
                if (! Schema::hasColumn('app_settings', $col)) {
                    $fn();
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
