<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('layer_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->boolean('visible')->default(true);
            $table->unsignedTinyInteger('opacity')->default(100);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('layer_settings');
    }
};
