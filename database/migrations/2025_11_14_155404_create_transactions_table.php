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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            //? uuid for offline first
            $table->uuid('uuid')->unique();

            //? foreign keys
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_method_id')->constrained()->cascadeOnDelete();

            //? core fields
            $table->decimal('transaction_amount', 10, 2);
            $table->date('transaction_date');

            //? conflict resolution client side timestamps
            $table->timestamp('client_updated_at')->nullable();

            //? sof delete for offline deletion
            $table->softDeletes();

            //? laravel timestamp server time
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
