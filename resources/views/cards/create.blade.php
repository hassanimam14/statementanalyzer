<x-app-layout>
  <x-slot name="header"><h2 class="font-semibold text-xl">Add Card</h2></x-slot>
  <div class="py-6 max-w-3xl mx-auto">
    <div class="bg-white shadow sm:rounded-lg p-6">
      <form method="post" action="{{ route('cards.store') }}" class="space-y-4">
        @csrf
        <div><label class="block">Issuer</label><input name="issuer" class="w-full border rounded p-2"></div>
        <div><label class="block">Nickname</label><input name="nickname" class="w-full border rounded p-2"></div>
        <div><label class="block">Last 4</label><input name="last4" class="w-full border rounded p-2" maxlength="4"></div>
        <div class="grid grid-cols-2 gap-4">
          <div><label class="block">Statement Day (1–28)</label><input type="number" name="statement_day" min="1" max="28" class="w-full border rounded p-2"></div>
          <div><label class="block">Due Day (1–28)</label><input type="number" name="due_day" min="1" max="28" class="w-full border rounded p-2"></div>
        </div>
        <button class="px-4 py-2 bg-indigo-600 text-white rounded">Save</button>
      </form>
    </div>
  </div>
</x-app-layout>
