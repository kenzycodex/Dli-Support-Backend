<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('ticket_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // Mental Health, Academic Support, etc.
            $table->string('slug')->unique(); // mental-health, academic-support
            $table->text('description')->nullable();
            $table->string('icon')->default('MessageSquare'); // Lucide icon name
            $table->string('color')->default('#3B82F6'); // Hex color code
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_assign')->default(true); // Enable auto-assignment
            $table->boolean('crisis_detection_enabled')->default(false);
            $table->integer('sla_response_hours')->default(24); // SLA response time
            $table->integer('max_priority_level')->default(3); // 1=Low, 2=Medium, 3=High, 4=Urgent
            $table->json('notification_settings')->nullable(); // Email, SMS preferences
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('ticket_categories');
    }
};