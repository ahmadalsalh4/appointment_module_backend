<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_duration_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->onDelete('restrict');
            $table->integer('old_duration');
            $table->integer('new_duration');
            $table->timestamp('applied_at')->useCurrent();
            $table->timestamps();

            $table->index(['service_id', 'applied_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_duration_history');
    }
};
