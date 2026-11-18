<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupMember extends Model
{
    protected $fillable = [
        'match_group_id', 'passenger_id', 'ride_request_id', 'pickup_order',
        'distance_km', 'fare_share', 'boarding_pin', 'status',
    ];

    protected $hidden = ['boarding_pin'];

    protected function casts(): array
    {
        return ['distance_km' => 'float', 'fare_share' => 'float'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(MatchGroup::class, 'match_group_id');
    }

    public function passenger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'passenger_id');
    }

    public function rideRequest(): BelongsTo
    {
        return $this->belongsTo(RideRequest::class);
    }
}
