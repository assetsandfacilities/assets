<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequisitionItem extends Model
{
    use HasFactory;
    protected $fillable = ['requisition_id', 'item_id', 'quantity_requested', 'quantity_approved', 'remarks', 'unit_price', 'total_amount'];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function requisition() { return $this->belongsTo(Requisition::class); }
    public function item() { return $this->belongsTo(Item::class); }
}
