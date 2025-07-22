<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Map old category strings to new category IDs
        $categoryMapping = [
            'mental-health' => 1, // Mental Health
            'crisis' => 2,        // Crisis Support
            'academic' => 3,      // Academic Support
            'general' => 4,       // General Inquiry
            'technical' => 5,     // Technical Issues
            'other' => 4,         // Map 'other' to General Inquiry
        ];

        // Update existing tickets with category_id based on old category field
        foreach ($categoryMapping as $oldCategory => $newCategoryId) {
            DB::table('tickets')
                ->where('category', $oldCategory)
                ->update(['category_id' => $newCategoryId]);
        }

        // For any tickets that don't match, set to General Inquiry
        DB::table('tickets')
            ->whereNull('category_id')
            ->update(['category_id' => 4]); // General Inquiry
    }

    public function down()
    {
        // Revert category_id back to category string
        $reverseMapping = [
            1 => 'mental-health',
            2 => 'crisis',
            3 => 'academic',
            4 => 'general',
            5 => 'technical',
        ];

        foreach ($reverseMapping as $categoryId => $oldCategory) {
            DB::table('tickets')
                ->where('category_id', $categoryId)
                ->update(['category' => $oldCategory]);
        }
    }
};
