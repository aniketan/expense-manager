<?php

namespace Tests\Feature;

use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AccountTransferSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_seeder_creates_one_transfer_root_with_incoming_and_outgoing_children(): void
    {
        $this->seed(CategorySeeder::class);

        $roots = DB::table('categories')
            ->whereNull('parent_id')
            ->whereRaw('LOWER(code) = ?', ['account_transfer'])
            ->get();

        $this->assertCount(1, $roots);
        $this->assertSame(
            1,
            DB::table('categories')->whereRaw('LOWER(code) = ?', ['account_transfer'])->count()
        );
        $this->assertEqualsCanonicalizing(
            ['TRANSFER_INCOMING', 'TRANSFER_OUTGOING'],
            DB::table('categories')
                ->where('parent_id', $roots->first()->id)
                ->pluck('code')
                ->all()
        );
    }

    public function test_transactions_table_has_nullable_transfer_group_id(): void
    {
        $this->assertTrue(Schema::hasColumn('transactions', 'transfer_group_id'));

        $column = collect(Schema::getColumns('transactions'))->firstWhere('name', 'transfer_group_id');

        $this->assertTrue($column['nullable']);
    }
}
