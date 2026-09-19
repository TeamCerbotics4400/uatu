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
        Schema::table('users', function (Blueprint $table) {
            // Telegram identifica al destinatario por chat_id, no por telefono.
            // Se obtiene cuando la persona le escribe /start al bot.
            $table->string('telegram_chat_id')->nullable()->unique()->after('phone_number');
            $table->string('telegram_username')->nullable()->after('telegram_chat_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['telegram_chat_id', 'telegram_username']);
        });
    }
};
