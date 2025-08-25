<?php
namespace App\Http\Controllers;
use App\Models\EduResource;

class ResourceController extends Controller {
    public function index(){ $items=EduResource::select('slug','title','summary','updated_at')->latest()->get(); return view('resources.index',compact('items')); }
    public function show($slug){ $res=EduResource::where('slug',$slug)->firstOrFail(); return view('resources.show',compact('res')); }
}
