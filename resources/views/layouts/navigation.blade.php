{{-- resources/views/partials/topbar.blade.php
<header x-data="{ profile:false, searchOpen:false }" class="sticky top-0 z-40">
  <!-- 1px gradient ribbon -->
  <div class="h-1 bg-gradient-to-r from-[#ff7e5f] via-[#0aa596] to-[#08897f]"></div>

  <!-- Bar -->
  <div class="bg-white border-b border-gray-200/80 shadow-sm">
    <div class="max-w-7xl mx-auto px-3 sm:px-4">
      <div class="h-14 flex items-center justify-between gap-3">

        <!-- Left : hamburger (mobile) + brand + breadcrumb -->
        <div class="flex items-center gap-2 sm:gap-3">
          <!-- Hamburger to open your existing LEFT SIDEBAR drawer -->
          <button
            class="lg:hidden w-10 h-10 grid place-items-center rounded-lg border border-gray-200 hover:bg-gray-50"
            aria-label="Open menu"
            @click="$dispatch('toggle-sidebar')">
            <svg class="w-5 h-5 text-slate-700" viewBox="0 0 20 20" fill="currentColor"><path d="M3 5h14M3 10h14M3 15h14"/></svg>
          </button>

          <!-- Brand -->
          <a href="{{ Route::has('dashboard') ? route('dashboard') : url('/') }}" class="flex items-center gap-2">
            <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-lg grid place-items-center text-white font-bold shadow-sm"
                 style="background:linear-gradient(135deg,#0aa596 0%,#08897f 100%);">
              Z
            </div>
            <span class="hidden sm:inline text-[15px] font-semibold tracking-tight text-slate-800">ZemixFi</span>
          </a>

          <!-- Optional breadcrumb (pass $breadcrumb = 'Reports › March 2025' when needed) -->
          @isset($breadcrumb)
            <div class="hidden md:flex items-center gap-2 text-sm text-slate-500">
              <svg class="w-4 h-4 text-slate-400" viewBox="0 0 20 20" fill="currentColor"><path d="M7 5l5 5-5 5"/></svg>
              <span class="truncate max-w-[32ch]">{{ $breadcrumb }}</span>
            </div>
          @endisset
        </div>

        <!-- Center : global search (desktop) -->
        <div class="hidden md:flex flex-1 max-w-xl mx-4">
          <form method="GET" action="{{ Route::has('search') ? route('search') : url()->current() }}" class="w-full">
            <label class="relative block">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
                <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path d="M12.9 14.32a8 8 0 111.414-1.414l3.387 3.387-1.414 1.414-3.387-3.387z"/></svg>
              </span>
              <input
                type="search" name="q" value="{{ request('q') }}"
                placeholder="Search transactions, merchants, fees…  (Press / to focus)"
                class="w-full h-10 pl-9 pr-3 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-500/30"
                x-on:keydown.window.prevent.slash="$event.target.tagName!=='INPUT'&&$event.target.tagName!=='TEXTAREA'&&$el.focus()">
            </label>
          </form>
        </div>

        <!-- Right : actions -->
        <div class="flex items-center gap-1 sm:gap-2">

          <!-- Search icon (mobile) opens small overlay -->
          <button class="md:hidden w-10 h-10 grid place-items-center rounded-lg border border-gray-200 hover:bg-gray-50"
                  @click="searchOpen=true" aria-label="Search">
            <svg class="w-5 h-5 text-slate-700" viewBox="0 0 20 20" fill="currentColor"><path d="M12.9 14.32a8 8 0 111.414-1.414l3.387 3.387-1.414 1.414-3.387-3.387z"/></svg>
          </button>

          <!-- Primary CTA -->
          <a href="{{ Route::has('statements.create') ? route('statements.create') : '#' }}"
             class="hidden sm:inline-flex items-center gap-2 h-10 px-3 rounded-lg text-white font-semibold
                    bg-gradient-to-r from-[#ff7e5f] via-[#0aa596] to-[#08897f] hover:brightness-105">
            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"/></svg>
            Upload
          </a>

          <!-- Notifications -->
          @php $notifCount = $notifCount ?? 0; @endphp
          <a href="{{ Route::has('notifications.index') ? route('notifications.index') : '#' }}"
             class="relative w-10 h-10 grid place-items-center rounded-lg border border-gray-200 hover:bg-gray-50"
             aria-label="Notifications">
            <svg class="w-5 h-5 text-slate-700" viewBox="0 0 20 20" fill="currentColor">
              <path d="M10 2a4 4 0 00-4 4v2.586l-.707 2.121A1 1 0 006.236 12h7.528a1 1 0 00.943-1.293L14 8.586V6a4 4 0 00-4-4z"/>
              <path d="M8 13a2 2 0 004 0H8z"/>
            </svg>
            @if($notifCount>0)
              <span class="absolute -top-1 -right-1 min-w-[18px] h-[18px] grid place-items-center rounded-full bg-rose-500 text-white text-[10px] px-1">
                {{ $notifCount > 99 ? '99+' : $notifCount }}
              </span>
            @endif
          </a>

          <!-- Help -->
          <a href="{{ Route::has('support') ? route('support') : '#' }}"
             class="hidden sm:grid w-10 h-10 place-items-center rounded-lg border border-gray-200 hover:bg-gray-50"
             aria-label="Help">
            <svg class="w-5 h-5 text-slate-700" viewBox="0 0 20 20" fill="currentColor"><path d="M18 10A8 8 0 11.001 10 8 8 0 0118 10zm-8 3a1 1 0 100 2 1 1 0 000-2zm.25-9a3.25 3.25 0 00-3.2 2.68 1 1 0 001.97.32 1.25 1.25 0 112.48.2c0 .6-.29.95-1.08 1.52-.93.65-1.42 1.3-1.42 2.48V13a1 1 0 002 0v-.35c0-.63.23-.93.94-1.44 1.04-.73 1.56-1.51 1.56-2.7A3.25 3.25 0 0010.25 4z"/></svg>
          </a>

          <!-- Plan badge (optional) -->
          @php $plan = $planName ?? (auth()->user()->plan->name ?? null); @endphp
          @if($plan)
            <span class="hidden md:inline px-2 h-6 grid place-items-center rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
              {{ $plan }}
            </span>
          @endif

          <!-- Avatar dropdown -->
          <div class="relative">
            <button @click="profile=!profile"
                    class="w-10 h-10 rounded-full grid place-items-center border border-gray-200 bg-white text-slate-700 font-semibold hover:bg-gray-50"
                    aria-label="User menu">
              {{ $initials ?? (Auth::user()?->name ? strtoupper(mb_substr(Auth::user()->name,0,1)) : 'U') }}
            </button>
            <div x-show="profile" x-transition.origin.top.right @click.outside="profile=false"
                 class="absolute right-0 mt-2 w-56 bg-white border border-gray-200 rounded-lg shadow-md overflow-hidden z-50">
              <div class="px-3 py-2 text-xs text-slate-500 bg-gray-50">
                {{ auth()->user()->name ?? 'Guest' }}
                @if(auth()->user()?->email)
                  <div class="text-[11px] truncate">{{ auth()->user()->email }}</div>
                @endif
              </div>

              @auth
                @if(Route::has('profile.edit'))
                  <a href="{{ route('profile.edit') }}" class="block px-3 py-2 text-sm text-slate-800 hover:bg-gray-50">Profile & Security</a>
                @endif
                @if(Route::has('billing'))
                  <a href="{{ route('billing') }}" class="block px-3 py-2 text-sm text-slate-800 hover:bg-gray-50">Billing & Plan</a>
                @endif
                @if(Route::has('integrations'))
                  <a href="{{ route('integrations') }}" class="block px-3 py-2 text-sm text-slate-800 hover:bg-gray-50">Integrations</a>
                @endif
                @if(Route::has('privacy'))
                  <a href="{{ route('privacy') }}" class="block px-3 py-2 text-sm text-slate-800 hover:bg-gray-50">Data & Privacy</a>
                @endif
                <div class="border-t border-gray-200"></div>
                <form method="POST" action="{{ route('logout') }}">
                  @csrf
                  <button class="w-full text-left px-3 py-2 text-sm text-slate-800 hover:bg-gray-50">Sign out</button>
                </form>
              @else
                @if(Route::has('login'))
                  <a href="{{ route('login') }}" class="block px-3 py-2 text-sm text-slate-800 hover:bg-gray-50">Sign in</a>
                @endif
                @if(Route::has('register'))
                  <a href="{{ route('register') }}" class="block px-3 py-2 text-sm text-slate-800 hover:bg-gray-50">Create account</a>
                @endif
              @endauth
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>

  <!-- Mobile search overlay -->
  <div x-show="searchOpen" x-cloak class="fixed inset-0 z-50">
    <div class="absolute inset-0 bg-black/30" @click="searchOpen=false"></div>
    <div class="absolute top-6 inset-x-4 rounded-xl bg-white border border-gray-200 shadow-lg p-3">
      <form method="GET" action="{{ Route::has('search') ? route('search') : url()->current() }}">
        <label class="relative block">
          <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path d="M12.9 14.32a8 8 0 111.414-1.414l3.387 3.387-1.414 1.414-3.387-3.387z"/></svg>
          </span>
          <input
            type="search" name="q" placeholder="Search…"
            class="w-full h-11 pl-9 pr-3 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-500/30"
            autofocus>
        </label>
      </form>
    </div>
  </div>
</header> --}}
