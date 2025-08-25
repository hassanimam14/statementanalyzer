@php
use App\Support\Currency;

$sum = is_array($summary ?? null) ? $summary : (json_decode($summary ?? '[]', true) ?: []);
$currency = strtoupper($statement->currency_code ?? $sum['currency'] ?? config('app.default_currency', 'USD'));
$currencySymbol = Currency::symbol($currency);
\Log::debug('pdf.report currency selection', [
    'statement_currency' => $statement->currency_code,
    'summary_currency' => $sum['currency'] ?? null,
    'final_currency' => $currency
]);

$fmtMoney = function ($amount, ?string $code = null, $forceAbs = false) use ($currency, $currencySymbol) {
    if (is_array($amount)) {
        $minor = isset($amount['amount_minor']) ? (int)$amount['amount_minor'] : (int)round(((float)($amount['amount'] ?? 0)) * 100);
        $code = strtoupper($amount['currency_code'] ?? $code ?? $currency);
        $val = $minor / 100.0;
    } else {
        $val = (float)$amount;
        $code = strtoupper($code ?? $currency);
    }
    $val = $forceAbs ? abs($val) : $val;

    $sym = Currency::symbol($code) ?: $currencySymbol;
    $prefix = ($code !== $currency) ? ($code . ' ') : $sym;
    return $prefix . number_format($val, 2);
};

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

// Use controller-provided totals if available, otherwise use AI summary or compute
$kpiSpend = isset($totalSpend) ? (float)$totalSpend : (isset($sum['totalSpend']) ? (float)$sum['totalSpend'] : 0.0);
$kpiFees = isset($totalFees) ? (float)$totalFees : (isset($sum['totalFees']) ? abs((float)$sum['totalFees']) : 0.0);
$kpiSavings = isset($savings) ? (float)$savings : (isset($sum['savings']) ? (float)$sum['savings'] : round($kpiFees * 0.70, 2));

// Fallback computation if totals are zero or missing
if ($kpiSpend == 0.0 || $kpiFees == 0.0) {
    $txs = collect($transactions ?? []);
    $kpiSpend = 0.0;
    $kpiFees = 0.0;

    foreach ($txs as $t) {
        $amt = isset($t->amount_minor) ? ((int)$t->amount_minor) / 100.0 : (float)$t->amount;
        if ($t->currency_code && $t->currency_code !== $currency) continue;
        if ($amt < 0 && $isFeeLike($t)) {
            $kpiFees += abs($amt);
            continue;
        }
        $cat = strtolower((string)($t->category ?? ''));
        if ($amt < 0 && (in_array($cat, $spendCats, true) || $cat === '')) {
            $kpiSpend += abs($amt);
        }
    }
    $kpiSavings = round($kpiFees * 0.70, 2);
}

\Log::debug('pdf.report KPI computation', [
    'controller_totalSpend' => $totalSpend ?? null,
    'controller_totalFees' => $totalFees ?? null,
    'controller_savings' => $savings ?? null,
    'summary_totalSpend' => $sum['totalSpend'] ?? null,
    'summary_totalFees' => $sum['totalFees'] ?? null,
    'computed_kpiSpend' => $kpiSpend,
    'computed_kpiFees' => $kpiFees,
    'computed_kpiSavings' => $kpiSavings,
    'transaction_count' => count($transactions ?? [])
]);

$hiddenFees = $sum['hiddenFees'] ?? [];
$hiddenFeeCount = is_countable($hiddenFees) ? count($hiddenFees) : 0;
$subsCount = is_countable($sum['subscriptions'] ?? []) ? count($sum['subscriptions']) : 0;
@endphp

<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Monthly Fee & Charge Analysis</title>
  <style>
    :root {
      --orange: #ff7e5f;
      --teal: #0aa596;
      --teal-dark: #08897f;
      --ink: #0f172a;
      --muted: #64748b;
      --line: #e5e7eb;
      --tile: #ffffff;
      --tableHead: #f8fafc;
    }
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; margin: 0; background: #fff; }
    h1 { font-size: 22px; margin: 0 0 6px; color: var(--ink); }
    h2 { font-size: 14px; margin: 0 0 8px; color: var(--ink); }
    h3 { font-size: 12px; margin: 0 0 4px; color: var(--ink); }
    .muted { color: var(--muted); }
    .small { font-size: 10px; }
    .header { max-width: 1200px; margin: 0 auto; padding: 12px 16px; }
    .header-gradient { height: 4px; background: linear-gradient(to right, var(--orange), var(--teal), var(--teal-dark)); opacity: 0.8; border-radius: 4px 4px 0 0; }
    .header-card { background: #fff; border: 1px solid var(--line); border-radius: 0 0 8px 8px; padding: 12px 16px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
    .header-logo { display: flex; align-items: center; gap: 8px; }
    .header-logo-box { width: 36px; height: 36px; border-radius: 8px; background: linear-gradient(135deg, var(--teal), var(--teal-dark)); color: #fff; font-weight: 700; font-size: 18px; display: grid; place-items: center; box-shadow: 0 1px 2px rgba(0,0,0,0.1); }
    .header-logo-text { font-size: 16px; font-weight: 600; color: var(--ink); }
    .header-meta { font-size: 11px; color: var(--muted); text-align: right; }
    .section { padding: 12px 16px; }
    .card { border: 1px solid var(--line); border-radius: 8px; padding: 12px; background: #fff; }
    .spacer8 { height: 8px; } .spacer12 { height: 12px; }
    .kpis { width: 100%; border-collapse: separate; border-spacing: 10px; }
    .kpi { width: 25%; padding: 12px; border: 1px solid var(--line); border-radius: 10px; background: var(--tile); }
    .kpi .label { font-size: 11px; color: var(--muted); }
    .kpi .value { font-size: 18px; font-weight: 700; color: var(--ink); }
    .grid2 { width: 100%; border-collapse: separate; border-spacing: 10px; }
    .grid2 td { width: 50%; vertical-align: top; }
    .chart { border: 1px solid var(--line); border-radius: 10px; padding: 10px; background: #fff; }
    .chart img { width: 100%; height: auto; }
    .title-row { display: block; border-bottom: 1px solid var(--line); padding-bottom: 6px; margin-bottom: 8px; font-weight: 700; color: var(--ink); }
    table.data { width: 100%; border-collapse: collapse; }
    table.data th, table.data td { border: 1px solid var(--line); padding: 6px; text-align: left; }
    table.data th { background: var(--tableHead); color: var(--ink); font-size: 11px; }
    .right { text-align: right; }
    .total { font-weight: 700; }
    .badge { display: inline-block; padding: 2px 6px; border: 1px solid #d1d5db; border-radius: 10px; font-size: 10px; background: #f3f4f6; color: #111827; }
  </style>
</head>
<body>
  <!-- Header -->
  <div class="header">
    <div class="header-gradient"></div>
    <div class="header-card">
      <div class="header-logo">
        <div class="header-logo-box">Z</div>
        <span class="header-logo-text">ZemixFi</span>
      </div>
      <div class="header-meta">
        <div>Statement: {{ optional($statement)->original_name }}</div>
        <div>Period: {{ optional($statement->period_start)->toDateString() }} — {{ optional($statement->period_end)->toDateString() }}</div>
        <div>Generated: {{ now()->format('Y-m-d H:i') }}</div>
      </div>
    </div>
  </div>

  <!-- KPIs -->
  <div class="section">
    <table class="kpis">
      <tr>
        <td class="kpi">
          <div class="label">Total Spend</div>
          <div class="value">{{ $fmtMoney($kpiSpend) }}</div>
        </td>
        <td class="kpi">
          <div class="label">Total Fees</div>
          <div class="value">{{ $fmtMoney($kpiFees) }}</div>
        </td>
        <td class="kpi">
          <div class="label">Hidden Fees Detected</div>
          <div class="value">{{ $hiddenFeeCount }}</div>
        </td>
        <td class="kpi">
          <div class="label">Subscriptions</div>
          <div class="value">{{ $subsCount }}</div>
        </td>
      </tr>
    </table>
  </div>

  <!-- Coaching Tips & Card Suggestions -->
  @if(!empty($sum['tips']) || !empty($sum['cardSuggestions']))
    <div class="section">
      <table class="grid2">
        <tr>
          <td>
            @if(!empty($sum['tips']))
              <div class="card">
                <span class="title-row">Coaching Tips</span>
                <ul style="margin:0; padding-left:16px;">
                  @foreach($sum['tips'] as $tip)
                    <li style="margin:4px 0;">{{ $tip }}</li>
                  @endforeach
                </ul>
              </div>
            @endif
          </td>
          <td>
            @if(!empty($sum['cardSuggestions']))
              <div class="card">
                <span class="title-row">Card Suggestions</span>
                <ul style="margin:0; padding-left:16px;">
                  @foreach($sum['cardSuggestions'] as $t)
                    <li style="margin:4px 0;">{{ $t }}</li>
                  @endforeach
                </ul>
              </div>
            @endif
          </td>
        </tr>
      </table>
    </div>
  @endif

  <!-- Charts -->
  @php
    $feeByCategory = collect($sum['feeByCategory'] ?? []);
    $feesOverTime = collect($sum['feesOverTime'] ?? []);
    $topMerchants = collect($sum['topFeeMerchants'] ?? []);

    $pieUrl = null;
    if ($feeByCategory->isNotEmpty()) {
      $pieConfig = urlencode(json_encode([
        'type' => 'pie',
        'data' => [
          'labels' => $feeByCategory->keys()->values(),
          'datasets' => [[
            'data' => $feeByCategory->values()->map(fn($v) => (float)$v)->values(),
            'backgroundColor' => ['#0aa596', '#ff7e5f', '#08897f', '#22c55e', '#f59e0b', '#ef4444']
          ]]
        ],
        'options' => [
          'plugins' => ['legend' => ['position' => 'bottom', 'labels' => ['boxWidth' => 10]]]
        ]
      ]));
      $pieUrl = "https://quickchart.io/chart?c={$pieConfig}";
    }

    $lineUrl = null;
    if ($feesOverTime->isNotEmpty()) {
      $lineConfig = urlencode(json_encode([
        'type' => 'line',
        'data' => [
          'labels' => $feesOverTime->keys()->values(),
          'datasets' => [[
            'data' => $feesOverTime->values()->map(fn($v) => (float)$v)->values(),
            'borderColor' => '#0aa596',
            'backgroundColor' => 'rgba(10,165,150,0.14)',
            'fill' => true,
            'tension' => 0.35,
            'pointRadius' => 2,
            'pointBorderColor' => '#0aa596',
            'pointBackgroundColor' => '#ffffff'
          ]]
        ],
        'options' => [
          'plugins' => ['legend' => ['display' => false]],
          'scales' => [
            'y' => ['beginAtZero' => true, 'grid' => ['color' => '#eef2f7']],
            'x' => ['grid' => ['display' => false]]
          ]
        ]
      ]));
      $lineUrl = "https://quickchart.io/chart?c={$lineConfig}";
    }

    $barUrl = null;
    if ($topMerchants->isNotEmpty()) {
      $barConfig = urlencode(json_encode([
        'type' => 'bar',
        'data' => [
          'labels' => $topMerchants->keys()->values(),
          'datasets' => [[
            'data' => $topMerchants->values()->map(fn($v) => (float)$v)->values(),
            'backgroundColor' => '#ff7e5f',
            'borderRadius' => 8,
            'borderSkipped' => false
          ]]
        ],
        'options' => [
          'plugins' => ['legend' => ['display' => false]],
          'scales' => [
            'y' => ['beginAtZero' => true, 'grid' => ['color' => '#eef2f7']],
            'x' => ['grid' => ['display' => false], 'ticks' => ['autoSkip' => false, 'maxRotation' => 45, 'minRotation' => 0]]
          ]
        ]
      ]));
      $barUrl = "https://quickchart.io/chart?c={$barConfig}";
    }
  @endphp

  <div class="section">
    <table class="grid2">
      <tr>
        <td>
          @if($pieUrl)
            <div class="chart">
              <span class="title-row">Fees by Category</span>
              <img src="{{ $pieUrl }}&width=640&height=360&backgroundColor=white" alt="Fees by Category">
            </div>
          @endif
        </td>
        <td>
          @if($lineUrl)
            <div class="chart">
              <span class="title-row">Fees Over Time</span>
              <img src="{{ $lineUrl }}&width=640&height=360&backgroundColor=white" alt="Fee Trend">
            </div>
          @endif
        </td>
      </tr>
    </table>

    @if($barUrl)
      <div class="spacer12"></div>
      <div class="chart">
        <span class="title-row">Top Fee Sources (Merchants)</span>
        <img src="{{ $barUrl }}&width=1280&height=320&backgroundColor=white" alt="Top Merchants">
      </div>
    @endif
  </div>

  <!-- Hidden Fees by Category -->
  @php
    $canonicalOrder = [
      'Foreign Transaction Fee', 'Currency Conversion Fee', 'Interchange Fee', 'Late Payment Fee',
      'Interest Charge', 'Overdraft Fee', 'Cash Advance Fee', 'Annual Fee', 'Service/Other Fee'
    ];
    $hiddenByCat = [];
    foreach ($canonicalOrder as $cat) { $hiddenByCat[$cat] = ['rows' => [], 'subtotal' => 0.0]; }
    foreach ($hiddenFees as $row) {
      $labels = collect($row['labels'] ?? [])->values();
      $destCats = $labels->intersect($canonicalOrder)->values();
      if ($destCats->isEmpty()) $destCats = collect(['Service/Other Fee']);
      foreach ($destCats as $cat) {
        $hiddenByCat[$cat]['rows'][] = [
          'date' => $row['date'] ?? '',
          'description' => $row['description'] ?? '',
          'amount' => (float)($row['amount'] ?? 0)
        ];
        $hiddenByCat[$cat]['subtotal'] += abs((float)($row['amount'] ?? 0));
      }
    }
    foreach ($hiddenByCat as $cat => $bucket) if (empty($bucket['rows'])) unset($hiddenByCat[$cat]);
    uasort($hiddenByCat, fn($a, $b) => ($b['subtotal'] <=> $a['subtotal']));
  @endphp

  @if(!empty($hiddenByCat))
    <div class="section">
      <div class="card">
        <span class="title-row">Hidden / Unexpected Fees — Breakdown by Category</span>
        <div class="small muted">Top examples per category shown. View dashboard for full list.</div>
        <div class="spacer8"></div>
        @foreach($hiddenByCat as $cat => $bucket)
          <h3>{{ $cat }} <span class="badge">Subtotal: {{ $fmtMoney($bucket['subtotal']) }}</span></h3>
          <table class="data">
            <thead>
              <tr>
                <th style="width:18%;">Date</th>
                <th>Description</th>
                <th class="right" style="width:20%;">Amount</th>
              </tr>
            </thead>
            <tbody>
              @php $examples = array_slice($bucket['rows'], 0, 5); @endphp
              @foreach($examples as $r)
                <tr>
                  <td>{{ $r['date'] }}</td>
                  <td>{{ $r['description'] }}</td>
                  <td class="right">{{ $fmtMoney($r['amount'], $currency, true) }}</td>
                </tr>
              @endforeach
              @if(count($bucket['rows']) > 5)
                <tr>
                  <td colspan="3" class="small muted">+ {{ count($bucket['rows']) - 5 }} more in this category</td>
                </tr>
              @endif
              <tr>
                <td colspan="2" class="total right">Category Subtotal</td>
                <td class="total right">{{ $fmtMoney($bucket['subtotal']) }}</td>
              </tr>
            </tbody>
          </table>
          <div class="spacer12"></div>
        @endforeach
      </div>
    </div>
  @endif

  <div class="section small muted">
    Note: Charts are image snapshots rendered via QuickChart. Ensure DOMPDF option <code>isRemoteEnabled=true</code> (already set in your controller).
  </div>
</body>
</html>