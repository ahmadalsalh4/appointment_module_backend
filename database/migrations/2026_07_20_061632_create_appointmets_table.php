<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointmets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->onDelete('cascade');
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->foreignId('service_id')->constrained('services')->onDelete('cascade');
            $table->foreignId('state_id')->constrained('statuses')->onDelete('restrict');
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->timestamps();

            $table->index(['staff_id', 'start_date', 'end_date']);
            $table->index('state_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointmets');
    }
};
