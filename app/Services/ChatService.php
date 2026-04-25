<?php

namespace App\Services;

use App\AI\Tools\CreateTransactionTool;
use App\AI\Tools\GetAccountBalancesTool;
use App\AI\Tools\GetSpendingInsightsTool;
use App\AI\Tools\ListCategoriesTool;
use App\AI\Tools\ManageResourceTool;
use App\AI\Tools\QueryExpensesTool;
use App\AI\Tools\SearchTransactionsTool;
use App\Models\ChatSession;
use App\Models\ChatMessage;
use Prism\Prism\Facades\Prism;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\Streaming\Events\TextDeltaEvent;
use Prism\Prism\Streaming\Events\ToolCallEvent;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatService
{
    protected LlmLoggingService $loggingService;

    public function __construct(LlmLoggingService $loggingService)
    {
        $this->loggingService = $loggingService;
    }

    /**
     * Stream an LLM response as Server-Sent Events.
     * Returns a StreamedResponse for direct output to the client.
     */
    public function streamedResponse(string $sessionId, string $userMessage): StreamedResponse
    {
        // Ensure session exists
        $session = ChatSession::firstOrCreate(
            ['session_id' => $sessionId],
        );

        // Save user message immediately
        ChatMessage::create([
            'chat_session_id' => $session->id,
            'role'            => 'user',
            'content'         => $userMessage,
        ]);

        // Load conversation history (last 20 messages)
        $messages = $session->messages()
            ->orderBy('created_at', 'asc')
            ->limit(20)
            ->get();

        // Convert to Prism message objects
        $history = $messages->map(function ($msg) {
            if ($msg->role === 'user') {
                return new UserMessage($msg->content);
            } else {
                return new AssistantMessage($msg->content);
            }
        })->all();

        $systemPrompt = $this->systemPrompt();

        // Log the request with full details
        $this->loggingService->logRequest(
            $sessionId,
            config('ai.provider'),
            config('ai.model'),
            [
                'user_message'   => $userMessage,
                'messages_count' => count($history),
                'tools_count'    => 7,
            ]
        );

        // Log messages being sent to LLM (detailed debug)
        $this->loggingService->logMessages($sessionId, $history, $systemPrompt);

        // Build the Prism request and stream response
        $startTime = microtime(true);

        try {
            return Prism::text()
                ->using(config('ai.provider'), config('ai.model'))
                ->withSystemPrompt($systemPrompt)
                ->withMessages($history)
                ->withTools([
                    new CreateTransactionTool(),
                    new QueryExpensesTool(),
                    new ListCategoriesTool(),
                    new SearchTransactionsTool(),
                    new GetAccountBalancesTool(),
                    new GetSpendingInsightsTool(),
                    new ManageResourceTool(),
                ])
                ->withMaxSteps(config('ai.max_steps'))
                ->withMaxTokens(config('ai.max_tokens', 4096))
                ->withProviderOptions(['thinking' => true, 'num_predict' => config('ai.max_tokens', 4096)])
                ->asEventStreamResponse(function ($request, $events) use (
                    $session,
                    $startTime,
                ) {
                    // Called after streaming completes
                    $this->persistAssistantMessage(
                        $session,
                        $events,
                        (int) ((microtime(true) - $startTime) * 1000)
                    );
                });

        } catch (\Exception $e) {
            $duration = (int) ((microtime(true) - $startTime) * 1000);
            $errorStatus = str_contains($e->getMessage(), 'timeout') ? 'timeout' : 'error';
            $this->loggingService->logError($sessionId, $errorStatus, $e);

            // Return error as SSE stream
            return new StreamedResponse(function () use ($e) {
                echo "event: error\n";
                echo "data: " . json_encode([
                    'error'  => $e->getMessage(),
                    'status' => 'error',
                ]) . "\n\n";
                echo "event: stream-end\n";
                echo "data: {}\n\n";
            }, 200, [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'Connection' => 'keep-alive',
            ]);
        }
    }

    /**
     * Extract final assistant message from events and persist to database.
     */
    protected function persistAssistantMessage($session, $events, int $durationMs): void
    {
        $finalText = '';
        $toolCalls = [];

        // Concatenate all text delta events
        foreach ($events as $event) {
            if ($event instanceof TextDeltaEvent) {
                $finalText .= $event->delta;
            } elseif ($event instanceof ToolCallEvent) {
                $toolCalls[] = [
                    'name'      => $event->toolCall->name,
                    'arguments' => $event->toolCall->arguments,
                ];
            }
        }

        // Save assistant message
        if (!empty($finalText)) {
            ChatMessage::create([
                'chat_session_id' => $session->id,
                'role'            => 'assistant',
                'content'         => $finalText,
                'tool_calls'      => !empty($toolCalls) ? $toolCalls : null,
            ]);
        }

        // Log the response summary
        $this->loggingService->logResponse(
            $session->session_id,
            $durationMs,
            [
                'text_length'      => strlen($finalText),
                'tool_calls_count' => count($toolCalls),
            ]
        );

        // Log full assistant response for debugging
        $this->loggingService->logAssistantResponse(
            $session->session_id,
            $finalText,
            $toolCalls
        );
    }

    /**
     * System prompt for the LLM.
     * Guides the model to use tools appropriately.
     */
    protected function systemPrompt(): string
    {
        $today = Carbon::now()->format('Y-m-d');

        $prompt = <<<'PROMPT'
You are a helpful financial assistant for an Indian expense manager app.
Today's date is PROMPT_TODAY. Currency is Indian Rupees (₹).

Your role:
- Help users track their income and expenses
- Create transactions when users mention spending or receiving money
- Answer questions about their spending patterns and budgets
- Always be friendly and encouraging about financial management

Rules:
1. When a user says they spent or earned money (e.g., "I spent ₹100 on groceries"), immediately call the create_transaction tool with the details.
2. When a user asks about spending/income, use query_expenses. For totals or "how much" only, omit list_limit (or use 0). For "show/list/recent/last transaction(s)", set list_limit (e.g. 10, or 1 for last only) and period=all unless they specify a timeframe; use type=both unless they specify income or expense only.
3. Extract amounts as numbers only (₹500 → 500)
4. Best-guess the category from context. If unsure, let the tool choose a default.
5. After a tool call, reply using the tool JSON only — never invent transactions.

Examples:
- User: "I spent 50 on coffee" → create_transaction(amount=50, description="coffee", category_hint="Food") → "✅ Recorded ₹50 on coffee!"
- User: "How much did I spend this month?" → query_expenses(period="this_month", type="expense") → answer from totals/count.
- User: "Show my recent transactions" → query_expenses(period="all", type="both", list_limit=10) → summarize the `transactions` array from the response.
- User: "How much on Restaurant?" → list_categories → find id for "Restaurant" → search_transactions(category_ids="<id>", type="expense") → answer with total and list.
- User: "Show UPI payments last month" → search_transactions(from_date="...", to_date="...", payment_method="UPI", type="both") → summarize results.
- User: "Outside eating expenses in January" → list_categories → find Restaurant/Food & Dining ids → search_transactions(category_ids="5,8", type="expense", from_date="2026-01-01", to_date="2026-01-31").

Category search rules:
1. Whenever the user mentions a category or subcategory name, ALWAYS call list_categories first to get the real IDs.
2. Then call search_transactions with category_ids as a comma-separated string of those IDs.
3. Never guess category IDs. If list_categories returns no close match, tell the user the available categories.
4. For multiple categories (e.g. "Restaurant and Groceries"), pass all matching IDs together: category_ids="5,12".
5. Use search_transactions for any combination of filters (category + date + account + payment method + tags + description).
6. Use query_expenses only for simple period-based summaries with no category/account/description filter.

ACCOUNT BALANCES:
- "balance", "how much do I have", "my money", "account balance" → get_account_balances
- Pass account_name to filter to a specific account.

SPENDING INSIGHTS & BUDGETS:
- "summary", "overview", "how am I doing", "financial summary" → get_spending_insights(query_type=spending_summary, period=...)
- "budget status", "over budget", "budget left", "am I on budget" → get_spending_insights(query_type=budget_status)
- "UPI vs cash", "how do I pay", "payment method breakdown" → get_spending_insights(query_type=payment_breakdown, period=...)
- "tags", "tag summary", "what tags" → get_spending_insights(query_type=tag_summary)
- "busiest day", "which day do I spend most" → get_spending_insights(query_type=day_breakdown, period=...)

RESOURCE MANAGEMENT (create / update / delete):
- "create/set a budget" → manage_resource(action=create, resource=budget, category_name=..., amount=..., period_type=...)
- "add/create a category" → list_categories first to check for duplicates, then manage_resource(action=create, resource=category, name=..., parent_category_name=...)
- "edit/update a transaction" → search_transactions first to get the ID, then manage_resource(action=update, resource=transaction, resource_id=..., <fields to change>)
- "delete a transaction" → search_transactions to get ID, then manage_resource(action=delete, resource=transaction, resource_id=..., confirmed=no) — show details and ask user to confirm — only use confirmed=yes after the user explicitly says yes
- "remove/deactivate a budget" → manage_resource(action=delete, resource=budget, resource_id=..., confirmed=no) first, then confirmed=yes after user confirms
PROMPT;

        return str_replace('PROMPT_TODAY', $today, $prompt);
    }
}
