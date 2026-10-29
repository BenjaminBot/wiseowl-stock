<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesChannel extends Model
{
    protected $fillable = ['name', 'type', 'is_system'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }
}
