<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Chapter extends Model
{
    use HasFactory;

    protected $fillable = ['novel_id', 'chapter_number', 'title', 'content', 'status', 'published_at'];

    protected function casts(): array
    {
        return ['chapter_number' => 'integer', 'published_at' => 'datetime'];
    }

    public function novel()
    {
        return $this->belongsTo(Novel::class);
    }

    public function readingRecords()
    {
        return $this->hasMany(ReadingRecord::class);
    }
}
