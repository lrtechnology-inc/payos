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
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('restrict')->onUpdate('cascade');
            $table->unsignedBigInteger('route_id');
            $table->foreign('route_id')->references('id')->on('routes')->onDelete('restrict')->onUpdate('cascade');
            $table->decimal('amount', 15, 2);
            $table->integer('interest');
            $table->integer('quantity_installments');
            $table->decimal('total_amount', 15, 2)->nullable();
            $table->integer('payment_count')->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('remaining_amount', 15, 2)->default(0);
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('frequency', ['daily', 'weekly', 'biweekly', 'monthly']);
            $table->date('last_payment_date')->nullable();
            $table->date('next_payment_date')->nullable();
            $table->enum('status', ['active', 'paid', 'canceled', 'overdue'])->default('active');
            $table->index('customer_id');
            $table->index('route_id');
            $table->index('status');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
