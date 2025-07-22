<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        $categories = [
            [
                'name' => 'Mental Health',
                'slug' => 'mental-health',
                'description' => 'Mental health support, counseling, and wellness resources',
                'icon' => 'Heart',
                'color' => '#EF4444',
                'sort_order' => 1,
                'crisis_detection_enabled' => true,
                'sla_response_hours' => 2,
                'max_priority_level' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Crisis Support',
                'slug' => 'crisis-support',
                'description' => 'Urgent crisis intervention and immediate support',
                'icon' => 'AlertTriangle',
                'color' => '#DC2626',
                'sort_order' => 2,
                'crisis_detection_enabled' => true,
                'sla_response_hours' => 1,
                'max_priority_level' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Academic Support',
                'slug' => 'academic-support',
                'description' => 'Academic guidance, study support, and educational planning',
                'icon' => 'BookOpen',
                'color' => '#3B82F6',
                'sort_order' => 3,
                'crisis_detection_enabled' => false,
                'sla_response_hours' => 24,
                'max_priority_level' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'General Inquiry',
                'slug' => 'general-inquiry',
                'description' => 'General questions and information requests',
                'icon' => 'MessageSquare',
                'color' => '#6B7280',
                'sort_order' => 4,
                'crisis_detection_enabled' => false,
                'sla_response_hours' => 48,
                'max_priority_level' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Technical Issues',
                'slug' => 'technical-issues',
                'description' => 'Technical problems and IT support',
                'icon' => 'Monitor',
                'color' => '#059669',
                'sort_order' => 5,
                'crisis_detection_enabled' => false,
                'sla_response_hours' => 24,
                'max_priority_level' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('ticket_categories')->insert($categories);
    }

    public function down()
    {
        DB::table('ticket_categories')->truncate();
    }
};