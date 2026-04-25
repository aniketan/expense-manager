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
        Schema::create('llm_logs', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->index(); // references chat_sessions.session_id
            $table->string('provider')->default('ollama'); // ollama, anthropic, openai, etc.
            $table->string('model'); // qwen3.5:9b, claude-3-5-haiku, etc.
            $table->enum('message_type', ['request', 'response', 'tool_call', 'tool_result', 'error']);
            $table->longText('content'); // JSON: {messages_count, tools_count} or response summary
            $table->integer('duration_ms')->nullable(); // milliseconds to complete
            $table->string('status')->default('success'); // success, timeout, error, malformed
            $table->longText('error_message')->nullable();
            $table->timestamps();
            
            $table->index(['created_at', 'session_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('llm_logs');
    }
};
