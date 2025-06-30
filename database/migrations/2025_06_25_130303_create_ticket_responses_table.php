<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('ticket_responses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id');
            $table->unsignedBigInteger('user_id'); // Who responded
            $table->text('message');
            $table->boolean('is_internal')->default(false); // Internal staff comments
            $table->string('visibility')->default('all'); // all, counselors, admins
            $table->boolean('is_urgent')->default(false);
            $table->timestamps();

            $table->foreign('ticket_id')->references('id')->on('tickets')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['ticket_id', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('ticket_responses');
    }
};
