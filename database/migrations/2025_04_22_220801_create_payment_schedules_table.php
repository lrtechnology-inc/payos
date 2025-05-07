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
        Schema::create('payment_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('loan_id');
            $table->foreign('loan_id')->references('id')->on('loans')->onDelete('restrict')->onUpdate('cascade');
            $table->date('due_date');
            $table->decimal('installment_amount', 15, 2);
            $table->enum('payment_status', ['pending', 'paid', 'overdue', 'partial'])->default('pending');
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('restrict')->onUpdate('cascade');
            $table->date('payment_date')->nullable();
            $table->decimal('paid_amount', 15, 2)->nullable();
            $table->index('loan_id');
            $table->index('payment_id');
            $table->index('payment_status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_schedules');
    }
};
