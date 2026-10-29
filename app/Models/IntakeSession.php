<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntakeSession extends Model
{
    protected $fillable = ['reference', 'supplier_id', 'receiving_location_id', 'destinations', 'default_condition', 'website_separated', 'currency', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['destinations' => 'array', 'website_separated' => 'boolean'];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function receivingLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'receiving_location_id');
    }
}
