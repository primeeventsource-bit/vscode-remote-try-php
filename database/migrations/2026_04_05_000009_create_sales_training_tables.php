<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        try {
            if (!Schema::hasTable('objection_library')) {
                Schema::create('objection_library', function (Blueprint $table) {
                    $table->id();
                    $table->text('objection_text');
                    $table->string('category', 50)->index();
                    $table->text('rebuttal_level_1')->nullable();
                    $table->text('rebuttal_level_2')->nullable();
                    $table->text('rebuttal_level_3')->nullable();
                    $table->string('keywords')->nullable();
                    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                    $table->boolean('is_active')->default(true);
                    $table->timestamps();
                });
            }
        } catch (\Throwable $e) {
            // Table already exists — safe to continue
        }

        try {
            if (!Schema::hasTable('call_sessions')) {
                Schema::create('call_sessions', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                    $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
                    $table->foreignId('deal_id')->nullable()->constrained('deals')->nullOnDelete();
                    $table->string('current_stage', 30)->default('fronter');
                    $table->integer('objection_count')->default(0);
                    $table->string('status', 20)->default('active');
                    $table->text('notes')->nullable();
                    $table->timestamps();
                    $table->index(['user_id', 'status']);
                });
            }
        } catch (\Throwable $e) {
            // Table already exists — safe to continue
        }

        try {
            if (!Schema::hasTable('objection_logs')) {
                Schema::create('objection_logs', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('call_session_id')->constrained('call_sessions')->cascadeOnDelete();
                    $table->foreignId('objection_id')->nullable()->constrained('objection_library')->nullOnDelete();
                    $table->text('objection_text');
                    $table->text('selected_rebuttal')->nullable();
                    $table->string('rebuttal_level', 20)->nullable();
                    $table->string('result', 20)->default('pending');
                    $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->timestamps();
                    $table->index('result');
                });
            }
        } catch (\Throwable $e) {
            // Table already exists — safe to continue
        }

        try {
            if (DB::table('objection_library')->count() === 0) {
                self::seedObjections();
            }
        } catch (\Throwable $e) {}
    }

    private static function seedObjections(): void
    {
        $objections = [
            ['category' => 'money', 'objection_text' => "It's too expensive / I can't afford it", 'keywords' => 'expensive,afford,cost,money,price,budget',
             'rebuttal_level_1' => "I understand — let me show you how this actually saves you money compared to what you're paying now in maintenance fees.",
             'rebuttal_level_2' => "If I could show you how to eliminate your annual fees and actually put money back in your pocket, would that change things?",
             'rebuttal_level_3' => "You're already spending thousands on something you don't use. We're offering a way out that costs less than one year of your current fees. The real question is — can you afford NOT to do this?"],

            ['category' => 'timing', 'objection_text' => "Call me back later / Not a good time", 'keywords' => 'later,busy,call back,not now,bad time',
             'rebuttal_level_1' => "Absolutely, when would be a better time? I want to make sure we connect when it works for you.",
             'rebuttal_level_2' => "I completely understand. But just so you know, this offer is time-sensitive. Can I take just 2 minutes to explain what's on the table so you don't miss out?",
             'rebuttal_level_3' => "I hear you, but here's the thing — every day you wait, you're paying fees on something you don't use. Two minutes now could save you thousands. Can I just share the basics?"],

            ['category' => 'spouse', 'objection_text' => "I need to talk to my spouse / partner first", 'keywords' => 'spouse,wife,husband,partner,discuss',
             'rebuttal_level_1' => "Of course — would it help if I prepared a quick summary you can share with them?",
             'rebuttal_level_2' => "Absolutely. Would your spouse want to keep paying fees on something you're not using, or would they prefer to get out? Let me get the details ready so you can present a solution, not a question.",
             'rebuttal_level_3' => "I respect that. But most of our clients say once they explained the savings, their spouse was completely on board. What if we get everything set up now, and you have 3 days to finalize? That way you lock in today's rate."],

            ['category' => 'trust', 'objection_text' => "How do I know this is legitimate?", 'keywords' => 'scam,legit,trust,real,verify,legitimate',
             'rebuttal_level_1' => "Great question — here's our company information, our BBB rating, and I can send you our service agreement before we proceed.",
             'rebuttal_level_2' => "I love that you're being careful. Let me verify everything with you right now — our licensing, our physical address, and I'll connect you with our verification department.",
             'rebuttal_level_3' => "Smart question. We're fully licensed, bonded, and regulated. I'll have our verification admin confirm everything with you directly. We don't ask for payment until you've verified us completely."],

            ['category' => 'card', 'objection_text' => "I don't want to give my card info over the phone", 'keywords' => 'card,credit card,payment,secure,phone',
             'rebuttal_level_1' => "Completely understandable. Your information is processed through a secure, encrypted system. We never store your full card details.",
             'rebuttal_level_2' => "I get it — security is important. We use bank-level encryption, and your card is only used for the one-time processing fee. Nothing recurring. Would it help if I explained our security process?",
             'rebuttal_level_3' => "Your card info is safer with us than when you use it at a gas station or restaurant. We're PCI compliant with encrypted processing. But if you prefer, we can do this through a secure online portal instead."],

            ['category' => 'thinking', 'objection_text' => "I need to think about it", 'keywords' => 'think,consider,decide,sleep on it,mull',
             'rebuttal_level_1' => "Of course — what specifically would you like to think about? Maybe I can help clarify right now.",
             'rebuttal_level_2' => "I understand. But what usually happens is people think about it, then the offer expires and they're stuck paying another year of fees. What part is making you hesitate?",
             'rebuttal_level_3' => "Thinking is great, but here's what I've seen: 90% of people who say they'll think about it never call back — and they lose another year of fees. I don't want that for you. What's the one thing holding you back right now?"],

            ['category' => 'interest', 'objection_text' => "I'm not interested", 'keywords' => 'not interested,no thanks,pass,decline',
             'rebuttal_level_1' => "I understand. Before I go — are you currently using your timeshare, or is it sitting unused while you pay maintenance fees?",
             'rebuttal_level_2' => "Fair enough. But just curious — are you happy paying $X per year for something you don't use? Because that's exactly what we help people stop doing.",
             'rebuttal_level_3' => "I hear you. But here's a question: if I could show you how to legally stop paying fees on a timeshare you don't use, for a one-time cost less than one year of fees — would that be worth 5 minutes?"],

            ['category' => 'competitor', 'objection_text' => "I've already tried another company", 'keywords' => 'tried,another company,before,didn\'t work,already',
             'rebuttal_level_1' => "I'm sorry that didn't work out. Can I ask what happened? We do things differently and I'd like to show you how.",
             'rebuttal_level_2' => "That's actually why you should talk to us. We specialize in cases where others have failed. We have a different approach and a different success rate.",
             'rebuttal_level_3' => "That's exactly why I'm calling. Most of our clients come to us AFTER another company failed them. We're the solution to that problem, not a repeat of it. Let me show you the difference."],
        ];

        foreach ($objections as $obj) {
            $obj['is_active'] = true;
            $obj['created_at'] = now();
            $obj['updated_at'] = now();
            DB::table('objection_library')->insert($obj);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('objection_logs');
        Schema::dropIfExists('call_sessions');
        Schema::dropIfExists('objection_library');
    }
};
