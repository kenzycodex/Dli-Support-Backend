<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Seed Help Categories
        $helpCategories = [
            [
                'name' => 'Appointments',
                'slug' => 'appointments',
                'description' => 'Questions about booking, managing, and attending appointments',
                'icon' => 'Calendar',
                'color' => '#3B82F6',
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Technical Support',
                'slug' => 'technical',
                'description' => 'Platform usage, technical issues, and troubleshooting',
                'icon' => 'Settings',
                'color' => '#8B5CF6',
                'sort_order' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Privacy & Security',
                'slug' => 'privacy',
                'description' => 'Information privacy, security, and data protection',
                'icon' => 'Shield',
                'color' => '#10B981',
                'sort_order' => 3,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Crisis Support',
                'slug' => 'crisis',
                'description' => 'Emergency resources and crisis intervention information',
                'icon' => 'AlertTriangle',
                'color' => '#EF4444',
                'sort_order' => 4,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Services',
                'slug' => 'services',
                'description' => 'Available counseling services and programs',
                'icon' => 'Heart',
                'color' => '#F59E0B',
                'sort_order' => 5,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'General',
                'slug' => 'general',
                'description' => 'General questions and information',
                'icon' => 'HelpCircle',
                'color' => '#6B7280',
                'sort_order' => 6,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('help_categories')->insert($helpCategories);

        // Seed Resource Categories
        $resourceCategories = [
            [
                'name' => 'Mental Health',
                'slug' => 'mental-health',
                'description' => 'Resources for mental health and emotional wellbeing',
                'icon' => 'Brain',
                'color' => '#8B5CF6',
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Academic Success',
                'slug' => 'academic-success',
                'description' => 'Study skills, time management, and academic support',
                'icon' => 'BookOpen',
                'color' => '#3B82F6',
                'sort_order' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Social Wellness',
                'slug' => 'social-wellness',
                'description' => 'Relationship building and social skills',
                'icon' => 'Users',
                'color' => '#10B981',
                'sort_order' => 3,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Physical Wellness',
                'slug' => 'physical-wellness',
                'description' => 'Physical health, exercise, and wellness resources',
                'icon' => 'Activity',
                'color' => '#F59E0B',
                'sort_order' => 4,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Life Skills',
                'slug' => 'life-skills',
                'description' => 'Practical skills for daily life and independence',
                'icon' => 'Tool',
                'color' => '#EF4444',
                'sort_order' => 5,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Crisis Resources',
                'slug' => 'crisis-resources',
                'description' => 'Emergency resources and crisis intervention tools',
                'icon' => 'AlertTriangle',
                'color' => '#DC2626',
                'sort_order' => 6,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('resource_categories')->insert($resourceCategories);
    }

    public function down()
    {
        DB::table('help_categories')->truncate();
        DB::table('resource_categories')->truncate();
    }
};