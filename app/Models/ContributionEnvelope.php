<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContributionEnvelope extends Model
{
    protected $table = "contribution_envelopes";
    protected $guarded = ["id"];

    public function recipient()
    {
        return $this->belongsTo(User::class, "recipient_id");
    }

    public function responsible()
    {
        return $this->belongsTo(User::class, "responsible_id");
    }

    public function assistantResponsible()
    {
        return $this->belongsTo(User::class, "assistant_responsible_id");
    }
}
