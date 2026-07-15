<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'type', 'updated_by'];

    public function updater() { return $this->belongsTo(User::class, 'updated_by'); }
}
