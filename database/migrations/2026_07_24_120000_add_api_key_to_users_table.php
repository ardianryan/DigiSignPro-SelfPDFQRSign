<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Models\User;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'api_key')) {
                $table->string('api_key', 64)->nullable()->unique()->after('signature_prefix');
            }
            if (! Schema::hasColumn('users', 'api_key_created_at')) {
                $table->timestamp('api_key_created_at')->nullable()->after('api_key');
            }
        });

        // Generate keys for existing users without one
        if (Schema::hasColumn('users', 'api_key')) {
            User::query()->whereNull('api_key')->each(function (User $user) {
                $user->forceFill([
                    'api_key' => 'digi_'.Str::random(48),
                    'api_key_created_at' => now(),
                ])->save();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'api_key_created_at')) {
                $table->dropColumn('api_key_created_at');
            }
            if (Schema::hasColumn('users', 'api_key')) {
                $table->dropColumn('api_key');
            }
        });
    }
};
