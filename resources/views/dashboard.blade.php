<x-app-layout>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <script src="https://cdn.tailwindcss.com"></script>
  <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <script src="https://code.highcharts.com/highcharts.js"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: { inter: ['Inter','sans-serif'], sans: ['Inter','sans-serif'] },
          colors: {
            primary: { 500:'#0aa596', 700:'#08897f' },
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
    /* page background + type */
    body{ background:#f3f4f6 !important; font-family:'Inter',sans-serif }

    /* hide Jetstream default nav so designs don't mix */
    nav.bg-white.border-b.border-gray-100{ display:none!important }
  </style>
</head>

@php
  $summary        = $summary ?? [];
  $feeByCategory  = $summary['feeByCategory'] ?? [];
  $tips           = $summary['tips'] ?? [];
  $statementRows  = $statementRows ?? [];
  $totalSpend     = (float)($summary['totalSpend'] ?? 0);
  $totalFees      = abs((float)($summary['totalFees'] ?? 0));
  $savings        = isset($summary['savings']) ? (float)$summary['savings'] : round($totalFees * 0.06, 2);
  $hasData        = ($totalSpend>0 || $totalFees>0) || !empty($feeByCategory) || !empty($statementRows);
  $user           = auth()->user();
  $initials       = strtoupper(mb_substr($user?->name ?? 'U',0,1));
@endphp

<!-- PAGE WRAPPER: provides top space and gray background -->
<div x-data="{ drawer:false, profile:false, stmtsOpen:true }" class="min-h-screen pt-6">

  <!-- HEADER (non-sticky) -->
  <header id="zf-header">
    <div class="max-w-7xl mx-auto px-4">
      <!-- Gradient accent line -->
      <div class="h-1 rounded-t-xl bg-gradient-to-r from-[#ff7e5f] via-[#0aa596] to-[#08897f] opacity-80"></div>

      <!-- Main navbar card -->
      <div class="bg-white border border-outline/60 rounded-b-xl shadow-sm">
        <div class="h-16 px-4 flex items-center justify-between">

          <!-- Left side - Brand + Mobile menu -->
          <div class="flex items-center gap-3">
            <!-- Mobile menu button -->
            <button @click="drawer=true"
                    class="lg:hidden w-10 h-10 grid place-items-center rounded-lg border border-outline/60 hover:bg-gray-50 transition"
                    aria-label="Open menu">
              <svg class="w-5 h-5 text-body" viewBox="0 0 20 20" fill="currentColor">
                <path d="M3 5h14M3 10h14M3 15h14"/>
              </svg>
            </button>

            <!-- Logo/Brand -->
            <div class="flex items-center gap-2">
              <div class="w-9 h-9 rounded-lg grid place-items-center text-white font-bold shadow-sm"
                   style="background:linear-gradient(135deg,#0aa596 0%,#08897f 100%);">
                Z
              </div>
              <span class="text-lg font-semibold tracking-tight text-body">ZemixFi</span>
            </div>
          </div>

          <!-- Right side - Actions -->
          <div class="flex items-center gap-3">
            <!-- Upload button (hidden on mobile) -->
            <a href="{{ route('statements.create') }}"
               class="hidden sm:inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-outline/60 text-body hover:bg-gray-50 transition">
              <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                <path d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"/>
              </svg>
              Upload
            </a>

            <!-- Profile dropdown -->
            <div class="relative">
              <button @click="profile=!profile"
                      class="w-10 h-10 rounded-full grid place-items-center border border-outline/60 bg-white text-body font-semibold hover:bg-gray-50 transition"
                      aria-label="User menu">
                {{ $initials }}
              </button>

              <!-- Dropdown menu -->
              <div x-show="profile"
                   x-transition.origin.top.right
                   @click.outside="profile=false"
                   class="absolute right-0 mt-2 w-48 bg-white border border-outline/60 rounded-lg shadow-md overflow-hidden z-50">
                <div class="px-3 py-2 text-xs text-muted bg-gray-50">
                  {{ $user?->name }}
                </div>
                <a href="{{ route('profile.edit') }}"
                   class="block px-3 py-2 text-sm text-body hover:bg-gray-50">Profile</a>
                <form method="POST" action="{{ route('logout') }}">
                  @csrf
                  <button class="w-full text-left px-3 py-2 text-sm text-body hover:bg-gray-50">
                    Log Out
                  </button>
                </form>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>
  </header>

  <!-- CONTENT WRAPPER -->
  <div class="max-w-7xl mx-auto px-4 py-8 grid grid-cols-1 lg:grid-cols-12 gap-8">

    <!-- SIDEBAR -->
    <aside class="lg:col-span-3">
      <nav class="bg-white rounded-xl border border-outline/60 p-3 space-y-2">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg bg-primary-500/10 text-primary-700 border border-primary-500/40">
          <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path d="M3 10l7-7 7 7v7a2 2 0 01-2 2h-3v-5H8v5H5a2 2 0 01-2-2v-7z"/></svg>
          Dashboard
        </a>

        <div class="pt-2 border-t border-outline/60"></div>

        <a href="{{ route('statements.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-body hover:bg-gray-50">
          <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path d="M4 3h12v2H4V3zm0 4h12v2H4V7zm0 4h12v2H4v-2z"/></svg>
          History
        </a>

        <a href="{{ route('coaching.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-body hover:bg-gray-50">
          <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a6 6 0 016 6c0 2.8-1.8 5.2-4.3 6v2H8.3v-2C5.8 13.2 4 10.8 4 8a6 6 0 016-6z"/></svg>
          Coaching Tips
        </a>

        <a href="{{ route('reports.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-body hover:bg-gray-50">
          <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path d="M3 3h14v2H3V3zm0 4h10v2H3V7zm0 4h14v2H3v-2zm0 4h10v2H3v-2z"/></svg>
          Reports
        </a>

        <div class="pt-2 border-t border-outline/60"></div>

        <a href="{{ route('cards.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-body hover:bg-gray-50">
          <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path d="M2 5a2 2 0 012-2h12a2 2 0 012 2v1H2V5zm0 3h16v7a2 2 0 01-2 2H4a2 2 0 01-2-2V8z"/></svg>
          Cards
        </a>

        <a href="{{ route('resources.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-body hover:bg-gray-50">
          <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path d="M4 4h12v12H4zM6 6h8v2H6zM6 10h8v2H6z"/></svg>
          Resources
        </a>

        <div class="pt-2 border-t border-outline/60"></div>

        <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-body hover:bg-gray-50">
          <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10 10a4 4 0 100-8 4 4 0 000 8zm-7 8a7 7 0 1114 0H3z"/></svg>
          Profile
        </a>
      </nav>
    </aside>

    <!-- MOBILE DRAWER -->
    <div x-show="drawer" x-transition.opacity class="lg:hidden fixed inset-0 z-50">
      <div class="absolute inset-0 bg-black/30" @click="drawer=false"></div>
      <aside class="absolute left-0 top-0 h-full w-72 bg-white border-r border-outline/60 p-3 space-y-2">
        <div class="flex items-center justify-between mb-2">
          <span class="text-base font-semibold text-body">Menu</span>
          <button @click="drawer=false" class="w-8 h-8 grid place-items-center rounded-lg hover:bg-gray-50">
            <svg class="w-5 h-5 text-body" viewBox="0 0 20 20" fill="currentColor"><path d="M6.2 6.2l7.6 7.6m0-7.6l-7.6 7.6"/></svg>
          </button>
        </div>
        <a href="{{ route('dashboard') }}" class="block px-3 py-2 rounded-lg bg-primary-500/10 text-primary-700 border border-primary-500/40">Dashboard</a>
        <a href="{{ route('statements.index') }}" class="block px-3 py-2 rounded-lg hover:bg-gray-50">History</a>
        <a href="{{ route('coaching.index') }}" class="block px-3 py-2 rounded-lg hover:bg-gray-50">Coaching Tips</a>
        <a href="{{ route('reports.index') }}" class="block px-3 py-2 rounded-lg hover:bg-gray-50">Reports</a>
        <a href="{{ route('cards.index') }}" class="block px-3 py-2 rounded-lg hover:bg-gray-50">Cards</a>
        <a href="{{ route('resources.index') }}" class="block px-3 py-2 rounded-lg hover:bg-gray-50">Resources</a>
        <a href="{{ route('profile.edit') }}" class="block px-3 py-2 rounded-lg hover:bg-gray-50">Profile</a>
      </aside>
    </div>

    <!-- MAIN CONTENT -->
    <main class="lg:col-span-9 space-y-8">
      @if(!$hasData)
        <!-- ONBOARDING CONTENT (no data yet) -->
        <section class="bg-white rounded-xl border border-outline/60 p-8">
          <h1 class="text-2xl font-bold text-body">Let's set you up, {{ $user?->name ?? 'there' }} 👋</h1>
          <p class="mt-1 text-muted">Upload your first bank statement to detect hidden fees and get personalized tips.</p>

          <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="p-4 rounded-lg border border-outline/60">
              <div class="text-xs text-muted">Total Spend</div>
              <div class="text-2xl font-bold text-gray-400">$0.00</div>
            </div>
            <div class="p-4 rounded-lg border border-outline/60">
              <div class="text-xs text-muted">Total Fees</div>
              <div class="text-2xl font-bold text-gray-400">$0.00</div>
            </div>
            <div class="p-4 rounded-lg border border-outline/60">
              <div class="text-xs text-muted">Estimated Savings</div>
              <div class="text-2xl font-bold text-gray-400">$0.00</div>
            </div>
          </div>

          <div class="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="rounded-xl border border-outline/60 p-6">
              <h2 class="text-lg font-semibold text-body mb-3">What you'll get</h2>
              <ul class="space-y-3 text-sm text-body">
                <li class="flex gap-3"><span class="w-5 h-5 grid place-items-center rounded-full border border-outline/60">✓</span>Clear breakdown of hidden & bank fees</li>
                <li class="flex gap-3"><span class="w-5 h-5 grid place-items-center rounded-full border border-outline/60">✓</span>One simple chart to see where money leaks</li>
                <li class="flex gap-3"><span class="w-5 h-5 grid place-items-center rounded-full border border-outline/60">✓</span>Actionable coaching tips tailored to your habits</li>
              </ul>
              <a href="{{ route('statements.create') }}" class="mt-6 inline-flex items-center gap-2 px-4 py-2 rounded-md bg-primary-500 text-white hover:bg-primary-700">
                Upload Statement
              </a>
            </div>

            <div class="rounded-xl border border-outline/60 p-6">
              <h2 class="text-lg font-semibold text-body mb-3">How it works</h2>
              <ol class="space-y-3 text-sm text-body">
                <li class="flex gap-3"><span class="w-5 h-5 grid place-items-center rounded-full border border-outline/60">1</span>Upload a CSV/PDF statement.</li>
                <li class="flex gap-3"><span class="w-5 h-5 grid place-items-center rounded-full border border-outline/60">2</span>We detect fees, duplicates & subscriptions.</li>
                <li class="flex gap-3"><span class="w-5 h-5 grid place-items-center rounded-full border border-outline/60">3</span>Review your report and follow tips to save.</li>
              </ol>
              <div class="mt-4 text-xs text-muted">Your data stays private to your account.</div>
            </div>
          </div>

          <div class="mt-6 rounded-xl border border-outline/60 p-6">
            <h2 class="text-lg font-semibold text-body mb-2">History</h2>
            <p class="text-sm text-muted">Once you upload, your statements will appear here with quick links to reports.</p>
          </div>
        </section>
      @else
        <!-- DASHBOARD CONTENT (user has data) -->
        <section class="bg-white rounded-xl border border-outline/60 p-6">
          <div class="max-w-7xl mx-auto relative">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-8">
              <div class="flex-1">
                <h1 class="text-3xl sm:text-4xl font-bold text-neutral-900 mb-2">
                  Welcome back, {{ auth()->user()?->name ?? 'there' }} 👋
                </h1>
                <p class="text-neutral-600 text-base sm:text-lg">
                  Here's what's happening with your finances today
                </p>
              </div>

              <div class="flex flex-col sm:flex-row gap-3">
                <a href="{{ route('statements.create') }}"
                   class="inline-flex items-center gap-2 px-6 py-3 rounded-md bg-primary-500 text-white hover:bg-primary-700 border border-primary-500 font-medium">
                  <svg class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"/>
                  </svg>
                  Upload Statement
                </a>

                <a href="{{ route('reports.index') }}"
                   class="inline-flex items-center gap-2 px-6 py-3 rounded-md bg-white text-neutral-700 hover:bg-gray-50 border border-outline/60 font-medium">
                  <i class="fa-solid fa-file-chart-column"></i>
                  View Reports
                </a>
              </div>
            </div>
          </div>
        </section>

        <!-- KPIs -->
        <section class="bg-white rounded-xl border border-outline/60 p-5">
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="p-4 rounded-lg border border-outline/60">
              <div class="text-xs text-muted">Total Spend</div>
              <div class="text-2xl font-bold text-body">${{ number_format($totalSpend, 2) }}</div>
            </div>
            <div class="p-4 rounded-lg border border-outline/60">
              <div class="text-xs text-muted">Total Fees</div>
              <div class="text-2xl font-bold text-coral">${{ number_format($totalFees, 2) }}</div>
            </div>
            <div class="p-4 rounded-lg border border-outline/60">
              <div class="text-xs text-muted">Estimated Savings</div>
              <div class="text-2xl font-bold text-body">${{ number_format($savings, 2) }}</div>
            </div>
          </div>
        </section>

                <!-- COACHING TIPS -->
<section id="coaching-section" class="mt-6">
  <h2 class="text-lg font-semibold text-body mb-3">Coaching Tips</h2>

  @php
    // pick an icon based on common keywords; fallback to lightbulb
    $pickIcon = function(string $t): string {
      $t = strtolower($t);
      return str_contains($t,'interest')     ? 'fa-percent' :
             (str_contains($t,'fx') || str_contains($t,'foreign') || str_contains($t,'travel') ? 'fa-earth-americas' :
             (str_contains($t,'subscription') || str_contains($t,'recurring') ? 'fa-rotate' :
             (str_contains($t,'alert') || str_contains($t,'notify')          ? 'fa-bell' :
             (str_contains($t,'fee') || str_contains($t,'charge')             ? 'fa-receipt' :
             (str_contains($t,'budget') || str_contains($t,'spend')           ? 'fa-wallet' :
             'fa-lightbulb')))));
    };
  @endphp

  <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
    @forelse($tips as $tip)
      <div class="bg-white rounded-xl border border-outline/60 p-6 hover:shadow-xl hover:shadow-gray-200/60 transition-all group">
        <div class="flex items-start gap-4">
          <div class="p-3 bg-primary-500/10 text-primary-500 rounded-xl group-hover:bg-primary-500 group-hover:text-white transition-all">
            <i class="fa-solid {{ $pickIcon(is_array($tip)?($tip['text']??''):$tip) }} text-lg"></i>
          </div>
          <div class="space-y-1">
            @if(is_array($tip) && !empty($tip['title']))
              <h4 class="text-base font-semibold text-body">{{ $tip['title'] }}</h4>
              <p class="text-sm leading-relaxed text-muted">
                {{ $tip['text'] ?? '' }}
              </p>
              @if(!empty($tip['cta']))
                <div class="pt-1">
                  <a href="{{ $tip['href'] ?? '#' }}" class="text-primary-500 text-sm font-medium hover:underline">
                    {{ $tip['cta'] }}
                  </a>
                </div>
              @endif
            @else
              <p class="text-sm leading-relaxed text-body">{{ is_array($tip) ? ($tip['text'] ?? '') : $tip }}</p>
            @endif
          </div>
        </div>
      </div>
    @empty
      <div class="col-span-full">
        <div class="bg-white rounded-xl border border-outline/60 p-6">
          <div class="flex items-start gap-4">
            <div class="p-3 bg-primary-500/10 text-primary-500 rounded-xl">
              <i class="fa-solid fa-circle-info text-lg"></i>
            </div>
            <div>
              <h4 class="text-base font-semibold text-body mb-1">No tips yet</h4>
              <p class="text-sm text-muted">Upload a statement to receive personalized coaching based on your spending.</p>
            </div>
          </div>
        </div>
      </div>
    @endforelse
  </div>
</section>

        <!-- CHART -->
        @if(!empty($feeByCategory))
        <section class="bg-white rounded-xl border border-outline/60 p-5">
          <h2 class="text-lg font-semibold text-body mb-3">Hidden Fee Breakdown</h2>
          <div id="fee-donut" class="h-72"></div>
        </section>
        @endif



        <!-- HISTORY TABLE -->
        <section class="bg-white rounded-xl border border-outline/60 p-5">
          <div class="flex items-center justify-between mb-3">
            <h2 class="text-lg font-semibold text-body">History</h2>
            <a href="{{ route('statements.index') }}" class="text-sm text-primary-500 hover:text-primary-700">View all</a>
          </div>
          @if(empty($statementRows))
            <div class="p-6 text-muted">No statements found yet.</div>
          @else
            <div class="overflow-x-auto">
              <table class="min-w-full">
                <thead class="bg-gray-50">
                  <tr class="border-b border-outline/60">
                    <th class="px-4 py-2 text-left text-xs text-muted uppercase tracking-wider">Period</th>
                    <th class="px-4 py-2 text-left text-xs text-muted uppercase tracking-wider">Uploaded</th>
                    <th class="px-4 py-2 text-left text-xs text-muted uppercase tracking-wider">Total Fees</th>
                    <th class="px-4 py-2 text-left text-xs text-muted uppercase tracking-wider">Hidden Fees</th>
                    <th class="px-4 py-2 text-left text-xs text-muted uppercase tracking-wider">Disputes</th>
                    <th class="px-4 py-2 text-left text-xs text-muted uppercase tracking-wider">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-outline/20">
                  @foreach($statementRows as $row)
                    <tr class="hover:bg-gray-50/60">
                      <td class="px-4 py-2 text-sm text-body">{{ $row['period_start'] ?? '—' }} — {{ $row['period_end'] ?? '—' }}</td>
                      <td class="px-4 py-2 text-sm text-muted">{{ $row['uploaded_at'] ?? '—' }}</td>
                      <td class="px-4 py-2 text-sm text-body">${{ number_format($row['total_fees'] ?? 0, 2) }}</td>
                      <td class="px-4 py-2 text-sm text-body">{{ $row['hidden_count'] ?? 0 }}</td>
                      <td class="px-4 py-2 text-sm">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-amber-50 text-amber-700 border border-amber-200">{{ $row['disputes'] ?? 0 }}</span>
                      </td>
                      <td class="px-4 py-2 text-sm">
                        <div class="flex flex-wrap gap-2">
                          <a href="{{ route('reports.show', $row['statement_id']) }}" class="px-2 py-1 bg-primary-500/10 text-primary-500 border border-primary-500/40 rounded text-xs hover:bg-primary-500/20">View Report</a>
                          <form method="POST" action="{{ route('statements.destroy', $row['statement_id']) }}" onsubmit="return confirm('Delete this statement and its report?');">
                            @csrf @method('DELETE')
                            <button class="px-2 py-1 bg-white text-red-600 border border-red-200 rounded text-xs hover:bg-red-50">Delete</button>
                          </form>
                        </div>
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @endif
        </section>
      @endif
    </main>
  </div>
</div>

<!-- CHART SCRIPT -->
@if(!empty($feeByCategory))
<script>
  const catLabels = @json(array_keys($feeByCategory));
  const catVals   = @json(array_values($feeByCategory));
  Highcharts.chart('fee-donut', {
    chart:{ type:'pie', backgroundColor:'transparent' },
    title:{ text:null },
    tooltip:{ pointFormat:'<b>{point.percentage:.1f}%</b> (${point.y:.2f})' },
    plotOptions:{ pie:{ innerSize:'60%', dataLabels:{ enabled:false } } },
    series:[{
      name:'Fees',
      data: catLabels.map((label,i)=>({
        name: label,
        y: Number(catVals[i]||0),
        color: ['#0aa596','#ff7e5f','#08897f','#06beb6','#f59e0b','#ef4444'][i%6]
      }))
    }],
    credits:{ enabled:false }
  });
</script>
<script>
(function(){
  const form = document.getElementById('stmt-upload');
  const box  = document.getElementById('stmt-progress');
  const bar  = document.getElementById('stmt-bar');
  const text = document.getElementById('stmt-status-text');

  if (!form) return;

  let progressTimer = null;
  let pollTimer = null;
  let pct = 0;

  function startProgress() {
    box.classList.remove('hidden');
    pct = 0;
    bar.style.width = '0%';
    text.textContent = 'Uploading…';

    // Faux progress: climb to 90% while we poll server
    progressTimer = setInterval(() => {
      pct = Math.min(pct + Math.random()*8, 90);
      bar.style.width = pct.toFixed(0) + '%';
    }, 400);
  }

  function stopProgress(done) {
    if (progressTimer) clearInterval(progressTimer);
    if (done) {
      bar.style.width = '100%';
      text.textContent = 'Analysis complete.';
      setTimeout(() => {
        // Reload the dashboard so the new statement/report shows up
        window.location.reload();
      }, 600);
    } else {
      text.textContent = 'Stopped.';
    }
  }

  async function pollStatus(statementId) {
    text.textContent = 'Analyzing statement…';
    const url = `{{ url('/statements') }}/${encodeURIComponent(statementId)}/status`;
    pollTimer = setInterval(async () => {
      try {
        const r = await fetch(url, { headers: { 'Accept': 'application/json' }});
        if (!r.ok) return;
        const j = await r.json();
        if (j.ready) {
          clearInterval(pollTimer);
          stopProgress(true);
        }
      } catch (e) {
        // keep trying silently
      }
    }, 1500);
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const fd = new FormData(form);
    // Tell the controller we want JSON instead of redirect
    fd.append('ajax', '1');

    startProgress();

    try {
      const r = await fetch(`{{ route('statements.store') }}`, {
        method: 'POST',
        body: fd,
        headers: { 'Accept': 'application/json' },
      });

      if (!r.ok) {
        stopProgress(false);
        const t = await r.text();
        alert('Upload failed: ' + t);
        return;
      }

      const j = await r.json();
      if (j.ok && j.statement_id) {
        // move progress a bit and start polling
        pct = Math.max(pct, 35);
        bar.style.width = pct.toFixed(0) + '%';
        pollStatus(j.statement_id);
      } else {
        stopProgress(false);
        alert('Unexpected server response.');
      }
    } catch (err) {
      stopProgress(false);
      alert('Network error: ' + (err?.message || err));
    }
  });
})();
</script>

@endif
</x-app-layout>
