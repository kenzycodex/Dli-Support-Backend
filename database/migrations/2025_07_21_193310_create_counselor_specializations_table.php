<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('counselor_specializations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // Counselor/advisor
            $table->unsignedBigInteger('category_id'); // Ticket category
            $table->enum('priority_level', ['primary', 'secondary', 'backup'])->default('primary');
            $table->integer('max_workload')->default(10); // Max tickets they can handle
            $table->integer('current_workload')->default(0); // Current active tickets
            $table->boolean('is_available')->default(true);
            $table->json('availability_schedule')->nullable(); // Working hours, days off
            $table->decimal('expertise_rating', 3, 2)->default(5.00); // 1.00 to 5.00
            $table->text('notes')->nullable(); // Admin notes about specialization
            $table->unsignedBigInteger('assigned_by')->nullable(); // Admin who assigned
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('ticket_categories')->onDelete('cascade');
            $table->foreign('assigned_by')->references('id')->on('users')->onDelete('set null');
            
            // Prevent duplicate specializations
            $table->unique(['user_id', 'category_id']);
            
            // FIXED: Use shorter custom index names
            $table->index(['category_id', 'is_available', 'priority_level'], 'cs_cat_avail_priority_idx');
            $table->index(['user_id', 'is_available'], 'cs_user_available_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('counselor_specializations');
    }
};