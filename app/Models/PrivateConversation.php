<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrivateConversation extends Model
{
    use HasFactory;

    protected $fillable = ['user_low_id', 'user_high_id'];

    protected static function booted(): void
    {
        static::saving(function (self $conversation) {
            if ($conversation->user_low_id === null || $conversation->user_high_id === null) {
                return;
            }

            if ((int) $conversation->user_low_id === (int) $conversation->user_high_id) {
                throw new \InvalidArgumentException('A private conversation requires two different users.');
            }

            if ((int) $conversation->user_low_id > (int) $conversation->user_high_id) {
                [$conversation->user_low_id, $conversation->user_high_id] = [
                    $conversation->user_high_id,
                    $conversation->user_low_id,
                ];
            }
        });
    }

    public function userLow()
    {
        return $this->belongsTo(User::class, 'user_low_id');
    }

    public function userHigh()
    {
        return $this->belongsTo(User::class, 'user_high_id');
    }

    public function messages()
    {
        return $this->hasMany(PrivateMessage::class);
    }
}
