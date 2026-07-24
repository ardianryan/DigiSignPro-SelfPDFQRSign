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
        Schema::create('signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('batch_id', 50)->nullable();
            $table->string('document_name');
            $table->string('document_number')->nullable();
            $table->text('document_subject')->nullable();
            $table->string('document_attachment')->nullable();
            $table->string('file_path')->nullable();
            $table->string('verify_code', 100)->unique();
            $table->enum('signature_type', ['digital', 'qr_manual'])->default('digital');
            $table->timestamp('signed_at')->useCurrent();
            $table->timestamps();
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
