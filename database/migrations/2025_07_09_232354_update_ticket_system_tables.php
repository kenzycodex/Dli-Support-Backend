<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Update or create tickets table
        if (!Schema::hasTable('tickets')) {
            Schema::create('tickets', function (Blueprint $table) {
                $table->id();
                $table->string('ticket_number')->unique();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->string('subject');
                $table->text('description');
                $table->enum('category', ['general', 'academic', 'mental-health', 'crisis', 'technical', 'other']);
                $table->enum('priority', ['Low', 'Medium', 'High', 'Urgent'])->default('Medium');
                $table->enum('status', ['Open', 'In Progress', 'Resolved', 'Closed'])->default('Open');
                $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
                $table->boolean('crisis_flag')->default(false);
                $table->json('tags')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamps();

                // Indexes for performance
                $table->index(['user_id', 'status', 'created_at']);
                $table->index(['assigned_to', 'status']);
                $table->index(['category', 'status']);
                $table->index(['priority', 'crisis_flag']);
                $table->index(['status', 'updated_at']);
                $table->index(['crisis_flag']);
            });
        } else {
            // Update existing tickets table
            Schema::table('tickets', function (Blueprint $table) {
                // Add missing columns if they don't exist
                if (!Schema::hasColumn('tickets', 'tags')) {
                    $table->json('tags')->nullable()->after('crisis_flag');
                }
                
                if (!Schema::hasColumn('tickets', 'closed_at')) {
                    $table->timestamp('closed_at')->nullable()->after('resolved_at');
                }
            });
            
            // Update priority enum to include 'Urgent' if not present
            try {
                DB::statement("ALTER TABLE tickets MODIFY COLUMN priority ENUM('Low', 'Medium', 'High', 'Urgent') DEFAULT 'Medium'");
            } catch (\Exception $e) {
                \Log::info('Priority column update skipped: ' . $e->getMessage());
            }
            
            // Update category enum to include all required options
            try {
                DB::statement("ALTER TABLE tickets MODIFY COLUMN category ENUM('general', 'academic', 'mental-health', 'crisis', 'technical', 'other') NOT NULL");
            } catch (\Exception $e) {
                \Log::info('Category column update skipped: ' . $e->getMessage());
            }
            
            // Add indexes - using try/catch to handle existing indexes
            try {
                DB::statement('CREATE INDEX IF NOT EXISTS tickets_category_status_index ON tickets (category, status)');
            } catch (\Exception $e) {
                \Log::info('Index tickets_category_status_index already exists or failed: ' . $e->getMessage());
            }
            
            try {
                DB::statement('CREATE INDEX IF NOT EXISTS tickets_priority_crisis_flag_index ON tickets (priority, crisis_flag)');
            } catch (\Exception $e) {
                \Log::info('Index tickets_priority_crisis_flag_index already exists or failed: ' . $e->getMessage());
            }
            
            try {
                DB::statement('CREATE INDEX IF NOT EXISTS tickets_crisis_flag_index ON tickets (crisis_flag)');
            } catch (\Exception $e) {
                \Log::info('Index tickets_crisis_flag_index already exists or failed: ' . $e->getMessage());
            }
        }

        // Update or create ticket_responses table
        if (!Schema::hasTable('ticket_responses')) {
            Schema::create('ticket_responses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ticket_id')->constrained()->onDelete('cascade');
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->text('message');
                $table->boolean('is_internal')->default(false);
                $table->string('visibility')->default('all'); // all, counselors, admins
                $table->boolean('is_urgent')->default(false);
                $table->timestamps();

                $table->index(['ticket_id', 'created_at']);
                $table->index(['user_id', 'created_at']);
                $table->index(['is_internal']);
            });
        } else {
            // Update existing ticket_responses table
            Schema::table('ticket_responses', function (Blueprint $table) {
                // Add missing columns if they don't exist
                if (!Schema::hasColumn('ticket_responses', 'visibility')) {
                    $table->string('visibility')->default('all')->after('is_internal');
                }
                
                if (!Schema::hasColumn('ticket_responses', 'is_urgent')) {
                    $table->boolean('is_urgent')->default(false)->after('visibility');
                }
            });
            
            // Add missing index
            try {
                DB::statement('CREATE INDEX IF NOT EXISTS ticket_responses_is_internal_index ON ticket_responses (is_internal)');
            } catch (\Exception $e) {
                \Log::info('Index ticket_responses_is_internal_index already exists or failed: ' . $e->getMessage());
            }
        }

        // Update or create ticket_attachments table
        if (!Schema::hasTable('ticket_attachments')) {
            Schema::create('ticket_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ticket_id')->constrained()->onDelete('cascade');
                $table->foreignId('response_id')->nullable()->constrained('ticket_responses')->onDelete('cascade');
                $table->string('original_name');
                $table->string('file_path');
                $table->string('file_type');
                $table->integer('file_size'); // in bytes
                $table->timestamps();

                $table->index(['ticket_id']);
                $table->index(['response_id']);
            });
        } else {
            // Add missing indexes for ticket_attachments
            try {
                DB::statement('CREATE INDEX IF NOT EXISTS ticket_attachments_ticket_id_index ON ticket_attachments (ticket_id)');
            } catch (\Exception $e) {
                \Log::info('Index ticket_attachments_ticket_id_index already exists or failed: ' . $e->getMessage());
            }
            
            try {
                DB::statement('CREATE INDEX IF NOT EXISTS ticket_attachments_response_id_index ON ticket_attachments (response_id)');
            } catch (\Exception $e) {
                \Log::info('Index ticket_attachments_response_id_index already exists or failed: ' . $e->getMessage());
            }
        }
    }

    public function down()
    {
        // Only remove columns/indexes added by this migration
        if (Schema::hasTable('tickets')) {
            Schema::table('tickets', function (Blueprint $table) {
                // Remove columns added by this migration
                if (Schema::hasColumn('tickets', 'tags')) {
                    $table->dropColumn('tags');
                }
                
                if (Schema::hasColumn('tickets', 'closed_at')) {
                    $table->dropColumn('closed_at');
                }
            });
            
            // Drop indexes we added (ignore errors if they don't exist)
            try {
                DB::statement('DROP INDEX IF EXISTS tickets_category_status_index');
                DB::statement('DROP INDEX IF EXISTS tickets_priority_crisis_flag_index');
                DB::statement('DROP INDEX IF EXISTS tickets_crisis_flag_index');
            } catch (\Exception $e) {
                \Log::info('Index cleanup failed (might not exist): ' . $e->getMessage());
            }
        }
        
        if (Schema::hasTable('ticket_responses')) {
            Schema::table('ticket_responses', function (Blueprint $table) {
                if (Schema::hasColumn('ticket_responses', 'visibility')) {
                    $table->dropColumn('visibility');
                }
                
                if (Schema::hasColumn('ticket_responses', 'is_urgent')) {
                    $table->dropColumn('is_urgent');
                }
            });
        }
    }
};