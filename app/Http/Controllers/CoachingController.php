<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\Statement;
use Illuminate\Support\Facades\Auth;

class CoachingController extends Controller
{
    public function index()
    {
        $userId = Auth::id();
        $statementIds = Statement::where('user_id', $userId)->pluck('id');
        $latest = Report::whereIn('statement_id', $statementIds)->latest('created_at')->first();

        $tips = [];
        if ($latest) {
            $raw = $latest->summary_json;
            $summary = is_array($raw) ? $raw : (json_decode($raw ?? '[]', true) ?: []);
            $tips = (array)($summary['tips'] ?? []);
        }

        return view('coaching.index', compact('tips','latest'));
    }
}
