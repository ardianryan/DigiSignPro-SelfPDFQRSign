<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
