<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RateCard extends Model
{
    protected $fillable = ['mode', 'base_fare', 'per_km', 'commission_pct', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
