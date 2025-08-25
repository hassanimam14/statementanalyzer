<x-app-layout>
  <x-slot name="header"><h2 class="font-semibold text-xl">{{ $res->title }}</h2></x-slot>

  <div class="py-6 max-w-3xl mx-auto">
    <div class="bg-white shadow sm:rounded-lg p-6 prose max-w-none">
      {!! $res->content !!}
    </div>
    <div class="mt-6">
      <a href="{{ route('resources.index') }}" class="text-indigo-600 underline">&larr; Back to resources</a>
    </div>
  </div>
</x-app-layout>
