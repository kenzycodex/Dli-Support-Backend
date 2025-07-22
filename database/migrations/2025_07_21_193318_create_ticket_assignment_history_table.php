<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('ticket_assignment_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id');
            $table->unsignedBigInteger('assigned_from')->nullable(); // Previous assignee
            $table->unsignedBigInteger('assigned_to')->nullable(); // New assignee
            $table->unsignedBigInteger('assigned_by'); // Who made the assignment
            $table->enum('assignment_type', ['auto', 'manual', 'transfer', 'unassign'])->default('manual');
            $table->text('reason')->nullable();
            $table->json('assignment_criteria')->nullable(); // Workload, expertise, etc.
            $table->timestamp('assigned_at');
            $table->timestamps();

            $table->foreign('ticket_id')->references('id')->on('tickets')->onDelete('cascade');
            $table->foreign('assigned_from')->references('id')->on('users')->onDelete('set null');
            $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');
            $table->foreign('assigned_by')->references('id')->on('users')->onDelete('cascade');
            
            // FIXED: Use shorter index names
            $table->index(['ticket_id', 'assigned_at'], 'tah_ticket_assigned_idx');
            $table->index(['assigned_to', 'assignment_type'], 'tah_assignee_type_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ticket_assignment_history');
    }
};