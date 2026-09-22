<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RevokedJwtToken extends Model
{
    protected $fillable = [
        'jti', 'user_id', 'expires_at', 'revoked_at', 'reason',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];
}
