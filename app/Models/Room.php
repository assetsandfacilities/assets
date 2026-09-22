<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    protected $fillable = ['floor_id', 'name', 'code'];

    public function floor()
    {
        return $this->belongsTo(Floor::class);
    }

    public function items()
    {
        return $this->hasMany(Item::class);
    }

    public function label(): string
    {
        return $this->code ? "{$this->name} ({$this->code})" : $this->name;
    }
}
