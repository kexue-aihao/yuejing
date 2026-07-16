<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id', 'action', 'auditable_type', 'auditable_id', 'metadata', 'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function user() { return $this->belongsTo(User::class); }

    public function auditable() { return $this->morphTo(); }
}
