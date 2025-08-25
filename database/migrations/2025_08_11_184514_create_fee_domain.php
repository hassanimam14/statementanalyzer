<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::create('cards', function (Blueprint $t) {
        $t->id();
        $t->foreignId('user_id')->constrained()->cascadeOnDelete();
        $t->string('issuer')->nullable();
        $t->string('nickname')->nullable();
        $t->string('last4', 4)->nullable();
        $t->unsignedTinyInteger('statement_day')->nullable();
        $t->unsignedTinyInteger('due_day')->nullable();
        $t->timestamps();
    });

    Schema::create('statements', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->foreignId('user_id')->constrained()->cascadeOnDelete();
        $t->foreignId('card_id')->nullable()->constrained('cards')->nullOnDelete();
        $t->string('original_name');
        $t->string('stored_path');
        $t->date('period_start')->nullable();
        $t->date('period_end')->nullable();
        $t->timestamps();
    });

    Schema::create('transactions', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->foreignId('user_id')->constrained()->cascadeOnDelete();
        $t->uuid('statement_id');
        $t->foreign('statement_id')->references('id')->on('statements')->cascadeOnDelete();

        $t->date('date');
        $t->string('description');
        $t->string('merchant')->nullable();
        $t->decimal('amount', 12, 2);
        $t->string('type')->nullable();     // debit|credit
        $t->string('category')->nullable(); // fee|subscription|expense|income
        $t->json('flags')->nullable();

        $t->timestamps();
        $t->index(['user_id','statement_id','date']);
    });

    Schema::create('reports', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->uuid('statement_id')->unique();
        $t->foreign('statement_id')->references('id')->on('statements')->cascadeOnDelete();
        $t->json('summary_json');
        $t->string('pdf_path');
        $t->timestamps();
    });

    Schema::create('fee_snapshots', function (Blueprint $t) {
        $t->id();
        $t->foreignId('user_id')->constrained()->cascadeOnDelete();
        $t->uuid('statement_id');
        $t->decimal('total_fees', 12, 2)->default(0);
        $t->json('by_category');
        $t->timestamps();
        $t->index(['user_id','statement_id']);
    });

    Schema::create('edu_resources', function (Blueprint $t) {
        $t->id();
        $t->string('slug')->unique();
        $t->string('title');
        $t->text('summary')->nullable();
        $t->longText('content');
        $t->timestamps();
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fee_domain');
    }
};
