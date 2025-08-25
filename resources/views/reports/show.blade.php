@php
  use App\Support\Currency;

  // ---------- Summary + currency ----------
  $sum = is_array($summary ?? null) ? $summary : (json_decode($summary ?? '[]', true) ?: []);

  // prefer statement → summary → app default
  $currency = strtoupper($statement->currency_code ?? ($sum['currency'] ?? config('app.default_currency', 'USD')));
  \Log::debug('reports.show currency selection', [
      'statement_currency' => $statement->currency_code,
      'summary_currency'   => $sum['currency'] ?? null,
      'final_currency'     => $currency
  ]);
  $currencySymbol = Currency::symbol($currency);

  // ---------- Helpers ----------
  // Generic formatter that respects the row currency (prints ISO if different from display currency)
  $fmtMoney = function ($amount, ?string $code = null, $forceAbs = false) use ($currency, $currencySymbol) {
      if (is_array($amount)) {
          $minor = isset($amount['amount_minor']) ? (int)$amount['amount_minor'] : (int)round(((float)($amount['amount'] ?? 0)) * 100);
          $code  = strtoupper($amount['currency_code'] ?? $code ?? $currency);
          $val   = $minor / 100.0;
      } else {
          $val  = (float)$amount;
          $code = strtoupper($code ?? $currency);
      }
      $val = $forceAbs ? abs($val) : $val;

      $sym    = Currency::symbol($code) ?: $currencySymbol;
      $prefix = ($code !== $currency) ? ($code . ' ') : $sym;
      return $prefix . number_format($val, 2);
  };

  // Display-currency-only formatter (always uses page currency + symbol)
  $fmtMoneyDisplayCcy = function ($amount, $forceAbs = false) use ($currency, $currencySymbol) {
      if (is_array($amount)) {
          $minor = isset($amount['amount_minor']) ? (int)$amount['amount_minor'] : (int)round(((float)($amount['amount'] ?? 0)) * 100);
          $val   = $minor / 100.0;
      } else {
          $val = (float)$amount;
      }
      $val = $forceAbs ? abs($val) : $val;
      return $currencySymbol . number_format($val, 2);
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
      $flags  = $normalizeFlags($t->flags ?? []);
      if (preg_match('/\bmarkup\s*rate\s*0\s*%\b/i', $descLC)) return false;
      if (($t->category ?? null) === 'fee') return true;
      $feeFlags = ['service_fee','tax','foreign_tx_fee','interest_charge','late_payment','cash_advance'];
      foreach ($feeFlags as $f) if (in_array($f, $flags, true)) return true;
      return (bool) preg_match('/\b(fee|charge|interest|finance|excise|tax|withholding|levy|pra|kpra|srb|vat|gst|sms\s*banking|giro|overlimit)\b/i', $descLC);
  };

  $spendCats = ['purchase','subscription','cash_advance'];

  // ---------- KPIs (display currency only) ----------
  $txnsInCurrency = $statement->transactions()
      ->where(function ($q) use ($currency) {
          $q->whereNull('currency_code')->orWhere('currency_code', $currency);
      })
      ->get(['amount','amount_minor','currency_code','category','flags','description','date','merchant']);

  $kpiSpend = 0.0;
  $kpiFees  = 0.0;

  foreach ($txnsInCurrency as $t) {
      $amt = isset($t->amount_minor) ? ((int)$t->amount_minor) / 100.0 : (float)$t->amount;
      if ($isFeeLike($t) && $amt < 0) { $kpiFees += abs($amt); continue; }
      $cat = strtolower((string)($t->category ?? ''));
      if ($amt < 0 && (in_array($cat, $spendCats, true) || $cat === '')) $kpiSpend += abs($amt);
  }

  $kpiSavings = round($kpiFees * 0.70, 2);

  \Log::debug('reports.show KPI computation', [
      'computed_kpiSpend'   => $kpiSpend,
      'computed_kpiFees'    => $kpiFees,
      'computed_kpiSavings' => $kpiSavings,
      'transaction_count'   => $txnsInCurrency->count()
  ]);

  // ---------- Chart series fallback (if summary empty) ----------
  $txnsForCharts = $txnsInCurrency->count()
      ? $txnsInCurrency
      : $statement->transactions()
          ->where(function ($q) use ($currency) {
              $q->whereNull('currency_code')->orWhere('currency_code', $currency);
          })
          ->get(['amount','amount_minor','currency_code','category','flags','description','date','merchant']);

  $monthKey = function($d) {
      try { return \Carbon\Carbon::parse($d)->format('Y-m'); } catch (\Throwable $e) { return 'Unknown'; }
  };

  $spendSeries = [];   // month => total spend
  $feesSeries  = [];   // month => total fees
  $feeCats     = [];   // category => total fees

  foreach ($txnsForCharts as $t) {
      $amt = isset($t->amount_minor) ? ((int)$t->amount_minor) / 100.0 : (float)$t->amount;
      $mon = $monthKey($t->date);
      $cat = strtolower((string)($t->category ?? ''));
      $feeLike = $isFeeLike($t);

      if ($amt < 0) {
          if ($feeLike) {
              $feesSeries[$mon] = ($feesSeries[$mon] ?? 0) + abs($amt);

              // simple label mapping
              $bucket = 'Service/Other Fee';
              $d = mb_strtolower((string)($t->description ?? ''));
              if (str_contains($d,'foreign transaction'))                           $bucket = 'Foreign Transaction Fee';
              elseif (str_contains($d,'conversion') || str_contains($d,'conv.rate')) $bucket = 'Currency Conversion Fee';
              elseif (str_contains($d,'late payment'))                                $bucket = 'Late Payment Fee';
              elseif (preg_match('/\binterest|finance charge|markup\b/i',$d) && !preg_match('/\b0\s*%\b/',$d))
                                                                                      $bucket = 'Interest Charge';
              elseif (str_contains($d,'cash advance'))                                $bucket = 'Cash Advance Fee';
              elseif (str_contains($d,'annual fee'))                                  $bucket = 'Annual Fee';
              $feeCats[$bucket] = ($feeCats[$bucket] ?? 0) + abs($amt);
          } else {
              if (in_array($cat, $spendCats, true) || $cat === '') {
                  $spendSeries[$mon] = ($spendSeries[$mon] ?? 0) + abs($amt);
              }
          }
      }
  }

  // Prefer summary-provided series; otherwise use our rebuilds
  $spendOver = !empty($sum['spendOverTime']) ? $sum['spendOverTime'] : $spendSeries;
  $feesOver  = !empty($sum['feesOverTime'])  ? $sum['feesOverTime']  : $feesSeries;
  $feeByCat  = !empty($sum['feeByCategory']) ? $sum['feeByCategory'] : $feeCats;

  // Hidden fees + tips
  $hiddenFees    = $sum['hiddenFees'] ?? [];
  $tips          = $sum['tips'] ?? [];
  $disputesCount = is_countable($hiddenFees) ? count($hiddenFees) : 0;
@endphp



<x-app-layout>
  <head>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.highcharts.com/highcharts.js"></script>
    <script>window.FontAwesomeConfig = { autoReplaceSvg: 'nest' };</script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js" crossorigin="anonymous"></script>
    <script>
      tailwind.config = {
        theme: {
          extend: {
            fontFamily: { inter: ['Inter', 'sans-serif'], sans: ['Inter', 'sans-serif'] },
            colors: {
              primary: {50: '#f0fdfa', 500: '#14b8a6', 600: '#0d9488', 900: '#134e4a'},
              secondary: {500: '#f97316', 600: '#ea580c'}
            },
            backgroundImage: {
              'gradient-primary': 'linear-gradient(135deg, #f97316 0%, #14b8a6 100%)'
            }
          }
        }
      }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap">
    <style>
      *{font-family:'Inter',sans-serif}
      ::-webkit-scrollbar{display:none}
      html,body{-ms-overflow-style:none;scrollbar-width:none}
    </style>
  </head>

  <div class="bg-white-50 min-h-screen">
    <!-- Top bar -->
    <div class="bg-white/80 border-b border-neutral-200/60 sticky top-0 z-40">
      <div class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <a href="{{ route('dashboard') }}" class="text-neutral-600 hover:text-primary-600 flex items-center">
            <i class="fa-solid fa-arrow-left mr-2"></i> Back to Dashboard
          </a>
        </div>
        <div class="flex items-center gap-3">
          @if(!empty(($report->pdf_path ?? null)))
          <a href="{{ route('reports.download', $statement) }}"
             data-turbo="false" data-turbolinks="false" wire:navigate="false"
             download
             class="px-4 py-2 text-sm border border-neutral-200 rounded-md bg-white hover:bg-neutral-50 text-neutral-700">
            <i class="fa-solid fa-download mr-2"></i>Download PDF
          </a>
          @endif

          <form method="POST" action="{{ route('statements.destroy', $statement) }}"
                onsubmit="return confirm('Delete this statement and its report?');">
            @csrf @method('DELETE')
            <button class="px-4 py-2 text-sm border border-red-200 rounded-md bg-white hover:bg-red-50 text-red-600">
              <i class="fa-solid fa-trash mr-2"></i>Delete Statement
            </button>
          </form>
        </div>
      </div>
    </div>

    <main class="max-w-7xl mx-auto px-4 py-8 space-y-8">
      <!-- Metadata -->
      <section>
        <h1 class="text-2xl font-bold text-neutral-900 mb-4">
          {{ $statement->original_name ?? 'Statement' }}
        </h1>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div class="bg-white rounded-lg border border-neutral-200/60 p-4">
            <p class="text-xs text-neutral-500 mb-1">Statement</p>
            <p class="text-neutral-800">{{ $statement->original_name ?? '—' }}</p>
          </div>
          <div class="bg-white rounded-lg border border-neutral-200/60 p-4">
            <p class="text-xs text-neutral-500 mb-1">Uploaded</p>
            <p class="text-neutral-800">{{ optional($statement->created_at)->toDayDateTimeString() ?? '—' }}</p>
          </div>
        </div>
      </section>

      <!-- Summary cards (KPI section) -->
      <section>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
          <div class="bg-white rounded-xl border border-neutral-200/60 p-6 flex items-center">
            <div class="p-3 rounded-lg bg-primary-50 text-primary-600 mr-4"><i class="fa-solid fa-money-bill-wave"></i></div>
            <div>
              <p class="text-sm text-neutral-500">Total Spending</p>
              <p class="text-2xl font-bold">{{ $fmtMoney($kpiSpend) }}</p>
            </div>
          </div>
          <div class="bg-white rounded-xl border border-neutral-200/60 p-6 flex items-center">
            <div class="p-3 rounded-lg bg-secondary-500/10 text-secondary-600 mr-4"><i class="fa-solid fa-hand-holding-dollar"></i></div>
            <div>
              <p class="text-sm text-neutral-500">Total Fees</p>
              <p class="text-2xl font-bold text-secondary-600">{{ $fmtMoney($kpiFees) }}</p>
            </div>
          </div>
          <div class="bg-white rounded-xl border border-neutral-200/60 p-6 flex items-center">
            <div class="p-3 rounded-lg bg-white-50 text-primary-600 mr-4"><i class="fa-solid fa-piggy-bank"></i></div>
            <div>
              <p class="text-sm text-neutral-500">Potential Savings</p>
              <p class="text-2xl font-bold">{{ $fmtMoney($kpiSavings) }}</p>
            </div>
          </div>
          <div class="bg-white rounded-xl border border-neutral-200/60 p-6 flex items-center">
            <div class="p-3 rounded-lg bg-secondary-500/10 text-secondary-600 mr-4"><i class="fa-solid fa-flag"></i></div>
            <div>
              <p class="text-sm text-neutral-500">Disputes Open</p>
              <p class="text-2xl font-bold">{{ $disputesCount }}</p>
            </div>
          </div>
        </div>
      </section>

      @php
        $ccyCount = $statement->transactions()
            ->selectRaw('COALESCE(currency_code, ?) as currency_code', [$currency])
            ->groupBy('currency_code')
            ->get()
            ->count();
      @endphp

      @if($ccyCount > 1)
        <p class="text-xs text-neutral-500 mt-2">Note: This statement contains multiple currencies. Totals shown in {{ $currency }}.</p>
      @endif

      <!-- Charts -->
      @if(!empty($tips))
        <section>
          <h2 class="text-xl font-bold text-neutral-900 mb-4">AI Recommendations</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($tips as $tip)
              <div class="bg-white-50 border border-primary-500/20 rounded-lg p-4">
                <div class="flex items-start">
                  <div class="mr-3 text-primary-600"><i class="fa-solid fa-lightbulb"></i></div>
                  <p class="text-neutral-800 text-sm">{{ $tip }}</p>
                </div>
              </div>
            @endforeach
          </div>
        </section>
      @endif

      <section>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="bg-white rounded-xl border border-neutral-200/60 p-6">
            <h3 class="text-lg font-semibold text-neutral-900 mb-4">Spending vs Fees Over Time</h3>
            <div id="spend-fees" class="h-72"></div>
          </div>
          <div class="bg-white rounded-xl border border-neutral-200/60 p-6">
            <h3 class="text-lg font-semibold text-neutral-900 mb-4">Fee Breakdown by Category</h3>
            <div id="fee-cat" class="h-72"></div>
          </div>
        </div>
      </section>

      <!-- Hidden fees table -->
      <section>
        <h2 class="text-xl font-bold text-neutral-900 mb-4">Hidden Fees Detected</h2>
        <div class="bg-white rounded-xl border border-neutral-200/60 overflow-hidden">
          <div class="overflow-x-auto">
            <table class="min-w-full">
              <thead class="bg-neutral-50">
                <tr class="border-b border-neutral-200/60">
                  <th class="px-6 py-3 text-left text-xs text-neutral-500 uppercase tracking-wider">Date</th>
                  <th class="px-6 py-3 text-left text-xs text-neutral-500 uppercase tracking-wider">Merchant/Description</th>
                  <th class="px-6 py-3 text-left text-xs text-neutral-500 uppercase tracking-wider">Amount</th>
                  <th class="px-6 py-3 text-left text-xs text-neutral-500 uppercase tracking-wider">Fee Type</th>
                  <th class="px-6 py-3 text-left text-xs text-neutral-500 uppercase tracking-wider">Status</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-neutral-100">
                @forelse($hiddenFees as $hf)
                  <tr class="hover:bg-neutral-50/60">
                    <td class="px-6 py-3 text-sm text-neutral-800">{{ $hf['date'] ?? '—' }}</td>
                    <td class="px-6 py-3 text-sm text-neutral-800">{{ $hf['description'] ?? 'Unknown' }}</td>
                    <td class="px-6 py-3 text-sm text-neutral-800">
                      {{ $fmtMoney(['amount' => $hf['amount'] ?? 0, 'amount_minor' => $hf['amount_minor'] ?? null, 'currency_code' => $hf['currency_code'] ?? $currency], null, true) }}
                    </td>
                    <td class="px-6 py-3 text-sm text-neutral-600">
                      @php $labels = $hf['labels'] ?? []; @endphp
                      @if(!empty($labels))
                        @foreach($labels as $lbl)
                          <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-700 mr-1">{{ $lbl }}</span>
                        @endforeach
                      @else
                        Fee
                      @endif
                    </td>
                    <td class="px-6 py-3 text-sm">
                      <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-secondary-500/10 text-secondary-700 border border-secondary-500/20">
                        Unresolved
                      </span>
                    </td>
                  </tr>
                @empty
                  <tr><td colspan="5" class="px-6 py-6 text-center text-neutral-500">No hidden fees found for this statement.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- Transactions explorer -->
      <section>
        <div class="flex justify-between items-center mb-3">
          <h2 class="text-xl font-bold text-neutral-900">Transactions</h2>
        </div>
        <div class="bg-white rounded-xl border border-neutral-200/60 overflow-hidden">
          <div class="overflow-x-auto">
            <table class="min-w-full">
              <thead class="bg-neutral-50">
                <tr class="border-b border-neutral-200/60">
                  <th class="px-6 py-3 text-left text-xs text-neutral-500 uppercase tracking-wider">Date</th>
                  <th class="px-6 py-3 text-left text-xs text-neutral-500 uppercase tracking-wider">Merchant</th>
                  <th class="px-6 py-3 text-left text-xs text-neutral-500 uppercase tracking-wider">Description</th>
                  <th class="px-6 py-3 text-left text-xs text-neutral-500 uppercase tracking-wider">Amount</th>
                  <th class="px-6 py-3 text-left text-xs text-neutral-500 uppercase tracking-wider">Category</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-neutral-100">
                @forelse($transactions as $t)
                  <tr class="hover:bg-neutral-50/60">
                    <td class="px-6 py-3 text-sm text-neutral-800">{{ optional($t->date)->toDateString() ?? '—' }}</td>
                    <td class="px-6 py-3 text-sm text-neutral-800">{{ $t->merchant ?? '—' }}</td>
                    <td class="px-6 py-3 text-sm text-neutral-800">{{ $t->description ?? '—' }}</td>
                    <td class="px-6 py-3 text-sm text-neutral-800">
                      @php
                        $code = $t->currency_code ?? $currency;
                        $minor = isset($t->amount_minor) ? (int)$t->amount_minor : null;
                        $val = $minor !== null ? $minor / 100.0 : (float)$t->amount;
                      @endphp
                      {{ $fmtMoney(['amount' => $val, 'amount_minor' => $minor, 'currency_code' => $code], null, true) }}
                    </td>
                    <td class="px-6 py-3 text-sm text-neutral-600">
                      {{ $t->category ?? ($t->amount < 0 ? 'debit' : 'credit') }}
                    </td>
                  </tr>
                @empty
                  <tr><td colspan="5" class="px-6 py-6 text-center text-neutral-500">No transactions.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>

          <div class="px-4 py-3 border-t border-neutral-200/60">
            {{ $transactions->withQueryString()->links() }}
          </div>
        </div>
      </section>
    </main>
  </div>

  {{-- Charts --}}
  <script>
    const spendLabels = @json(array_keys($spendOver));
    const spendVals = @json(array_values($spendOver));
    const feeLabels = @json(array_keys($feesOver));
    const feeVals = @json(array_values($feesOver));
    const catLabels = @json(array_keys($feeByCat));
    const catVals = @json(array_values($feeByCat));

    Highcharts.chart('spend-fees', {
      chart: { type: 'line', backgroundColor: 'transparent' },
      title: { text: null },
      xAxis: { categories: spendLabels, labels: { style: { color: '#64748b' } } },
      yAxis: { title: { text: null }, labels: { style: { color: '#64748b' } } },
      series: [
        { name: 'Spending', data: spendVals, color: '#14b8a6' },
        { name: 'Fees', data: feeVals.length ? feeVals : new Array(spendVals.length).fill(0), color: '#f97316' }
      ],
      credits: { enabled: false }
    });

    Highcharts.chart('fee-cat', {
      chart: { type: 'pie', backgroundColor: 'transparent' },
      title: { text: null },
      plotOptions: { pie: { innerSize: '60%', dataLabels: { enabled: false } } },
      series: [{
        name: 'Share',
        data: catLabels.map((label, i) => ({
          name: label,
          y: Number(catVals[i] || 0),
          color: ['#14b8a6', '#f97316', '#134e4a', '#60a5fa', '#f59e0b', '#ef4444'][i % 6]
        }))
      }],
      credits: { enabled: false }
    });
  </script>
</x-app-layout>