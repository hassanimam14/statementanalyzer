<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('alert_states', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('statement_id')->nullable();
            $t->string('alert_key', 255); // statement|idx|date|desc|amt
            $t->enum('status', ['open','resolved','disputed'])->default('open');
            $t->text('notes')->nullable();
            $t->timestamps();

            $t->unique(['user_id','alert_key']); // user-specific state
            $t->index(['statement_id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('alert_states');
    }
};
