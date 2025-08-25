<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\Statement;
use App\Models\AlertState;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function show(Request $request)
{
    $userId = Auth::id();

    // ---- time-range handling (optional) -----------------------------------
    [$from, $to] = $this->parseRange($request);

    $stmtQuery = Statement::query()->where('user_id', $userId);

    if ($from && $to) {
        $stmtQuery->where(function ($q) use ($from, $to) {
            $q->whereBetween('period_start', [$from, $to])
              ->orWhereBetween('period_end',   [$from, $to]);
        });
    }

    $statementIds = $stmtQuery->pluck('id');

    // Load ALL reports for selected statements
    $reports = Report::whereIn('statement_id', $statementIds)
        ->with('statement') // ensure Report has: public function statement(){ return $this->belongsTo(Statement::class); }
        ->orderByDesc('created_at')
        ->get();

    // If none in range, fall back to all user's reports
    if ($reports->isEmpty()) {
        $fallbackStmtIds = Statement::where('user_id', $userId)->pluck('id');
        $reports = Report::whereIn('statement_id', $fallbackStmtIds)
            ->with('statement')
            ->orderByDesc('created_at')
            ->get();
    }

    // ---------- Aggregate account-wide summary from ALL reports -------------
    $totalSpend     = 0.0;
    $totalFees      = 0.0;
    $spendOverTime  = [];
    $feesOverTime   = [];
    $feeByCategory  = [];
    $hiddenFeesAgg  = [];

    foreach ($reports as $rep) {
        $raw = $rep->summary_json;
        $sum = is_array($raw) ? $raw : (json_decode($raw ?? '[]', true) ?: []);

        $totalSpend += (float) ($sum['totalSpend'] ?? 0);
        $totalFees  += abs((float) ($sum['totalFees'] ?? 0));

        foreach (($sum['spendOverTime'] ?? []) as $k => $v) {
            $spendOverTime[$k] = ($spendOverTime[$k] ?? 0) + (float) $v;
        }
        foreach (($sum['feesOverTime'] ?? []) as $k => $v) {
            $feesOverTime[$k] = ($feesOverTime[$k] ?? 0) + (float) $v;
        }
        foreach (($sum['feeByCategory'] ?? []) as $k => $v) {
            $feeByCategory[$k] = ($feeByCategory[$k] ?? 0) + (float) $v;
        }
        // Keep a combined list (cap later if needed)
        foreach (($sum['hiddenFees'] ?? []) as $hf) {
            // normalize record
            $hiddenFeesAgg[] = [
                'date' => $hf['date'] ?? null,
                'description' => $hf['description'] ?? 'Unknown',
                'amount' => abs((float)($hf['amount'] ?? 0)),
            ];
        }
    }

    // Sort keys for charts (optional but nicer)
    ksort($spendOverTime);
    ksort($feesOverTime);

    $summary = [
        'totalSpend'     => round($totalSpend, 2),
        'totalFees'      => round($totalFees, 2),
        'savings'        => round($totalFees * 0.06, 2),
        'spendOverTime'  => $spendOverTime,
        'feesOverTime'   => $feesOverTime,
        'feeByCategory'  => $feeByCategory,
        'hiddenFees'     => array_slice($hiddenFeesAgg, 0, 50), // keep shape if you reuse later
        'topFeeMerchants'=> [],
        'tips'           => [
            "Pay statements in full to avoid interest charges.",
            "Use a 0% FX card when traveling to avoid conversion fees.",
            "Set up alerts for large/recurring charges."
        ],
    ];

    // --------- Build the "Statements" table rows for the dashboard ----------
    // Disputes count comes from AlertState per statement_id (status='disputed')
    $statementRows = $reports->map(function ($rep) use ($userId) {
        $sum = is_array($rep->summary_json) ? $rep->summary_json : (json_decode($rep->summary_json ?? '[]', true) ?: []);
        $fees = abs((float)($sum['totalFees'] ?? 0));
        $hiddenCount = is_array($sum['hiddenFees'] ?? null) ? count($sum['hiddenFees']) : 0;

        $disputes = AlertState::where('user_id', $userId)
            ->where('statement_id', $rep->statement_id)
            ->where('status', 'disputed')
            ->count();

        return [
            'statement_id' => $rep->statement_id,
            'period_start' => optional($rep->statement?->period_start)->toDateString(),
            'period_end'   => optional($rep->statement?->period_end)->toDateString(),
            'uploaded_at'  => optional($rep->created_at)->toDateTimeString(),
            'total_fees'   => round($fees, 2),
            'hidden_count' => $hiddenCount,
            'disputes'     => $disputes,
        ];
    })->values()->all();

    // Truthy flag for Blade to render main sections
    $latest = $reports->first(); // any report means "we have data"

    return view('dashboard', [
        'latest'          => $latest,
        'summary'         => $summary,
        'feeByCategory'   => $summary['feeByCategory'],
        'feesOverTime'    => $summary['feesOverTime'],
        'topFeeMerchants' => $summary['topFeeMerchants'],
        'tips'            => $summary['tips'],
        // replace alerts with statements table data:
        'statementRows'   => $statementRows,
    ]);
}


    public function resolve(Request $request, Statement $statement)
    {
        return $this->upsertAlertState($request, $statement, 'resolved', 'Alert resolved.');
    }

    public function dispute(Request $request, Statement $statement)
    {
        return $this->upsertAlertState($request, $statement, 'disputed', 'Marked for dispute.');
    }

    // ---------- helpers ------------------------------------------------------

    private function upsertAlertState(Request $request, Statement $statement, string $status, string $flash)
    {
        $idx     = (int) $request->input('idx');
        $payload = $request->input('payload', []);
        $key     = $this->keyFor($statement->id, $idx, $payload);

        AlertState::updateOrCreate(
            ['user_id' => Auth::id(), 'statement_id' => $statement->id, 'alert_key' => $key],
            ['status' => $status, 'notes' => $request->input('notes')]
        );

        return back()->with('status', $flash);
    }

    private function keyFor($statementId, $idx, $payload): string
    {
        $date = $payload['date'] ?? '';
        $desc = strtolower($payload['description'] ?? '');
        $amt  = (string) abs((float)($payload['amount'] ?? 0));
        return "{$statementId}|{$idx}|{$date}|{$desc}|{$amt}";
    }

    /**
     * Parse range from query:
     * ?range=this_month|last_90|all   OR   ?from=YYYY-MM-DD&to=YYYY-MM-DD
     */
    private function parseRange(Request $request): array
    {
        $range = $request->query('range');
        $from  = $request->query('from');
        $to    = $request->query('to');

        if ($from && $to) {
            return [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()];
        }

        if ($range === 'this_month') {
            return [now()->startOfMonth(), now()->endOfMonth()];
        }
        if ($range === 'last_90') {
            return [now()->subDays(90)->startOfDay(), now()->endOfDay()];
        }

        return [null, null]; // no filtering
    }
}
