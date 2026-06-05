<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Console\Command;

/**
 * Minimal stdio MCP server for external agents (e.g. Claude Desktop).
 *
 * Claude Desktop config example (paths must be absolute):
 * {
 *   "mcpServers": {
 *     "expense-manager": {
 *       "command": "php",
 *       "args": ["/path/to/project/artisan", "mcp:serve"],
 *       "env": { "APP_ENV": "local" }
 *     }
 *   }
 * }
 */
class McpServerCommand extends Command
{
    protected $signature = 'mcp:serve';

    protected $description = 'Start MCP server on STDIO (JSON-RPC) for Claude Desktop / other agents';

    public function handle(): int
    {
        $this->components->warn('MCP server listening on STDIO. Do not run interactively in a TTY for Claude Desktop.');

        while (true) {
            $line = fgets(STDIN);
            if ($line === false) {
                break;
            }

            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            $request = json_decode($trimmed, true);
            if (! is_array($request)) {
                continue;
            }

            $response = $this->dispatch($request);
            fwrite(STDOUT, json_encode($response)."\n");
            fflush(STDOUT);
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $req
     * @return array<string, mixed>
     */
    private function dispatch(array $req): array
    {
        $id = $req['id'] ?? null;
        $method = $req['method'] ?? '';

        return match ($method) {
            'initialize' => $this->handleInitialize($id),
            'tools/list' => $this->handleToolsList($id),
            'tools/call' => $this->handleToolCall($id, is_array($req['params'] ?? null) ? $req['params'] : []),
            default => $this->errorResponse($id, -32601, 'Method not found'),
        };
    }

    private function handleInitialize(mixed $id): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => [
                'protocolVersion' => '2024-11-05',
                'capabilities' => ['tools' => new \stdClass],
                'serverInfo' => ['name' => 'expense-manager', 'version' => '1.0.0'],
            ],
        ];
    }

    private function handleToolsList(mixed $id): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => [
                'tools' => [
                    [
                        'name' => 'list_transactions',
                        'description' => 'List recent expense/income transactions',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => [
                                'limit' => ['type' => 'integer', 'description' => 'Max rows (default 20)'],
                                'type' => ['type' => 'string', 'enum' => ['income', 'expense', 'all']],
                                'period' => ['type' => 'string', 'enum' => ['today', 'week', 'month', 'all']],
                            ],
                        ],
                    ],
                    [
                        'name' => 'create_transaction',
                        'description' => 'Create a new income or expense transaction',
                        'inputSchema' => [
                            'type' => 'object',
                            'required' => ['amount', 'description', 'type'],
                            'properties' => [
                                'amount' => ['type' => 'number'],
                                'description' => ['type' => 'string'],
                                'type' => ['type' => 'string', 'enum' => ['income', 'expense']],
                                'category' => ['type' => 'string'],
                                'date' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                            ],
                        ],
                    ],
                    [
                        'name' => 'get_balance',
                        'description' => 'Get current account balances',
                        'inputSchema' => ['type' => 'object', 'properties' => new \stdClass],
                    ],
                    [
                        'name' => 'spending_summary',
                        'description' => 'Get spending summary by category',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => [
                                'period' => ['type' => 'string', 'enum' => ['this_month', 'last_month', 'all']],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function handleToolCall(mixed $id, array $params): array
    {
        $name = $params['name'] ?? '';
        $input = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];

        try {
            $result = match ($name) {
                'list_transactions' => $this->listTransactions($input),
                'create_transaction' => $this->createTransaction($input),
                'get_balance' => $this->getBalance(),
                'spending_summary' => $this->spendingSummary($input),
                default => throw new \InvalidArgumentException("Unknown tool: {$name}"),
            };

            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => [
                    'content' => [['type' => 'text', 'text' => json_encode($result, JSON_PRETTY_PRINT)]],
                ],
            ];
        } catch (\Throwable $e) {
            return $this->errorResponse($id, -32000, $e->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<int, array<string, mixed>>
     */
    private function listTransactions(array $input): array
    {
        $limit = min((int) ($input['limit'] ?? 20), 100);
        $type = $input['type'] ?? 'all';
        $period = $input['period'] ?? 'all';

        $query = Transaction::query()->with(['category', 'account'])->latest('transaction_date');

        if ($type !== 'all') {
            $query->where('transaction_type', $type);
        }

        match ($period) {
            'today' => $query->whereDate('transaction_date', today()),
            'week' => $query->where('transaction_date', '>=', now()->startOfWeek()),
            'month' => $query->whereMonth('transaction_date', now()->month)
                ->whereYear('transaction_date', now()->year),
            default => null,
        };

        return $query->limit($limit)->get()->map(fn (Transaction $t) => [
            'id' => $t->id,
            'date' => $t->transaction_date->format('Y-m-d'),
            'description' => $t->description,
            'amount' => (float) $t->amount,
            'type' => $t->transaction_type,
            'category' => $t->category?->name,
            'account' => $t->account?->name,
        ])->all();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function createTransaction(array $input): array
    {
        $account = Account::query()->active()->first();
        if (! $account) {
            throw new \RuntimeException('No active account found.');
        }

        $categoryHint = (string) ($input['category'] ?? '');
        $category = Category::query()
            ->where('name', 'like', '%'.$categoryHint.'%')
            ->whereNotNull('parent_id')
            ->first()
            ?? Category::query()->whereNotNull('parent_id')->first();

        if (! $category) {
            throw new \RuntimeException('No category found.');
        }

        $t = Transaction::create([
            'account_id' => $account->id,
            'category_id' => $category->id,
            'transaction_type' => $input['type'],
            'amount' => abs((float) $input['amount']),
            'description' => (string) $input['description'],
            'transaction_date' => $input['date'] ?? now()->toDateString(),
            'payment_method' => 'Other',
        ]);

        return ['success' => true, 'id' => $t->id, 'message' => "Transaction #{$t->id} created"];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getBalance(): array
    {
        return Account::query()->active()->get()->map(fn (Account $a) => [
            'name' => $a->name,
            'type' => $a->type,
            'balance' => (float) $a->current_balance,
        ])->all();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<int, array<string, mixed>>
     */
    private function spendingSummary(array $input): array
    {
        $period = $input['period'] ?? 'this_month';
        $query = Transaction::query()->where('transaction_type', 'expense');

        match ($period) {
            'this_month' => $query->whereMonth('transaction_date', now()->month)
                ->whereYear('transaction_date', now()->year),
            'last_month' => $query->whereMonth('transaction_date', now()->subMonth()->month)
                ->whereYear('transaction_date', now()->subMonth()->year),
            default => null,
        };

        return $query->with('category')
            ->selectRaw('category_id, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'category' => $r->category?->name ?? 'Uncategorized',
                'total' => (float) $r->total,
                'count' => (int) $r->count,
            ])
            ->all();
    }

    private function errorResponse(mixed $id, int $code, string $message): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => ['code' => $code, 'message' => $message],
        ];
    }
}
