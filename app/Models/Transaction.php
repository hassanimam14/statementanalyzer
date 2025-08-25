<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\UsesUuids;

class Transaction extends Model {
    use UsesUuids;
    protected $fillable = ['user_id','statement_id','date','description','merchant','amount','type','category','flags'];
    protected $casts = ['date'=>'date','amount'=>'decimal:2','flags'=>'array'];
    public function user(){ return $this->belongsTo(User::class); }
    public function statement(){ return $this->belongsTo(Statement::class); }
}
