<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\RequisitionItem;

class Department extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'code', 'opex_limit'];
    protected $casts = ['restrict_supply_requests' => 'boolean'];

    public function users() { return $this->hasMany(User::class); }
    public function allocations() { return $this->hasMany(Allocation::class); }
    public function requisitions() { return $this->hasMany(Requisition::class); }

    /**
     * Total OPEX pesos already committed against this department's budget --
     * every submitted charge slip counts against the budget as soon as it is
     * filed, except ones that were rejected (their amount is freed back up)
     * or deleted. Approval stage does not change the amount charged; the
     * department is charged for what the requestor asked for, not the
     * (possibly lower) quantity Asset Management ends up approving.
     */
    public function opexConsumed(): float
    {
        return (float) RequisitionItem::query()
            ->whereHas('requisition', function ($query) {
                $query->where('department_id', $this->id)
                    ->where('status', '!=', 'rejected');
            })
            ->sum('total_amount');
    }

    public function opexRemaining(): float
    {
        return round((float) $this->opex_limit - $this->opexConsumed(), 2);
    }
}
