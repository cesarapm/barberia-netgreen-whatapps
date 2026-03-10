<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    protected $fillable = ['phone', 'name'];

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function latestConversation()
    {
        return $this->hasOne(Conversation::class)->latestOfMany();
    }
}
