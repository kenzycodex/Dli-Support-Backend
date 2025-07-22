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
            // Check and add columns only if they don't exist
            if (!Schema::hasColumn('tickets', 'category_id')) {
                $table->unsignedBigInteger('category_id')->nullable()->after('description');
            }
            
            if (!Schema::hasColumn('tickets', 'detected_crisis_keywords')) {
                $table->json('detected_crisis_keywords')->nullable()->after('crisis_flag');
            }
            
            if (!Schema::hasColumn('tickets', 'auto_assigned')) {
                $table->enum('auto_assigned', ['yes', 'no', 'manual'])->default('no')->after('assigned_to');
            }
            
            if (!Schema::hasColumn('tickets', 'assigned_at')) {
                $table->timestamp('assigned_at')->nullable()->after('auto_assigned');
            }
            
            if (!Schema::hasColumn('tickets', 'assignment_reason')) {
                $table->text('assignment_reason')->nullable()->after('assigned_at');
            }
            
            if (!Schema::hasColumn('tickets', 'tags')) {
                $table->json('tags')->nullable()->after('crisis_flag');
            }
            
            if (!Schema::hasColumn('tickets', 'closed_at')) {
                $table->timestamp('closed_at')->nullable()->after('resolved_at');
            }
            
            if (!Schema::hasColumn('tickets', 'priority_score')) {
                $table->decimal('priority_score', 5, 2)->default(1.00)->after('priority');
            }
        });

        // Add foreign key and indexes separately to handle existing constraints
        $this->addForeignKeysAndIndexes();
    }

    private function addForeignKeysAndIndexes()
    {
        // Check if foreign key exists before adding
        $foreignKeys = DB::select("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'tickets' 
            AND CONSTRAINT_NAME = 'tickets_category_id_foreign'
        ");

        if (empty($foreignKeys)) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->foreign('category_id', 'tickets_category_id_foreign')
                      ->references('id')
                      ->on('ticket_categories')
                      ->onDelete('set null');
            });
        }

        // Add indexes with shorter names
        $this->addIndexIfNotExists('tickets', ['category_id', 'status', 'created_at'], 'tickets_cat_status_created_idx');
        $this->addIndexIfNotExists('tickets', ['auto_assigned', 'assigned_at'], 'tickets_auto_assigned_idx');
        $this->addIndexIfNotExists('tickets', ['priority_score'], 'tickets_priority_score_idx');
    }

    private function addIndexIfNotExists($table, $columns, $name)
    {
        $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$name]);
        
        if (empty($indexes)) {
            Schema::table($table, function (Blueprint $tableSchema) use ($columns, $name) {
                $tableSchema->index($columns, $name);
            });
        }
    }

    public function down()
    {
        Schema::table('tickets', function (Blueprint $table) {
            // Drop foreign key if exists
            try {
                $table->dropForeign('tickets_category_id_foreign');
            } catch (Exception $e) {
                // Foreign key doesn't exist, continue
            }

            // Drop indexes if they exist
            $this->dropIndexIfExists('tickets', 'tickets_cat_status_created_idx');
            $this->dropIndexIfExists('tickets', 'tickets_auto_assigned_idx');
            $this->dropIndexIfExists('tickets', 'tickets_priority_score_idx');

            // Drop columns if they exist
            $columnsToCheck = [
                'category_id',
                'detected_crisis_keywords',
                'auto_assigned',
                'assigned_at',
                'assignment_reason',
                'tags',
                'closed_at',
                'priority_score'
            ];

            foreach ($columnsToCheck as $column) {
                if (Schema::hasColumn('tickets', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function dropIndexIfExists($table, $indexName)
    {
        $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
        
        if (!empty($indexes)) {
            Schema::table($table, function (Blueprint $tableSchema) use ($indexName) {
                $tableSchema->dropIndex($indexName);
            });
        }
    }
};