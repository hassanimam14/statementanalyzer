<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\UsesUuids;

class Statement extends Model {
    use UsesUuids;
    protected $fillable = ['user_id','card_id','original_name','stored_path','period_start','period_end'];
    protected $casts = ['period_start'=>'date','period_end'=>'date'];
    public function user(){ return $this->belongsTo(User::class); }
    public function card(){ return $this->belongsTo(Card::class); }
    public function transactions(){ return $this->hasMany(Transaction::class); }
    public function report(){ return $this->hasOne(Report::class); }
}
