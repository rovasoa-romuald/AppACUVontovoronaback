<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserDetail extends Model
{
    protected $table = 'user_details';
    protected $guarded = ["id"];
    protected $casts = ['phone_numbers' => 'array'];

    /**
     * The user that owns this detail
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function users()
    {
        return $this->hasOne(User::class, 'id', 'user_detail_id');
    }
}
