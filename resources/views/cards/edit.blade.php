<x-app-layout>
  <x-slot name="header"><h2 class="font-semibold text-xl">Edit Card</h2></x-slot>
  <div class="py-6 max-w-3xl mx-auto">
    <div class="bg-white shadow sm:rounded-lg p-6">
      <form method="post" action="{{ route('cards.update',$card) }}" class="space-y-4">
        @csrf @method('PUT')
        <div><label class="block">Issuer</label><input name="issuer" class="w-full border rounded p-2" value="{{ old('issuer',$card->issuer) }}"></div>
        <div><label class="block">Nickname</label><input name="nickname" class="w-full border rounded p-2" value="{{ old('nickname',$card->nickname) }}"></div>
        <div><label class="block">Last 4</label><input name="last4" class="w-full border rounded p-2" maxlength="4" value="{{ old('last4',$card->last4) }}"></div>
        <div class="grid grid-cols-2 gap-4">
          <div><label class="block">Statement Day (1–28)</label><input type="number" name="statement_day" min="1" max="28" class="w-full border rounded p-2" value="{{ old('statement_day',$card->statement_day) }}"></div>
          <div><label class="block">Due Day (1–28)</label><input type="number" name="due_day" min="1" max="28" class="w-full border rounded p-2" value="{{ old('due_day',$card->due_day) }}"></div>
        </div>
        <button class="px-4 py-2 bg-indigo-600 text-white rounded">Update</button>
      </form>
    </div>
  </div>
</x-app-layout>
