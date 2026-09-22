<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SignatureAudit extends Model
{
    protected $fillable = ['user_id', 'changed_by', 'source', 'previous_hash', 'new_hash', 'changed_at'];
    protected $casts = ['changed_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }
    public function changer() { return $this->belongsTo(User::class, 'changed_by'); }
}
