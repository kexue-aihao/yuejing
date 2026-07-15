<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserTwoFactorSetting extends Model
{
    protected $fillable = ['user_id', 'enabled', 'secret', 'recovery_codes', 'confirmed_at'];

    protected $hidden = ['secret', 'recovery_codes'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'confirmed_at' => 'datetime'];
    }

    public function user() { return $this->belongsTo(User::class); }
}
