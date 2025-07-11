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
        Schema::create('help_analytics', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50); // 'search', 'category_click', 'faq_view', etc.
            $table->string('reference_id', 255); // search query, category slug, faq id, etc.
            $table->date('date'); // date for aggregation
            $table->integer('count')->default(1); // occurrence count
            $table->json('metadata')->nullable(); // additional data like results_count, user_agent, etc.
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['type', 'date']);
            $table->index(['type', 'reference_id']);
            $table->index(['date']);
            
            // Unique constraint to prevent duplicate entries per day
            $table->unique(['type', 'reference_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('help_analytics');
    }
};