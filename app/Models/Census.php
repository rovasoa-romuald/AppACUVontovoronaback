<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Census extends Model
{
    protected $guarded = ["id"];

    public function student()
    {
        return $this->belongsTo(User::class, "id_user");
    }

    public function major()
    {
        return $this->belongsTo(Major::class, "id_major");
    }

    public function level()
    {
        return $this->belongsTo(Level::class, "id_level");
    }
}
