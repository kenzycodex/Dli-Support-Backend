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
        // Add email tracking columns to users table
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'welcome_email_sent_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp('welcome_email_sent_at')->nullable()->after('email_verified_at');
                $table->timestamp('last_email_sent_at')->nullable()->after('welcome_email_sent_at');
                $table->timestamp('password_reset_email_sent_at')->nullable()->after('last_email_sent_at');
                $table->integer('total_emails_sent')->default(0)->after('password_reset_email_sent_at');
                
                // Add indexes for performance
                $table->index('welcome_email_sent_at');
                $table->index('last_email_sent_at');
            });
        }

        // Create failed emails tracking table
        if (!Schema::hasTable('failed_emails')) {
            Schema::create('failed_emails', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->string('email');
                $table->enum('type', ['welcome', 'password_reset', 'bulk_welcome', 'status_change', 'role_change', 'notification'])->default('welcome');
                $table->text('error')->nullable();
                $table->json('context')->nullable();
                $table->timestamp('failed_at');
                $table->timestamp('retry_at')->nullable();
                $table->integer('retry_count')->default(0);
                $table->enum('status', ['failed', 'retrying', 'permanently_failed'])->default('failed');
                $table->timestamps();

                $table->index(['user_id', 'type']);
                $table->index('failed_at');
                $table->index('status');
                $table->index('retry_at');
            });
        }

        // Create email analytics table - FIX: Add the missing table
        if (!Schema::hasTable('email_analytics')) {
            Schema::create('email_analytics', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->string('email_type'); // welcome, password_reset, notification, etc.
                $table->string('subject');
                $table->timestamp('sent_at');
                $table->timestamp('opened_at')->nullable();
                $table->timestamp('clicked_at')->nullable();
                $table->integer('open_count')->default(0);
                $table->integer('click_count')->default(0);
                $table->string('email_service')->nullable(); // smtp, sendgrid, mailgun, etc.
                $table->string('template_used')->nullable();
                $table->json('metadata')->nullable(); // Additional tracking data
                $table->timestamps();

                // Indexes
                $table->index(['user_id', 'email_type']);
                $table->index('sent_at');
                $table->index('opened_at');
                $table->index('email_type');
            });
        }

        // Create bulk operations tracking table
        if (!Schema::hasTable('bulk_operations')) {
            Schema::create('bulk_operations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('admin_user_id')->constrained('users')->onDelete('cascade');
                $table->enum('operation_type', ['bulk_create', 'bulk_update', 'bulk_delete', 'csv_import'])->default('bulk_create');
                $table->integer('total_records')->default(0);
                $table->integer('successful_records')->default(0);
                $table->integer('failed_records')->default(0);
                $table->integer('skipped_records')->default(0);
                $table->integer('emails_sent')->default(0);
                $table->integer('emails_failed')->default(0);
                $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled'])->default('pending');
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->text('error_message')->nullable();
                $table->json('operation_data')->nullable();
                $table->json('results_summary')->nullable();
                $table->string('source_file')->nullable();
                $table->timestamps();

                $table->index(['admin_user_id', 'operation_type']);
                $table->index('status');
                $table->index('started_at');
                $table->index('completed_at');
            });
        }

        // Create email templates table for customization
        if (!Schema::hasTable('email_templates')) {
            Schema::create('email_templates', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique(); // welcome_user, password_reset, etc.
                $table->string('subject');
                $table->text('html_content');
                $table->text('text_content')->nullable();
                $table->json('variables')->nullable(); // Available template variables
                $table->boolean('is_active')->default(true);
                $table->string('language', 10)->default('en');
                $table->timestamps();

                // Indexes
                $table->index('name');
                $table->index('is_active');
                $table->index('language');
            });
        }

        // Create email preferences table for users
        if (!Schema::hasTable('user_email_preferences')) {
            Schema::create('user_email_preferences', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->boolean('receive_welcome_emails')->default(true);
                $table->boolean('receive_password_reset_emails')->default(true);
                $table->boolean('receive_status_change_emails')->default(true);
                $table->boolean('receive_role_change_emails')->default(true);
                $table->boolean('receive_system_notifications')->default(true);
                $table->boolean('receive_marketing_emails')->default(false);
                $table->string('preferred_language', 10)->default('en');
                $table->enum('email_frequency', ['immediate', 'daily', 'weekly', 'never'])->default('immediate');
                $table->timestamps();

                // Indexes
                $table->unique('user_id');
                $table->index('email_frequency');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_email_preferences');
        Schema::dropIfExists('email_templates');
        Schema::dropIfExists('bulk_operations');
        Schema::dropIfExists('email_analytics');
        Schema::dropIfExists('failed_emails');

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (Schema::hasColumn('users', 'welcome_email_sent_at')) {
                    $table->dropIndex(['welcome_email_sent_at']);
                }
                if (Schema::hasColumn('users', 'last_email_sent_at')) {
                    $table->dropIndex(['last_email_sent_at']);
                }
                
                $table->dropColumn([
                    'welcome_email_sent_at',
                    'last_email_sent_at', 
                    'password_reset_email_sent_at',
                    'total_emails_sent'
                ]);
            });
        }
    }
};