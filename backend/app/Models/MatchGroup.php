<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MatchGroup extends Model
{
    protected $fillable = [
        'mode', 'driver_id', 'status', 'total_distance_km', 'total_fare',
        'commission_amount', 'planned_route', 'depart_at',
    ];

    protected function casts(): array
    {
        return [
            'planned_route' => 'array',
            'depart_at' => 'datetime',
            'total_distance_km' => 'float',
            'total_fare' => 'float',
            'commission_amount' => 'float',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(GroupMember::class)->orderBy('pickup_order');
    }

    public function activeMembers(): HasMany
    {
        return $this->members()->where('status', '!=', 'cancelled');
    }
}
