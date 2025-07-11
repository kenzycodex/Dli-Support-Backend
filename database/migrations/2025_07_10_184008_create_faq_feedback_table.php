<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('faq_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faq_id')->constrained('faqs')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->boolean('is_helpful');
            $table->text('comment')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();

            // Prevent duplicate feedback from same user on same FAQ
            $table->unique(['faq_id', 'user_id']);
            $table->index(['faq_id', 'is_helpful']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('faq_feedback');
    }
};