<x-app-layout>
  <x-slot name="header"><h2 class="font-semibold text-xl">Cards</h2></x-slot>
  <div class="py-6 max-w-3xl mx-auto">
    <div class="mb-4">
      <a href="{{ route('cards.create') }}" class="px-3 py-2 bg-indigo-600 text-white rounded">Add Card</a>
    </div>
    @if (session('status')) <div class="p-3 bg-green-50 text-green-700 rounded">{{ session('status') }}</div> @endif
    <div class="bg-white shadow sm:rounded-lg divide-y">
      @forelse($cards as $c)
      <div class="p-4 flex justify-between">
        <div>
          <div class="font-semibold">{{ $c->nickname ?? 'Card' }} ({{ $c->issuer ?? 'Issuer' }}) • **** {{ $c->last4 }}</div>
          <div class="text-sm text-gray-600">Due day: {{ $c->due_day ?? '—' }} • Statement day: {{ $c->statement_day ?? '—' }}</div>
        </div>
        <a class="text-indigo-600" href="{{ route('cards.edit',$c) }}">Edit</a>
      </div>
      @empty
      <div class="p-4 text-gray-500">No cards yet.</div>
      @endforelse
    </div>
  </div>
</x-app-layout>
