<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('ticket_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id');
            $table->unsignedBigInteger('response_id')->nullable(); // If attached to a response
            $table->string('original_name');
            $table->string('file_path');
            $table->string('file_type');
            $table->integer('file_size'); // in bytes
            $table->timestamps();

            $table->foreign('ticket_id')->references('id')->on('tickets')->onDelete('cascade');
            $table->foreign('response_id')->references('id')->on('ticket_responses')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ticket_attachments');
    }
};