<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Account>
 */
class AccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $accountTypes = ['savings', 'current', 'credit_card', 'cash', 'investment'];
        $banks = ['SBI', 'HDFC', 'ICICI', 'Axis', 'Kotak', 'PNB', 'BOB'];
        
        $openingBalance = $this->faker->randomFloat(2, 1000, 100000);
        
        return [
            'code' => strtoupper($this->faker->unique()->lexify('????')),
            'name' => $this->faker->company . ' Account',
            'type' => $this->faker->randomElement($accountTypes),
            'bank_name' => $this->faker->randomElement($banks),
            'account_number' => $this->faker->numerify('############'),
            'ifsc_code' => strtoupper($this->faker->lexify('????0??????')),
            'opening_balance' => $openingBalance,
            'current_balance' => $openingBalance + $this->faker->randomFloat(2, -5000, 10000),
            'credit_limit' => $this->faker->randomFloat(2, 0, 500000),
            'is_active' => $this->faker->boolean(90), // 90% chance of being active
        ];
    }
}
