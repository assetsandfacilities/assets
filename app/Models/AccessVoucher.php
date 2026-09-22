<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccessVoucher extends Model
{
    use HasFactory;

    protected $fillable = [
        'code_hash', 'code_hint', 'voucher_type', 'approver_type', 'department_id',
        'generated_by', 'expires_at', 'used_at', 'used_by', 'revoked_at', 'revoked_by',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function department() { return $this->belongsTo(Department::class); }
    public function generator() { return $this->belongsTo(User::class, 'generated_by'); }
    public function usedBy() { return $this->belongsTo(User::class, 'used_by'); }
    public function revokedBy() { return $this->belongsTo(User::class, 'revoked_by'); }

    public function isUsable(): bool
    {
        return !$this->used_at && !$this->revoked_at && $this->expires_at?->isFuture();
    }

    public static function normalizeCode(string $code): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($code)));
    }

    public static function hashCode(string $code): string
    {
        return hash('sha256', self::normalizeCode($code));
    }
}
