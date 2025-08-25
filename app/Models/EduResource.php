<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EduResource extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'url',
        'type',       // e.g., article, video, pdf
        'published_at'
    ];
}
