<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        $keywords = [
            // Critical keywords
            ['keyword' => 'suicide', 'severity_level' => 'critical', 'exact_match' => false],
            ['keyword' => 'kill myself', 'severity_level' => 'critical', 'exact_match' => true],
            ['keyword' => 'end my life', 'severity_level' => 'critical', 'exact_match' => true],
            ['keyword' => 'want to die', 'severity_level' => 'critical', 'exact_match' => true],
            ['keyword' => 'suicidal', 'severity_level' => 'critical', 'exact_match' => false],
            
            // High severity keywords
            ['keyword' => 'self-harm', 'severity_level' => 'high', 'exact_match' => false],
            ['keyword' => 'cutting', 'severity_level' => 'high', 'exact_match' => false],
            ['keyword' => 'hurt myself', 'severity_level' => 'high', 'exact_match' => true],
            ['keyword' => 'emergency', 'severity_level' => 'high', 'exact_match' => false],
            ['keyword' => 'crisis', 'severity_level' => 'high', 'exact_match' => false],
            
            // Medium severity keywords
            ['keyword' => 'depressed', 'severity_level' => 'medium', 'exact_match' => false],
            ['keyword' => 'anxiety', 'severity_level' => 'medium', 'exact_match' => false],
            ['keyword' => 'panic attack', 'severity_level' => 'medium', 'exact_match' => true],
            ['keyword' => 'overwhelmed', 'severity_level' => 'medium', 'exact_match' => false],
            ['keyword' => 'hopeless', 'severity_level' => 'medium', 'exact_match' => false],
            
            // Low severity keywords
            ['keyword' => 'stressed', 'severity_level' => 'low', 'exact_match' => false],
            ['keyword' => 'worried', 'severity_level' => 'low', 'exact_match' => false],
            ['keyword' => 'struggling', 'severity_level' => 'low', 'exact_match' => false],
        ];

        foreach ($keywords as $keyword) {
            DB::table('crisis_keywords')->insert([
                'keyword' => $keyword['keyword'],
                'severity_level' => $keyword['severity_level'],
                'exact_match' => $keyword['exact_match'],
                'case_sensitive' => false,
                'is_active' => true,
                'trigger_count' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down()
    {
        DB::table('crisis_keywords')->truncate();
    }
};
