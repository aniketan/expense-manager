<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statement_ledger_bundles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('ledger_transaction_id')->constrained('transactions')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('statement_ledger_bundle_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bundle_id')->constrained('statement_ledger_bundles')->cascadeOnDelete();
            $table->unsignedInteger('statement_sequence')->nullable();
            $table->date('statement_date');
            $table->decimal('amount', 14, 2);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statement_ledger_bundle_rows');
        Schema::dropIfExists('statement_ledger_bundles');
    }
};
