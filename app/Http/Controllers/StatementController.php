<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

use App\Models\Statement;
use App\Models\Report;
use App\Services\StatementExtractor;
use App\Services\StatementAnalysis;

class StatementController extends Controller
{
    public function create()
    {
        $cards = Auth::user()->cards()->get();
        return view('statements.upload', compact('cards'));
    }

    public function store(Request $req, StatementExtractor $extractor, StatementAnalysis $analysis)
{
    $req->validate([
        'file' => ['required', 'file', 'mimes:csv,txt,pdf,jpg,jpeg,png,tif,tiff'],
        'card_id' => ['nullable', 'exists:cards,id'],
        'period_start' => ['nullable', 'date'],
        'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
    ]);

    // 1) Save upload
    $uploaded = $req->file('file');
    $disk = 'public';
    $path = $uploaded->store('statements', $disk);
    $absPath = Storage::disk($disk)->path($path);

    // 2) Create Statement row
    $statement = Statement::create([
        'id' => (string) Str::uuid(),
        'user_id' => Auth::id(),
        'card_id' => $req->card_id,
        'original_name' => $uploaded->getClientOriginalName(),
        'stored_path' => $path,
        'period_start' => $req->period_start,
        'period_end' => $req->period_end,
    ]);

    // Feature flags
    $aiAlwaysOn = (bool) config('ai.always_on', true);
    $aiMinRows = (int) config('ai.min_rows_threshold', 10);
    $enableDebug = (bool) config('ai.debug_import', false);

    try {
        // 3) Primary deterministic extraction
        $payload = $extractor->extract($absPath, [
            'filename' => $uploaded->getClientOriginalName(),
            'mime' => $uploaded->getMimeType(),
            'ext' => $uploaded->getClientOriginalExtension(),
        ]);
        $rowsExtractor = $payload['transactions'] ?? [];
        if ($enableDebug) \Log::info('Extractor rows', ['count' => count($rowsExtractor)]);

        // 3b) AI assist (resilient)
        $rowsAi = [];
        $ai = [];
        if ($aiAlwaysOn || count($rowsExtractor) < $aiMinRows) {
            try {
                /** @var \App\Services\GeminiStatementAnalysis $gemini */
                $gemini = app(\App\Services\GeminiStatementAnalysis::class);
                $mime = $uploaded->getMimeType() ?: 'application/octet-stream';
                $ai = $gemini->analyzeFile($absPath, $mime);
                \Log::debug('Raw Gemini AI response', ['response' => $ai]);

                $ai = $this->coerceAiJson($ai); // Parse AI response
                \Log::debug('Parsed AI response', ['parsed' => $ai]);

                if (empty($ai['error']) && !empty($ai['transactions']) && is_array($ai['transactions'])) {
                    $rowsAi = $ai['transactions'];
                }
            } catch (\Throwable $e) {
                if ($enableDebug) \Log::warning('AI analyzeFile failed', ['err' => $e->getMessage()]);
            }
        }
        if ($enableDebug) \Log::info('AI rows', ['count' => count($rowsAi)]);

        // 3c) Merge + robust de-dupe + echo-fragment filter
        $rows = $this->mergeAndDedup($rowsExtractor, $rowsAi);
        if ($enableDebug) \Log::info('Merged rows after dedupe', ['count' => count($rows)]);

        // 3d) Guard if empty
        if (empty($rows)) {
            $isPdf = strtolower($uploaded->getClientOriginalExtension()) === 'pdf';
            $hint = $isPdf
                ? 'No itemized activity detected. If this is a scanned PDF, OCR may have failed — try a higher-DPI scan or the bank’s CSV export.'
                : 'Try the bank’s CSV export if available.';
            return back()->withErrors(['error' => "No transactions detected. {$hint}"])->withInput();
        }

        // 4) Normalize for DB insert
        $now = now();
        $userId = Auth::id();
        $buffer = [];

        // Detect currency early to set transaction currency_code
        $detectedCurrency = $this->detectCurrency(
            $ai['summary']['currency'] ?? $ai['currency'] ?? null, // Use summary.currency first
            $payload['currency'] ?? null,
            [], // Defer transaction currencies until buffer is built
            $statement->currency_code
        );
        \Log::debug('Early currency detection', [
            'aiCurrency' => $ai['summary']['currency'] ?? $ai['currency'] ?? null,
            'extractorCurrency' => $payload['currency'] ?? null,
            'statementCurrency' => $statement->currency_code,
            'preliminary' => $detectedCurrency
        ]);

        foreach ($rows as $t) {
            $date = $t['date'] ?? null;
            $description = trim((string)($t['description'] ?? ''));
            $merchant = trim((string)($t['merchant'] ?? $description));
            $amount = is_numeric($t['amount'] ?? null) ? (float)$t['amount'] : null;

            // guardrails
            if (
                !$date || $description === '' || $amount === null ||
                !is_finite($amount) || abs($amount) > 10_000_000
            ) {
                continue;
            }

            $type = $amount < 0 ? 'debit' : 'credit';

            // minimal categorization + flags
            [$category, $flags] = $this->quickCategorize($description, $amount);
            $currency =  $detectedCurrency; // Use detected currency if not specified

            $minor = isset($t['amount_minor']) ? (int)$t['amount_minor'] : null;

            $buffer[] = [
                'id' => (string) Str::uuid(),
                'user_id' => $userId,
                'statement_id' => $statement->id,
                'date' => $date,
                'description' => $description,
                'merchant' => $merchant,
                'amount' => round($amount, 2),
                'amount_minor' => $minor,
                'currency_code' => $currency,
                'type' => $type,
                'category' => $category,
                'flags' => json_encode($flags, JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($enableDebug) \Log::info('Buffer count after normalization', ['count' => count($buffer)]);

        if (empty($buffer)) {
            return back()->withErrors(['error' => "Parsed the file but couldn't build valid transaction rows."])->withInput();
        }

        // 4b) Clamp row dates to provided period (if any)
        $start = $statement->period_start ? Carbon::parse($statement->period_start) : null;
        $end = $statement->period_end ? Carbon::parse($statement->period_end) : null;
        if ($start || $end) {
            foreach ($buffer as &$row) {
                if (!empty($row['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $row['date'])) {
                    $dt = Carbon::parse($row['date']);
                    if ($start && $dt->lt($start)) $row['date'] = $start->toDateString();
                    if ($end && $dt->gt($end)) $row['date'] = $end->toDateString();
                }
            }
            unset($row);
        }

        // 5) Persist + build report, detect currency again with transaction currencies
        DB::transaction(function () use ($buffer, $statement, $analysis, $payload, $ai) {
            foreach (array_chunk($buffer, 1000) as $chunk) {
                DB::table('transactions')->insert($chunk);
            }

            $txs = $statement->transactions()->get([
                'id', 'date', 'description', 'merchant', 'amount', 'amount_minor', 'currency_code', 'type', 'category', 'flags'
            ]);

            // Build summary via analyzer
            $summary = $analysis->summarize($txs);

            // Detect currency (reconfirm with transaction currencies)
            $detectedCurrency = $this->detectCurrency(
                $ai['summary']['currency'] ?? $ai['currency'] ?? null,
                $payload['currency'] ?? null,
                $txs->pluck('currency_code')->filter()->all(),
                $statement->currency_code
            );
            \Log::info('Detected currency in store', [
                'aiCurrency' => $ai['summary']['currency'] ?? $ai['currency'] ?? null,
                'extractorCurrency' => $payload['currency'] ?? null,
                'txnCurrencies' => $txs->pluck('currency_code')->filter()->all(),
                'statementCurrency' => $statement->currency_code,
                'final' => $detectedCurrency
            ]);

            // Attach to summary and persist on statement
            $summary['currency'] = $detectedCurrency;
            DB::table('statements')->where('id', $statement->id)->update([
                'currency_code' => $detectedCurrency,
                'updated_at' => now(),
            ]);
            // Normalize transaction currencies to the detected statement currency.
// This fixes rows that were saved with a wrong default (e.g., PKR) for a single-currency statement.
\DB::table('transactions')
    ->where('statement_id', $statement->id)
    ->update(['currency_code' => $detectedCurrency]);


            // Upsert report
            DB::table('reports')->updateOrInsert(
                ['statement_id' => $statement->id],
                [
                    'id' => (string) Str::uuid(),
                    'summary_json' => json_encode($summary, JSON_UNESCAPED_UNICODE),
                    'pdf_path' => $statement->stored_path ?? '',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        });

        // 6) Auto-redirect to show
        return redirect()
            ->route('statements.show', $statement)
            ->with('status', 'Imported ' . count($buffer) . ' transactions and generated analysis.');
    } catch (\Throwable $e) {
        report($e);
        return back()->withErrors(['error' => 'Unexpected error: ' . $e->getMessage()])->withInput();
    }
}

    public function status(Statement $statement)
    {
        abort_unless($statement->user_id === Auth::id(), 403);

        $report = DB::table('reports')->where('statement_id', $statement->id)->first();

        return response()->json([
            'ready'           => (bool) $report,
            'report_id'       => $report->id ?? null,
            'summary_present' => !empty($report?->summary_json),
            'pdf_path'        => $report->pdf_path ?? null,
        ]);
    }

    public function index(Request $request)
    {
        $userId = Auth::id();
        $q      = trim((string)$request->query('q'));
        $from   = $request->query('from');
        $to     = $request->query('to');

        $statements = Statement::query()
            ->where('user_id', $userId)
            ->with('report')
            ->when($q, function ($qb) use ($q) {
                $qb->where(function ($inner) use ($q) {
                    $inner->where('original_name', 'like', "%{$q}%")
                          ->orWhere('id', 'like', "%{$q}%");
                });
            })
            ->when($from && $to, function ($qb) use ($from, $to) {
                $qb->where(function ($w) use ($from, $to) {
                    $w->whereBetween('period_start', [$from, $to])
                      ->orWhereBetween('period_end',   [$from, $to]);
                });
            })
            ->orderByDesc('created_at')
            ->paginate(12)
            ->withQueryString();

        return view('statements.index', compact('statements'));
    }

    public function show(Statement $statement)
    {
        $this->authorizeOwnership($statement);
        $txns = $statement->transactions()->orderBy('date')->paginate(25);
        return view('statements.show', compact('statement','txns'));
    }

    public function analyze(Statement $statement, StatementAnalysis $analysis)
    {
        $this->authorizeOwnership($statement);

        $txs = $statement->transactions()->get(['id','date','description','merchant','amount','type','category','flags']);
        $summary = $analysis->summarize($txs);

        Report::updateOrCreate(
            ['statement_id' => $statement->id],
            [
                'id'           => (string) Str::uuid(),
        'summary_json' => $summary, // ← was json_encode(...). Keep it as array.
        'pdf_path'     => $statement->stored_path ?? '',
        'updated_at'   => now(),
        'created_at'   => now(),
            ]
        );

        return redirect()->route('statements.show', $statement)->with('status', 'Analysis updated.');
    }

    public function destroy(Statement $statement)
    {
        $this->authorizeOwnership($statement);
        $statement->transactions()->delete();
        $statement->reports()->delete();
        $statement->delete();

        return redirect()->route('dashboard')->with('status', 'Statement deleted successfully.');
    }

    protected function authorizeOwnership(Statement $s)
    {
        abort_unless($s->user_id === Auth::id(), 403);
    }

    /**
     * Merge extractor + AI rows and deduplicate:
     *  - bucket by date±1d + amount + normalized description
     *  - prefer richer (longer) description on collision
     *  - drop echo fragments (e.g., 166.74 vs 2166.74 same-day similar text)
     */
    private function mergeAndDedup(array $rowsExtractor, array $rowsAi): array
    {
        $rows = array_merge($rowsExtractor, $rowsAi);

        $bucket = [];
        foreach ($rows as $t) {
            $date = $t['date'] ?? null;
            $desc = $t['description'] ?? '';
            $amt  = is_numeric($t['amount'] ?? null) ? round((float)$t['amount'], 2) : null;
            if (!$date || $desc === '' || $amt === null) continue;

            $d0 = $this->parseDateSafe($date);
            if (!$d0) continue;

            foreach ([-1,0,1] as $off) {
                $d = $d0->copy()->addDays($off)->toDateString();
                $k = $d.'|'.number_format($amt,2,'.','').'|'.$this->normalizeDesc($desc);
                if (!isset($bucket[$k]) || mb_strlen($desc) > mb_strlen($bucket[$k]['description'] ?? '')) {
                    $bucket[$k] = $t;
                }
            }
        }

        // collapse echo fragments
        $final = [];
        $byDateAmt = [];
        foreach ($bucket as $t) {
            $d = (string)($t['date'] ?? '');
            $a = number_format((float)$t['amount'], 2, '.', '');
            $byDateAmt[$d][$a][] = $t;
        }

        foreach ($byDateAmt as $d => $byAmt) {
            $drop = [];
            $amts = array_map('floatval', array_keys($byAmt));
            sort($amts);
            foreach ($amts as $small) {
                foreach ($amts as $big) {
                    if ($big <= $small) continue;
                    $smallS = number_format($small,2,'.','');
                    $bigS   = number_format($big,2,'.','');
                    $isEcho = (str_ends_with($bigS, $smallS) && ($big - $small) >= 500);
                    if ($isEcho) {
                        $sDesc = $this->normalizeDesc($byAmt[$smallS][0]['description'] ?? '');
                        $bDesc = $this->normalizeDesc($byAmt[$bigS][0]['description'] ?? '');
                        similar_text($sDesc, $bDesc, $pct);
                        if ($pct >= 70) { $drop[$smallS] = true; }
                    }
                }
            }
            foreach ($byAmt as $aStr => $arr) {
                if (!isset($drop[$aStr])) {
                    usort($arr, fn($x,$y)=>mb_strlen($y['description']??'')<=>mb_strlen($x['description']??'')); // richer first
                    $final[] = $arr[0];
                }
            }
        }

        return array_values($final);
    }

    private function normalizeDesc(?string $s): string
    {
        $s = mb_strtolower((string)$s);
        $s = preg_replace('/\s+/', ' ', $s);
        $s = preg_replace('/\b(jan|feb|mar|apr|may|jun|jul|aug|sep|sept|oct|nov|dec)\b/','',$s);
        $s = trim(preg_replace('/\b(\w+)\s+\1\b/u','\1',$s));
        $map = [
            'foreign transaction fee'   => 'fxfee',
            'excise duty on charges'    => 'exciseduty',
            'adv tax-236y (filer)'      => 'advtax',
            'service charge'            => 'servicecharge',
            'late payment charge'       => 'latefee',
            'sms banking fee'           => 'smsfee',
            'rejected giro service fee' => 'girofee',
        ];
        foreach ($map as $k=>$v) $s = str_replace($k,$v,$s);
        return $s;
    }

    private function parseDateSafe($d): ?Carbon
    {
        try { return Carbon::parse($d)->startOfDay(); } catch (\Throwable $e) { return null; }
    }

    /**
     * Currency detection priority:
     *  1) $aiCurrency (e.g., from model output)
     *  2) $extractorCurrency (e.g., from deterministic extractor)
     *  3) Majority currency among transaction rows
     *  4) Statement's existing currency_code
     *  5) app.default_currency (config)
     */
private function detectCurrency(?string $aiCurrency, ?string $extractorCurrency, array $txnCurrencies, ?string $statementCurrency): string
{
    $try = function ($val) {
        $v = is_string($val) ? strtoupper(trim($val)) : null;
        return ($v && preg_match('/^[A-Z]{3}$/', $v)) ? $v : null;
    };

    // 1) AI currency (highest priority)
    if ($aiCurrency = $try($aiCurrency)) {
        \Log::debug('Currency detection: Using AI currency', ['currency' => $aiCurrency]);
        return $aiCurrency;
    }

    // 2) Extractor currency
    if ($extractorCurrency = $try($extractorCurrency)) {
        \Log::debug('Currency detection: Using extractor currency', ['currency' => $extractorCurrency]);
        return $extractorCurrency;
    }

    // 3) Majority in transactions
    if (!empty($txnCurrencies)) {
        $freq = [];
        foreach ($txnCurrencies as $ccy) {
            $ccy = $try($ccy);
            if (!$ccy) continue;
            $freq[$ccy] = ($freq[$ccy] ?? 0) + 1;
        }
        if (!empty($freq)) {
            arsort($freq);
            $picked = array_key_first($freq);
            \Log::debug('Currency detection: Using transaction majority', ['frequencies' => $freq, 'picked' => $picked]);
            return $picked;
        }
    }

    // 4) Statement currency
    if ($statementCurrency = $try($statementCurrency)) {
        \Log::debug('Currency detection: Using statement currency', ['currency' => $statementCurrency]);
        return $statementCurrency;
    }

    // 5) Default
    $default = $try(config('app.default_currency', 'USD')) ?: 'USD';
    \Log::debug('Currency detection: Using default', ['currency' => $default]);
    return $default;
}
    /** Tiny built-in rules so this controller works without extra services */
    protected function quickCategorize(string $description, float $amount): array
    {
        $d = mb_strtolower($description);

        $taxNeedles  = ['adv tax','withholding','wht','pra','srb','kpra','fbr','gst','vat','sales tax','excise duty','236y'];
        $hardFees    = ['fee','charge','service charge','giro','sms banking','overlimit','assessment','maintenance'];
        $lateNeedles = ['late payment'];
        $interest    = ['interest','finance charge','markup']; // markup = interest unless 0%

        $isFee = false; $isInterest = false; $flags = [];

        foreach ($taxNeedles as $n) if (str_contains($d, $n)) { $isFee = true; $flags[]='tax'; $flags[]='service_fee'; }
        foreach ($hardFees as $n)  if (str_contains($d, $n)) { $isFee = true; $flags[]='service_fee'; }
        foreach ($lateNeedles as $n) if (str_contains($d, $n)) { $isFee = true; $flags[]='late_payment'; $flags[]='service_fee'; }
        foreach ($interest as $n) if (preg_match('/\b'.$n.'\b/i', $description)) { $isInterest = true; }

        if (str_contains($d,'foreign transaction')) { $isFee = true; $flags[]='foreign_tx_fee'; $flags[]='service_fee'; }
        if (str_contains($d,'conv.rate') || str_contains($d,'usd -')) { $flags[]='currency_conversion'; }

        if ($isInterest && !preg_match('/\b0\s*%\b/', $d)) {
            return ['interest', array_values(array_unique($flags))];
        }
        if ($isFee) return ['fee', array_values(array_unique($flags))];

        $category = $amount < 0
            ? (preg_match('/\b(netflix|spotify|icloud|prime|hulu|disney\+)\b/i',$d) ? 'subscription' : 'purchase')
            : 'payment';

        return [$category, array_values(array_unique($flags))];
    }

    /**
     * Coerce possibly-string AI output (with code fences or embedded JSON) into an array.
     */
private function coerceAiJson($ai): array
{
    if (is_array($ai)) {
        \Log::debug('coerceAiJson: Input is already an array', ['input' => $ai]);
        return $ai;
    }

    if (is_string($ai)) {
        $s = trim($ai);

        // Strip code fences
        $s = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $s);
        \Log::debug('coerceAiJson: Raw input after stripping fences', ['input' => $s]);

        // 1) Try decode as-is
        $decoded = json_decode($s, true);
        if (is_array($decoded)) {
            \Log::debug('coerceAiJson: Decoded successfully', ['decoded' => $decoded]);
            return $decoded;
        }

        // 2) Try to extract the first JSON object
        if (preg_match('/\{(?:[^{}]|(?R))*\}/s', $s, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) {
                \Log::debug('coerceAiJson: Extracted JSON object', ['decoded' => $decoded]);
                return $decoded;
            }
        }

        // 3) Handle double-encoded JSON
        $once = json_decode($s, true);
        if (is_string($once)) {
            $twice = json_decode($once, true);
            if (is_array($twice)) {
                \Log::debug('coerceAiJson: Double-decoded JSON', ['decoded' => $twice]);
                return $twice;
            }
        }

        // 4) Salvage currency from partial JSON
        if (preg_match('/"currency"\s*:\s*"([A-Z]{3})"/i', $s, $m)) {
            $currency = strtoupper($m[1]);
            \Log::warning('coerceAiJson: Salvaged currency from partial JSON', ['currency' => $currency]);
            return ['currency' => $currency, 'transactions' => []];
        }

        \Log::warning('coerceAiJson: Failed to parse JSON', ['input' => $s, 'error' => json_last_error_msg()]);
    }

    \Log::warning('coerceAiJson: Invalid input type', ['type' => gettype($ai)]);
    return [];
}
}
