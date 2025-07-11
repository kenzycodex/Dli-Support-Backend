<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create or update help_categories table
        if (!Schema::hasTable('help_categories')) {
            Schema::create('help_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->string('icon', 100)->default('HelpCircle');
                $table->string('color', 7)->default('#3B82F6');
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                
                $table->index(['is_active', 'sort_order']);
                $table->index('slug');
            });
            
            $this->log('✅ Created help_categories table');
        } else {
            // Update existing table
            Schema::table('help_categories', function (Blueprint $table) {
                if (!Schema::hasColumn('help_categories', 'icon')) {
                    $table->string('icon', 100)->default('HelpCircle')->after('description');
                }
                if (!Schema::hasColumn('help_categories', 'color')) {
                    $table->string('color', 7)->default('#3B82F6')->after('icon');
                }
                if (!Schema::hasColumn('help_categories', 'sort_order')) {
                    $table->integer('sort_order')->default(0)->after('color');
                }
                if (!Schema::hasColumn('help_categories', 'is_active')) {
                    $table->boolean('is_active')->default(true)->after('sort_order');
                }
            });
            
            // Add missing indexes
            if (!$this->indexExists('help_categories', 'help_categories_is_active_sort_order_index')) {
                Schema::table('help_categories', function (Blueprint $table) {
                    $table->index(['is_active', 'sort_order']);
                });
            }
            if (!$this->indexExists('help_categories', 'help_categories_slug_index')) {
                Schema::table('help_categories', function (Blueprint $table) {
                    $table->index('slug');
                });
            }
            
            $this->log('✅ Updated help_categories table');
        }

        // 2. Create or update faqs table
        if (!Schema::hasTable('faqs')) {
            Schema::create('faqs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('category_id');
                $table->string('question', 500);
                $table->longText('answer');
                $table->string('slug')->unique();
                $table->json('tags')->nullable();
                $table->integer('helpful_count')->default(0);
                $table->integer('not_helpful_count')->default(0);
                $table->integer('view_count')->default(0);
                $table->integer('sort_order')->default(0);
                $table->boolean('is_published')->default(false);
                $table->boolean('is_featured')->default(false);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
                
                // Foreign keys
                $table->foreign('category_id')->references('id')->on('help_categories')->onDelete('cascade');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
                $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
                
                // Indexes
                $table->index(['category_id', 'is_published']);
                $table->index(['is_published', 'is_featured']);
                $table->index(['is_published', 'sort_order']);
                $table->index('created_by');
                $table->index('published_at');
                $table->index('slug');
            });
            
            $this->log('✅ Created faqs table');
        } else {
            // Update existing table
            Schema::table('faqs', function (Blueprint $table) {
                if (!Schema::hasColumn('faqs', 'helpful_count')) {
                    $table->integer('helpful_count')->default(0)->after('tags');
                }
                if (!Schema::hasColumn('faqs', 'not_helpful_count')) {
                    $table->integer('not_helpful_count')->default(0)->after('helpful_count');
                }
                if (!Schema::hasColumn('faqs', 'view_count')) {
                    $table->integer('view_count')->default(0)->after('not_helpful_count');
                }
                if (!Schema::hasColumn('faqs', 'sort_order')) {
                    $table->integer('sort_order')->default(0)->after('view_count');
                }
                if (!Schema::hasColumn('faqs', 'is_featured')) {
                    $table->boolean('is_featured')->default(false)->after('is_published');
                }
                if (!Schema::hasColumn('faqs', 'created_by')) {
                    $table->unsignedBigInteger('created_by')->nullable()->after('is_featured');
                }
                if (!Schema::hasColumn('faqs', 'updated_by')) {
                    $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');
                }
                if (!Schema::hasColumn('faqs', 'published_at')) {
                    $table->timestamp('published_at')->nullable()->after('updated_by');
                }
                if (!Schema::hasColumn('faqs', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
            
            // Add foreign keys if they don't exist
            if (!$this->foreignKeyExists('faqs', 'faqs_category_id_foreign')) {
                Schema::table('faqs', function (Blueprint $table) {
                    $table->foreign('category_id')->references('id')->on('help_categories')->onDelete('cascade');
                });
            }
            if (!$this->foreignKeyExists('faqs', 'faqs_created_by_foreign')) {
                Schema::table('faqs', function (Blueprint $table) {
                    $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
                });
            }
            if (!$this->foreignKeyExists('faqs', 'faqs_updated_by_foreign')) {
                Schema::table('faqs', function (Blueprint $table) {
                    $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
                });
            }
            
            // Add missing indexes
            $indexes = [
                'faqs_category_id_is_published_index' => ['category_id', 'is_published'],
                'faqs_is_published_is_featured_index' => ['is_published', 'is_featured'],
                'faqs_is_published_sort_order_index' => ['is_published', 'sort_order'],
                'faqs_created_by_index' => ['created_by'],
                'faqs_published_at_index' => ['published_at'],
                'faqs_slug_index' => ['slug'],
            ];
            
            foreach ($indexes as $indexName => $columns) {
                if (!$this->indexExists('faqs', $indexName)) {
                    Schema::table('faqs', function (Blueprint $table) use ($columns) {
                        $table->index($columns);
                    });
                }
            }
            
            $this->log('✅ Updated faqs table');
        }

        // 3. Create or update faq_feedback table
        if (!Schema::hasTable('faq_feedback')) {
            Schema::create('faq_feedback', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('faq_id');
                $table->unsignedBigInteger('user_id');
                $table->boolean('is_helpful');
                $table->text('comment')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamps();
                
                // Foreign keys
                $table->foreign('faq_id')->references('id')->on('faqs')->onDelete('cascade');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                
                // Indexes and constraints
                $table->index(['faq_id', 'user_id']);
                $table->index('is_helpful');
                $table->unique(['faq_id', 'user_id']); // One feedback per user per FAQ
            });
            
            $this->log('✅ Created faq_feedback table');
        } else {
            // Check if foreign keys exist
            if (!$this->foreignKeyExists('faq_feedback', 'faq_feedback_faq_id_foreign')) {
                Schema::table('faq_feedback', function (Blueprint $table) {
                    $table->foreign('faq_id')->references('id')->on('faqs')->onDelete('cascade');
                });
            }
            if (!$this->foreignKeyExists('faq_feedback', 'faq_feedback_user_id_foreign')) {
                Schema::table('faq_feedback', function (Blueprint $table) {
                    $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                });
            }
            
            $this->log('✅ Updated faq_feedback table');
        }

        // 4. Create help_analytics table
        if (!Schema::hasTable('help_analytics')) {
            Schema::create('help_analytics', function (Blueprint $table) {
                $table->id();
                $table->string('type', 50); // 'search', 'category_click', 'faq_view', etc.
                $table->string('reference_id', 255); // search query, category slug, faq id, etc.
                $table->date('date'); // date for aggregation
                $table->integer('count')->default(1); // occurrence count
                $table->json('metadata')->nullable(); // additional data
                $table->timestamps();
                
                // Indexes for performance
                $table->index(['type', 'date']);
                $table->index(['type', 'reference_id']);
                $table->index(['date']);
                $table->index('type');
                
                // Unique constraint to prevent duplicate entries per day
                $table->unique(['type', 'reference_id', 'date']);
            });
            
            $this->log('✅ Created help_analytics table');
        }

        // 5. Create accessibility_reports table
        if (!Schema::hasTable('accessibility_reports')) {
            Schema::create('accessibility_reports', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('faq_id');
                $table->unsignedBigInteger('user_id');
                $table->string('type', 100); // 'screen_reader', 'keyboard_navigation', etc.
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
                $table->index('type');
            });
            
            $this->log('✅ Created accessibility_reports table');
        }

        // 6. Create user_bookmarks table (for FAQ bookmarks)
        if (!Schema::hasTable('user_bookmarks')) {
            Schema::create('user_bookmarks', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->morphs('bookmarkable'); // faq_id, resource_id, etc.
                $table->timestamps();
                
                // Foreign keys
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                
                // Indexes
                $table->index(['user_id', 'bookmarkable_type']);
                $table->unique(['user_id', 'bookmarkable_id', 'bookmarkable_type']);
            });
            
            $this->log('✅ Created user_bookmarks table');
        }

        // 7. Create faq_versions table (for version history)
        if (!Schema::hasTable('faq_versions')) {
            Schema::create('faq_versions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('faq_id');
                $table->integer('version_number');
                $table->string('question', 500);
                $table->longText('answer');
                $table->json('tags')->nullable();
                $table->json('changes')->nullable(); // What changed in this version
                $table->unsignedBigInteger('created_by');
                $table->timestamp('created_at');
                
                // Foreign keys
                $table->foreign('faq_id')->references('id')->on('faqs')->onDelete('cascade');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
                
                // Indexes
                $table->index(['faq_id', 'version_number']);
                $table->index('created_at');
            });
            
            $this->log('✅ Created faq_versions table');
        }

        // 8. Update users table if needed
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'bio')) {
                    $table->text('bio')->nullable()->after('email');
                }
                if (!Schema::hasColumn('users', 'phone')) {
                    $table->string('phone', 20)->nullable()->after('bio');
                }
            });
            
            $this->log('✅ Updated users table');
        }

        $this->log('🎉 Help system migration completed successfully!');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop tables in reverse order to handle foreign key constraints
        $tables = [
            'faq_versions',
            'user_bookmarks',
            'accessibility_reports',
            'help_analytics',
            'faq_feedback',
            'faqs',
            'help_categories'
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::dropIfExists($table);
                $this->log("🗑️ Dropped {$table} table");
            }
        }

        // Remove added columns from users table
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (Schema::hasColumn('users', 'bio')) {
                    $table->dropColumn('bio');
                }
                if (Schema::hasColumn('users', 'phone')) {
                    $table->dropColumn('phone');
                }
            });
            
            $this->log('✅ Cleaned up users table');
        }

        $this->log('🔄 Help system migration rolled back successfully!');
    }

    /**
     * Check if an index exists
     */
    private function indexExists(string $table, string $index): bool
    {
        $indexes = DB::select("SHOW INDEX FROM {$table}");
        return collect($indexes)->contains('Key_name', $index);
    }

    /**
     * Check if a foreign key exists
     */
    private function foreignKeyExists(string $table, string $foreignKey): bool
    {
        $constraints = DB::select("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.TABLE_CONSTRAINTS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = '{$table}' 
            AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        ");
        
        return collect($constraints)->contains('CONSTRAINT_NAME', $foreignKey);
    }

    /**
     * Log migration progress
     */
    private function log(string $message): void
    {
        echo "\n" . $message;
    }
};