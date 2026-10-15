<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $table = "conversations";
    protected $guarded = ["id"];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function templateMessage()
    {
        return $this->belongsTo(TemplateMessage::class);
    }
}
