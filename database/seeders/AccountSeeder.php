<?php

namespace Database\Seeders;

use App\Models\Account;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create some default accounts
        Account::create([
            'code' => 'CASH',
            'name' => 'Cash Account',
            'type' => 'cash',
            'opening_balance' => 5000.00,
            'current_balance' => 5000.00,
            'is_active' => true,
        ]);

        Account::create([
            'code' => 'SBI01',
            'name' => 'SBI Savings Account',
            'type' => 'savings',
            'bank_name' => 'State Bank of India',
            'account_number' => '12345678901',
            'ifsc_code' => 'SBIN0001234',
            'opening_balance' => 25000.00,
            'current_balance' => 28500.00,
            'is_active' => true,
        ]);

        Account::create([
            'code' => 'HDFC01',
            'name' => 'HDFC Credit Card',
            'type' => 'credit_card',
            'bank_name' => 'HDFC Bank',
            'account_number' => '4532********1234',
            'opening_balance' => 0.00,
            'current_balance' => -15000.00,
            'credit_limit' => 200000.00,
            'is_active' => true,
        ]);

        // Create additional random accounts
        Account::factory(5)->create();
    }
}
