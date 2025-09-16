<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Update categories with appropriate Font Awesome icons
        $updates = [
            // Main Categories
            2 => ['icon' => 'fas fa-car', 'color' => '#795548'],
            3 => ['icon' => 'fas fa-film', 'color' => '#e83e8c'],
            4 => ['icon' => 'fas fa-users', 'color' => '#6c757d'],
            5 => ['icon' => 'fas fa-utensils', 'color' => '#fd7e14'],
            6 => ['icon' => 'fas fa-heartbeat', 'color' => '#dc3545'],
            7 => ['icon' => 'fas fa-laptop-house', 'color' => '#17a2b8'],
            8 => ['icon' => 'fas fa-home', 'color' => '#6c757d'],
            9 => ['icon' => 'fas fa-shield-alt', 'color' => '#6c757d'],
            10 => ['icon' => 'fas fa-chart-line', 'color' => '#28a745'],
            11 => ['icon' => 'fas fa-hand-holding-usd', 'color' => '#6c757d'],
            12 => ['icon' => 'fas fa-question-circle', 'color' => '#6c757d'],
            13 => ['icon' => 'fas fa-user', 'color' => '#6f42c1'],
            14 => ['icon' => 'fas fa-calculator', 'color' => '#6c757d'],
            15 => ['icon' => 'fas fa-plane', 'color' => '#6610f2'],
            16 => ['icon' => 'fas fa-bolt', 'color' => '#ffc107'],
            17 => ['icon' => 'fas fa-umbrella-beach', 'color' => '#20c997'],
            90 => ['icon' => 'fas fa-arrow-up', 'color' => '#28a745'],
            91 => ['icon' => 'fas fa-exchange-alt', 'color' => '#6c757d'],
            111 => ['icon' => 'fas fa-money-check-alt', 'color' => '#6c757d'],

            // Automobile Subcategories
            18 => ['icon' => 'fas fa-road', 'color' => '#795548'],
            19 => ['icon' => 'fas fa-gas-pump', 'color' => '#795548'],
            20 => ['icon' => 'fas fa-file-contract', 'color' => '#795548'],
            21 => ['icon' => 'fas fa-wrench', 'color' => '#795548'],
            22 => ['icon' => 'fas fa-tachometer-alt', 'color' => '#795548'],
            23 => ['icon' => 'fas fa-clipboard-list', 'color' => '#795548'],
            92 => ['icon' => 'fas fa-question-circle', 'color' => '#795548'],

            // Entertainment Subcategories
            24 => ['icon' => 'fas fa-film', 'color' => '#e83e8c'],
            25 => ['icon' => 'fas fa-glass-cheers', 'color' => '#e83e8c'],
            26 => ['icon' => 'fas fa-tv', 'color' => '#e83e8c'],
            93 => ['icon' => 'fas fa-question-circle', 'color' => '#e83e8c'],

            // Family Subcategories
            27 => ['icon' => 'fas fa-baby', 'color' => '#6c757d'],
            28 => ['icon' => 'fas fa-graduation-cap', 'color' => '#6c757d'],
            29 => ['icon' => 'fas fa-puzzle-piece', 'color' => '#6c757d'],
            94 => ['icon' => 'fas fa-question-circle', 'color' => '#6c757d'],

            // Food Subcategories
            30 => ['icon' => 'fas fa-shopping-cart', 'color' => '#fd7e14'],
            31 => ['icon' => 'fas fa-mobile-alt', 'color' => '#fd7e14'],
            32 => ['icon' => 'fas fa-utensils', 'color' => '#fd7e14'],
            33 => ['icon' => 'fas fa-cookie-bite', 'color' => '#fd7e14'],
            95 => ['icon' => 'fas fa-question-circle', 'color' => '#fd7e14'],

            // Health Care Subcategories
            34 => ['icon' => 'fas fa-tooth', 'color' => '#dc3545'],
            35 => ['icon' => 'fas fa-eye', 'color' => '#dc3545'],
            36 => ['icon' => 'fas fa-shield-alt', 'color' => '#dc3545'],
            37 => ['icon' => 'fas fa-stethoscope', 'color' => '#dc3545'],
            38 => ['icon' => 'fas fa-apple-alt', 'color' => '#dc3545'],
            39 => ['icon' => 'fas fa-pills', 'color' => '#dc3545'],

            // Home Office Subcategories
            40 => ['icon' => 'fas fa-laptop', 'color' => '#17a2b8'],
            41 => ['icon' => 'fas fa-plug', 'color' => '#17a2b8'],
            42 => ['icon' => 'fas fa-chair', 'color' => '#17a2b8'],
            43 => ['icon' => 'fas fa-paperclip', 'color' => '#17a2b8'],
            44 => ['icon' => 'fas fa-pen', 'color' => '#17a2b8'],
            96 => ['icon' => 'fas fa-question-circle', 'color' => '#17a2b8'],

            // Household Subcategories
            45 => ['icon' => 'fas fa-blender', 'color' => '#6c757d'],
            46 => ['icon' => 'fas fa-shopping-basket', 'color' => '#6c757d'],
            47 => ['icon' => 'fas fa-hammer', 'color' => '#6c757d'],
            48 => ['icon' => 'fas fa-home', 'color' => '#6c757d'],
            49 => ['icon' => 'fas fa-toolbox', 'color' => '#6c757d'],
            50 => ['icon' => 'fas fa-boxes', 'color' => '#6c757d'],
            51 => ['icon' => 'fas fa-envelope', 'color' => '#6c757d'],
            52 => ['icon' => 'fas fa-home', 'color' => '#6c757d'],
            97 => ['icon' => 'fas fa-question-circle', 'color' => '#6c757d'],

            // Insurance Subcategories
            53 => ['icon' => 'fas fa-car', 'color' => '#6c757d'],
            54 => ['icon' => 'fas fa-heartbeat', 'color' => '#6c757d'],
            55 => ['icon' => 'fas fa-home', 'color' => '#6c757d'],
            56 => ['icon' => 'fas fa-user-shield', 'color' => '#6c757d'],

            // Investment Subcategories
            57 => ['icon' => 'fas fa-chart-pie', 'color' => '#28a745'],
            58 => ['icon' => 'fas fa-piggy-bank', 'color' => '#28a745'],
            59 => ['icon' => 'fas fa-piggy-bank', 'color' => '#28a745'],
            60 => ['icon' => 'fas fa-piggy-bank', 'color' => '#28a745'],

            // Loans Subcategories
            61 => ['icon' => 'fas fa-home', 'color' => '#6c757d'],
            62 => ['icon' => 'fas fa-home', 'color' => '#6c757d'],
            63 => ['icon' => 'fas fa-graduation-cap', 'color' => '#6c757d'],
            98 => ['icon' => 'fas fa-question-circle', 'color' => '#6c757d'],
            99 => ['icon' => 'fas fa-car', 'color' => '#6c757d'],

            // Other Category Subcategories
            100 => ['icon' => 'fas fa-question-circle', 'color' => '#6c757d'],

            // Personal Subcategories
            64 => ['icon' => 'fas fa-tshirt', 'color' => '#6f42c1'],
            65 => ['icon' => 'fas fa-hand-holding-heart', 'color' => '#6f42c1'],
            66 => ['icon' => 'fas fa-gift', 'color' => '#6f42c1'],
            67 => ['icon' => 'fas fa-spa', 'color' => '#6f42c1'],
            101 => ['icon' => 'fas fa-question-circle', 'color' => '#6f42c1'],

            // Tax Subcategories
            68 => ['icon' => 'fas fa-home', 'color' => '#6c757d'],

            // Travel Subcategories
            69 => ['icon' => 'fas fa-plane', 'color' => '#6610f2'],
            70 => ['icon' => 'fas fa-bus', 'color' => '#6610f2'],
            71 => ['icon' => 'fas fa-car', 'color' => '#6610f2'],
            72 => ['icon' => 'fas fa-bed', 'color' => '#6610f2'],
            73 => ['icon' => 'fas fa-taxi', 'color' => '#6610f2'],
            74 => ['icon' => 'fas fa-train', 'color' => '#6610f2'],

            // Utilities Subcategories
            75 => ['icon' => 'fas fa-tv', 'color' => '#ffc107'],
            76 => ['icon' => 'fas fa-bolt', 'color' => '#ffc107'],
            77 => ['icon' => 'fas fa-trash', 'color' => '#ffc107'],
            78 => ['icon' => 'fas fa-fire', 'color' => '#ffc107'],
            79 => ['icon' => 'fas fa-wifi', 'color' => '#ffc107'],
            80 => ['icon' => 'fas fa-phone', 'color' => '#ffc107'],
            81 => ['icon' => 'fas fa-tint', 'color' => '#ffc107'],

            // Vacation Subcategories
            82 => ['icon' => 'fas fa-question-circle', 'color' => '#20c997'],
            83 => ['icon' => 'fas fa-bus', 'color' => '#20c997'],
            102 => ['icon' => 'fas fa-question-circle', 'color' => '#20c997'],

            // Income Subcategories
            84 => ['icon' => 'fas fa-money-bill-wave', 'color' => '#28a745'],
            85 => ['icon' => 'fas fa-laptop', 'color' => '#17a2b8'],
            86 => ['icon' => 'fas fa-chart-line', 'color' => '#ffc107'],
            87 => ['icon' => 'fas fa-briefcase', 'color' => '#6f42c1'],
            88 => ['icon' => 'fas fa-home', 'color' => '#fd7e14'],
            89 => ['icon' => 'fas fa-percentage', 'color' => '#20c997'],
            103 => ['icon' => 'fas fa-balance-scale', 'color' => '#28a745'],
            104 => ['icon' => 'fas fa-money-bill-wave', 'color' => '#28a745'],
            105 => ['icon' => 'fas fa-chart-line', 'color' => '#28a745'],
            106 => ['icon' => 'fas fa-home', 'color' => '#28a745'],
            107 => ['icon' => 'fas fa-piggy-bank', 'color' => '#28a745'],
            108 => ['icon' => 'fas fa-question-circle', 'color' => '#28a745'],
            109 => ['icon' => 'fas fa-cookie-bite', 'color' => '#28a745'],
            110 => ['icon' => 'fas fa-exchange-alt', 'color' => '#28a745'],
        ];

        // Update each category
        foreach ($updates as $categoryId => $data) {
            DB::table('categories')
                ->where('id', $categoryId)
                ->update($data);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Reset all icons to generic folder icon and original colors
        $rollback = [
            // Main Categories - Reset to original icons
            2 => ['icon' => 'fas fa-car', 'color' => '#795548'],
            3 => ['icon' => 'fas fa-film', 'color' => '#e83e8c'],
            4 => ['icon' => 'fas fa-folder', 'color' => '#6c757d'],
            5 => ['icon' => 'fas fa-utensils', 'color' => '#fd7e14'],
            6 => ['icon' => 'fas fa-folder', 'color' => '#6c757d'],
            7 => ['icon' => 'fas fa-folder', 'color' => '#6c757d'],
            8 => ['icon' => 'fas fa-folder', 'color' => '#6c757d'],
            9 => ['icon' => 'fas fa-folder', 'color' => '#6c757d'],
            10 => ['icon' => 'fas fa-folder', 'color' => '#6c757d'],
            11 => ['icon' => 'fas fa-folder', 'color' => '#6c757d'],
            12 => ['icon' => 'fas fa-question-circle', 'color' => '#6c757d'],
            13 => ['icon' => 'fas fa-user', 'color' => '#6f42c1'],
            14 => ['icon' => 'fas fa-folder', 'color' => '#6c757d'],
            15 => ['icon' => 'fas fa-plane', 'color' => '#6610f2'],
            16 => ['icon' => 'fas fa-bolt', 'color' => '#ffc107'],
            17 => ['icon' => 'fas fa-folder', 'color' => '#6c757d'],
            90 => ['icon' => 'fas fa-arrow-up', 'color' => '#28a745'],
            91 => ['icon' => 'fas fa-exchange-alt', 'color' => '#6c757d'],
            111 => ['icon' => null, 'color' => null],
        ];

        // Update subcategories to have null icons (original state)
        $subcategoriesToReset = [
            18, 19, 20, 21, 22, 23, 92, 24, 25, 26, 93, 27, 28, 29, 94, 30, 31, 32, 33, 95,
            34, 35, 36, 37, 38, 39, 40, 41, 42, 43, 44, 96, 45, 46, 47, 48, 49, 50, 51, 52, 97,
            53, 54, 55, 56, 57, 58, 59, 60, 61, 62, 63, 98, 99, 100, 64, 65, 66, 67, 101,
            68, 69, 70, 71, 72, 73, 74, 75, 76, 77, 78, 79, 80, 81, 82, 83, 102,
            103, 104, 105, 106, 107, 108, 109, 110
        ];

        // Reset main categories
        foreach ($rollback as $categoryId => $data) {
            DB::table('categories')
                ->where('id', $categoryId)
                ->update($data);
        }

        // Reset subcategories to null icons
        DB::table('categories')
            ->whereIn('id', $subcategoriesToReset)
            ->update(['icon' => null, 'color' => null]);
    }
};