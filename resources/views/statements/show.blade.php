@php
  use App\Support\Currency;

  $report = $statement->report;
  $sum    = is_array($report?->summary_json)
              ? $report->summary_json
              : (json_decode($report?->summary_json ?? '[]', true) ?: []);

  // Currency priority: summary → statement → app default
  $currency = strtoupper($sum['currency'] ?? $statement->currency_code ?? config('app.default_currency', 'USD'));
  $currencySymbol = Currency::symbol($currency);

  $allTxns     = $statement->transactions()->get(['amount','category','flags','description','date','merchant']);
  $totalSpend  = 0.0;
  $hiddenFees  = 0.0;

  $spendCats = ['purchase','subscription','cash_advance'];

  $normalizeFlags = function ($flags): array {
      if (is_string($flags)) { $decoded = json_decode($flags, true); return (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : []; }
      if ($flags instanceof \Illuminate\Support\Collection) return $flags->all();
      return is_array($flags) ? $flags : (array) $flags;
  };

  $isFeeLikeFn = function ($t) use ($normalizeFlags): bool {
      $flags  = $normalizeFlags($t->flags ?? []);
      $descLC = mb_strtolower((string)($t->description ?? ''));
      if (preg_match('/\bmarkup\s*rate\s*0\s*%\b/i', $descLC)) return false;
      if (($t->category ?? null) === 'fee') return true;
      $feeFlags = ['service_fee','tax','foreign_tx_fee','interest_charge','late_payment','cash_advance'];
      foreach ($feeFlags as $f) if (in_array($f, $flags, true)) return true;
      return (bool) preg_match('/\b(fee|charge|interest|finance|excise|tax|withholding|levy|pra|kpra|srb|vat|gst|sms\s*banking|giro)\b/i', $descLC);
  };

  foreach ($allTxns as $t) {
      $amt = (float) $t->amount;
      $isFeeLike = $isFeeLikeFn($t);
      if ($isFeeLike && $amt < 0) { $hiddenFees += abs($amt); continue; }
      $cat = strtolower((string)($t->category ?? ''));
      if ($amt < 0 && in_array($cat, $spendCats, true)) { $totalSpend += abs($amt); continue; }
      if ($cat === '' && $amt < 0 && !$isFeeLike) {
          $desc = mb_strtolower((string)($t->description ?? ''));
          if (preg_match('/[a-z]{3,}/', $desc) && !preg_match('/\b(total|summary|balance|opening|closing)\b/i', $desc)) {
              $totalSpend += abs($amt);
          }
      }
  }

  $savings = $hiddenFees * 0.7;
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Transaction Analysis - ZemixFi</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: { inter: ['Inter', 'sans-serif'], sans: ['Inter', 'sans-serif'] },
          colors: {
            primary: {
              50: '#f0fdfa', 100: '#ccfbf1', 200: '#99f6e4', 300: '#5eead4',
              400: '#2dd4bf', 500: '#0aa596', 600: '#089981', 700: '#08897f',
              800: '#115e59', 900: '#134e4a'
            },
            outline: '#e6e6e6',
            body: '#1f2b37',
            muted: '#5f6b7a',
            coral: '#ff7e5f'
          }
        }
      }
    }
  </script>
  <style>
    body { background: #f3f4f6 !important; font-family: 'Inter', sans-serif; }
    ::-webkit-scrollbar { display: none; }
  </style>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="min-h-screen bg-gray-50">
  <div x-data="{ mobileSidebar: false, profile: false }" class="min-h-screen">
    <header class="bg-white border-b border-outline/60 sticky top-0 z-40">
      <div class="max-w-7xl mx-auto px-4 py-3">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <button @click="mobileSidebar = !mobileSidebar"
                    class="lg:hidden w-10 h-10 grid place-items-center rounded-lg border border-outline/60 hover:bg-gray-50 transition"
                    aria-label="Open menu">
              <svg class="w-5 h-5 text-body" viewBox="0 0 20 20" fill="currentColor">
                <path d="M3 5h14M3 10h14M3 15h14"/>
              </svg>
            </button>
            <a href="/dashboard" class="flex items-center gap-2 group">
              <div class="w-8 h-8 rounded-lg grid place-items-center text-white font-bold shadow-sm transition-all duration-300 group-hover:scale-105"
                   style="background:linear-gradient(135deg,#0aa596 0%,#08897f 100%);">
                <i class="fa-solid fa-shield text-sm"></i>
              </div>
              <span class="text-lg font-semibold tracking-tight text-body">ZemixFi</span>
            </a>
          </div>
          <div class="flex items-center gap-3">
            <button class="w-10 h-10 grid place-items-center rounded-lg border border-outline/60 hover:bg-gray-50 transition text-body">
              <i class="fa-regular fa-bell"></i>
            </button>
            <div class="relative">
              <button @click="profile=!profile"
                      class="w-10 h-10 rounded-full grid place-items-center border border-outline/60 bg-white text-body font-semibold hover:bg-gray-50 transition"
                      aria-label="User menu">U</button>
              <div x-show="profile" x-transition.origin.top.right @click.outside="profile=false"
                   class="absolute right-0 mt-2 w-48 bg-white border border-outline/60 rounded-lg shadow-md overflow-hidden z-50">
                <div class="px-3 py-2 text-xs text-muted bg-gray-50">User Name</div>
                <a href="#" class="block px-3 py-2 text-sm text-body hover:bg-gray-50">Profile</a>
                <form method="POST" action="#"><button class="w-full text-left px-3 py-2 text-sm text-body hover:bg-gray-50">Log Out</button></form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 py-6 grid grid-cols-1 lg:grid-cols-12 gap-6">
      <div class="lg:col-span-3 relative">
        <div x-show="mobileSidebar" x-cloak class="fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden" @click="mobileSidebar = false"></div>
        <aside :class="mobileSidebar ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
               class="fixed left-0 top-0 h-full w-72 bg-white border-r border-outline/60 p-4 space-y-2 z-50 transition-transform duration-300 ease-in-out lg:static lg:transform-none lg:w-full">
          <div class="flex items-center justify-between mb-4 lg:hidden">
            <span class="text-lg font-semibold text-body">Menu</span>
            <button @click="mobileSidebar = false" class="w-8 h-8 grid place-items-center rounded-lg hover:bg-gray-50">
              <svg class="w-5 h-5 text-body" viewBox="0 0 20 20" fill="currentColor"><path d="M6.2 6.2l7.6 7.6m0-7.6l-7.6 7.6"/></svg>
            </button>
          </div>
          <nav class="space-y-2">
            <a href="/dashboard" class="flex items-center gap-3 px-3 py-2 rounded-lg bg-primary-500/10 text-primary-700 border border-primary-500/40">
              <i class="fa-solid fa-gauge-high w-4 h-4"></i>Dashboard
            </a>
            <div class="pt-2 border-t border-outline/60"></div>
            <a href="/statements" class="flex items-center gap-3 px-3 py-2 rounded-lg text-body hover:bg-gray-50">
              <i class="fa-solid fa-list w-4 h-4"></i>History
            </a>
            <a href="/coaching" class="flex items-center gap-3 px-3 py-2 rounded-lg text-body hover:bg-gray-50">
              <i class="fa-solid fa-lightbulb w-4 h-4"></i>Coaching Tips
            </a>
            <a href="/reports" class="flex items-center gap-3 px-3 py-2 rounded-lg text-body hover:bg-gray-50">
              <i class="fa-solid fa-chart-simple w-4 h-4"></i>Reports
            </a>
            <div class="pt-2 border-t border-outline/60"></div>
            <a href="/cards" class="flex items-center gap-3 px-3 py-2 rounded-lg text-body hover:bg-gray-50">
              <i class="fa-solid fa-credit-card w-4 h-4"></i>Cards
            </a>
            <a href="/resources" class="flex items-center gap-3 px-3 py-2 rounded-lg text-body hover:bg-gray-50">
              <i class="fa-solid fa-book w-4 h-4"></i>Resources
            </a>
            <div class="pt-2 border-t border-outline/60"></div>
            <a href="/profile" class="flex items-center gap-3 px-3 py-2 rounded-lg text-body hover:bg-gray-50">
              <i class="fa-solid fa-user w-4 h-4"></i>Profile
            </a>
          </nav>
        </aside>
      </div>

      <main class="lg:col-span-9 space-y-6">
        @if (session('status'))
        <div class="bg-green-50 text-green-700 p-4 rounded-lg border border-green-200">{{ session('status') }}</div>
        @endif

        <section class="bg-white rounded-xl border border-outline/60 p-6">
          <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
              <h1 class="text-2xl font-bold text-body">Statement: {{ $statement->original_name }}</h1>
              <p class="text-muted">Uploaded {{ $statement->created_at->diffForHumans() }}</p>
            </div>
            <form method="post" action="{{ route('reports.generate', $statement) }}">
              @csrf
              <button type="submit" class="px-4 py-2 bg-primary-500 text-white rounded-lg hover:bg-primary-600 transition-colors font-medium">
                <i class="fa-solid fa-wand-magic-sparkles mr-2"></i>Generate Report
              </button>
            </form>
          </div>
        </section>

        <section class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div class="bg-gradient-to-br from-primary-50 to-primary-100 p-6 rounded-xl border border-primary-200">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-primary-700 text-sm">Total Spend</p>
                <p class="text-2xl font-bold text-primary-900">{{ $currencySymbol }}{{ number_format($totalSpend, 2) }}</p>
              </div>
              <div class="w-12 h-12 bg-primary-200 rounded-lg flex items-center justify-center">
                <i class="fa-solid fa-credit-card text-primary-600"></i>
              </div>
            </div>
          </div>

          <div class="bg-gradient-to-br from-red-50 to-red-100 p-6 rounded-xl border border-red-200">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-red-700 text-sm">Hidden Fees</p>
                <p class="text-2xl font-bold text-red-900">{{ $currencySymbol }}{{ number_format($hiddenFees, 2) }}</p>
              </div>
              <div class="w-12 h-12 bg-red-200 rounded-lg flex items-center justify-center">
                <i class="fa-solid fa-exclamation-triangle text-red-600"></i>
              </div>
            </div>
          </div>

          <div class="bg-gradient-to-br from-green-50 to-green-100 p-6 rounded-xl border border-green-200">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-green-700 text-sm">Savings Potential</p>
                <p class="text-2xl font-bold text-green-900">{{ $currencySymbol }}{{ number_format($savings, 2) }}</p>
              </div>
              <div class="w-12 h-12 bg-green-200 rounded-lg flex items-center justify-center">
                <i class="fa-solid fa-piggy-bank text-green-600"></i>
              </div>
            </div>
          </div>
        </section>

        <section class="bg-white rounded-xl border border-outline/60 overflow-hidden">
          <div class="p-4 border-b border-outline/60"><h2 class="text-lg font-semibold text-body">Transactions</h2></div>

          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead class="bg-gray-50 text-left">
                <tr>
                  <th class="px-4 py-3 font-medium text-muted">Date</th>
                  <th class="px-4 py-3 font-medium text-muted">Description</th>
                  <th class="px-4 py-3 font-medium text-muted text-right">Amount</th>
                  <th class="px-4 py-3 font-medium text-muted">Category</th>
                  <th class="px-4 py-3 font-medium text-muted">Flags</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-outline/40">
                @foreach($txns as $t)
                @php
                  $rowFlags = $t->flags ?? [];
                  if (is_string($rowFlags)) {
                      $d = json_decode($rowFlags, true);
                      $rowFlags = (json_last_error() === JSON_ERROR_NONE && is_array($d)) ? $d : [];
                  } elseif ($rowFlags instanceof \Illuminate\Support\Collection) {
                      $rowFlags = $rowFlags->all();
                  } elseif (!is_array($rowFlags)) {
                      $rowFlags = (array) $rowFlags;
                  }
                @endphp
                <tr class="hover:bg-gray-50/60">
                  <td class="px-4 py-3 text-body">{{ optional($t->date)->toDateString() ?? (string)$t->date }}</td>
                  <td class="px-4 py-3 text-body">{{ $t->description }}</td>
                  <td class="px-4 py-3 text-right {{ $t->amount < 0 ? 'text-red-600' : 'text-green-600' }}">
                    {{ $currencySymbol }}{{ number_format(abs((float)$t->amount), 2) }}
                  </td>
                  <td class="px-4 py-3 text-body">{{ $t->category }}</td>
                  <td class="px-4 py-3">
                    @if(!empty($rowFlags))
                      <div class="flex flex-wrap gap-1">
                        @foreach($rowFlags as $flag)
                          <span class="px-2 py-1 bg-gray-100 text-body rounded text-xs">{{ $flag }}</span>
                        @endforeach
                      </div>
                    @endif
                  </td>
                </tr>
                @endforeach
              </tbody>
            </table>
          </div>

          <div class="p-4 border-t border-outline/60">{{ $txns->links() }}</div>
        </section>
      </main>
    </div>
  </div>
</body>
</html>
