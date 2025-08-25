{{-- resources/views/landing.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>ZemixFi – AI-Powered Financial Coach</title>

  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <style>
    :root{
      --ink:#0f1720; --body:#1f2b37; --muted:#5f6b7a;
      --card:#0e1a2b; --bg:#fffdf9;
      --primary:#0aa596; --primary-700:#08897f; --outline:#e6e6e6;
    }
    *{box-sizing:border-box} html,body{height:100%}
    body{margin:0;font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial;color:var(--body);background:var(--bg);-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}
    .nav{position:sticky;top:0;z-index:50;background:#fff;border-bottom:1px solid #f1f2f4}
    .nav__inner{max-width:1120px;margin:0 auto;padding:14px 20px;display:flex;align-items:center;gap:24px}
    .brand{display:flex;align-items:center;gap:10px;text-decoration:none;color:#0e1a2b;font-weight:800;letter-spacing:.2px}
    .brand__logo{width:28px;height:28px;border-radius:7px;background:linear-gradient(135deg,#13c2b0,#0d9488);display:grid;place-items:center;color:#fff;font-weight:800}
    .nav__links{margin-left:auto;display:flex;gap:22px}
    .nav__links a{text-decoration:none;color:#334155;font-weight:500;font-size:14px}
    .nav__actions{display:flex;gap:10px;margin-left:14px}
    .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:10px 16px;border-radius:10px;font-weight:600;font-size:14px;text-decoration:none;transition:.15s ease}
    .btn--ghost{color:#334155}
    .btn--primary{color:#fff;background:var(--primary);border:1px solid var(--primary);box-shadow:0 6px 16px rgba(10,165,150,.18)}
    .btn--primary:hover{background:var(--primary-700);border-color:var(--primary-700)}
    .hero{padding:88px 0 0}
    .hero__wrap{max-width:960px;margin:0 auto;text-align:center;padding:0 20px;}
    .eyebrow{font-size:12px;letter-spacing:.14em;text-transform:uppercase;color:#6b7280}
    .h1{margin:.35em 0 .3em;line-height:1.1;font-size:clamp(36px,5vw,52px);font-weight:800;color:#0e1a2b}
    .lead{max-width:720px;margin:0 auto 26px;color:var(--muted);font-size:18px;line-height:1.6}
    .hero__cta{display:flex;gap:14px;justify-content:center;flex-wrap:wrap}
    .btn--outline{border:1px solid #cfd6de;color:#0e1a2b;background:#fff}
    .btn--outline:hover{background:#f7fafc}
    .section{padding:56px 20px}
    .container{max-width:1120px;margin:0 auto}
    .h2{font-size:clamp(26px,4vw,36px);font-weight:800;color:#0e1a2b;margin-bottom:12px}
    .sub{color:var(--muted);max-width:700px;line-height:1.7}
    .icons{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;margin-top:28px}
    .card{border-radius:14px;padding:18px;background:#fff;border:1px solid #e6e6e6;transition:.15s ease;height:100%}
    .card:hover{transform:translateY(-2px);box-shadow:0 10px 22px rgba(22,34,55,.06)}
    .card--dark{background:var(--card);color:#e9eef5;border:0}
    .card__icon{width:48px;height:48px;border-radius:12px;margin-bottom:12px;display:grid;place-items:center;font-weight:800;color:#0e1a2b}
    .card--yellow .card__icon{background:#ffd66d}
    .card--teal   .card__icon{background:#96efe4}
    .card--pink   .card__icon{background:#f98dae}
    .card--dark   .card__icon{background:#0b2749;color:#fff}
    .card h3{font-size:16px;margin:2px 0 4px;color:#0e1a2b}
    .card--dark h3{color:#fff}
    .card p{font-size:13px;color:#5f6b7a}
    .card--dark p{color:#b9c6d6}
    .cta{margin:64px auto 80px;text-align:center;padding:36px 20px;border-radius:16px;border:1px solid #e6e6e6;background:#fff;max-width:960px}
    @media (max-width:900px){.icons{grid-template-columns:1fr 1fr}}
    @media (max-width:520px){.nav__links{display:none}.icons{grid-template-columns:1fr}}
  </style>
</head>
<body>

  {{-- NAVBAR --}}
  <header class="nav">
    <div class="nav__inner">
      <a class="brand" href="{{ route('landing') }}">
        <span class="brand__logo">Z</span> ZemixFi
      </a>

      <nav class="nav__links" aria-label="Primary">
        <a href="#features">Features</a>
        <a href="#pricing">Pricing</a>
        <a href="#about">About</a>
        <a href="#blog">Blog</a>
      </nav>

      <div class="nav__actions">
        @auth
          <a class="btn btn--ghost" href="{{ route('dashboard') }}">Dashboard</a>
        @else
          @if (Route::has('login'))
            <a class="btn btn--ghost" href="{{ route('login') }}">Sign in</a>
          @endif
          @if (Route::has('register'))
            <a class="btn btn--primary" href="{{ route('register') }}">Get Early Access</a>
          @endif
        @endauth
      </div>
    </div>
  </header>

  {{-- HERO --}}
  <section class="hero">
    <div class="hero__wrap">
      <p class="eyebrow">Designed to protect your wallet</p>
      <h1 class="h1">Your AI-Powered<br/>Financial Coach.</h1>
      <p class="lead">
        ZemixFi helps you avoid hidden fees, track spending, and make smarter financial
        decisions — all powered by AI.
      </p>
      <div class="hero__cta">
        @if (Route::has('register'))
          <a class="btn btn--primary" href="{{ route('register') }}">Get Early Access</a>
        @else
          <a class="btn btn--primary" href="#pricing">Get Early Access</a>
        @endif

        <a class="btn btn--outline" href="#demo">Watch Demo</a>
      </div>
    </div>

    <div class="wave-divider" aria-hidden="true">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320" preserveAspectRatio="none">
        <defs>
          <linearGradient id="waveGradient" x1="0%" y1="0%" x2="100%" y2="0%">
            <stop offset="0%"  style="stop-color:#ff7e5f"/>
            <stop offset="100%" style="stop-color:#06beb6"/>
          </linearGradient>
        </defs>
        <path fill="url(#waveGradient)" d="M0,160 C360,280 1080,40 1440,160 L1440,320 L0,320 Z"></path>
      </svg>
    </div>
  </section>

  {{-- PROBLEM / VALUE --}}
  <section class="section">
    <div class="container">
      <h2 class="h2">Banks are charging you hidden fees.</h2>
      <p class="sub">
        Every year, consumers lose billions to hidden credit card charges.
        ZemixFi makes sure you never overpay.
      </p>

      <div class="icons" id="features">
        <article class="card card--yellow">
          <div class="card__icon">💡</div>
          <h3>AI Coaching</h3>
          <p>Personalized money guidance</p>
        </article>

        <article class="card card--teal">
          <div class="card__icon">🔔</div>
          <h3>Compliance Alerts</h3>
          <p>Real-time notifications</p>
        </article>

        <article class="card card--pink">
          <div class="card__icon">📈</div>
          <h3>Smart Insights</h3>
          <p>Spending analytics &amp; financial trends</p>
        </article>

        <article class="card card--dark">
          <div class="card__icon">🔒</div>
          <h3>Secure &amp; Private</h3>
          <p>Bank-level encryption</p>
        </article>
      </div>
    </div>
  </section>

  {{-- FINAL CTA --}}
  <section class="cta" id="pricing">
    <h3 class="h2" style="margin:0 0 8px">Ready to stop paying hidden fees?</h3>
    <p class="sub" style="margin:0 auto 18px">Join the early access list and get your first fee analysis free.</p>
    <div class="hero__cta" style="margin-top:6px">
      @if (Route::has('register'))
        <a class="btn btn--primary" href="{{ route('register') }}">Get Early Access</a>
      @endif
      @if (Route::has('login'))
        <a class="btn btn--outline" href="{{ route('login') }}">Sign in</a>
      @endif
    </div>
  </section>

  <footer class="section" style="padding-top:0">
    <div class="container" style="border-top:1px solid #eef1f4;padding-top:18px;display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap;color:#6b7280;font-size:14px">
      <span>© <span id="y"></span> ZemixFi, Inc.</span>
      <span><a href="#" style="color:#6b7280;text-decoration:none">Privacy</a> • <a href="#" style="color:#6b7280;text-decoration:none">Terms</a></span>
    </div>
  </footer>

  <script>document.getElementById('y').textContent = new Date().getFullYear();</script>
</body>
</html>
