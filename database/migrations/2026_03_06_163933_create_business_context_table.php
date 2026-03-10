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
        Schema::create('business_context', function (Blueprint $table) {
            $table->id();
            $table->longText('content')->nullable();   // Texto libre del negocio
            $table->string('updated_by')->nullable();   // Email del admin que lo editó
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_context');
    }
};
