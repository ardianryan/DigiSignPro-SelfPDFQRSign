<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('app_settings')) {
            return;
        }

        Schema::table('app_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('app_settings', 'legacy_cutover_at')) {
                $table->timestamp('legacy_cutover_at')->nullable()->after('s3_public_url');
            }
            if (! Schema::hasColumn('app_settings', 'legacy_cutover_note')) {
                $table->string('legacy_cutover_note', 500)->nullable()->after('legacy_cutover_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('app_settings')) {
            return;
        }

        Schema::table('app_settings', function (Blueprint $table) {
            if (Schema::hasColumn('app_settings', 'legacy_cutover_note')) {
                $table->dropColumn('legacy_cutover_note');
            }
            if (Schema::hasColumn('app_settings', 'legacy_cutover_at')) {
                $table->dropColumn('legacy_cutover_at');
            }
        });
    }
};
