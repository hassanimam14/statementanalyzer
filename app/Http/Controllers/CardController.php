<?php
namespace App\Http\Controllers;

use App\Models\Card;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CardController extends Controller
{
    public function index() {
        $cards = Auth::user()->cards()->get();
        return view('cards.index', compact('cards'));
    }

    public function create() {
        return view('cards.create');
    }

    public function store(Request $req) {
        $req->validate([
            'issuer' => ['nullable','string','max:80'],
            'nickname' => ['nullable','string','max:80'],
            'last4' => ['nullable','digits:4'],
            'due_day' => ['nullable','integer','min:1','max:28'],
            'statement_day' => ['nullable','integer','min:1','max:28'],
        ]);
        Auth::user()->cards()->create($req->only('issuer','nickname','last4','due_day','statement_day'));
        return redirect()->route('cards.index')->with('status','Card saved.');
    }

    public function edit(Card $card) {
        abort_unless($card->user_id === Auth::id(), 403);
        return view('cards.edit', compact('card'));
    }

    public function update(Request $req, Card $card) {
        abort_unless($card->user_id === Auth::id(), 403);
        $req->validate([
            'issuer' => ['nullable','string','max:80'],
            'nickname' => ['nullable','string','max:80'],
            'last4' => ['nullable','digits:4'],
            'due_day' => ['nullable','integer','min:1','max:28'],
            'statement_day' => ['nullable','integer','min:1','max:28'],
        ]);
        $card->update($req->only('issuer','nickname','last4','due_day','statement_day'));
        return redirect()->route('cards.index')->with('status','Card updated.');
    }
}
