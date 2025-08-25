<x-app-layout>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <script src="https://cdn.tailwindcss.com"></script>
  <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: { 
            sans: ['Inter', 'sans-serif'],
            heading: ['Inter', 'sans-serif'] 
          },
          colors: {
            primary: { 500:'#0aa596', 700:'#08897f' },
            outline: '#e6e6e6',
            body: '#1f2b37',
            muted: '#5f6b7a',
            coral: '#ff7e5f',
            teal: '#06beb6'
          },
          boxShadow: {
            'button': '0 2px 6px rgba(10,165,150,0.2)',
            'button-hover': '0 4px 12px rgba(10,165,150,0.3)'
          }
        }
      }
    }
  </script>
  <style>
    body { 
      background: #f8fafc !important;
      font-family: 'Inter', sans-serif;
    }
    nav.bg-white.border-b.border-gray-100 { display: none !important }
    
    /* Button animations */
    .btn-primary {
      transition: all 0.2s ease;
      box-shadow: 0 2px 6px rgba(10,165,150,0.2);
    }
    .btn-primary:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(10,165,150,0.3);
    }
    .btn-secondary {
      transition: all 0.2s ease;
    }
    .btn-secondary:hover {
      transform: translateY(-1px);
    }
  </style>
</head>

@php 
  $user = auth()->user();
  $initials = strtoupper(mb_substr($user?->name ?? 'U', 0, 1));
@endphp

<div x-data="{ drawer: false, profile: false }" class="min-h-screen bg-gray-50 pt-6">
  <!-- Header -->
    <header id="zf-header">
    <div class="max-w-7xl mx-auto px-4">
      <!-- Gradient accent line -->
      <div class="h-1 rounded-t-xl bg-gradient-to-r from-coral via-primary-500 to-teal opacity-80"></div>
      
      <!-- Main navbar card -->
      <div class="bg-white border border-outline/60 rounded-b-xl shadow-sm">
        <div class="h-16 px-4 flex items-center justify-between">
          <!-- Left side -->
          <div class="flex items-center gap-3">
            <!-- Mobile menu button -->
            <button @click="drawer=true"
                    class="lg:hidden w-10 h-10 grid place-items-center rounded-lg border border-outline/60 hover:bg-gray-50 transition"
                    aria-label="Open menu">
              <svg class="w-5 h-5 text-body" viewBox="0 0 20 20" fill="currentColor">
                <path d="M3 5h14M3 10h14M3 15h14"/>
              </svg>
            </button>

            <!-- Logo -->
            <div class="flex items-center gap-2">
              <div class="w-8 h-8 rounded-lg grid place-items-center text-white font-bold shadow-md"
                   style="background:linear-gradient(135deg,#0aa596 0%,#08897f 100%)">
                Z
              </div>
              <span class="text-lg font-semibold text-body font-heading">ZemixFi</span>
            </div>
          </div>

          <!-- Right side -->
          <div class="flex items-center gap-3">
            <a href="{{ route('statements.create') }}"
               class="hidden sm:inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-primary-500 text-white hover:bg-primary-600 border border-primary-500 btn-primary">
              <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                <path d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"/>
              </svg>
              Upload
            </a>

            <!-- Profile dropdown -->
            <div class="relative">
              <button @click="profile=!profile"
                      class="w-10 h-10 rounded-full bg-primary-500 text-white font-semibold hover:bg-primary-600 transition"
                      aria-label="User menu">
                {{ $initials }}
              </button>

              <div x-show="profile"
                   x-transition
                   @click.outside="profile=false"
                   class="absolute right-0 mt-2 w-48 bg-white border border-outline/60 rounded-lg shadow-lg py-1 z-50">
                <div class="px-4 py-2 text-xs text-muted border-b border-outline/20">{{ $user?->email }}</div>
                <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-sm text-body hover:bg-gray-50">Profile</a>
                <form method="POST" action="{{ route('logout') }}">
                  @csrf
                  <button class="w-full text-left px-4 py-2 text-sm text-body hover:bg-gray-50">Log Out</button>
                </form>
              </div>
            </div>
          </div>
        </div> <!-- /h-16 -->
      </div> <!-- /navbar card -->
    </div> <!-- /container -->
  </header>

  <!-- Content -->
  <div class="max-w-7xl mx-auto px-4 py-8 grid grid-cols-1 lg:grid-cols-12 gap-8">
    <!-- Sidebar -->
    <aside class="lg:col-span-3">
      <nav class="bg-white rounded-xl border border-outline/60 p-4 space-y-1">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg {{ request()->routeIs('dashboard') ? 'bg-primary-500/10 text-primary-700 border border-primary-500/40' : 'text-body hover:bg-gray-50' }}">
          <svg class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor">
            <path d="M3 10l7-7 7 7v7a2 2 0 01-2 2h-3v-5H8v5H5a2 2 0 01-2-2v-7z"/>
          </svg>
          <span>Dashboard</span>
        </a>

        <div class="border-t border-outline/20 my-2"></div>

        <a href="{{ route('statements.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg {{ request()->routeIs('statements.*') ? 'bg-primary-500/10 text-primary-700 border border-primary-500/40' : 'text-body hover:bg-gray-50' }}">
          <svg class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor">
            <path d="M4 3h12v2H4V3zm0 4h12v2H4V7zm0 4h12v2H4v-2z"/>
          </svg>
          <span>History</span>
        </a>

        <a href="{{ route('coaching.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg {{ request()->routeIs('coaching.*') ? 'bg-primary-500/10 text-primary-700 border border-primary-500/40' : 'text-body hover:bg-gray-50' }}">
          <svg class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor">
            <path d="M10 2a6 6 0 016 6c0 2.8-1.8 5.2-4.3 6v2H8.3v-2C5.8 13.2 4 10.8 4 8a6 6 0 016-6z"/>
          </svg>
          <span>Coaching Tips</span>
        </a>

        <a href="{{ route('reports.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg {{ request()->routeIs('reports.*') ? 'bg-primary-500/10 text-primary-700 border border-primary-500/40' : 'text-body hover:bg-gray-50' }}">
          <svg class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor">
            <path d="M3 3h14v2H3V3zm0 4h10v2H3V7zm0 4h14v2H3v-2zm0 4h10v2H3v-2z"/>
          </svg>
          <span>Reports</span>
        </a>

        <div class="border-t border-outline/20 my-2"></div>

        <a href="{{ route('cards.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg {{ request()->routeIs('cards.*') ? 'bg-primary-500/10 text-primary-700 border border-primary-500/40' : 'text-body hover:bg-gray-50' }}">
          <svg class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor">
            <path d="M2 5a2 2 0 012-2h12a2 2 0 012 2v1H2V5zm0 3h16v7a2 2 0 01-2 2H4a2 2 0 01-2-2V8z"/>
          </svg>
          <span>Cards</span>
        </a>

        <a href="{{ route('resources.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg {{ request()->routeIs('resources.*') ? 'bg-primary-500/10 text-primary-700 border border-primary-500/40' : 'text-body hover:bg-gray-50' }}">
          <svg class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor">
            <path d="M4 4h12v12H4zM6 6h8v2H6zM6 10h8v2H6z"/>
          </svg>
          <span>Resources</span>
        </a>

        <div class="border-t border-outline/20 my-2"></div>

        <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg {{ request()->routeIs('profile.*') ? 'bg-primary-500/10 text-primary-700 border border-primary-500/40' : 'text-body hover:bg-gray-50' }}">
          <svg class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor">
            <path d="M10 10a4 4 0 100-8 4 4 0 000 8zm-7 8a7 7 0 1114 0H3z"/>
          </svg>
          <span>Profile</span>
        </a>
      </nav>
    </aside>

    <!-- Mobile drawer -->
    <div x-show="drawer" x-transition.opacity class="lg:hidden fixed inset-0 z-50">
      <div class="absolute inset-0 bg-black/30" @click="drawer=false"></div>
      <aside class="absolute left-0 top-0 h-full w-72 bg-white border-r border-outline/60 p-4 space-y-1">
        <div class="flex items-center justify-between mb-2 px-2 py-3 border-b border-outline/20">
          <span class="text-lg font-semibold text-body">Menu</span>
          <button @click="drawer=false" class="w-8 h-8 grid place-items-center rounded-lg hover:bg-gray-50">
            <svg class="w-5 h-5 text-body" viewBox="0 0 20 20" fill="currentColor">
              <path d="M6.2 6.2l7.6 7.6m0-7.6l-7.6 7.6"/>
            </svg>
          </button>
        </div>
        <!-- Same nav links as desktop sidebar -->
        <a href="{{ route('dashboard') }}" class="block px-3 py-2.5 rounded-lg {{ request()->routeIs('dashboard') ? 'bg-primary-500/10 text-primary-700 border border-primary-500/40' : 'text-body hover:bg-gray-50' }}">Dashboard</a>
        <a href="{{ route('statements.index') }}" class="block px-3 py-2.5 rounded-lg {{ request()->routeIs('statements.*') ? 'bg-primary-500/10 text-primary-700 border border-primary-500/40' : 'text-body hover:bg-gray-50' }}">History</a>
        <a href="{{ route('coaching.index') }}" class="block px-3 py-2.5 rounded-lg {{ request()->routeIs('coaching.*') ? 'bg-primary-500/10 text-primary-700 border border-primary-500/40' : 'text-body hover:bg-gray-50' }}">Coaching Tips</a>
        <a href="{{ route('reports.index') }}" class="block px-3 py-2.5 rounded-lg {{ request()->routeIs('reports.*') ? 'bg-primary-500/10 text-primary-700 border border-primary-500/40' : 'text-body hover:bg-gray-50' }}">Reports</a>
        <a href="{{ route('cards.index') }}" class="block px-3 py-2.5 rounded-lg {{ request()->routeIs('cards.*') ? 'bg-primary-500/10 text-primary-700 border border-primary-500/40' : 'text-body hover:bg-gray-50' }}">Cards</a>
        <a href="{{ route('resources.index') }}" class="block px-3 py-2.5 rounded-lg {{ request()->routeIs('resources.*') ? 'bg-primary-500/10 text-primary-700 border border-primary-500/40' : 'text-body hover:bg-gray-50' }}">Resources</a>
        <a href="{{ route('profile.edit') }}" class="block px-3 py-2.5 rounded-lg {{ request()->routeIs('profile.*') ? 'bg-primary-500/10 text-primary-700 border border-primary-500/40' : 'text-body hover:bg-gray-50' }}">Profile</a>
      </aside>
    </div>

    <!-- Main content -->
    <main class="lg:col-span-9 space-y-6">
      <section class="bg-white rounded-xl border border-outline/60 p-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <h1 class="text-2xl font-semibold text-body font-heading">Reports</h1>
            <p class="text-muted">All generated PDFs & summaries across your statements.</p>
          </div>
          
        </div>
      </section>

      @if($reports->count() === 0)
        <section class="bg-white rounded-xl border border-outline/60 p-10 text-center">
          <h3 class="text-lg font-semibold text-body font-heading">No reports yet</h3>
          <p class="text-muted mt-1">Upload a statement to generate your first report.</p>
          <a href="{{ route('statements.create') }}" class="mt-4 inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-primary-500 text-white hover:bg-primary-600 border border-primary-500 btn-primary">
            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
              <path d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"/>
            </svg>
            Upload Statement
          </a>
        </section>
      @else
  <!-- Reports grid: always 2 cards per row -->
  <section class="grid grid-cols-1 sm:grid-cols-2 gap-6">
    @foreach($reports as $rep)
      @php
        $s   = $rep->statement;
        $sum = is_array($rep->summary_json) ? $rep->summary_json : (json_decode($rep->summary_json ?? '[]', true) ?: []);
        $fees  = number_format(abs((float)($sum['totalFees'] ?? 0)), 2);
        $spend = number_format((float)($sum['totalSpend'] ?? 0), 2);
        $hCnt  = is_countable($sum['hiddenFees'] ?? null) ? count($sum['hiddenFees']) : 0;
      @endphp

      <article class="bg-white rounded-xl border border-outline/60 p-5 flex flex-col hover:shadow-md transition">
        <!-- Period -->
        <div class="text-xs text-muted">Period</div>
        <div class="text-sm text-body font-medium">
          {{ optional($s?->period_start)->toDateString() }} — {{ optional($s?->period_end)->toDateString() }}
        </div>

        <!-- Metrics (2 per row; Hidden full width) -->
        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
          <!-- Spend -->
          <div class="p-3 rounded-lg border border-outline/60 bg-gray-50 min-w-0 overflow-hidden">
            <div class="text-[11px] text-muted">Spend</div>
            <div class="text-sm sm:text-base font-semibold text-body tabular-nums text-right break-all leading-tight">
              ${{ $spend }}
            </div>
          </div>

          <!-- Fees -->
          <div class="p-3 rounded-lg border border-outline/60 bg-gray-50 min-w-0 overflow-hidden">
            <div class="text-[11px] text-muted">Fees</div>
            <div class="text-sm sm:text-base font-semibold text-coral tabular-nums text-right break-all leading-tight">
              ${{ $fees }}
            </div>
          </div>

          <!-- Hidden (full width) -->
          <div class="p-3 rounded-lg border border-outline/60 bg-gray-50 min-w-0 overflow-hidden sm:col-span-2">
            <div class="text-[11px] text-muted">Hidden</div>
            <div class="text-sm sm:text-base font-semibold text-body tabular-nums text-right break-all leading-tight">
              {{ $hCnt }}
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div class="mt-5 flex gap-2">
          <a href="{{ route('reports.show', $s) }}"
             class="flex-1 px-3 py-2 rounded-lg bg-primary-500/10 text-primary-600 border border-primary-500/40 text-sm hover:bg-primary-500/20 btn-secondary">
            View Summary
          </a>
          @if(!empty($rep->pdf_path))
            <a href="{{ asset($rep->pdf_path) }}" target="_blank"
               class="flex-1 px-3 py-2 rounded-lg bg-white border border-outline/60 text-sm hover:bg-gray-50 btn-secondary">
              Open PDF
            </a>
          @endif
        </div>
      </article>
    @endforeach
  </section>

  @if(method_exists($reports, 'links'))
    <div class="border-t border-outline/60 pt-4 mt-2">
      {{ $reports->withQueryString()->links() }}
    </div>
  @endif
@endif

    </main>
  </div>
</div>
</x-app-layout>