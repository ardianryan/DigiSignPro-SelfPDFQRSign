<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Idempotent for legacy DigiSign signatures tables.
     * document_name is nullable to accept legacy NULL rows.
     */
    public function up(): void
    {
        if (! Schema::hasTable('signatures')) {
            Schema::create('signatures', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
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

            return;
        }

        Schema::table('signatures', function (Blueprint $table) {
            $add = [
                'batch_id' => fn () => $table->string('batch_id', 50)->nullable(),
                'document_name' => fn () => $table->string('document_name')->nullable(),
                'document_number' => fn () => $table->string('document_number')->nullable(),
                'document_subject' => fn () => $table->text('document_subject')->nullable(),
                'document_attachment' => fn () => $table->string('document_attachment')->nullable(),
                'file_path' => fn () => $table->string('file_path')->nullable(),
                'verify_code' => fn () => $table->string('verify_code', 100),
                'signature_type' => fn () => $table->enum('signature_type', ['digital', 'qr_manual'])->default('digital'),
                'signed_at' => fn () => $table->timestamp('signed_at')->nullable(),
                'created_at' => fn () => $table->timestamp('created_at')->nullable(),
                'updated_at' => fn () => $table->timestamp('updated_at')->nullable(),
            ];

            foreach ($add as $col => $fn) {
                if (! Schema::hasColumn('signatures', $col)) {
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
        Schema::dropIfExists('signatures');
    }
};
