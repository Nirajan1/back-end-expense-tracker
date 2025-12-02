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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            //?uuid for offline
            $table->string('uuid')->unique();

            //?foreign keys
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();

            //? core fields
            $table->string('name')->unique();
            $table->boolean('is_global')->default(false);
            $table->timestamp('client_update_at')->nullable();

            $table->timestamps();
            //?for sof deletes
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
