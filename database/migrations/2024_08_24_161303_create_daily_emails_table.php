<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('daily_emails', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('person_id');
            $table->timestamp('sent_at')->nullable();   
            $table->foreign('person_id')->references('id')->on('people')->onDelete('cascade');
            $table->enum('status', ['pending', 'sent', 'error'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_emails');
    }
};
