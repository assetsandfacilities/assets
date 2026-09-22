<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryUsageLog extends Model
{
    use HasFactory;

    protected $fillable = ['item_id','requisition_id','usage_date','quantity_used','source','remarks'];
    protected $casts = ['usage_date' => 'date'];

    public function item() { return $this->belongsTo(Item::class); }
    public function requisition() { return $this->belongsTo(Requisition::class); }
}
