<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('help_categories')->onDelete('cascade');
            $table->string('question');
            $table->text('answer');
            $table->string('slug')->unique();
            $table->json('tags')->nullable();
            $table->integer('helpful_count')->default(0);
            $table->integer('not_helpful_count')->default(0);
            $table->integer('view_count')->default(0);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_published')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['category_id', 'is_published', 'sort_order']);
            $table->index(['is_published', 'is_featured']);
            $table->index(['is_published', 'view_count']);
            $table->fullText(['question', 'answer']); // For better search
        });
    }

    public function down()
    {
        Schema::dropIfExists('faqs');
    }
};