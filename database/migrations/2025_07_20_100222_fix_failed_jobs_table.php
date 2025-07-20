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
        // Check if the table exists and add missing columns
        if (Schema::hasTable('failed_jobs')) {
            Schema::table('failed_jobs', function (Blueprint $table) {
                // Add created_at column if it doesn't exist
                if (!Schema::hasColumn('failed_jobs', 'created_at')) {
                    $table->timestamp('created_at')->nullable()->after('failed_at');
                }
                
                // Add updated_at column if it doesn't exist
                if (!Schema::hasColumn('failed_jobs', 'updated_at')) {
                    $table->timestamp('updated_at')->nullable()->after('created_at');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('failed_jobs')) {
            Schema::table('failed_jobs', function (Blueprint $table) {
                if (Schema::hasColumn('failed_jobs', 'created_at')) {
                    $table->dropColumn('created_at');
                }
                if (Schema::hasColumn('failed_jobs', 'updated_at')) {
                    $table->dropColumn('updated_at');
                }
            });
        }
    }
};