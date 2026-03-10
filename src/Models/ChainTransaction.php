<?php

namespace Locpx\MoralisTracker\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChainTransaction extends Model
{
    protected $fillable = [
        'tx_hash',
        'type',
        'chain',
        'tracked_address',
        'block_number',
        'block_timestamp',
        'transaction_index',
        'from_address',
        'to_address',
        'value',
        'value_native',
        'gas',
        'gas_price',
        'gas_used',
        'tx_fee_native',
        'nonce',
        'input',
        'is_error',
        'tx_receipt_status',
        'contract_address',
        'token_name',
        'token_symbol',
        'token_decimal',
        'raw_data',
    ];

    protected $casts = [
        'block_timestamp'   => 'datetime',
        'block_number'      => 'integer',
        'transaction_index' => 'integer',
        'value_native'      => 'decimal:18',
        'tx_fee_native'     => 'decimal:18',
        'is_error'          => 'boolean',
        'token_decimal'     => 'integer',
        'raw_data'          => 'array',
    ];

    public function getTable(): string
    {
        return config('moralis.table_names.chain_transactions', 'chain_transactions');
    }

    public function trackedAddress(): BelongsTo
    {
        return $this->belongsTo(TrackedAddress::class, 'tracked_address', 'address');
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeOnChain($query, string $chain)
    {
        return $query->where('chain', $chain);
    }

    public function scopeForAddress($query, string $address)
    {
        return $query->where('tracked_address', strtolower($address));
    }

    public function scopeIncoming($query, string $address)
    {
        return $query->where('to_address', strtolower($address));
    }

    public function scopeOutgoing($query, string $address)
    {
        return $query->where('from_address', strtolower($address));
    }
}
