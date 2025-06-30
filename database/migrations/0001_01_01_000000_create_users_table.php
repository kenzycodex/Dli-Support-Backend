<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->enum('role', ['student', 'counselor', 'advisor', 'admin'])->default('student');
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('student_id')->nullable()->unique(); // For students
            $table->string('employee_id')->nullable()->unique(); // For staff
            $table->json('specializations')->nullable(); // For counselors/advisors
            $table->text('bio')->nullable();
            $table->string('profile_photo')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            
            // Indexes for better performance
            $table->index(['role', 'status']);
            $table->index('email');
        });
    }

    public function down()
    {
        Schema::dropIfExists('users');
    }
};