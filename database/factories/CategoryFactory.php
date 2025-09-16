<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $categoryNames = [
            'Food & Dining', 'Transportation', 'Shopping', 'Entertainment',
            'Bills & Utilities', 'Healthcare', 'Education', 'Travel',
            'Salary', 'Business Income', 'Investment Returns', 'Freelancing',
            'Gifts', 'Personal Care', 'Home & Garden', 'Auto & Transport'
        ];
        
        $icons = [
            'utensils', 'car', 'shopping-cart', 'film',
            'receipt', 'heart', 'graduation-cap', 'plane',
            'wallet', 'briefcase', 'chart-line', 'laptop',
            'gift', 'user', 'home', 'car-side'
        ];
        
        $colors = [
            '#3B82F6', '#EF4444', '#10B981', '#F59E0B',
            '#8B5CF6', '#06B6D4', '#84CC16', '#F97316',
            '#EC4899', '#6366F1', '#14B8A6', '#F43F5E',
            '#8B5A2B', '#059669', '#7C3AED', '#DC2626'
        ];
        
        return [
            'name' => $this->faker->randomElement($categoryNames),
            'code' => strtoupper($this->faker->unique()->lexify('???')),
            'description' => $this->faker->sentence(),
            'icon' => $this->faker->randomElement($icons),
            'color' => $this->faker->randomElement($colors),
            'is_active' => $this->faker->boolean(90), // 90% chance of being active
        ];
    }

    /**
     * Indicate that the category is a parent category.
     */
    public function parent(): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => null,
        ]);
    }

    /**
     * Indicate that the category is a child category.
     */
    public function child(): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => Category::factory()->parent(),
        ]);
    }

    /**
     * Indicate that the category is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
