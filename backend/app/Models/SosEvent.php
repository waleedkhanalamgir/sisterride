<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SosEvent extends Model
{
    protected $fillable = ['trip_id', 'user_id', 'lat', 'lng', 'status', 'handled_by', 'admin_note'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
