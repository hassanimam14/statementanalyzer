<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>ZemixFi — Sign in / Create account</title>

  <!-- Tailwind + Alpine + FA (icons optional) -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: { inter: ['Inter','ui-sans-serif','system-ui'], sans: ['Inter','ui-sans-serif','system-ui'] },
          colors: {
            body:'#1f2b37', muted:'#5f6b7a', outline:'#e6e6e6',
            primary: { 50:'#f0fdfa', 500:'#0aa596', 600:'#08897f' },
            coral:'#ff7e5f'
          },
          dropShadow: { soft: '0 10px 25px rgba(10,165,150,.18)' }
        }
      }
    }
  </script>
  <script>window.FontAwesomeConfig = { autoReplaceSvg: 'nest' };</script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js" crossorigin="anonymous"></script>

  <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap">
  <style>
    *{font-family:'Inter',sans-serif}
    ::-webkit-scrollbar{display:none}
    html,body{-ms-overflow-style:none;scrollbar-width:none}
    [x-cloak]{display:none!important}
  </style>
</head>

<body class="min-h-full bg-gray-50 text-body overflow-x-hidden">

  {{-- NEW NAVBAR DESIGN --}}
  <header class="mt-6 mb-4 px-4">
    <div class="max-w-7xl mx-auto">
      <div class="bg-white rounded-2xl shadow-md border border-outline/40 p-4 md:p-5">
        <div class="flex items-center justify-between">
          <!-- Logo/Brand -->
          <div class="flex items-center">
            <a href="{{ url('/') }}" class="flex items-center gap-3 group">
              <div class="w-10 h-10 rounded-xl grid place-items-center text-white font-bold shadow-sm transition-all duration-300 group-hover:scale-105"
                   style="background:linear-gradient(135deg,#0aa596 0%,#08897f 100%);">
                <i class="fa-solid fa-shield text-lg"></i>
              </div>
              <div>
                <h1 class="text-xl font-bold text-body">ZemixFi</h1>
                <p class="text-xs text-muted -mt-1 hidden sm:block">Financial Intelligence</p>
              </div>
            </a>
          </div>

          <!-- Desktop Navigation -->
          <nav class="hidden md:flex items-center space-x-6">
            <a href="{{ url('/') }}" class="text-body hover:text-primary-500 font-medium transition-colors">
              Home
            </a>
            <a href="{{ route('statements.create') }}" class="text-body hover:text-primary-500 font-medium transition-colors">
              Upload
            </a>
            <a href="#features" class="text-body hover:text-primary-500 font-medium transition-colors">
              Features
            </a>
            <a href="#pricing" class="text-body hover:text-primary-500 font-medium transition-colors">
              Pricing
            </a>
          </nav>

          <!-- Auth Buttons -->
          <div class="flex items-center space-x-3">
            <a href="{{ route('login') }}" 
               class="px-4 py-2 rounded-lg font-medium text-body hover:bg-gray-100 transition-colors hidden sm:block">
              Sign in
            </a>
            <a href="{{ route('register') }}" 
               class="px-4 py-2 rounded-lg font-medium text-white bg-gradient-to-r from-primary-500 to-primary-600 hover:from-primary-600 hover:to-primary-700 transition-all shadow-sm hover:shadow-md">
              Get Started
            </a>
            
            <!-- Mobile menu button -->
            <button @click="mobileMenuOpen = !mobileMenuOpen" class="md:hidden w-10 h-10 grid place-items-center rounded-lg border border-outline/60 hover:bg-gray-50 transition">
              <svg class="w-5 h-5 text-body" viewBox="0 0 20 20" fill="currentColor">
                <path d="M3 5h14M3 10h14M3 15h14"/>
              </svg>
            </button>
          </div>
        </div>
        
        <!-- Mobile menu (dropdown) -->
        <div x-data="{ mobileMenuOpen: false }" class="relative">
          <div x-show="mobileMenuOpen" x-cloak 
               class="absolute top-full left-0 right-0 mt-2 bg-white rounded-xl border border-outline/60 shadow-lg z-50 py-3">
            <a href="{{ url('/') }}" class="block px-5 py-2 text-body hover:bg-gray-50 transition-colors">
              Home
            </a>
            <a href="{{ route('statements.create') }}" class="block px-5 py-2 text-body hover:bg-gray-50 transition-colors">
              Upload
            </a>
            <a href="#features" class="block px-5 py-2 text-body hover:bg-gray-50 transition-colors">
              Features
            </a>
            <a href="#pricing" class="block px-5 py-2 text-body hover:bg-gray-50 transition-colors">
              Pricing
            </a>
            <div class="border-t border-outline/40 my-2"></div>
            <a href="{{ route('login') }}" class="block px-5 py-2 text-body hover:bg-gray-50 transition-colors">
              Sign in
            </a>
            <a href="{{ route('register') }}" class="block px-5 py-2 text-primary-500 hover:bg-primary-50 transition-colors font-medium">
              Create account
            </a>
          </div>
        </div>
      </div>
    </div>
  </header>

  {{-- CONTENT --}}
  <main class="py-6 md:py-10">
    <div class="max-w-7xl mx-auto px-4">
      <!-- Expanded split card (no curve) -->
      <section
        x-data="{
          tab:(new URLSearchParams(location.search)).get('tab')==='register'?'register':'signin',
          showPwd:false,showPwd2:false,
          switchTo(t){ this.tab=t; const u=new URL(location); u.searchParams.set('tab',t); history.replaceState(null,'',u); }
        }"
        class="mx-auto bg-white border border-outline/70 rounded-2xl shadow-xl drop-shadow-soft overflow-hidden">

        <div class="grid grid-cols-1 lg:grid-cols-2">
          <!-- LEFT PANEL: minimal, clean, fixed -->
          <aside class="relative bg-gradient-to-br from-[#0aa596] via-[#09b3a4] to-[#08897f] text-white p-8 md:p-10">
            <div class="flex items-center gap-3 mb-6">
              <div class="w-9 h-9 rounded-lg grid place-items-center text-white font-bold bg-white/10">Z</div>
              <h2 class="text-lg font-semibold tracking-tight">ZemixFi</h2>
            </div>

            <h3 class="text-2xl md:text-3xl font-extrabold leading-snug">
              Scan statements. <br/>Stop hidden fees.
            </h3>
            <p class="mt-3 text-white/85 text-sm leading-6">
              Works with PDFs, CSVs, and clear photos. No bank login needed.
            </p>

            <ul class="mt-6 space-y-3 text-sm">
              <li class="flex items-center gap-2">
                <i class="fa-solid fa-shield-halved"></i> Bank-grade privacy
              </li>
              <li class="flex items-center gap-2">
                <i class="fa-solid fa-bolt"></i> Analysis in minutes
              </li>
              <li class="flex items-center gap-2">
                <i class="fa-solid fa-file-invoice-dollar"></i> Any issuer, any format
              </li>
            </ul>

            <!-- subtle pattern -->
            <div class="pointer-events-none absolute inset-0 opacity-10"
                 style="background-image: radial-gradient(white 1px, transparent 1px); background-size: 14px 14px;"></div>
          </aside>

          <!-- RIGHT PANEL: fixed size container so tabs don't shift -->
          <section class="p-6 sm:p-8">
            <!-- Tabs -->
            <div class="grid grid-cols-2 rounded-lg bg-gray-50 p-1 mb-6">
              <button @click="switchTo('signin')"
                      :class="tab==='signin' ? 'bg-white shadow-sm text-body' : 'text-muted'"
                      class="py-2 rounded-md text-sm font-semibold transition">Sign in</button>
              <button @click="switchTo('register')"
                      :class="tab==='register' ? 'bg-white shadow-sm text-body' : 'text-muted'"
                      class="py-2 rounded-md text-sm font-semibold transition">Create account</button>
            </div>

            {{-- Alerts (outside the fixed-height stage to avoid jumps) --}}
            @if (session('status'))
              <div class="mb-4 text-sm px-3 py-2 rounded-md bg-primary-50 text-primary-600 border border-primary-500/20">
                {{ session('status') }}
              </div>
            @endif
            @if ($errors->any())
              <div class="mb-4 text-sm px-3 py-2 rounded-md bg-[#ff7e5f]/10 text-[#ff7e5f] border border-[#ff7e5f]/30">
                {{ __('There were some problems with your submission.') }}
              </div>
            @endif

            <!-- FIXED HEIGHT STAGE: forms overlayed, no layout shift -->
            <div class="relative h-[460px]"> {{-- <- keep this height same for both tabs --}}
              <!-- Sign in -->
              <form x-show="tab==='signin'" x-cloak
                    x-transition.opacity
                    method="POST" action="{{ route('login') }}"
                    class="absolute inset-0 overflow-y-auto pr-1">
                @csrf
                <div class="space-y-4">
                  <div>
                    <label for="login_email" class="block text-sm text-muted mb-1">Email</label>
                    <input id="login_email" type="email" name="email" value="{{ old('email') }}" required autofocus
                           class="w-full h-11 px-3 rounded-lg border border-outline focus:outline-none focus:ring-2 focus:ring-primary-500/40"
                           placeholder="you@example.com">
                    @error('email') <p class="text-xs text-[#ef4444] mt-1">{{ $message }}</p> @enderror
                  </div>

                  <div>
                    <div class="flex justify-between">
                      <label for="login_password" class="block text-sm text-muted mb-1">Password</label>
                      @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}" class="text-xs font-semibold text-[#0aa596] hover:text-[#08897f]">Forgot?</a>
                      @endif
                    </div>
                    <div class="relative">
                      <input :type="showPwd ? 'text' : 'password'" id="login_password" name="password" required
                             class="w-full h-11 pl-3 pr-10 rounded-lg border border-outline focus:outline-none focus:ring-2 focus:ring-primary-500/40"
                             placeholder="••••••••">
                      <button type="button" @click="showPwd=!showPwd" class="absolute inset-y-0 right-0 px-3 text-muted">
                        <i :class="showPwd ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye'"></i>
                      </button>
                    </div>
                    @error('password') <p class="text-xs text-[#ef4444] mt-1">{{ $message }}</p> @enderror
                  </div>

                  <label class="inline-flex items-center gap-2 text-sm text-muted">
                    <input type="checkbox" name="remember"
                           class="h-4 w-4 text-primary-500 border-outline rounded focus:ring-primary-500/40">
                    Remember me
                  </label>

                  <button type="submit"
                          class="w-full h-11 rounded-lg text-white font-semibold
                                 bg-gradient-to-r from-[#ff7e5f] to-[#06beb6]
                                 hover:brightness-105 transition">Sign in</button>

                  <div class="flex items-center gap-3">
                    <div class="h-px flex-1 bg-gray-100"></div>
                    <span class="text-xs text-muted">or</span>
                    <div class="h-px flex-1 bg-gray-100"></div>
                  </div>

                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <button type="button" class="h-11 rounded-lg border border-outline bg-white hover:bg-gray-50 text-sm">
                      <i class="fa-brands fa-google mr-2 text-[#ea4335]"></i> Google
                    </button>
                    <button type="button" class="h-11 rounded-lg border border-outline bg-white hover:bg-gray-50 text-sm">
                      <i class="fa-brands fa-github mr-2"></i> GitHub
                    </button>
                  </div>
                </div>
              </form>

              <!-- Register -->
              <form x-show="tab==='register'" x-cloak
                    x-transition.opacity
                    method="POST" action="{{ route('register') }}"
                    class="absolute inset-0 overflow-y-auto pr-1">
                @csrf
                <div class="space-y-4">
                  <div>
                    <label for="reg_name" class="block text-sm text-muted mb-1">Full name</label>
                    <input id="reg_name" type="text" name="name" value="{{ old('name') }}" required
                           class="w-full h-11 px-3 rounded-lg border border-outline focus:outline-none focus:ring-2 focus:ring-primary-500/40"
                           placeholder="Alex Johnson">
                    @error('name') <p class="text-xs text-[#ef4444] mt-1">{{ $message }}</p> @enderror
                  </div>

                  <div>
                    <label for="reg_email" class="block text-sm text-muted mb-1">Email</label>
                    <input id="reg_email" type="email" name="email" value="{{ old('email') }}" required
                           class="w-full h-11 px-3 rounded-lg border border-outline focus:outline-none focus:ring-2 focus:ring-primary-500/40"
                           placeholder="you@example.com">
                    @error('email') <p class="text-xs text-[#ef4444] mt-1">{{ $message }}</p> @enderror
                  </div>

                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                      <label for="reg_password" class="block text-sm text-muted mb-1">Password</label>
                      <div class="relative">
                        <input :type="showPwd ? 'text' : 'password'" id="reg_password" name="password" required
                               class="w-full h-11 pl-3 pr-10 rounded-lg border border-outline focus:outline-none focus:ring-2 focus:ring-primary-500/40"
                               placeholder="••••••••">
                        <button type="button" @click="showPwd=!showPwd" class="absolute inset-y-0 right-0 px-3 text-muted">
                          <i :class="showPwd ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye'"></i>
                        </button>
                      </div>
                      @error('password') <p class="text-xs text-[#ef4444] mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                      <label for="reg_password_confirmation" class="block text-sm text-muted mb-1">Confirm password</label>
                      <div class="relative">
                        <input :type="showPwd2 ? 'text' : 'password'" id="reg_password_confirmation" name="password_confirmation" required
                               class="w-full h-11 pl-3 pr-10 rounded-lg border border-outline focus:outline-none focus:ring-2 focus:ring-primary-500/40"
                               placeholder="••••••••">
                        <button type="button" @click="showPwd2=!showPwd2" class="absolute inset-y-0 right-0 px-3 text-muted">
                          <i :class="showPwd2 ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye'"></i>
                        </button>
                      </div>
                    </div>
                  </div>

                  <label class="inline-flex items-start gap-2 text-sm text-muted">
                    <input type="checkbox" required
                           class="mt-1 h-4 w-4 text-primary-500 border-outline rounded focus:ring-primary-500/40">
                    <span>By creating an account, you agree to our
                      <a href="#" class="text-[#0aa596] hover:text-[#08897f]">Terms</a> and
                      <a href="#" class="text-[#0aa596] hover:text-[#08897f]">Privacy Policy</a>.
                    </span>
                  </label>

                  <button type="submit"
                          class="w-full h-11 rounded-lg text-white font-semibold
                                 bg-gradient-to-r from-[#ff7e5f] to-[#06beb6]
                                 hover:brightness-105 transition">Create account</button>

                  <div class="flex items-center gap-3">
                    <div class="h-px flex-1 bg-gray-100"></div>
                    <span class="text-xs text-muted">or</span>
                    <div class="h-px flex-1 bg-gray-100"></div>
                  </div>

                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <button type="button" class="h-11 rounded-lg border border-outline bg-white hover:bg-gray-50 text-sm">
                      <i class="fa-brands fa-google mr-2 text-[#ea4335]"></i> Google
                    </button>
                    <button type="button" class="h-11 rounded-lg border border-outline bg-white hover:bg-gray-50 text-sm">
                      <i class="fa-brands fa-github mr-2"></i> GitHub
                    </button>
                  </div>
                </div>
              </form>
            </div>

            <!-- small reassurance -->
            <p class="text-[11px] text-muted mt-4">
              Protected by reCAPTCHA • <a href="#" class="text-[#0aa596] hover:text-[#08897f]">Privacy</a> • <a href="#" class="text-[#0aa596] hover:text-[#08897f]">Terms</a>
            </p>
          </section>
        </div>
      </section>
    </div>
  </main>

  {{-- Footer --}}
  <footer class="py-8 border-t border-outline/60 bg-white/60 mt-12">
    <div class="max-w-7xl mx-auto px-4 flex flex-col md:flex-row items-center justify-between">
      <div class="flex items-center mb-4 md:mb-0">
        <div class="w-8 h-8 rounded-lg grid place-items-center text-white font-bold shadow-sm mr-2"
             style="background:linear-gradient(135deg,#0aa596 0%,#08897f 100%);">
          <i class="fa-solid fa-shield"></i>
        </div>
        <span class="font-semibold text-body">ZemixFi</span>
      </div>
      <p class="text-sm text-muted mb-4 md:mb-0">© {{ date('Y') }} ZemixFi. All rights reserved.</p>
      <div class="flex gap-6 text-sm">
        <a href="#" class="text-muted hover:text-body">Privacy</a>
        <a href="#" class="text-muted hover:text-body">Terms</a>
        <a href="#" class="text-muted hover:text-body">Support</a>
      </div>
    </div>
  </footer>
</body>
</html>