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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('symbol', 10); // BTC/ETH
            $table->enum('side', ['buy', 'sell']);
            $table->decimal('price', 20, 8);
            $table->decimal('amount', 20, 8);
            $table->decimal('remaining_amount', 20, 8);

            $table->enum('status', ['open', 'filled', 'cancelled'])->default('open');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('filled_at')->nullable();

            $table->timestamps();

            $table->index(['symbol', 'status', 'side']);
            $table->index(['symbol', 'side', 'status', 'price', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
