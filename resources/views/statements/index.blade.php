<x-app-layout>
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1" />
  <script src="https://cdn.tailwindcss.com"></script>
  <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <script>
    tailwind.config = { theme:{ extend:{
      fontFamily:{ inter:['Inter','sans-serif'], sans:['Inter','sans-serif'] },
      colors:{ primary:{500:'#0aa596',700:'#08897f'}, outline:'#e6e6e6', body:'#1f2b37', muted:'#5f6b7a', coral:'#ff7e5f' }
    }}}
  </script>
  <style>
    body{background:#fff!important;font-family:'Inter',sans-serif}
    nav.bg-white.border-b.border-gray-100{display:none!important}
  </style>
</head>

@php
  $user = auth()->user(); $initials = strtoupper(mb_substr($user?->name ?? 'U',0,1));
@endphp

<div x-data="{ drawer: false, profile: false }" class="min-h-screen bg-gray-50 pt-6">

  {{-- Header (non-sticky, with spacing from top) --}}
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

  <div class="max-w-7xl mx-auto px-4 py-8 grid grid-cols-1 lg:grid-cols-12 gap-8">
    {{-- Sidebar --}}
    <aside class="lg:col-span-3">
      <nav class="bg-white rounded-xl border border-outline/60 p-3 space-y-2">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-body hover:bg-gray-50">
          <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path d="M3 10l7-7 7 7v7a2 2 0 01-2 2h-3v-5H8v5H5a2 2 0 01-2-2v-7z"/></svg> Dashboard
        </a>
        <a href="{{ route('statements.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg bg-primary-500/10 text-primary-700 border border-primary-500/40">
          <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path d="M4 3h12v2H4V3zm0 4h12v2H4V7zm0 4h12v2H4v-2z"/></svg> History
        </a>
        <a href="{{ route('coaching.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-body hover:bg-gray-50">
          <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a6 6 0 016 6c0 2.8-1.8 5.2-4.3 6v2H8.3v-2C5.8 13.2 4 10.8 4 8a6 6 0 016-6z"/></svg> Coaching Tips
        </a>
        <a href="{{ route('reports.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-body hover:bg-gray-50">
          <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path d="M3 3h14v2H3V3zm0 4h10v2H3V7zm0 4h14v2H3v-2zm0 4h10v2H3v-2z"/></svg> Reports
        </a>
        <div class="pt-2 border-t border-outline/60"></div>
        <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-body hover:bg-gray-50">
          <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10 10a4 4 0 100-8 4 4 0 000 8zm-7 8a7 7 0 1114 0H3z"/></svg> Profile
        </a>
      </nav>
    </aside>

    {{-- Mobile drawer --}}
    <div x-show="drawer" x-transition.opacity class="lg:hidden fixed inset-0 z-50">
      <div class="absolute inset-0 bg-black/30" @click="drawer=false"></div>
      <aside class="absolute left-0 top-0 h-full w-72 bg-white border-r border-outline/60 p-3 space-y-2">
        <div class="flex items-center justify-between mb-2">
          <span class="text-base font-semibold text-body">Menu</span>
          <button @click="drawer=false" class="w-8 h-8 grid place-items-center rounded-lg hover:bg-gray-50">
            <svg class="w-5 h-5 text-body" viewBox="0 0 20 20" fill="currentColor"><path d="M6.2 6.2l7.6 7.6m0-7.6l-7.6 7.6"/></svg>
          </button>
        </div>
        <a href="{{ route('dashboard') }}" class="block px-3 py-2 rounded-lg hover:bg-gray-50">Dashboard</a>
        <a href="{{ route('statements.index') }}" class="block px-3 py-2 rounded-lg bg-primary-500/10 text-primary-700 border border-primary-500/40">Statements</a>
        <a href="{{ route('reports.index') }}" class="block px-3 py-2 rounded-lg hover:bg-gray-50">Reports</a>
        <a href="{{ route('coaching.index') }}" class="block px-3 py-2 rounded-lg hover:bg-gray-50">Coaching Tips</a>
        <a href="{{ route('profile.edit') }}" class="block px-3 py-2 rounded-lg hover:bg-gray-50">Profile</a>
      </aside>
    </div>

    {{-- MAIN --}}
    <main class="lg:col-span-9 space-y-6">

      {{-- Header + Filters --}}
      <section class="bg-white rounded-xl border border-outline/60 p-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <h1 class="text-2xl font-semibold text-body">Statements</h1>
            <p class="text-muted">Browse, search and manage your uploaded statements.</p>
          </div>
          <a href="{{ route('statements.create') }}"
             class="inline-flex items-center gap-2 px-4 py-2 rounded-md bg-primary-500 text-white hover:bg-primary-700 border border-primary-500">
            + Upload Statement
          </a>
        </div>

        <form method="GET" class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-3">
          <input type="text" name="q" value="{{ request('q') }}"
                 class="w-full rounded-md border border-outline/60 px-3 py-2" placeholder="Search file or description…">
          <input type="date" name="from" value="{{ request('from') }}" class="w-full rounded-md border border-outline/60 px-3 py-2">
          <input type="date" name="to"   value="{{ request('to')   }}" class="w-full rounded-md border border-outline/60 px-3 py-2">
          <div class="sm:col-span-3 flex gap-2">
            <button class="px-3 py-2 rounded-md bg-white border border-outline/60 hover:bg-gray-50">Filter</button>
            <a href="{{ route('statements.index') }}" class="px-3 py-2 rounded-md text-muted hover:text-body">Reset</a>
          </div>
        </form>
      </section>

      {{-- Table --}}
      <section class="bg-white rounded-xl border border-outline/60 p-0 overflow-hidden">
        @if($statements->count() === 0)
          <div class="p-10 text-center">
            <h3 class="text-lg font-semibold text-body">No statements yet</h3>
            <p class="text-muted mt-1">Upload your first statement to populate history and reports.</p>
            <a href="{{ route('statements.create') }}" class="mt-4 inline-flex items-center gap-2 px-4 py-2 rounded-md bg-primary-500 text-white hover:bg-primary-700 border border-primary-500">+ Upload</a>
          </div>
        @else
          <div class="overflow-x-auto">
            <table class="min-w-full">
              <thead class="bg-gray-50">
                <tr class="border-b border-outline/60">
                  <th class="px-4 py-2 text-left text-xs text-muted uppercase tracking-wider">File</th>
                  <th class="px-4 py-2 text-left text-xs text-muted uppercase tracking-wider">Period</th>
                  <th class="px-4 py-2 text-left text-xs text-muted uppercase tracking-wider">Uploaded</th>
                  <th class="px-4 py-2 text-left text-xs text-muted uppercase tracking-wider">Fees</th>
                  <th class="px-4 py-2 text-left text-xs text-muted uppercase tracking-wider">Hidden</th>
                  <th class="px-4 py-2 text-left text-xs text-muted uppercase tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-outline/20">
                @foreach($statements as $s)
                  @php
                    $sum  = is_array($s->report?->summary_json) ? $s->report->summary_json : (json_decode($s->report?->summary_json ?? '[]', true) ?: []);
                    $fees = number_format(abs((float)($sum['totalFees'] ?? 0)),2);
                    $hCnt = is_countable($sum['hiddenFees'] ?? null) ? count($sum['hiddenFees']) : 0;
                  @endphp
                  <tr class="hover:bg-gray-50/60">
                    <td class="px-4 py-2 text-sm text-body">{{ $s->original_name ?? 'Statement' }}</td>
                    <td class="px-4 py-2 text-sm text-body">{{ optional($s->period_start)->toDateString() }} — {{ optional($s->period_end)->toDateString() }}</td>
                    <td class="px-4 py-2 text-sm text-muted">{{ optional($s->created_at)->toDayDateTimeString() }}</td>
                    <td class="px-4 py-2 text-sm text-body">${{ $fees }}</td>
                    <td class="px-4 py-2 text-sm text-body">{{ $hCnt }}</td>
                    <td class="px-4 py-2 text-sm">
                      <div class="flex flex-wrap gap-2">
                        @if($s->report)
                          <a href="{{ route('reports.show',$s) }}" class="px-2 py-1 bg-primary-500/10 text-primary-600 border border-primary-500/40 rounded text-xs hover:bg-primary-500/20">View Report</a>
                        @endif
                        <form method="POST" action="{{ route('statements.destroy',$s) }}" onsubmit="return confirm('Delete this statement and its report?');">
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

          <div class="p-4 border-t border-outline/60">
            {{ $statements->withQueryString()->links() }}
          </div>
        @endif
      </section>

    </main>
  </div>
</div>
</x-app-layout>
