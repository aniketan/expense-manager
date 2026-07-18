<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\AccountTransferService;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia;
use RuntimeException;
use Tests\TestCase;

class AccountTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_moves_balance_and_creates_two_linked_transfer_legs(): void
    {
        $this->seed(CategorySeeder::class);
        $source = Account::factory()->create(['opening_balance' => 10000, 'current_balance' => 10000]);
        $destination = Account::factory()->create(['opening_balance' => 1000, 'current_balance' => 1000]);

        app(AccountTransferService::class)->create([
            'account_id' => $source->id,
            'transfer_to_account_id' => $destination->id,
            'amount' => 500,
            'description' => 'Move savings',
            'transaction_date' => '2026-07-18',
            'transaction_time' => '10:30',
        ]);

        $legs = Transaction::orderBy('id')->get();

        $this->assertCount(2, $legs);
        $this->assertSame('transfer', $legs[0]->transaction_type);
        $this->assertSame('TRANSFER_OUTGOING', $legs[0]->category->code);
        $this->assertSame('transfer', $legs[1]->transaction_type);
        $this->assertSame('TRANSFER_INCOMING', $legs[1]->category->code);
        $this->assertNotNull($legs[0]->transfer_group_id);
        $this->assertSame($legs[0]->transfer_group_id, $legs[1]->transfer_group_id);
        $this->assertSame('9500.00', $source->fresh()->current_balance);
        $this->assertSame('1500.00', $destination->fresh()->current_balance);
    }

    public function test_update_changes_both_legs_and_balances(): void
    {
        $this->seed(CategorySeeder::class);
        $source = Account::factory()->create(['opening_balance' => 10000, 'current_balance' => 10000]);
        $destination = Account::factory()->create(['opening_balance' => 1000, 'current_balance' => 1000]);
        $service = app(AccountTransferService::class);
        [$outgoing] = $service->create($this->transferData($source, $destination, 500));

        $service->update($outgoing, $this->transferData($source, $destination, 750));

        $legs = Transaction::where('transfer_group_id', $outgoing->transfer_group_id)->get();
        $this->assertCount(2, $legs);
        $this->assertTrue($legs->every(fn (Transaction $leg) => $leg->amount === '750.00'));
        $this->assertSame('9250.00', $source->fresh()->current_balance);
        $this->assertSame('1750.00', $destination->fresh()->current_balance);
    }

    public function test_update_can_move_both_legs_to_different_accounts(): void
    {
        $this->seed(CategorySeeder::class);
        $oldSource = Account::factory()->create(['opening_balance' => 10000, 'current_balance' => 10000]);
        $oldDestination = Account::factory()->create(['opening_balance' => 1000, 'current_balance' => 1000]);
        $newSource = Account::factory()->create(['opening_balance' => 8000, 'current_balance' => 8000]);
        $newDestination = Account::factory()->create(['opening_balance' => 2000, 'current_balance' => 2000]);
        $service = app(AccountTransferService::class);
        [$outgoing] = $service->create($this->transferData($oldSource, $oldDestination, 500));

        $service->update($outgoing, $this->transferData($newSource, $newDestination, 750));

        $this->assertSame('10000.00', $oldSource->fresh()->current_balance);
        $this->assertSame('1000.00', $oldDestination->fresh()->current_balance);
        $this->assertSame('7250.00', $newSource->fresh()->current_balance);
        $this->assertSame('2750.00', $newDestination->fresh()->current_balance);
    }

    public function test_delete_removes_both_legs_and_restores_balances(): void
    {
        $this->seed(CategorySeeder::class);
        $source = Account::factory()->create(['opening_balance' => 10000, 'current_balance' => 10000]);
        $destination = Account::factory()->create(['opening_balance' => 1000, 'current_balance' => 1000]);
        $service = app(AccountTransferService::class);
        [$outgoing] = $service->create($this->transferData($source, $destination, 500));

        $service->delete($outgoing);

        $this->assertDatabaseCount('transactions', 0);
        $this->assertSame('10000.00', $source->fresh()->current_balance);
        $this->assertSame('1000.00', $destination->fresh()->current_balance);
    }

    public function test_recalculate_balance_understands_transfer_direction(): void
    {
        $this->seed(CategorySeeder::class);
        $source = Account::factory()->create(['opening_balance' => 10000, 'current_balance' => 10000]);
        $destination = Account::factory()->create(['opening_balance' => 1000, 'current_balance' => 1000]);
        app(AccountTransferService::class)->create($this->transferData($source, $destination, 500));

        $source->update(['current_balance' => 0]);
        $destination->update(['current_balance' => 0]);

        $this->assertSame(9500.0, $source->recalculateBalance());
        $this->assertSame(1500.0, $destination->recalculateBalance());
    }

    public function test_store_creates_transfer_without_payment_method(): void
    {
        $this->seed(CategorySeeder::class);
        $source = Account::factory()->create(['opening_balance' => 10000, 'current_balance' => 10000]);
        $destination = Account::factory()->create(['opening_balance' => 1000, 'current_balance' => 1000]);

        $response = $this->post(route('transactions.store'), array_merge(
            $this->transferData($source, $destination, 500),
            ['transaction_type' => Transaction::TYPE_TRANSFER]
        ));

        $response->assertRedirect(route('transactions.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('transactions', 2);
        $this->assertSame('9500.00', $source->fresh()->current_balance);
        $this->assertSame('1500.00', $destination->fresh()->current_balance);
    }

    public function test_store_rejects_same_source_and_destination_account(): void
    {
        $this->seed(CategorySeeder::class);
        $account = Account::factory()->create();

        $response = $this->from(route('transactions.create'))->post(route('transactions.store'), array_merge(
            $this->transferData($account, $account, 500),
            ['transaction_type' => Transaction::TYPE_TRANSFER]
        ));

        $response->assertRedirect(route('transactions.create'))
            ->assertSessionHasErrors('transfer_to_account_id');
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_edit_prefills_destination_from_either_transfer_leg(): void
    {
        $this->seed(CategorySeeder::class);
        $source = Account::factory()->create();
        $destination = Account::factory()->create();
        [$outgoing, $incoming] = app(AccountTransferService::class)->create(
            $this->transferData($source, $destination, 500)
        );

        $this->get(route('transactions.edit', $outgoing))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('transaction.account_id', $source->id)
                ->where('transaction.transfer_to_account_id', $destination->id)
            );

        $this->get(route('transactions.edit', $incoming))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('transaction.account_id', $source->id)
                ->where('transaction.transfer_to_account_id', $destination->id)
            );
    }

    public function test_update_and_destroy_routes_mutate_the_complete_transfer(): void
    {
        $this->seed(CategorySeeder::class);
        $source = Account::factory()->create(['opening_balance' => 10000, 'current_balance' => 10000]);
        $destination = Account::factory()->create(['opening_balance' => 1000, 'current_balance' => 1000]);
        [$outgoing] = app(AccountTransferService::class)->create($this->transferData($source, $destination, 500));

        $this->put(route('transactions.update', $outgoing), array_merge(
            $this->transferData($source, $destination, 750),
            [
                'transaction_type' => Transaction::TYPE_TRANSFER,
                'expensed_date' => '2026-07-18',
            ]
        ))->assertRedirect(route('transactions.index'))->assertSessionHasNoErrors();

        $this->assertSame(2, Transaction::where('transfer_group_id', $outgoing->transfer_group_id)->count());
        $this->assertSame('9250.00', $source->fresh()->current_balance);
        $this->assertSame('1750.00', $destination->fresh()->current_balance);

        $this->delete(route('transactions.destroy', $outgoing))
            ->assertRedirect(route('transactions.index'));

        $this->assertDatabaseCount('transactions', 0);
        $this->assertSame('10000.00', $source->fresh()->current_balance);
        $this->assertSame('1000.00', $destination->fresh()->current_balance);
    }

    public function test_second_leg_failure_rolls_back_first_leg_and_balance_change(): void
    {
        $this->seed(CategorySeeder::class);
        $source = Account::factory()->create(['opening_balance' => 10000, 'current_balance' => 10000]);
        $destination = Account::factory()->create(['opening_balance' => 1000, 'current_balance' => 1000]);
        $incomingCategoryId = Category::where('code', Transaction::CATEGORY_TRANSFER_INCOMING)->value('id');
        $eventName = 'eloquent.creating: '.Transaction::class;

        Event::listen($eventName, function (Transaction $transaction) use ($incomingCategoryId) {
            if ($transaction->category_id === $incomingCategoryId) {
                throw new RuntimeException('Forced incoming leg failure.');
            }
        });

        try {
            app(AccountTransferService::class)->create($this->transferData($source, $destination, 500));
            $this->fail('The forced incoming leg failure was not raised.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Forced incoming leg failure.', $exception->getMessage());
        } finally {
            Event::forget($eventName);
        }

        $this->assertDatabaseCount('transactions', 0);
        $this->assertSame('10000.00', $source->fresh()->current_balance);
        $this->assertSame('1000.00', $destination->fresh()->current_balance);
    }

    public function test_bulk_destroy_deletes_each_selected_transfer_group_once(): void
    {
        $this->seed(CategorySeeder::class);
        $source = Account::factory()->create(['opening_balance' => 10000, 'current_balance' => 10000]);
        $destination = Account::factory()->create(['opening_balance' => 1000, 'current_balance' => 1000]);
        [$outgoing, $incoming] = app(AccountTransferService::class)->create(
            $this->transferData($source, $destination, 500)
        );

        $this->post(route('transactions.bulk-destroy'), ['ids' => [$outgoing->id, $incoming->id]])
            ->assertRedirect(route('transactions.index'));

        $this->assertDatabaseCount('transactions', 0);
        $this->assertSame('10000.00', $source->fresh()->current_balance);
        $this->assertSame('1000.00', $destination->fresh()->current_balance);
    }

    public function test_store_redirect_uses_the_current_request_origin(): void
    {
        $this->seed(CategorySeeder::class);
        $source = Account::factory()->create();
        $destination = Account::factory()->create();

        $response = $this->post('http://127.0.0.1:8001/transactions', array_merge(
            $this->transferData($source, $destination, 500),
            ['transaction_type' => Transaction::TYPE_TRANSFER]
        ));

        $response->assertHeader('Location', 'http://127.0.0.1:8001/transactions');
    }

    private function transferData(Account $source, Account $destination, int $amount): array
    {
        return [
            'account_id' => $source->id,
            'transfer_to_account_id' => $destination->id,
            'amount' => $amount,
            'description' => 'Move savings',
            'transaction_date' => '2026-07-18',
            'transaction_time' => '10:30',
        ];
    }
}
