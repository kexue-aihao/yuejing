<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatGroup extends Model
{
    use HasFactory;

    protected $fillable = ['creator_id', 'name', 'description'];

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function memberRecords()
    {
        return $this->hasMany(ChatGroupMember::class);
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'chat_group_members')
            ->withPivot(['role', 'joined_at', 'last_read_at'])
            ->withTimestamps();
    }

    public function messages()
    {
        return $this->hasMany(ChatGroupMessage::class);
    }

    public function messageReads()
    {
        return $this->hasManyThrough(
            ChatGroupMessageRead::class,
            ChatGroupMessage::class,
            'chat_group_id',
            'chat_group_message_id',
            'id',
            'id',
        );
    }
}
