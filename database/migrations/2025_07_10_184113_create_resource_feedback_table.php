<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('resource_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')->constrained('resources')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->integer('rating')->unsigned()->nullable(); // 1-5 stars
            $table->text('comment')->nullable();
            $table->boolean('is_recommended')->default(true);
            $table->timestamps();

            // Prevent duplicate feedback from same user on same resource
            $table->unique(['resource_id', 'user_id']);
            $table->index(['resource_id', 'rating']);
            $table->index(['resource_id', 'is_recommended']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('resource_feedback');
    }
};
