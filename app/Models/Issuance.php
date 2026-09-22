<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Issuance extends Model
{
    use HasFactory;
    protected $fillable = ['requisition_id', 'issued_by', 'received_by', 'issued_at', 'status', 'remarks'];

    protected $casts = [
        'issued_at' => 'datetime',
    ];

    public function requisition() { return $this->belongsTo(Requisition::class); }
    public function issuer() { return $this->belongsTo(User::class, 'issued_by'); }
    public function receiver() { return $this->belongsTo(User::class, 'received_by'); }
}
