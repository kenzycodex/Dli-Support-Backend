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
        Schema::create('accessibility_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('faq_id');
            $table->unsignedBigInteger('user_id');
            $table->string('type', 100); // 'screen_reader', 'keyboard_navigation', 'color_contrast', etc.
            $table->text('description');
            $table->string('user_agent', 500);
            $table->enum('status', ['pending', 'in_progress', 'resolved', 'dismissed'])->default('pending');
            $table->text('admin_notes')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            
            // Foreign key constraints
            $table->foreign('faq_id')->references('id')->on('faqs')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('resolved_by')->references('id')->on('users')->onDelete('set null');
            
            // Indexes
            $table->index(['faq_id', 'status']);
            $table->index(['user_id']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accessibility_reports');
    }
};