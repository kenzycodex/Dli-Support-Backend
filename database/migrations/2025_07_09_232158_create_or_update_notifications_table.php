<?php
// This migration will create the notifications table if it doesn't exist, 
// or update it if it's missing required fields

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('notifications')) {
            // Create notifications table if it doesn't exist
            Schema::create('notifications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->string('type');
                $table->string('title');
                $table->text('message');
                $table->string('priority')->default('medium');
                $table->boolean('read')->default(false);
                $table->json('data')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
                
                // Indexes for better performance
                $table->index(['user_id', 'read']);
                $table->index(['user_id', 'type']);
                $table->index(['user_id', 'priority']);
                $table->index(['created_at']);
            });
        } else {
            // Update existing notifications table
            Schema::table('notifications', function (Blueprint $table) {
                // Check and add missing columns
                if (!Schema::hasColumn('notifications', 'type')) {
                    $table->string('type')->after('user_id');
                }
                
                if (!Schema::hasColumn('notifications', 'title')) {
                    $table->string('title')->after('type');
                }
                
                if (!Schema::hasColumn('notifications', 'message')) {
                    $table->text('message')->after('title');
                }
                
                if (!Schema::hasColumn('notifications', 'priority')) {
                    $table->string('priority')->default('medium')->after('message');
                }
                
                if (!Schema::hasColumn('notifications', 'read')) {
                    $table->boolean('read')->default(false)->after('priority');
                }
                
                if (!Schema::hasColumn('notifications', 'data')) {
                    $table->json('data')->nullable()->after('read');
                }
                
                if (!Schema::hasColumn('notifications', 'read_at')) {
                    $table->timestamp('read_at')->nullable()->after('data');
                }
                
                // Add indexes if they don't exist
                if (!Schema::hasIndex('notifications', ['user_id', 'read'])) {
                    $table->index(['user_id', 'read']);
                }
                
                if (!Schema::hasIndex('notifications', ['user_id', 'type'])) {
                    $table->index(['user_id', 'type']);
                }
                
                if (!Schema::hasIndex('notifications', ['user_id', 'priority'])) {
                    $table->index(['user_id', 'priority']);
                }
                
                if (!Schema::hasIndex('notifications', ['created_at'])) {
                    $table->index(['created_at']);
                }
            });
        }
    }

    public function down()
    {
        // Drop the entire table (be careful with this in production)
        Schema::dropIfExists('notifications');
    }
};