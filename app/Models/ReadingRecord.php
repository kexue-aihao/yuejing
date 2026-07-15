<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReadingRecord extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'novel_id', 'chapter_id', 'progress', 'last_read_at'];

    protected function casts(): array
    {
        return ['progress' => 'decimal:2', 'last_read_at' => 'datetime'];
    }

    public function user() { return $this->belongsTo(User::class); }
    public function novel() { return $this->belongsTo(Novel::class); }
    public function chapter() { return $this->belongsTo(Chapter::class); }
}
