<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('resource_categories')->onDelete('cascade');
            $table->string('title');
            $table->text('description');
            $table->string('slug')->unique();
            $table->enum('type', ['article', 'video', 'audio', 'exercise', 'tool', 'worksheet']);
            $table->string('subcategory')->nullable();
            $table->enum('difficulty', ['beginner', 'intermediate', 'advanced'])->default('beginner');
            $table->string('duration')->nullable(); // e.g., "10 min", "2 hours"
            $table->string('external_url')->nullable(); // Main access URL
            $table->string('download_url')->nullable(); // Download link if applicable
            $table->string('thumbnail_url')->nullable(); // Preview image
            $table->json('tags')->nullable();
            $table->string('author_name')->nullable();
            $table->text('author_bio')->nullable();
            $table->decimal('rating', 2, 1)->default(0.0); // 0.0 to 5.0
            $table->integer('download_count')->default(0);
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
            $table->index(['type', 'is_published']);
            $table->index(['difficulty', 'is_published']);
            $table->index(['is_published', 'is_featured']);
            $table->index(['is_published', 'rating']);
            $table->index(['is_published', 'view_count']);
            $table->fullText(['title', 'description', 'author_name']); // For better search
        });
    }

    public function down()
    {
        Schema::dropIfExists('resources');
    }
};