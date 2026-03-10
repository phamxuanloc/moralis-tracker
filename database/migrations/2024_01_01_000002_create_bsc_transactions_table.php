<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('moralis.table_names.bsc_transactions', 'bsc_transactions');

        Schema::create($tableName, function (Blueprint $table) {
            $table->id();

            $table->string('tx_hash', 66)->comment('Transaction hash');
            $table->string('type', 20)->default('normal')->comment('normal|internal|token|nft');

            $table->string('tracked_address', 42)->comment('The address being tracked');
            $table->unsignedBigInteger('block_number');
            $table->timestamp('block_timestamp')->nullable();
            $table->unsignedInteger('transaction_index')->nullable();

            $table->string('from_address', 42)->nullable();
            $table->string('to_address', 42)->nullable();

            $table->string('value', 78)->default('0')->comment('Value in wei');
            $table->decimal('value_bnb', 30, 18)->default(0)->comment('Value in BNB');

            $table->string('gas', 20)->nullable();
            $table->string('gas_price', 30)->nullable();
            $table->string('gas_used', 20)->nullable();
            $table->decimal('tx_fee_bnb', 30, 18)->default(0)->comment('Fee in BNB');

            $table->string('nonce', 20)->nullable();
            $table->text('input')->nullable()->comment('Input data / method call');
            $table->boolean('is_error')->default(false);
            $table->string('tx_receipt_status', 5)->nullable();

            $table->string('contract_address', 42)->nullable()->comment('Contract involved (for token txs)');
            $table->string('token_name')->nullable();
            $table->string('token_symbol', 30)->nullable();
            $table->unsignedInteger('token_decimal')->nullable();

            $table->json('raw_data')->nullable()->comment('Full raw response from BscScan');
            $table->timestamps();

            $table->unique(['tx_hash', 'type', 'tracked_address'], 'unique_tx_per_address');
            $table->index('tracked_address');
            $table->index('block_number');
            $table->index('from_address');
            $table->index('to_address');
            $table->index('type');
            $table->index('block_timestamp');
        });
    }

    public function down(): void
    {
        $tableName = config('moralis.table_names.bsc_transactions', 'bsc_transactions');
        Schema::dropIfExists($tableName);
    }
};
