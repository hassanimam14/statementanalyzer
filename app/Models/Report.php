<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\UsesUuids;

class Report extends Model {
    use UsesUuids;
    protected $fillable = ['statement_id','summary_json','pdf_path'];
    protected $casts = ['summary_json'=>'array'];
    public function statement(){ return $this->belongsTo(Statement::class); }
}
