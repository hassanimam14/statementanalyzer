<x-app-layout>
@include('partials._zf_head') {{-- Or paste the <head> from statements.index --}}
@php $user=auth()->user(); $initials=strtoupper(mb_substr($user?->name ?? 'U',0,1)); @endphp

<div x-data="{ drawer:false, profile:false }" class="min-h-screen">
  @includeIf('partials._zf_header', ['initials'=>$initials,'user'=>$user])
  <div class="max-w-7xl mx-auto px-4 py-8 grid grid-cols-1 lg:grid-cols-12 gap-8">
    @includeIf('partials._zf_sidebar', ['active'=>'history'])

    <main class="lg:col-span-9 space-y-6">
      <section class="bg-white rounded-xl border border-outline/60 p-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <h1 class="text-2xl font-semibold text-body">History</h1>
            <p class="text-muted">Your past periods in one place — filter by date range.</p>
          </div>
          <a href="{{ route('statements.create') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-md bg-primary-500 text-white hover:bg-primary-700 border border-primary-500">+ Upload</a>
        </div>

        <form method="GET" class="mt-4 grid grid-cols-1 sm:grid-cols-4 gap-3">
          <select name="range" class="rounded-md border border-outline/60 px-3 py-2">
            <option value="">Custom range…</option>
            <option value="this_month" @selected(request('range')==='this_month')>This month</option>
            <option value="last_90" @selected(request('range')==='last_90')>Last 90 days</option>
            <option value="all" @selected(request('range')==='all')>All time</option>
          </select>
          <input type="date" name="from" value="{{ request('from') }}" class="rounded-md border border-outline/60 px-3 py-2">
          <input type="date" name="to"   value="{{ request('to')   }}" class="rounded-md border border-outline/60 px-3 py-2">
          <button class="px-3 py-2 rounded-md bg-white border border-outline/60 hover:bg-gray-50">Apply</button>
        </form>
      </section>

      <section class="bg-white rounded-xl border border-outline/60 p-0">
        @if($items->count()===0)
          <div class="p-10 text-center">
            <h3 class="text-lg font-semibold text-body">Nothing here yet</h3>
            <p class="text-muted mt-1">Once you upload statements, they’ll appear here grouped by period.</p>
          </div>
        @else
          <ul class="divide-y divide-outline/20">
            @foreach($items as $it)
              @php
                $sum=is_array($it->report?->summary_json)?$it->report->summary_json:(json_decode($it->report?->summary_json ?? '[]',true)?:[]);
                $fees=number_format(abs((float)($sum['totalFees']??0)),2);
                $hidden=is_countable($sum['hiddenFees']??null)?count($sum['hiddenFees']):0;
              @endphp
              <li class="p-4 sm:p-5 hover:bg-gray-50/60">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                  <div>
                    <div class="text-sm text-muted">Period</div>
                    <div class="text-body font-medium">
                      {{ optional($it->period_start)->toDateString() }} — {{ optional($it->period_end)->toDateString() }}
                    </div>
                  </div>
                  <div class="flex gap-4">
                    <div><div class="text-[11px] text-muted">Fees</div><div class="text-sm font-semibold text-coral">${{ $fees }}</div></div>
                    <div><div class="text-[11px] text-muted">Hidden</div><div class="text-sm font-semibold text-body">{{ $hidden }}</div></div>
                    <div><div class="text-[11px] text-muted">Uploaded</div><div class="text-sm text-body">{{ optional($it->created_at)->toDayDateTimeString() }}</div></div>
                  </div>
                  <div class="flex gap-2">
                    @if($it->report)
                      <a href="{{ route('reports.show',$it) }}" class="px-3 py-2 rounded-md bg-primary-500/10 text-primary-600 border border-primary-500/40 text-sm hover:bg-primary-500/20">View Report</a>
                      @if(!empty($it->report->pdf_path))
                        <a href="{{ asset($it->report->pdf_path) }}" target="_blank" class="px-3 py-2 rounded-md bg-white border border-outline/60 text-sm hover:bg-gray-50">Open PDF</a>
                      @endif
                    @endif
                  </div>
                </div>
              </li>
            @endforeach
          </ul>

          <div class="p-4 border-t border-outline/60">
            {{ $items->withQueryString()->links() }}
          </div>
        @endif
      </section>
    </main>
  </div>
</div>
</x-app-layout>
