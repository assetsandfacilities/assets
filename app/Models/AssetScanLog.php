<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssetScanLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_id','user_id','scanned_floor_id','scanned_room_id','expected_room','scanned_room',
        'latitude','longitude','distance_meters','status','notes','resolved_at','resolved_by'
    ];

    protected $casts = ['resolved_at' => 'datetime'];

    public function item() { return $this->belongsTo(Item::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function resolver() { return $this->belongsTo(User::class, 'resolved_by'); }
    public function scannedFloorRef() { return $this->belongsTo(Floor::class, 'scanned_floor_id'); }
    public function scannedRoomRef() { return $this->belongsTo(Room::class, 'scanned_room_id'); }

    public function isUnresolvedMismatch(): bool
    {
        return $this->status === 'mismatch' && $this->resolved_at === null;
    }
}
