<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('crisis_keywords', function (Blueprint $table) {
            $table->id();
            $table->string('keyword'); // suicide, self-harm, emergency, etc.
            $table->enum('severity_level', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->unsignedBigInteger('category_id')->nullable(); // Specific to category or global
            $table->boolean('is_active')->default(true);
            $table->boolean('exact_match')->default(false); // Exact word match vs partial
            $table->boolean('case_sensitive')->default(false);
            $table->integer('trigger_count')->default(0); // How many times it's been triggered
            $table->text('response_action')->nullable(); // What to do when triggered
            $table->json('notification_rules')->nullable(); // Who to notify
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('ticket_categories')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            
            // FIXED: Use shorter custom index names
            $table->index(['is_active', 'severity_level'], 'ck_active_severity_idx');
            $table->index(['category_id', 'is_active'], 'ck_cat_active_idx');
            $table->index('keyword', 'ck_keyword_idx'); // For fast keyword matching
        });
    }

    public function down()
    {
        Schema::dropIfExists('crisis_keywords');
    }
};