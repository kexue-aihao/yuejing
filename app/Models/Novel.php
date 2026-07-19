<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Novel extends Model
{
    use HasFactory;

    protected $fillable = [
        'author_id', 'title', 'slug', 'synopsis', 'cover_url', 'status', 'published_at', 'views_count',
    ];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'views_count' => 'integer'];
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class);
    }

    public function chapters()
    {
        return $this->hasMany(Chapter::class)->orderBy('chapter_number');
    }

    public function publishedChapters()
    {
        return $this->chapters()->where('status', 'published');
    }

    public function ratings()
    {
        return $this->hasMany(Rating::class);
    }

    public function activeRatings()
    {
        return $this->ratings()->whereNull('withdrawn_at');
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }

    public function submissions()
    {
        return $this->hasMany(Submission::class);
    }
}
