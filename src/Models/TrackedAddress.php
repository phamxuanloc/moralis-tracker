<?php

namespace Locpx\MoralisTracker\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrackedAddress extends Model
{
    protected $fillable = [
        'address',
        'label',
        'is_active',
        'last_synced_block',
        'last_synced_at',
        'meta',
    ];

    protected $casts = [
        'is_active'        => 'boolean',
        'last_synced_block' => 'integer',
        'last_synced_at'   => 'datetime',
        'meta'             => 'array',
    ];

    public function getTable(): string
    {
        return config('moralis.table_names.tracked_addresses', 'tracked_addresses');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BscTransaction::class, 'tracked_address', 'address');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function markSynced(int $lastBlock): void
    {
        $this->update([
            'last_synced_block' => $lastBlock,
            'last_synced_at'    => now(),
        ]);
    }
}
