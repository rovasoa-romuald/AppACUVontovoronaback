<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TemplateMessage extends Model
{
    protected $table = 'template_messages';
    protected $guarded = ["id"];
    protected $casts = [
        'tags' => 'array',
    ];
}
