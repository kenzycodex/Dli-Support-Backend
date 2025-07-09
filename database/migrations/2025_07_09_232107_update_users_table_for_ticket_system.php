<?php
// Run this migration only if your users table is missing any of these fields

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // Check if these columns don't exist before adding them
            
            if (!Schema::hasColumn('users', 'role')) {
                $table->string('role')->default('student')->after('email');
            }
            
            if (!Schema::hasColumn('users', 'status')) {
                $table->string('status')->default('active')->after('role');
            }
            
            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone')->nullable()->after('status');
            }
            
            if (!Schema::hasColumn('users', 'address')) {
                $table->text('address')->nullable()->after('phone');
            }
            
            if (!Schema::hasColumn('users', 'date_of_birth')) {
                $table->date('date_of_birth')->nullable()->after('address');
            }
            
            if (!Schema::hasColumn('users', 'student_id')) {
                $table->string('student_id')->nullable()->unique()->after('date_of_birth');
            }
            
            if (!Schema::hasColumn('users', 'employee_id')) {
                $table->string('employee_id')->nullable()->unique()->after('student_id');
            }
            
            if (!Schema::hasColumn('users', 'specializations')) {
                $table->json('specializations')->nullable()->after('employee_id');
            }
            
            if (!Schema::hasColumn('users', 'bio')) {
                $table->text('bio')->nullable()->after('specializations');
            }
            
            if (!Schema::hasColumn('users', 'profile_photo')) {
                $table->string('profile_photo')->nullable()->after('bio');
            }
            
            if (!Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('profile_photo');
            }
            
            // Add indexes for better performance
            if (!Schema::hasIndex('users', ['role'])) {
                $table->index('role');
            }
            
            if (!Schema::hasIndex('users', ['status'])) {
                $table->index('status');
            }
            
            if (!Schema::hasIndex('users', ['role', 'status'])) {
                $table->index(['role', 'status']);
            }
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            // Only drop columns that were added by this migration
            $columnsToCheck = [
                'last_login_at', 'profile_photo', 'bio', 'specializations', 
                'employee_id', 'student_id', 'date_of_birth', 'address', 
                'phone', 'status', 'role'
            ];
            
            foreach ($columnsToCheck as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
            
            // Drop indexes
            $table->dropIndex(['role']);
            $table->dropIndex(['status']);
            $table->dropIndex(['role', 'status']);
        });
    }
};