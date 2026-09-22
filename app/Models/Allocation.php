<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Allocation extends Model
{
    use HasFactory;
    protected $fillable = ['department_id', 'item_type', 'max_quantity', 'period_label'];

    public function department() { return $this->belongsTo(Department::class); }
}
