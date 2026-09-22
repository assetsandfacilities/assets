<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Floor extends Model
{
    protected $fillable = ['name', 'sort_order'];

    public function rooms()
    {
        return $this->hasMany(Room::class)->orderBy('name');
    }

    public function items()
    {
        return $this->hasMany(Item::class);
    }
}
