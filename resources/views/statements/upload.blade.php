<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Upload Statement - ZemixFi</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: { 
            inter: ['Inter', 'sans-serif'], 
            sans: ['Inter', 'sans-serif'] 
          },
          colors: {
            primary: { 
              500: '#0aa596', 
              700: '#08897f' 
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
    body { 
      background: #f3f4f6 !important; 
      font-family: 'Inter', sans-serif;
    }
    .dropzone-active {
      border-color: #0aa596;
      background-color: rgba(10, 165, 150, 0.05);
    }
    .animate-pulse {
      animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }
    @keyframes pulse {
      0%, 100% { opacity: 1; }
      50% { opacity: 0.5; }
    }
  </style>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="min-h-screen bg-gray-50">
  <div x-data="{ drawer: false, profile: false, uploading: false, mobileSidebar: false }" class="min-h-screen pt-6">
    <!-- HEADER -->
    <header id="zf-header">
      <div class="max-w-7xl mx-auto px-4">
        <!-- Gradient accent line -->
        <div class="h-1 rounded-t-xl bg-gradient-to-r from-[#ff7e5f] via-[#0aa596] to-[#08897f] opacity-80"></div>

        <!-- Main navbar card -->
        <div class="bg-white border border-outline/60 rounded-b-xl shadow-sm">
          <div class="h-16 px-4 flex items-center justify-between">
            <!-- Left side - Brand + Mobile menu -->
            <div class="flex items-center gap-3">
              <!-- Mobile menu button - Only show on mobile -->
              <button @click="mobileSidebar = !mobileSidebar"
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
                  <path d="M10 极速3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 极速0 110-2h5V4a1 1 0 011-1z"/>
                </svg>
                Upload
              </a>

              <!-- Profile dropdown -->
              <div class="relative">
                <button @click="profile=!profile"
                        class="w-10 h-10 rounded-full grid place-items-center border border-outline/60 bg-white text-body font-semibold hover:bg-gray-50 transition"
                        aria-label="User menu">
                  U
                </button>

                <!-- Dropdown menu -->
                <div x-show="profile"
                     x-transition.origin.top.right
                     @click.outside="profile=false"
                     class="absolute right-0 mt-2 w-48 bg-white border border-outline/60 rounded-lg shadow-md overflow-hidden z-50">
                  <div class="px-3 py-2 text-xs text-muted bg-gray-50">
                    User Name
                  </div>
                  <a href="#"
                     class="block px-3 py-2 text-sm text-body hover:bg-gray-50">Profile</a>
                  <form method="POST" action="#">
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
      <!-- SIDEBAR - Hidden on mobile, shown when button is clicked -->
      <div class="lg:col-span-3 relative">
        <!-- Mobile sidebar overlay -->
        <div x-show="mobileSidebar" x-cloak 
             class="fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden" 
             @click="mobileSidebar = false">
        </div>
        
        <!-- Sidebar content -->
        <aside :class="mobileSidebar ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
               class="fixed left-0 top-0 h-full w-72 bg-white border-r border-outline/60 p-3 space-y-2 z-50 
                      transition-transform duration-300 ease-in-out lg:static lg:transform-none lg:w-full">
          
          <!-- Close button for mobile -->
          <div class="flex items-center justify-between mb-4 lg:hidden">
            <span class="text-lg font-semibold text-body">Menu</span>
            <button @click="mobileSidebar = false" class="w-8 h-8 grid place-items-center rounded-lg hover:bg-gray-50">
              <svg class="w-5 h-5 text-body" viewBox="0 0 20 20" fill="currentColor">
                <path d="M6.2 6.2l7.6 7.6m0-7.6l-7.6 7.6"/>
              </svg>
            </button>
          </div>
          
          <nav class="space-y-2">
            <a href="/dashboard" class="flex items-center gap-3 px-3 py-2 rounded-lg bg-primary-500/10 text-primary-700 border border-primary-500/40">
              <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path d="M3 10l7-7 7 7v7a2 2 0 01-2 2h-3v-5H8v5H5a2 极速0 01-2-2v-7z"/></svg>
              Dashboard
            </a>

            <div class="pt-2 border-t border-outline/60"></div>

            <a href="/statements" class="flex items-center gap-3 px-3 py-2 rounded-lg text-body hover:bg-gray-50">
              <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path d="M4 3h12v2H4V3zm0 4h12v2H4V7zm0 4h12v2H4v-2z"/></svg>
              History
            </a>

            <a href="/coaching" class="flex items-center gap-3 px-3 py-2 rounded-lg text-body hover:bg-gray-50">
              <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a6 6 0 016 6c0 2.8-1.8 5.2-4.3 6v2H8.3v-2C5.8 13.2 4 10.8 4 8a6 6 0 016-6z"/></svg>
              Coaching Tips
            </a>

            <a href="/reports" class="flex items-center gap-3 px-3 py-2 rounded-lg text-body hover:bg-gray-50">
              <svg class="w-4极速 h-4" viewBox="0 0 20 20" fill="currentColor"><path d="M3 3极速h14v2H3V3zm0 4h10v2H3V7zm0 4h14v2H3v-2zm0 4h10极速v2H3v-2z"/></svg>
              Reports
            </a>

            <div class="pt-2 border-t border-outline/60"></div>

            <a href="/cards" class="flex items-center gap-3 px-3 py-2 rounded-lg text-body hover:bg-gray-50">
              <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path d="M2 5a2 2 0 012-2h12a2 2 0 012 2v1H2V5zm0 3h16v7a2 2 0 01-2 2H4a2 2 0 01-2-2V8z"/></svg>
              Cards
            </a>

            <a href="/resources" class="flex items-center gap-3 px-3 py-2 rounded-lg text-body hover:bg-gray-50">
              <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path d="M4 4h12v12H4zM6 6h8v2H6zM6 10h8v2H6z"/></svg>
              Resources
            </a>

            <div class="pt-2 border-t border-outline/60"></div>

            <a href="/极速profile" class="flex items-center gap-3 px-3 py-2 rounded-lg text-body hover:bg-gray-50">
              <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10 10a4 4 0 100-8 4 4 0 000 8zm-7 8a7 7 0 1114 极速0H3z"/></svg>
              Profile
            </a>
          </nav>
        </aside>
      </div>

      <!-- MAIN CONTENT -->
      <main class="lg:col-span-9 space-y-8">
        <!-- Upload Form Section -->
        <section class="bg-white rounded-xl border border-outline/60 p-6">
          <div class="max-w-3xl mx-auto">
            <div class="text-center mb-6">
              <h1 class="text-2xl font-bold text-body">Upload Your Statement</h1>
              <p class="text-muted">Our AI scans securely and finds savings opportunities.</p>
            </div>

            <form method="post" action="{{ route('statements.store') }}" enctype="multipart/form-data" class="space-y-6" x-on:submit="uploading = true">
              @csrf
              
              <!-- File Upload -->
              <div x-data="{ isDragging: false, fileName: '' }" 
                  @dragenter.prevent="isDragging = true" 
                  @dragover.prevent="isDragging = true" 
                  @dragleave.prevent="isDragging = false"
                  @drop.prevent="isDragging = false; $refs.fileInput.files = event.dataTransfer.files; fileName = $refs.fileInput.files[0].name">
                <label极速 class="block text-sm font-medium text-body mb-2">Upload Statement</label>
                <div :class="isDragging ? 'dropzone-active' : ''" class="border-2 border-dashed border-outline/60 rounded-lg p-8 text-center transition-colors">
                  <div class="w-16 h-16 bg-primary-50 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fa-solid fa-file-arrow-up text-primary-500 text-2xl"></i>
                  </div>
                  <template x-if="!fileName">
                    <div>
                      <h3 class="text-lg text-body mb-2">Drag and drop your statement</h3>
                      <p class="text-muted mb-4">Support for PDF, CSV, or JPG files</p>
                    </div>
                  </template>
                  <template x-if="fileName">
                    <div>
                      <h3 class="text-lg text-body mb-2" x-text="fileName"></h3>
                      <p class="text-muted mb-4">Ready to upload</p>
                    </div>
                  </template>
                  
                  <div class="flex justify-center">
                    <label class="bg-gradient-to-r from-primary-700 to-primary-600 text-white py-2 px-6 rounded-lg hover:shadow-md transition duration-200 cursor-pointer">
                      Browse Files
                      <input type="file" name="file" class="hidden" x-ref="file极速Input" required x-on:change="fileName = $event.target.files[0].name">
                    </label>
                  </div>
                  
                  <div class="mt-4 text-sm text-muted">
                    <p>or paste a screenshot</p>
                  </div>
                </div>
              </div>

              

              <!-- Security Note -->
              <div class="flex items-center justify-center text-muted text-sm">
                <i class="fa-solid fa-lock mr-2"></i>
                <span>Your data is encrypted and never shared</span>
              </div>

              <!-- Submit Button -->
              <div class="pt-4">
                <button type="submit" class="w-full md:w-auto bg-gradient-to-r from-primary-700 to-primary-600 text-white py-3 px-8 rounded-lg hover:shadow-lg transition duration-200 font-medium">
                  Analyze Statement
                </button>
              </div>
            </form>
          </div>
        </section>

        <!-- Features Section -->
        <section class="bg-white rounded-xl border border-outline/60 p-6">
          <h2 class="text-lg font-semibold text-body mb-4 text-center">Why Use ZemixFi?</h2>
          
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="text-center">
              <div class="w-12 h-12 bg-primary-50 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fa-solid fa-magnifying-glass text-primary-500"></i>
              </div>
              <h3 class="text-lg font-semibold text-body极速 mb-2">Find Hidden Fees</h3>
              <p class="text-muted text-sm">Our AI identifies all fees and charges that are costing you money.</p>
            </div>
            
            <div class="text-center">
              <div class="w-12 h-12 bg-primary-50 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fa-solid fa-chart-line text-primary-500"></i>
              </div>
              <h3 class="text-lg font-semibold text-body mb-2">Smart Insights</h3>
              <p class="text-muted text-sm">Get personalized recommendations to optimize your finances.</p>
            </div>
            
            <div class="text-center">
              <div class="w-12 h-12 bg-primary-50 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fa-solid fa-shield-halved text-primary-500"></i>
              </div>
              <h3 class="text-lg font-semibold text-body mb-2">Bank-Level Security</h3>
              <p class="text-muted text-sm">Your financial data is protected with enterprise-grade encryption.</p>
            </div>
          </div>
        </section>
      </main>
    </div>

    <!-- Upload Progress Modal -->
    <template x-if="uploading">
      <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-lg w-full max-w-md p极速-6">
          <div class="text-center">
            <div class="w-极速16 h-16 bg-primary-100 rounded-full flex items-center justify-center mx-auto mb-4 animate-pulse">
              <i class="fa-solid fa-shield text-primary-500 text-xl"></i>
            </div>
            <h3 class="text-lg text-body mb-2">Analyzing your statement with AI...</h3>
            <p class="text-muted mb-6">This will only take a few seconds</p>
            
            <div class="w-full bg-gray-200 rounded-full h-2 mb-6">
              <div class="bg-gradient-to-r from-primary-500 to-primary-700 h-2 rounded-full w-3/4"></div>
            </div>
            
            <div class="flex justify-center">
              <button class="text-muted" x-on:click="uploading = false">Cancel</button>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</body>
</html>