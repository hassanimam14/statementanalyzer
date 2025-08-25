<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Card extends Model {
    protected $fillable = ['user_id','issuer','nickname','last4','statement_day','due_day'];
    public function user(){ return $this->belongsTo(User::class); }
    public function statements(){ return $this->hasMany(Statement::class); }
}
