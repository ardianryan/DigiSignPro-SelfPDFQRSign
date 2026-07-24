<?php

use App\Support\LegacyDigisignSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aligns legacy DigiSign (PHP native) databases with the Laravel schema.
 * Safe on fresh installs (no-op if everything already matches).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Core adapt (users / settings / signatures / support tables)
        (new LegacyDigisignSchema)->adapt();

        // Ensure signatures.document_name remains nullable for legacy rows
        if (Schema::hasTable('signatures') && Schema::hasColumn('signatures', 'document_name')) {
            try {
                Schema::table('signatures', function (Blueprint $table) {
                    $table->string('document_name')->nullable()->change();
                });
            } catch (\Throwable $e) {
                // doctrine/dbal may be missing — adapter already filled nulls
            }
        }
    }

    public function down(): void
    {
        // Non-destructive adapt — nothing to roll back safely
    }
};
