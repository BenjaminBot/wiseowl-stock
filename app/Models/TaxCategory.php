<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxCategory extends Model
{
    protected $fillable = ['name', 'rate'];

    protected function casts(): array
    {
        return ['rate' => 'decimal:4'];
    }
}
