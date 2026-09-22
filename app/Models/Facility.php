<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Facility extends Model
{
    use HasFactory;

    protected $fillable = ['code','name','location','latitude','longitude','capacity','resources','is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function reservations(): HasMany
    {
        return $this->hasMany(FacilityReservation::class);
    }
}
