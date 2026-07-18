<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SearchEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'category_id', 'novel_id', 'query', 'locale', 'timezone', 'session_hash',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function novel()
    {
        return $this->belongsTo(Novel::class);
    }
}
