<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlertState extends Model
{
    protected $fillable = ['user_id','statement_id','alert_key','status','notes'];

    public function scopeForUser($q, $userId)     { return $q->where('user_id', $userId); }
    public function scopeForStatement($q, $sid)   { return $sid ? $q->where('statement_id', $sid) : $q; }
}
