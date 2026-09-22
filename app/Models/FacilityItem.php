<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Database-driven catalogue behind the "Other Items Needed and Services"
 * checklist on the reservation / activity proposal form. Managed entirely by
 * the FMO Super Admin -- nothing here is hard-coded in the views anymore.
 */
class FacilityItem extends Model
{
    use HasFactory;

    public const TYPE_ITEM = 'item';
    public const TYPE_SERVICE = 'service';

    protected $fillable = ['name', 'type', 'unit', 'description', 'allows_quantity', 'is_active', 'sort_order'];

    protected $casts = [
        'allows_quantity' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeItems(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_ITEM);
    }

    public function scopeServices(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_SERVICE);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function typeLabel(): string
    {
        return $this->type === self::TYPE_SERVICE ? 'Service' : 'Item';
    }

    public static function types(): array
    {
        return [self::TYPE_ITEM, self::TYPE_SERVICE];
    }
}
