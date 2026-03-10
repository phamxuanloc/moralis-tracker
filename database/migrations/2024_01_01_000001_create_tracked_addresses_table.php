<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('moralis.table_names.tracked_addresses', 'tracked_addresses');

        Schema::create($tableName, function (Blueprint $table) {
            $table->id();
            $table->string('address', 42)->unique()->comment('BSC wallet address (0x...)');
            $table->string('label')->nullable()->comment('Human-readable label for this address');
            $table->boolean('is_active')->default(true)->comment('Whether to actively sync this address');
            $table->unsignedBigInteger('last_synced_block')->default(0)->comment('Last block successfully synced');
            $table->timestamp('last_synced_at')->nullable()->comment('Timestamp of last successful sync');
            $table->json('meta')->nullable()->comment('Extra metadata');
            $table->timestamps();

            $table->index('is_active');
            $table->index('last_synced_block');
        });
    }

    public function down(): void
    {
        $tableName = config('moralis.table_names.tracked_addresses', 'tracked_addresses');
        Schema::dropIfExists($tableName);
    }
};
