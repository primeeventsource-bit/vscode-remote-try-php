<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── Sales Scripts ────────────────────────────────────
        Schema::create('sales_scripts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 100)->unique();
            $table->string('category', 30)->index(); // fronter, closer, verification, voicemail, bridge, closing
            $table->string('stage', 30)->nullable()->index();
            $table->longText('content');
            $table->boolean('is_active')->default(true);
            $table->integer('order_index')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        // ── Training Modules ─────────────────────────────────
        Schema::create('training_modules', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug', 100)->unique();
            $table->string('category', 30)->index();
            $table->text('description')->nullable();
            $table->longText('content')->nullable();
            $table->integer('order_index')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_required')->default(true);
            $table->integer('estimated_minutes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('training_quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained('training_modules');
            $table->string('title');
            $table->integer('passing_score')->default(80);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('training_quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained('training_quizzes');
            $table->text('question');
            $table->json('options');
            $table->string('correct_answer');
            $table->text('explanation')->nullable();
            $table->integer('order_index')->default(0);
            $table->timestamps();
        });

        Schema::create('training_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('module_id')->constrained('training_modules');
            $table->string('status', 20)->default('not_started');
            $table->decimal('score', 5, 2)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'module_id']);
        });

        Schema::create('training_quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained('training_quizzes');
            $table->foreignId('user_id')->constrained('users');
            $table->decimal('score', 5, 2);
            $table->boolean('passed');
            $table->json('answers')->nullable();
            $table->timestamps();
        });

        // ── Sales Targets + Daily Metrics ────────────────────
        Schema::create('sales_targets', function (Blueprint $table) {
            $table->id();
            $table->string('role', 30)->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->integer('calls_target')->nullable();
            $table->integer('contacts_target')->nullable();
            $table->integer('transfers_target')->nullable();
            $table->integer('deals_target')->nullable();
            $table->decimal('revenue_target', 12, 2)->nullable();
            $table->date('effective_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('daily_sales_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->date('metric_date')->index();
            $table->integer('calls_count')->default(0);
            $table->integer('contacts_count')->default(0);
            $table->integer('transfers_count')->default(0);
            $table->integer('deals_closed_count')->default(0);
            $table->decimal('revenue_total', 12, 2)->default(0);
            $table->integer('objection_count')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'metric_date']);
        });

        // ── Enhance call_sessions with stage tracking ────────
        if (!Schema::hasColumn('call_sessions', 'started_at')) {
            Schema::table('call_sessions', function (Blueprint $table) {
                $table->timestamp('started_at')->nullable()->after('notes');
                $table->timestamp('ended_at')->nullable()->after('started_at');
                $table->string('final_outcome', 30)->nullable()->after('ended_at');
                $table->json('metadata')->nullable()->after('final_outcome');
            });
        }

        // ── Enhance objection_library with stage + bridge ────
        if (!Schema::hasColumn('objection_library', 'stage')) {
            Schema::table('objection_library', function (Blueprint $table) {
                $table->string('stage', 20)->default('both')->after('category');
                $table->text('bridge_line')->nullable()->after('rebuttal_level_3');
                $table->text('quick_close_line')->nullable()->after('bridge_line');
                $table->integer('order_index')->default(0)->after('is_active');
            });
        }

        self::seedScriptsAndModules();
    }

    private static function seedScriptsAndModules(): void
    {
        // Seed default scripts
        $scripts = [
            ['name' => 'Fronter Opening Script', 'slug' => 'fronter-opening', 'category' => 'fronter', 'stage' => 'fronter',
             'content' => "Hi, this is [NAME] calling from [COMPANY]. I'm reaching out because we help timeshare owners like yourself who are looking to reduce or eliminate their annual maintenance fees.\n\nAre you currently using your timeshare, or is it something that's been sitting unused?\n\n[LISTEN]\n\nI understand. A lot of our clients were in the same position — paying thousands a year for something they don't use. That's exactly what we help with.\n\nWould it be okay if I connected you with one of our specialists who can explain exactly how the process works? It only takes about 15 minutes."],

            ['name' => 'Closer Main Script', 'slug' => 'closer-main', 'category' => 'closer', 'stage' => 'closer',
             'content' => "Hi [CLIENT NAME], this is [CLOSER NAME]. [FRONTER NAME] mentioned you've been looking for a way out of your timeshare situation. Is that right?\n\n[BUILD RAPPORT]\n\nLet me explain exactly how this works...\n\n1. We file the proper legal documentation on your behalf\n2. We work directly with the resort and management company\n3. The entire process typically takes 6-12 months\n4. You get a written guarantee\n\nThe one-time fee covers everything — there are no hidden costs, no monthly payments, and no surprises.\n\n[PRESENT PRICE]\n\nNow, if everything I've explained works exactly the way I described — would you be ready to move forward today?"],

            ['name' => 'Verification Script', 'slug' => 'verification-script', 'category' => 'verification', 'stage' => 'verification',
             'content' => "Hi [CLIENT NAME], this is [ADMIN NAME] from our verification department.\n\nI'm calling to confirm the details of your enrollment and make sure everything was explained correctly.\n\n1. You understand this is a one-time fee of $[AMOUNT]?\n2. You understand the process takes approximately 6-12 months?\n3. You've been provided with our company information and service agreement?\n4. Are you authorizing the charge of $[AMOUNT] to your [CARD TYPE] ending in [LAST4]?\n\nPerfect. Your confirmation number is [NUMBER]. Welcome aboard."],
        ];

        foreach ($scripts as $s) {
            $s['is_active'] = true;
            $s['order_index'] = 0;
            $s['created_at'] = now();
            $s['updated_at'] = now();
            DB::table('sales_scripts')->insert($s);
        }

        // Seed training modules
        $modules = [
            ['title' => 'Company Overview', 'slug' => 'company-overview', 'category' => 'company', 'estimated_minutes' => 15,
             'description' => 'Understand the business model, how revenue is generated, and how to explain the service to clients.',
             'content' => "## Welcome to Prime\n\nPrime helps timeshare owners exit their timeshare contracts legally and permanently.\n\n### How the Business Works\n- Clients own timeshares they no longer use\n- They pay annual maintenance fees ($1,000-$15,000/year)\n- We help them legally exit their timeshare obligation\n- We charge a one-time fee for the service\n\n### How Revenue is Generated\n- Fronters qualify leads and transfer to closers\n- Closers present the solution and close the sale\n- Verification admin confirms and charges the deal\n- Commission is calculated and paid weekly\n\n### Key Points to Remember\n- We are a legitimate company with real results\n- We provide written guarantees\n- The process typically takes 6-12 months\n- One-time fee, no recurring charges"],

            ['title' => 'Fronter System', 'slug' => 'fronter-system', 'category' => 'fronter', 'estimated_minutes' => 20,
             'description' => 'Master the fronter workflow — lead handling, qualification, and transfer process.',
             'content' => "## The Fronter System\n\n### Your Role\nYou are the first point of contact. Your job is to:\n1. Make the initial connection\n2. Qualify the lead\n3. Build enough interest to transfer\n\n### Lead Qualification Checklist\n- Do they own a timeshare? (must be YES)\n- Are they paying maintenance fees? (must be YES)\n- Are they interested in getting out? (must be YES or MAYBE)\n- Can they speak for 15 minutes? (preferred YES)\n\n### Transfer Rules\n- Only transfer qualified leads\n- Always add notes before transferring\n- Select the right closer from the dropdown\n- Include: name, resort, fees, interest level\n\n### Common Mistakes to Avoid\n- Transferring unqualified leads\n- Not adding notes\n- Rushing the qualification\n- Being too aggressive too early"],

            ['title' => 'Closer Domination Framework', 'slug' => 'closer-framework', 'category' => 'closer', 'estimated_minutes' => 30,
             'description' => 'The complete closer system — scripts, micro-closes, tone control, and deal closing techniques.',
             'content' => "## Closer Domination Framework\n\n### The 5-Step Close\n1. **Build Rapport** — Connect personally, reference fronter notes\n2. **Identify Pain** — How much are fees? How long unused? What's the frustration?\n3. **Present Solution** — Explain the process clearly and confidently\n4. **Handle Objections** — Use the 3-level rebuttal system\n5. **Close** — Ask the closing question with confidence\n\n### Micro-Closes Throughout the Call\n- \"Does that make sense so far?\"\n- \"Can you see how this would help you?\"\n- \"If we could do that for you, would you want to move forward?\"\n\n### The Final Close\n**Always ask this before collecting payment:**\n\"If everything I've explained works exactly how I described — would you be ready to move forward today?\"\n\n### Tone Control\n- Start **warm and friendly**\n- Move to **professional and authoritative**\n- If needed, shift to **direct and urgent**\n- Never be rude — be confident"],

            ['title' => 'Objection Mastery', 'slug' => 'objection-mastery', 'category' => 'rebuttal', 'estimated_minutes' => 25,
             'description' => 'Master every objection with the 3-level rebuttal system.',
             'content' => "## Objection Mastery System\n\n### The 3-Level Rebuttal System\n\n**Level 1 — Soft:** Acknowledge and redirect gently\n**Level 2 — Closer:** Apply logic and urgency\n**Level 3 — Aggressive:** Direct challenge with conviction\n\n### Top Objection Categories\n1. Money / Price\n2. Timing / Call Back\n3. Spouse / Partner\n4. Trust / Legitimacy\n5. Card / Payment\n6. Thinking / Deciding\n7. Not Interested\n8. Tried Before\n\n### Rebuttal Stacking\nWhen one rebuttal doesn't work, move to the next level.\nNever repeat the same rebuttal — escalate.\n\n### The Bridge Line\nAfter handling any objection, always bridge back:\n\"I totally understand. Now, setting that aside for a moment...\"\n\nThen re-present the solution or ask a micro-close.\n\n### Practice Daily\nUse the Sales Training → Live Close Assist panel to practice in real time."],

            ['title' => 'Sales Psychology', 'slug' => 'sales-psychology', 'category' => 'psychology', 'estimated_minutes' => 20,
             'description' => 'Psychological principles that drive buying decisions.',
             'content' => "## Sales Psychology\n\n### 5 Principles That Close Deals\n\n**1. Urgency**\n- Time-limited offers\n- \"This rate is only available today\"\n- \"Every day you wait is another day paying fees\"\n\n**2. Scarcity**\n- Limited spots\n- \"We can only take on X cases this month\"\n\n**3. Authority**\n- Position yourself as the expert\n- Reference company credentials\n- Use verification department as authority signal\n\n**4. Social Proof**\n- \"Most of our clients were in your exact situation\"\n- \"We've helped thousands of timeshare owners\"\n\n**5. Emotional Anchoring**\n- Connect to their frustration with fees\n- Paint the picture of freedom from obligation\n- Make them feel the relief of being done\n\n### Control the Frame\n- You are the expert, not the salesperson\n- You are offering a solution, not asking for money\n- The client is making a smart financial decision, not a purchase"],
        ];

        foreach ($modules as $m) {
            $m['is_active'] = true;
            $m['is_required'] = true;
            $m['created_at'] = now();
            $m['updated_at'] = now();
            DB::table('training_modules')->insert($m);
        }
    }

    public function down(): void
    {
        Schema::table('objection_library', function (Blueprint $table) {
            $cols = ['stage', 'bridge_line', 'quick_close_line', 'order_index'];
            $existing = array_filter($cols, fn($c) => Schema::hasColumn('objection_library', $c));
            if (!empty($existing)) $table->dropColumn($existing);
        });

        Schema::table('call_sessions', function (Blueprint $table) {
            $cols = ['started_at', 'ended_at', 'final_outcome', 'metadata'];
            $existing = array_filter($cols, fn($c) => Schema::hasColumn('call_sessions', $c));
            if (!empty($existing)) $table->dropColumn($existing);
        });

        Schema::dropIfExists('daily_sales_metrics');
        Schema::dropIfExists('sales_targets');
        Schema::dropIfExists('training_quiz_attempts');
        Schema::dropIfExists('training_quiz_questions');
        Schema::dropIfExists('training_quizzes');
        Schema::dropIfExists('training_progress');
        Schema::dropIfExists('training_modules');
        Schema::dropIfExists('sales_scripts');
    }
};
