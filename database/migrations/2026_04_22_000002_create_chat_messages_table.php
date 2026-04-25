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
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_session_id')
                ->constrained('chat_sessions')
                ->cascadeOnDelete();
            $table->enum('role', ['user', 'assistant', 'tool']);
            $table->longText('content');
            $table->json('tool_calls')->nullable(); // stores tool call details {name, params, result}
            $table->timestamps();
            
            $table->index(['chat_session_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
