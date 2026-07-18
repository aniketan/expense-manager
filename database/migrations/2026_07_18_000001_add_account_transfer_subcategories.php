<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $root = DB::table('categories')
            ->whereNull('parent_id')
            ->whereRaw('LOWER(code) = ?', ['account_transfer'])
            ->first();

        if (! $root) {
            return;
        }

        $duplicates = DB::table('categories')
            ->whereNotNull('parent_id')
            ->whereRaw('LOWER(code) = ?', ['account_transfer'])
            ->get();

        foreach ($duplicates as $duplicate) {
            if (DB::table('transactions')->where('category_id', $duplicate->id)->exists()) {
                throw new RuntimeException('Cannot remove duplicate ACCOUNT_TRANSFER category because transactions reference it.');
            }

            DB::table('categories')->where('id', $duplicate->id)->delete();
        }

        $now = now();
        foreach ([
            ['name' => 'Transfer Incoming', 'code' => 'TRANSFER_INCOMING'],
            ['name' => 'Transfer Outgoing', 'code' => 'TRANSFER_OUTGOING'],
        ] as $category) {
            DB::table('categories')->updateOrInsert(
                ['parent_id' => $root->id, 'code' => $category['code']],
                [
                    'name' => $category['name'],
                    'description' => null,
                    'icon' => 'fas fa-exchange-alt',
                    'color' => '#6c757d',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        $rootIds = DB::table('categories')
            ->whereNull('parent_id')
            ->whereRaw('LOWER(code) = ?', ['account_transfer'])
            ->pluck('id');

        DB::table('categories')
            ->whereIn('parent_id', $rootIds)
            ->whereIn('code', ['TRANSFER_INCOMING', 'TRANSFER_OUTGOING'])
            ->delete();
    }
};
