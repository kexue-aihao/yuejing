<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatGroupMessageRead extends Model
{
    use HasFactory;

    protected $fillable = ['chat_group_message_id', 'user_id', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function message()
    {
        return $this->belongsTo(ChatGroupMessage::class, 'chat_group_message_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
