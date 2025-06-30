<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::table('tickets', function (Blueprint $table) {
            // Add new fields that were added to the enhanced model
            if (!Schema::hasColumn('tickets', 'tags')) {
                $table->json('tags')->nullable()->after('crisis_flag');
            }
            
            if (!Schema::hasColumn('tickets', 'closed_at')) {
                $table->timestamp('closed_at')->nullable()->after('resolved_at');
            }
        });

        // Fix the priority column to include 'Urgent' - do this with raw SQL to avoid enum issues
        DB::statement("ALTER TABLE tickets MODIFY COLUMN priority ENUM('Low', 'Medium', 'High', 'Urgent') DEFAULT 'Medium'");
        
        // Fix the category column to include all new categories
        DB::statement("ALTER TABLE tickets MODIFY COLUMN category ENUM('general', 'academic', 'mental-health', 'crisis', 'technical', 'administrative', 'other') NOT NULL");

        Schema::table('tickets', function (Blueprint $table) {
            // Add indexes for better performance
            if (!Schema::hasIndex('tickets', ['category', 'status'])) {
                $table->index(['category', 'status'], 'idx_tickets_category_status');
            }
            
            if (!Schema::hasIndex('tickets', ['priority', 'crisis_flag'])) {
                $table->index(['priority', 'crisis_flag'], 'idx_tickets_priority_crisis');
            }
            
            if (!Schema::hasIndex('tickets', ['assigned_to', 'status', 'updated_at'])) {
                $table->index(['assigned_to', 'status', 'updated_at'], 'idx_tickets_assigned_status_updated');
            }
        });
        
        // Update existing data to ensure consistency
        DB::statement("UPDATE tickets SET tags = '[]' WHERE tags IS NULL");
    }

    public function down()
    {
        Schema::table('tickets', function (Blueprint $table) {
            // Remove added columns
            if (Schema::hasColumn('tickets', 'tags')) {
                $table->dropColumn('tags');
            }
            
            if (Schema::hasColumn('tickets', 'closed_at')) {
                $table->dropColumn('closed_at');
            }
            
            // Drop added indexes (check if they exist first)
            $indexes = Schema::getConnection()->getDoctrineSchemaManager()->listTableIndexes('tickets');
            
            if (isset($indexes['idx_tickets_category_status'])) {
                $table->dropIndex('idx_tickets_category_status');
            }
            if (isset($indexes['idx_tickets_priority_crisis'])) {
                $table->dropIndex('idx_tickets_priority_crisis');
            }
            if (isset($indexes['idx_tickets_assigned_status_updated'])) {
                $table->dropIndex('idx_tickets_assigned_status_updated');
            }
        });

        // Revert priority column to original values
        DB::statement("ALTER TABLE tickets MODIFY COLUMN priority ENUM('Low', 'Medium', 'High') DEFAULT 'Medium'");
        
        // Revert category column to original values  
        DB::statement("ALTER TABLE tickets MODIFY COLUMN category ENUM('technical', 'academic', 'mental-health', 'administrative', 'other') NOT NULL");
    }
};