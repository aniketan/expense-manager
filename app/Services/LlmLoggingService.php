<?php

namespace App\Services;

use App\Models\LlmLog;
use Illuminate\Support\Facades\Log;
use Throwable;

class LlmLoggingService
{
    protected function baseMeta(): array
    {
        return [
            'provider' => (string) config('ai.provider', 'ollama'),
            'model' => (string) config('ai.model', 'qwen3.5:9b'),
        ];
    }

    protected function llmLog(): \Psr\Log\LoggerInterface
    {
        return Log::channel('llm');
    }

    /**
     * Log an LLM request before it's sent.
     */
    public function logRequest(string $sessionId, string $provider, string $model, array $payload): void
    {
        // Database log
        LlmLog::create([
            'session_id'   => $sessionId,
            'provider'     => $provider,
            'model'        => $model,
            'message_type' => 'request',
            'content'      => $payload,
            'status'       => 'success',
        ]);

        // File log
        $this->llmLog()->info("REQUEST [{$sessionId}]", [
            'provider' => $provider,
            'model'    => $model,
            'payload'  => $payload,
        ]);
    }

    /**
     * Log an LLM response after streaming completes.
     */
    public function logResponse(string $sessionId, int $durationMs, array $payload): void
    {
        $meta = $this->baseMeta();

        // Database log
        LlmLog::create([
            'session_id'   => $sessionId,
            ...$meta,
            'message_type' => 'response',
            'content'      => $payload,
            'duration_ms'  => $durationMs,
            'status'       => 'success',
        ]);

        // File log
        $this->llmLog()->info("RESPONSE [{$sessionId}] ({$durationMs}ms)", [
            'provider' => $meta['provider'],
            'model'    => $meta['model'],
            'payload'  => $payload,
        ]);
    }

    /**
     * Log a tool call event.
     */
    public function logToolCall(string $sessionId, string $toolName, array $arguments = [], ?string $result = null): void
    {
        $meta = $this->baseMeta();

        // Database log
        LlmLog::create([
            'session_id'   => $sessionId,
            ...$meta,
            'message_type' => 'tool_call',
            'content'      => [
                'tool'      => $toolName,
                'arguments' => $arguments,
                'result'    => $result,
            ],
            'status'       => 'success',
        ]);

        // File log
        $this->llmLog()->info("TOOL_CALL [{$sessionId}] {$toolName}", [
            'arguments' => $arguments,
            'result'    => $result,
        ]);
    }

    /**
     * Log an error or exception.
     */
    public function logError(string $sessionId, string $errorStatus, Throwable $exception): void
    {
        $meta = $this->baseMeta();

        // Database log
        LlmLog::create([
            'session_id'    => $sessionId,
            ...$meta,
            'message_type'  => 'error',
            'content'       => ['type' => class_basename($exception)],
            'status'        => $errorStatus,
            'error_message' => $exception->getMessage(),
        ]);

        // File log
        $this->llmLog()->error("ERROR [{$sessionId}] {$errorStatus}", [
            'exception' => class_basename($exception),
            'message'   => $exception->getMessage(),
            'trace'     => $exception->getTraceAsString(),
        ]);
    }

    /**
     * Log raw messages being sent to LLM (for debugging).
     */
    public function logMessages(string $sessionId, array $messages, string $systemPrompt = ''): void
    {
        $meta = $this->baseMeta();

        $formatted = array_map(function ($msg) {
            if (is_object($msg)) {
                $content = '';
                if (property_exists($msg, 'content')) {
                    $content = $msg->content;
                } elseif (method_exists($msg, 'text')) {
                    $content = $msg->text();
                }
                return [
                    'role'    => class_basename($msg),
                    'content' => $content,
                ];
            }
            return $msg;
        }, $messages);

        // File log only (detailed)
        $this->llmLog()->debug("MESSAGES [{$sessionId}]", [
            'provider'      => $meta['provider'],
            'model'         => $meta['model'],
            'system_prompt' => mb_substr($systemPrompt, 0, 500) . (mb_strlen($systemPrompt) > 500 ? '...' : ''),
            'messages'      => $formatted,
        ]);
    }

    /**
     * Log the final assistant text response.
     */
    public function logAssistantResponse(string $sessionId, string $text, array $toolCalls = []): void
    {
        $this->llmLog()->info("ASSISTANT [{$sessionId}]", [
            'text'       => $text,
            'tool_calls' => $toolCalls,
        ]);
    }
}
