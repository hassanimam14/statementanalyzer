{{-- resources/views/auth/portal.blade.php --}}
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
          fontFamily: { inter: ['Inter','sans-serif'], sans: ['Inter','sans-serif'] },
          colors: {
            body:'#1f2b37', muted:'#5f6b7a', outline:'#e6e6e6',
            primary: { 50:'#f0fdfa', 500:'#0aa596', 600:'#08897f' },
            coral: '#ff7e5f'
          },
          dropShadow: {
            soft: '0 10px 25px rgba(0,0,0,0.06)'
          }
        }
      }
    }
  </script>
  <script>window.FontAwesomeConfig = { autoReplaceSvg: 'nest' };</script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js" crossorigin="anonymous"></script>

  <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap">
  <style>
    *{font-family:'Inter',sans-serif}
    ::-webkit-scrollbar{display:none}
    html,body{-ms-overflow-style:none;scrollbar-width:none}
    [x-cloak]{display:none !important}
  </style>
</head>

<body class="min-h-full bg-gray-50 text-body relative overflow-x-hidden">

  <!-- Gradient Curve Background (SVG Wave) -->
  <div class="pointer-events-none absolute inset-x-0 -top-40 -z-10">
    <svg class="w-[140%] min-w-[1200px] h-[340px] mx-auto block" viewBox="0 0 1440 320" aria-hidden="true">
      <defs>
        <linearGradient id="zemixfiWave" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%"  stop-color="#ff7e5f" />
          <stop offset="50%" stop-color="#0aa596" />
          <stop offset="100%" stop-color="#08897f" />
        </linearGradient>
      </defs>
      <!-- wave -->
      <path fill="url(#zemixfiWave)" fill-opacity="0.22"
            d="M0,128L80,138.7C160,149,320,171,480,165.3C640,160,800,128,960,133.3C1120,139,1280,181,1360,202.7L1440,224L1440,0L1360,0C1280,0,1120,0,960,0C800,0,640,0,480,0C320,0,160,0,80,0L0,0Z"></path>
      <!-- deeper wave -->
      <path fill="url(#zemixfiWave)" fill-opacity="0.12"
            d="M0,160L60,181.3C120,203,240,245,360,250.7C480,256,600,224,720,213.3C840,203,960,213,1080,202.7C1200,192,1320,160,1380,144L1440,128L1440,0L0,0Z"></path>
    </svg>
  </div>

  <!-- Soft Gradient Blobs -->
  <div class="pointer-events-none absolute -top-10 -right-20 w-[420px] h-[420px] rounded-full blur-3xl opacity-40 -z-10"
       style="background: radial-gradient(50% 50% at 50% 50%, rgba(10,165,150,0.35) 0%, rgba(8,137,127,0.05) 60%, transparent 70%);">
  </div>
  <div class="pointer-events-none absolute top-40 -left-24 w-[380px] h-[380px] rounded-full blur-3xl opacity-40 -z-10"
       style="background: radial-gradient(50% 50% at 50% 50%, rgba(255,126,95,0.35) 0%, rgba(255,126,95,0.06) 60%, transparent 70%);">
  </div>

  <!-- Top accent ribbon -->
  <div class="h-1 w-full bg-gradient-to-r from-[#ff7e5f] via-[#0aa596] to-[#08897f]"></div>

  <div class="max-w-7xl mx-auto px-4 py-10 md:py-14">
    <!-- Header -->
    <header class="mb-8 md:mb-12 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg grid place-items-center text-white font-bold drop-shadow-soft"
             style="background:linear-gradient(135deg,#0aa596 0%,#08897f 100%);">Z</div>
        <span class="text-lg font-semibold">ZemixFi</span>
      </div>
      <a href="{{ url('/') }}" class="text-sm text-muted hover:text-body">Back to site</a>
    </header>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-10 items-start">
      <!-- Left: Brand + Benefits (AUTH-FRIENDLY) -->
      <section class="order-2 md:order-1">
        <h1 class="text-3xl md:text-4xl font-bold leading-tight">
          Sign in. Scan a statement. Save money.
        </h1>
        <p class="mt-2 text-muted text-lg">
          We read your card statements and flag hidden fees, subscriptions, and overcharges — in minutes.
        </p>

        <!-- Value props -->
        <div class="mt-6 grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div class="bg-white rounded-xl border border-outline p-4 shadow-sm">
            <div class="flex items-center gap-2 text-primary-600">
              <i class="fa-solid fa-bolt"></i>
              <span class="text-sm font-semibold">Fast</span>
            </div>
            <p class="mt-2 text-sm text-muted">Results in under a minute.</p>
          </div>
          <div class="bg-white rounded-xl border border-outline p-4 shadow-sm">
            <div class="flex items-center gap-2 text-[#ff7e5f]">
              <i class="fa-solid fa-shield-halved"></i>
              <span class="text-sm font-semibold">Private</span>
            </div>
            <p class="mt-2 text-sm text-muted">Bank-grade security & deletion.</p>
          </div>
          <div class="bg-white rounded-xl border border-outline p-4 shadow-sm">
            <div class="flex items-center gap-2 text-sky-600">
              <i class="fa-solid fa-file-invoice-dollar"></i>
              <span class="text-sm font-semibold">Any Statement</span>
            </div>
            <p class="mt-2 text-sm text-muted">PDF, CSV, or clear photo.</p>
          </div>
        </div>

        <!-- How it works -->
        <div class="mt-6 bg-white rounded-xl border border-outline p-5 shadow-sm">
          <div class="flex items-center gap-2 mb-2">
            <i class="fa-solid fa-diagram-project text-primary-600"></i>
            <h3 class="text-sm font-semibold">How it works</h3>
          </div>

          <ol class="mt-2 space-y-4">
            <li class="flex gap-3">
              <div class="h-6 w-6 rounded-full grid place-items-center text-white text-xs font-bold"
                   style="background:linear-gradient(135deg,#0aa596,#08897f)">1</div>
              <div>
                <p class="text-sm font-semibold">Upload your statement</p>
                <p class="text-sm text-muted">Any bank, any layout — PDF/CSV/JPG/PNG.</p>
              </div>
            </li>
            <li class="flex gap-3">
              <div class="h-6 w-6 rounded-full grid place-items-center text-white text-xs font-bold"
                   style="background:linear-gradient(135deg,#ff7e5f,#ffa987)">2</div>
              <div>
                <p class="text-sm font-semibold">We analyze automatically</p>
                <p class="text-sm text-muted">Find fees, subscriptions, FX charges, and duplicates.</p>
              </div>
            </li>
            <li class="flex gap-3">
              <div class="h-6 w-6 rounded-full grid place-items-center text-white text-xs font-bold"
                   style="background:linear-gradient(135deg,#3b82f6,#93c5fd)">3</div>
              <div>
                <p class="text-sm font-semibold">Get simple next steps</p>
                <p class="text-sm text-muted">Call script to waive fees, cancel unused subs, switch plans.</p>
              </div>
            </li>
          </ol>
        </div>

        <!-- Trust/Privacy -->
        <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="bg-white rounded-xl border border-outline p-5 shadow-sm">
            <div class="flex items-center gap-2 mb-2">
              <i class="fa-solid fa-lock text-primary-600"></i>
              <h3 class="text-sm font-semibold">Privacy first</h3>
            </div>
            <p class="text-sm text-muted">
              Files are processed securely and can be deleted anytime. We only store the insights you approve.
            </p>
          </div>
          <div class="bg-white rounded-xl border border-outline p-5 shadow-sm">
            <div class="flex items-center gap-2 mb-2">
              <i class="fa-solid fa-thumbs-up text-[#ff7e5f]"></i>
              <h3 class="text-sm font-semibold">No bank login required</h3>
            </div>
            <p class="text-sm text-muted">
              Just your statement. Works with most issuers around the world.
            </p>
          </div>
        </div>

        <!-- Small testimonial -->
        <div class="mt-6 bg-white rounded-xl border border-outline p-5 shadow-sm">
          <div class="flex items-center gap-3">
            <img src="https://i.pravatar.cc/40?img=12" alt="" class="h-10 w-10 rounded-full">
            <div>
              <p class="text-sm text-body font-semibold">“Found $120 in fees in 3 minutes.”</p>
              <p class="text-xs text-muted">Avery M., switched plans and saved instantly</p>
            </div>
          </div>
        </div>
      </section>

      <!-- Right: Auth Card (tabs) -->
      <section class="order-1 md:order-2"
               x-data="{
                 tab: (new URLSearchParams(location.search)).get('tab')==='register' ? 'register' : 'signin',
                 switchTo(t){ this.tab=t; const u=new URL(location); u.searchParams.set('tab', t); history.replaceState(null,'',u); }
               }">

        <!-- Gradient border wrapper -->
        <div class="bg-gradient-to-r from-[#ff7e5f] via-[#0aa596] to-[#08897f] p-[1px] rounded-2xl drop-shadow-soft">
          <div class="bg-white rounded-2xl border border-outline/60 shadow-sm p-6 sm:p-8">
            <!-- Tabs -->
            <div class="grid grid-cols-2 mb-6 rounded-lg bg-gray-50 p-1">
              <button @click="switchTo('signin')"
                      :class="tab==='signin' ? 'bg-white shadow-sm text-body' : 'text-muted'"
                      class="py-2 rounded-md text-sm font-semibold transition">Sign in</button>
              <button @click="switchTo('register')"
                      :class="tab==='register' ? 'bg-white shadow-sm text-body' : 'text-muted'"
                      class="py-2 rounded-md text-sm font-semibold transition">Create account</button>
            </div>

            {{-- Status flash (Laravel session) --}}
            @if (session('status'))
              <div class="mb-4 text-sm px-3 py-2 rounded-md bg-primary-50 text-primary-600 border border-primary-500/20">
                {{ session('status') }}
              </div>
            @endif

            {{-- Global errors --}}
            @if ($errors->any())
              <div class="mb-4 text-sm px-3 py-2 rounded-md bg-[#ff7e5f]/10 text-[#ff7e5f] border border-[#ff7e5f]/30">
                {{ __('There were some problems with your submission.') }}
              </div>
            @endif

            <!-- Sign in -->
            <form x-show="tab==='signin'" x-cloak method="POST" action="{{ route('login') }}" class="space-y-4">
              @csrf
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
                    <a href="{{ route('password.request') }}" class="text-sm text-primary-500 hover:text-primary-600">Forgot?</a>
                  @endif
                </div>
                <input id="login_password" type="password" name="password" required
                       class="w-full h-11 px-3 rounded-lg border border-outline focus:outline-none focus:ring-2 focus:ring-primary-500/40"
                       placeholder="••••••••">
                @error('password') <p class="text-xs text-[#ef4444] mt-1">{{ $message }}</p> @enderror
              </div>

              <label class="inline-flex items-center gap-2 text-sm text-muted">
                <input type="checkbox" name="remember"
                       class="h-4 w-4 text-primary-500 border-outline rounded focus:ring-primary-500/40">
                Remember me
              </label>

              <button type="submit"
                      class="w-full h-11 rounded-lg text-white font-semibold
                             bg-gradient-to-r from-[#ff7e5f] via-[#0aa596] to-[#08897f]
                             hover:brightness-105 transition">Sign in</button>
            </form>

            <!-- Register -->
            <form x-show="tab==='register'" x-cloak method="POST" action="{{ route('register') }}" class="space-y-4">
              @csrf
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
                  <input id="reg_password" type="password" name="password" required
                         class="w-full h-11 px-3 rounded-lg border border-outline focus:outline-none focus:ring-2 focus:ring-primary-500/40"
                         placeholder="••••••••">
                  @error('password') <p class="text-xs text-[#ef4444] mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                  <label for="reg_password_confirmation" class="block text-sm text-muted mb-1">Confirm password</label>
                  <input id="reg_password_confirmation" type="password" name="password_confirmation" required
                         class="w-full h-11 px-3 rounded-lg border border-outline focus:outline-none focus:ring-2 focus:ring-primary-500/40"
                         placeholder="••••••••">
                </div>
              </div>

              <label class="inline-flex items-start gap-2 text-sm text-muted">
                <input type="checkbox" required
                       class="mt-1 h-4 w-4 text-primary-500 border-outline rounded focus:ring-primary-500/40">
                <span>By creating an account, you agree to our
                  <a href="#" class="text-primary-500 hover:text-primary-600">Terms</a> and
                  <a href="#" class="text-primary-500 hover:text-primary-600">Privacy Policy</a>.
                </span>
              </label>

              <button type="submit"
                      class="w-full h-11 rounded-lg text-white font-semibold
                             bg-gradient-to-r from-[#ff7e5f] via-[#0aa596] to-[#08897f]
                             hover:brightness-105 transition">Create account</button>
            </form>

            <!-- Divider -->
            <div class="my-6 flex items-center gap-3">
              <div class="h-px flex-1 bg-gray-100"></div>
              <span class="text-xs text-muted">or</span>
              <div class="h-px flex-1 bg-gray-100"></div>
            </div>

            <!-- Social (optional placeholders) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <button type="button" class="h-11 rounded-lg border border-outline bg-white hover:bg-gray-50 text-sm">
                <i class="fa-brands fa-google mr-2 text-[#ea4335]"></i> Continue with Google
              </button>
              <button type="button" class="h-11 rounded-lg border border-outline bg-white hover:bg-gray-50 text-sm">
                <i class="fa-brands fa-github mr-2"></i> Continue with GitHub
              </button>
            </div>
          </div>
        </div>

        <!-- Small legal -->
        <p class="text-xs text-muted mt-4 text-center">
          Protected by reCAPTCHA • <a href="#" class="text-primary-500 hover:text-primary-600">Privacy</a> • <a href="#" class="text-primary-500 hover:text-primary-600">Terms</a>
        </p>
      </section>
    </div>
  </div>

  <footer class="py-8 border-t border-outline/60 bg-white/60">
    <div class="max-w-7xl mx-auto px-4 flex items-center justify-between">
      <p class="text-sm text-muted">© {{ date('Y') }} ZemixFi. All rights reserved.</p>
      <div class="flex gap-6 text-sm">
        <a href="#" class="text-muted hover:text-body">Privacy</a>
        <a href="#" class="text-muted hover:text-body">Terms</a>
        <a href="#" class="text-muted hover:text-body">Support</a>
      </div>
    </div>
  </footer>
</body>
</html>
