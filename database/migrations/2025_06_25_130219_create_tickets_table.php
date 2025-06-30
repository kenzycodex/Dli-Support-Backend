<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number')->unique(); // T001, T002, etc.
            $table->unsignedBigInteger('user_id'); // Student who created the ticket
            $table->string('subject');
            $table->text('description');
            $table->string('category'); // technical, academic, mental-health, administrative, other
            $table->enum('priority', ['Low', 'Medium', 'High'])->default('Medium');
            $table->enum('status', ['Open', 'In Progress', 'Resolved', 'Closed'])->default('Open');
            $table->unsignedBigInteger('assigned_to')->nullable(); // Counselor/staff assigned
            $table->boolean('crisis_flag')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');
            $table->index(['user_id', 'status', 'created_at']);
            $table->index(['assigned_to', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('tickets');
    }
};