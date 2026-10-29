<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

abstract class ImmutableRecord extends Model
{
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Posted history is immutable; use a compensating operation.'));
        static::deleting(fn () => throw new LogicException('Posted history cannot be deleted.'));
    }
}
