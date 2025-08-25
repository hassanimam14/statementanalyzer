<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\Statement;
use App\Support\Currency;
use App\Models\FeeSnapshot;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\StatementAnalysis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ReportController extends Controller
{
    /**
     * LIST: /reports
     * Supports: ?q=, ?from=YYYY-MM-DD&to=YYYY-MM-DD, ?per_page=
     */
    public function index(Request $request)
    {
        $userId  = Auth::id();
        $q       = trim((string)$request->query('q'));
        $from    = $request->query('from');
        $to      = $request->query('to');
        $perPage = (int) $request->query('per_page', 12);

        $reports = Report::query()
            ->whereHas('statement', function ($s) use ($userId, $from, $to) {
                $s->where('user_id', $userId);
                if ($from && $to) {
                    $s->where(function ($w) use ($from, $to) {
                        $w->whereBetween('period_start', [$from, $to])
                          ->orWhereBetween('period_end',   [$from, $to]);
                    });
                }
            })
            ->with('statement')
            ->when($q, function ($qb) use ($q) {
                $qb->whereHas('statement', function ($s) use ($q) {
                    $s->where('original_name', 'like', "%{$q}%")
                      ->orWhere('id', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();

        return view('reports.index', compact('reports'));
    }

    /**
     * GENERATE a PDF report for a statement
     */
public function generate(Statement $statement, StatementAnalysis $analysis)
{
    abort_unless($statement->user_id === Auth::id(), 403);

    $tx = $statement->transactions()->orderBy('date')->get();
    if ($tx->isEmpty()) return back()->with('status', 'No transactions found for this statement.');

    $summary = $analysis->summarize($tx);
    $totals  = $this->computeTotalsForStatement($statement);

    $currency       = strtoupper($statement->currency_code ?? ($summary['currency'] ?? config('app.default_currency','USD')));
    $currencySymbol =Currency::symbol($currency);

    $pdf =Pdf::setOptions(['isRemoteEnabled' => true])
        ->loadView('pdf.report', [
            'statement'      => $statement,
            'summary'        => $summary,
            'transactions'   => $tx,
            'currency'       => $currency,
            'currencySymbol' => $currencySymbol,
            'totalSpend'     => $totals['totalSpend'],
            'totalFees'      => $totals['totalFees'],
            'savings'        => $totals['savings'],
        ]);

    $disk='public'; $dir='reports'; $filename=$statement->id.'.pdf'; $pdfPath="$dir/$filename";
    \Storage::disk($disk)->makeDirectory($dir);

    \DB::transaction(function () use ($statement,$summary,$pdf,$pdfPath,$disk) {
        \Storage::disk($disk)->put($pdfPath, $pdf->output());
        Report::updateOrCreate(
            ['statement_id'=>$statement->id],
            ['summary_json'=>$summary,'pdf_path'=>$pdfPath]
        );
    });

    return redirect()->route('reports.show', $statement)->with('status', 'Report generated successfully.');
}

    /**
     * SHOW one report
     */
public function show(Statement $statement)
{
    abort_unless($statement->user_id === Auth::id(), 403);

    $report = $statement->report;
    if (!$report) return back()->with('status', 'No report yet. Click Generate.');

    $raw     = $report->summary_json;
    $summary = is_array($raw) ? $raw : (json_decode($raw ?? '[]', true) ?: []);

    $currency = strtoupper($statement->currency_code ?? ($summary['currency'] ?? config('app.default_currency','USD')));
    $currencySymbol = Currency::symbol($currency);

    // recompute page KPIs (display currency only)
    $totals = $this->computeTotalsForStatement($statement);

    $transactions = $statement->transactions()->orderBy('date')->paginate(25)->withQueryString();
    $disputesCount = collect($summary['hiddenFees'] ?? [])->count();

    return view('reports.show', compact(
        'statement','report','summary','transactions','disputesCount','currency','currencySymbol'
    ) + [
        'totalSpend' => $totals['totalSpend'],
        'totalFees'  => $totals['totalFees'],
        'savings'    => $totals['savings'],
    ]);
}


    /**
     * GET /reports/{statement}/download
     */
    public function download(Statement $statement)
    {
        abort_unless($statement->user_id === Auth::id(), 403);

        $report = $statement->report;
        if (!$report || empty($report->pdf_path)) {
            return back()->with('status', 'PDF not found for this report.');
        }

        $disk = 'public';
        $rawPath = trim($report->pdf_path);
        $path = preg_replace('#^storage/#', '', $rawPath);

        if (Storage::disk($disk)->exists($path)) {
            $absolutePath = Storage::disk($disk)->path($path);
            $filename = basename($path);
            Log::info('Report download via disk', compact('disk','path','absolutePath'));

            return response()->download($absolutePath, $filename, [
                'Cache-Control'       => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma'              => 'no-cache',
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]);
        }

        $publicStoragePath = public_path('storage/'.ltrim($path, '/'));
        if (is_file($publicStoragePath)) {
            $filename = basename($path);
            Log::warning('Report download via public_path fallback', ['path' => $publicStoragePath]);

            return response()->download($publicStoragePath, $filename, [
                'Cache-Control'       => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma'              => 'no-cache',
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]);
        }

        Log::error('Report PDF missing', ['db_value' => $report->pdf_path, 'normalized' => $path]);
        return back()->with('status', 'PDF file is missing.');
    }

    /**
     * IMPORTANT: compute totals only for the chosen display currency
     *            (avoid mixing multi-ccy rows in KPIs).
     */
    private function computeTotalsForStatement(Statement $statement): array
{
    $currency = strtoupper($statement->currency_code ?? config('app.default_currency', 'USD'));
    $txs = $statement->transactions()
        ->where(function ($q) use ($currency) {
            $q->whereNull('currency_code')->orWhere('currency_code', $currency);
        })
        ->get(['amount', 'amount_minor', 'category', 'flags', 'description', 'date', 'merchant']);

    $normalizeFlags = function ($flags): array {
        if (is_string($flags)) {
            $d = json_decode($flags, true);
            return (json_last_error() === JSON_ERROR_NONE && is_array($d)) ? $d : [];
        }
        if ($flags instanceof \Illuminate\Support\Collection) return $flags->all();
        return is_array($flags) ? $flags : (array) $flags;
    };

    $isFeeLike = function ($t) use ($normalizeFlags): bool {
        $descLC = mb_strtolower((string)($t->description ?? ''));
        $flags = $normalizeFlags($t->flags ?? []);

        if (preg_match('/\bmarkup\s*rate\s*0\s*%\b/i', $descLC)) return false;

        if (($t->category ?? null) === 'fee') return true;
        $feeFlags = ['service_fee', 'tax', 'foreign_tx_fee', 'interest_charge', 'late_payment', 'cash_advance'];
        foreach ($feeFlags as $f) if (in_array($f, $flags, true)) return true;

        return (bool) preg_match('/\b(fee|charge|interest|finance|excise|tax|withholding|levy|pra|kpra|srb|vat|gst|sms\s*banking|giro|overlimit)\b/i', $descLC);
    };

    $spendCats = ['purchase', 'subscription', 'cash_advance'];
    $totalSpend = 0.0;
    $totalFees = 0.0;

    foreach ($txs as $t) {
        $amt = isset($t->amount_minor) ? ((int)$t->amount_minor) / 100.0 : (float)$t->amount;
        $fee = $isFeeLike($t);

        if ($fee && $amt < 0) {
            $totalFees += abs($amt);
            continue;
        }

        $cat = strtolower((string)($t->category ?? ''));
        if ($amt < 0 && (in_array($cat, $spendCats, true) || $cat === '')) {
            $totalSpend += abs($amt);
        }
    }

    return [
        'totalSpend' => round($totalSpend, 2),
        'totalFees' => round($totalFees, 2),
        'savings' => round($totalFees * 0.70, 2),
    ];
}
}
