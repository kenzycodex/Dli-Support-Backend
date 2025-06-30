<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('type'); // appointment, ticket, system, reminder
            $table->string('title');
            $table->text('message');
            $table->string('priority')->default('medium'); // low, medium, high
            $table->boolean('read')->default(false);
            $table->json('data')->nullable(); // Additional data for the notification
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'read', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('notifications');
    }
};