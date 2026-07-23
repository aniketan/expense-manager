<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('transactions', 'payee_payer')) {
                $table->string('payee_payer')->nullable()->after('description');
            }

            if (! Schema::hasColumn('transactions', 'tax')) {
                $table->decimal('tax', 15, 2)->nullable()->after('amount');
            }

            if (! Schema::hasColumn('transactions', 'status')) {
                $table->string('status', 20)->default('Cleared')->after('transaction_time');
            }

            if (! Schema::hasColumn('transactions', 'notes')) {
                $table->text('notes')->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            foreach (['notes', 'status', 'tax', 'payee_payer'] as $column) {
                if (Schema::hasColumn('transactions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
