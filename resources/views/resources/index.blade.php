<x-app-layout>
  <x-slot name="header"><h2 class="font-semibold text-xl">Educational Resources</h2></x-slot>

  <div class="py-6 max-w-4xl mx-auto">
    <div class="bg-white shadow sm:rounded-lg divide-y">
      @forelse($items as $res)
        <a href="{{ route('resources.show', $res->slug) }}" class="block p-4 hover:bg-gray-50">
          <div class="flex items-center justify-between">
            <div>
              <div class="font-semibold">{{ $res->title }}</div>
              @if($res->summary)<div class="text-sm text-gray-600 mt-1">{{ $res->summary }}</div>@endif
            </div>
            <div class="text-xs text-gray-400">{{ $res->updated_at->diffForHumans() }}</div>
          </div>
        </a>
      @empty
        <div class="p-4 text-gray-500">No resources yet.</div>
      @endforelse
    </div>
  </div>
</x-app-layout>
