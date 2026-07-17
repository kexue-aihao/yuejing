<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, MustVerifyEmailTrait;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function novels()
    {
        return $this->hasMany(Novel::class, 'author_id');
    }

    public function submissions()
    {
        return $this->hasMany(Submission::class);
    }

    public function ratings()
    {
        return $this->hasMany(Rating::class);
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }

    public function readingRecords()
    {
        return $this->hasMany(ReadingRecord::class);
    }

    public function privateConversationsAsLow()
    {
        return $this->hasMany(PrivateConversation::class, 'user_low_id');
    }

    public function privateConversationsAsHigh()
    {
        return $this->hasMany(PrivateConversation::class, 'user_high_id');
    }

    public function privateMessages()
    {
        return $this->hasMany(PrivateMessage::class, 'sender_id');
    }

    public function chatGroupsCreated()
    {
        return $this->hasMany(ChatGroup::class, 'creator_id');
    }

    public function chatGroupMemberships()
    {
        return $this->hasMany(ChatGroupMember::class);
    }

    public function chatGroups()
    {
        return $this->belongsToMany(ChatGroup::class, 'chat_group_members')
            ->withPivot(['role', 'joined_at', 'last_read_at'])
            ->withTimestamps();
    }

    public function chatGroupMessages()
    {
        return $this->hasMany(ChatGroupMessage::class, 'sender_id');
    }

    public function chatGroupMessageReads()
    {
        return $this->hasMany(ChatGroupMessageRead::class);
    }

    public function twoFactorSetting()
    {
        return $this->hasOne(UserTwoFactorSetting::class);
    }

    public function isRole(string|array $roles): bool
    {
        return in_array($this->role, (array) $roles, true);
    }
}
