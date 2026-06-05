<?php

return [
    /*
     * AI Provider Configuration
     *
     * Supported providers: 'ollama', 'anthropic', 'openai'
     */
    'provider' => env('AI_PROVIDER', 'ollama'),

    /*
     * Model Name
     *
     * For Ollama: 'qwen3.5:9b', 'mistral:7b', 'llama3.1:8b'
     * For Anthropic: 'claude-3-5-haiku-20241022', 'claude-3-5-sonnet-20241022'
     * For OpenAI: 'gpt-4-turbo', 'gpt-3.5-turbo'
     */
    'model' => env('AI_MODEL', 'qwen3.5:9b'),

    /*
     * Optional: use a smaller/faster model for per-row statement categorization.
     * Defaults to the main AI_MODEL when unset.
     */
    'categorize_model' => env('AI_CATEGORIZE_MODEL'),

    /*
     * Max tokens for categorize responses (short JSON only).
     */
    'categorize_max_tokens' => (int) env('AI_CATEGORIZE_MAX_TOKENS', 512),

    /*
     * Tool rounds for categorize (list_categories + final JSON reply).
     */
    'categorize_max_steps' => (int) env('AI_CATEGORIZE_MAX_STEPS', 4),

    /*
     * Maximum tool-calling steps
     * Limits number of sequential tool calls before stopping
     */
    'max_steps' => (int) env('AI_MAX_STEPS', 5),

    /*
     * Maximum tokens the model can generate in a single response.
     * Higher = longer replies but slower. 4096 is a safe default.
     */
    'max_tokens' => (int) env('AI_MAX_TOKENS', 4096),

    /*
     * Ollama Configuration
     */
    'ollama' => [
        'url' => env('OLLAMA_URL', 'http://localhost:11434'),
    ],

    /*
     * Request Timeout (seconds)
     * Time to wait for first token from LLM
     * Increase if using cold Ollama loads
     */
    'request_timeout' => (int) env('PRISM_REQUEST_TIMEOUT', 120),
];
