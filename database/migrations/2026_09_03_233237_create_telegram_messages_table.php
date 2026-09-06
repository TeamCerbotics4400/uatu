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
        Schema::create('telegram_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id')->nullable();
            $table->string('chat_id');

            // La Bot API solo confirma si Telegram acepto el envio; no expone
            // delivered/read como WhatsApp, por eso no existen esos estados.
            $table->enum('status', ['PENDING', 'SENT', 'FAILED'])->default('PENDING');

            $table->unsignedBigInteger('telegram_message_id')->nullable();
            $table->text('body')->nullable();
            $table->json('payload')->nullable();
            $table->json('response')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');

            $table->index('telegram_message_id');
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_messages');
    }
};
