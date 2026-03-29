<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $fillable = [
        'user_one',
        'user_two',
    ];

    public function userOne()
    {
        return $this->belongsTo(User::class, 'user_one');
    }

    public function userTwo()
    {
        return $this->belongsTo(User::class, 'user_two');
    }
}
