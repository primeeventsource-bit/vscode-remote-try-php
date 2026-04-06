<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Production fix: replace wrong placeholder scripts with correct
 * Travel Enterprises rental scripts, add is_default column,
 * and update training modules to match actual business model.
 *
 * Safe: does not delete records — updates existing, adds new.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Step 1: Add is_default column ────────────────────
        if (! Schema::hasColumn('sales_scripts', 'is_default')) {
            Schema::table('sales_scripts', function (Blueprint $table) {
                $table->boolean('is_default')->default(false)->after('is_active');
            });
        }

        // ── Step 2: Unset all defaults first ─────────────────
        DB::table('sales_scripts')->update(['is_default' => false]);

        // ── Step 3: Fix the CLOSER script ────────────────────
        $closerContent = <<<'SCRIPT'
Hi, may I speak with Mr./Mrs. ________?

Hi, my name is ________ with The Travel Enterprises. I'm calling in regards to your ________ vacation property.

Right now we are booking for upcoming corporate conventions and executive travel events, and I wanted to see if you would have any interest in renting out some of the unused getaway time you have available?

(If YES continue — if NO, lightly probe)

STEP 1 – QUALIFYING

Great. What I'm going to do is gather a little information from you so we can see what we would be able to guarantee based on our upcoming events. If the numbers make sense, we can move forward from there.

(Take property info)

Based on what I'm seeing, we would be able to take on up to 12 total getaway weeks through your (RCI/Interval) membership.

• Low Season: $1,600 per week
• High Season: $1,800 per week

That would generate approximately $21,600 for you.

Would that be enough for you to rent out those unused weeks?

STEP 2 – EDUCATION

Are you familiar with how your getaway weeks work?

When you purchased your vacation property, you work with three separate entities:

1. Your Resort – where you get your deeded week.
2. Your Exchange Company (RCI / Interval International) – your banking and exchange system.
3. Us – The Travel Enterprises – we facilitate rentals for exchange members.

As a member of RCI/II, you have two benefits:
• Banking & exchanging your deeded week
• Unlimited access to getaway weeks

Whether someone owns a $100,000 penthouse or a smaller ownership, getaway access is the same.

What we do is activate those getaway weeks and rent them out.

These are strictly getaway weeks — they do NOT affect your personal deeded time.

Does that make sense?

STEP 3 – COMPANY POSITIONING + APP VALUE

Now in case you're not familiar with us…

The Travel Enterprises is a SaaS-based vacation property marketing platform — very similar to Airbnb — but designed specifically for exchange members and corporate executive travel.

We now have our Travel Enterprises Mobile App, available on:
• Google Play Store
• Apple App Store

Through our app, you can:
• Track rental offers
• Review confirmations
• Accept offers directly
• Monitor your bookings
• View documentation
• Communicate securely

Just like Airbnb gives property owners a dashboard — our app gives you a secure owner portal specifically built for timeshare and exchange rentals.

We work directly with corporations attending conventions in Orlando, Las Vegas, Chicago, Atlanta and other major cities.

Instead of putting executives in $350 per night hotel rooms, we provide full one-bedroom and two-bedroom resort accommodations for a flat weekly rate.

It saves corporations money and provides a better experience.

You know how much nicer a timeshare is compared to a standard hotel room, correct?

Exactly.

STEP 4 – PROCESS

Here's how the process works:
• All four weeks are guaranteed to receive rental offers within 180 days.
• Most offers come within 60–90 days.
• You approve all offers — we do NOT take power of attorney.

Once you accept an offer:
1. You confirm availability.
2. The renter places a security credit card on file.
3. Deposit is placed.
4. Booking confirmation is issued.
5. Funds are released directly to you.

You can track all confirmations and documentation directly inside the Travel Enterprises app.

Any questions on how that works?

STEP 5 – BUILD VALUE

Do you currently have your timeshare paid off?

(If no) How much per month?

Multiply x12.

Add maintenance fees.

So instead of coming out of pocket $____ per year…

You now have income coming in to offset:
• Monthly payments
• Maintenance fees
• Taxes

Now your timeshare begins paying for itself.

Does that make sense?

STEP 6 – ACTIVATION FEE

These are getaway weeks, not deeded weeks.

The activation fee is $399 per week.

For four weeks:
$1,596 activation total.

That guarantees:
• Minimum $7,200 return
• 180-day offer guarantee
• App dashboard access
• Corporate marketing placement

Does that make sense?

STEP 7 – CLOSE

To get started, I'm going to verify your information and send you a Rental Release Form via email.

This locks in:
• Your four guaranteed rentals
• Minimum $1,800 per week
• 180-day guarantee
• Continued remarketing if an acceptable offer is not presented

You'll also receive login access to download the Travel Enterprises mobile app from the Apple or Google store so you can monitor everything in real time.

What's the best email address to send your confirmation package to?

(GET DEAL SHEET INFO)
SCRIPT;

        $closerExists = DB::table('sales_scripts')->where('slug', 'closer-main')->exists();
        $mainCloserExists = DB::table('sales_scripts')->where('slug', 'main-closer-script')->exists();

        if ($mainCloserExists) {
            // Record already exists from a previous run — just update it
            DB::table('sales_scripts')->where('slug', 'main-closer-script')->update([
                'name'       => 'Main Closer Script',
                'content'    => $closerContent,
                'category'   => 'closer',
                'stage'      => 'closer',
                'is_active'  => true,
                'is_default' => true,
                'order_index' => 0,
                'updated_at' => now(),
            ]);
        } else {
            // Insert new record
            DB::table('sales_scripts')->insert([
                'name'       => 'Main Closer Script',
                'slug'       => 'main-closer-script',
                'category'   => 'closer',
                'stage'      => 'closer',
                'content'    => $closerContent,
                'is_active'  => true,
                'is_default' => true,
                'order_index' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Deactivate default on old closer script but keep the record
        if ($closerExists) {
            DB::table('sales_scripts')->where('slug', 'closer-main')->update([
                'name'       => '[Old] Closer Main Script',
                'is_default' => false,
                'order_index' => 99,
                'updated_at' => now(),
            ]);
        }

        // ── Step 4: Fix the VERIFICATION / WARM DOWN script ──
        $verificationContent = <<<'SCRIPT'
WARM DOWN / VERIFICATION TRANSFER

Now what I'm going to do is transfer you to our verification department.

They'll place everything on a digital recording for your protection and mine.

This is time-sensitive and word-sensitive.

They will verify:
• Your name
• Address
• Property information
• Payment authorization

You may only answer YES or NO.

If you say anything else, it stops the recording and we restart.

One question will be:
"Do you authorize the charge?"
The answer is YES.

Another question:
"Were you given a renter name at this time?"
The answer is NO.

You understand we are not affiliated with RCI, Interval International, or your resort?
The answer is YES.

Three separate entities — same industry.

It takes about 90 seconds.

FINAL OWNER PROTECTION POINTS
1. You are paid before renters check in.
2. Renters leave a major credit card for incidentals.
3. You are not liable for damages.

Congratulations and welcome to The Travel Enterprises family.
Give me one moment while I message verifications and get you to the front of the line.
SCRIPT;

        $verifyExists = DB::table('sales_scripts')->where('slug', 'verification-script')->exists();
        $mainVerifyExists = DB::table('sales_scripts')->where('slug', 'main-verification-script')->exists();

        if ($mainVerifyExists) {
            DB::table('sales_scripts')->where('slug', 'main-verification-script')->update([
                'name'       => 'Verification / Warm Down Script',
                'content'    => $verificationContent,
                'category'   => 'verification',
                'stage'      => 'verification',
                'is_active'  => true,
                'is_default' => true,
                'order_index' => 0,
                'updated_at' => now(),
            ]);
        } else {
            DB::table('sales_scripts')->insert([
                'name'       => 'Verification / Warm Down Script',
                'slug'       => 'main-verification-script',
                'category'   => 'verification',
                'stage'      => 'verification',
                'content'    => $verificationContent,
                'is_active'  => true,
                'is_default' => true,
                'order_index' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if ($verifyExists) {
            DB::table('sales_scripts')->where('slug', 'verification-script')->update([
                'name'       => '[Old] Verification Script',
                'is_default' => false,
                'order_index' => 99,
                'updated_at' => now(),
            ]);
        }

        // ── Step 5: Fix the FRONTER script ───────────────────
        $fronterContent = <<<'SCRIPT'
Hi, may I speak with Mr./Mrs. ________?

Hi, my name is ________ with The Travel Enterprises. I'm calling in regards to your ________ vacation property.

Right now we are booking for upcoming corporate conventions and executive travel events, and I wanted to see if you would have any interest in renting out some of the unused getaway time you have available?

(If YES — transfer to closer)
(If HESITANT — use qualifying questions below)

QUALIFYING QUESTIONS:

1. How long have you owned your timeshare?
2. Do you currently use your weeks, or do they go unused?
3. Are you familiar with your getaway week benefits through RCI or Interval International?
4. Would you be interested in earning income from those unused weeks?

TRANSFER SCRIPT:

Great — what I'm going to do is connect you with one of our rental coordinators who handles the corporate booking side. They'll be able to go over exactly how many weeks we can take on and what kind of income you can expect.

It only takes about 15 minutes.

One moment while I get them on the line for you.

(ADD NOTES: owner name, resort, number of weeks, interest level)
(TRANSFER TO CLOSER)
SCRIPT;

        $fronterExists = DB::table('sales_scripts')->where('slug', 'fronter-opening')->exists();
        $mainFronterExists = DB::table('sales_scripts')->where('slug', 'main-fronter-script')->exists();

        if ($mainFronterExists) {
            DB::table('sales_scripts')->where('slug', 'main-fronter-script')->update([
                'name'       => 'Main Fronter Script',
                'content'    => $fronterContent,
                'category'   => 'fronter',
                'stage'      => 'fronter',
                'is_active'  => true,
                'is_default' => true,
                'order_index' => 0,
                'updated_at' => now(),
            ]);
        } else {
            DB::table('sales_scripts')->insert([
                'name'       => 'Main Fronter Script',
                'slug'       => 'main-fronter-script',
                'category'   => 'fronter',
                'stage'      => 'fronter',
                'content'    => $fronterContent,
                'is_active'  => true,
                'is_default' => true,
                'order_index' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if ($fronterExists) {
            DB::table('sales_scripts')->where('slug', 'fronter-opening')->update([
                'name'       => '[Old] Fronter Opening Script',
                'is_default' => false,
                'order_index' => 99,
                'updated_at' => now(),
            ]);
        }

        // ── Step 6: Fix training module content ──────────────
        // Update company overview to match Travel Enterprises rental model
        DB::table('training_modules')->where('slug', 'company-overview')->update([
            'content' => <<<'MD'
## Welcome to The Travel Enterprises

The Travel Enterprises is a SaaS-based vacation property marketing platform — similar to Airbnb — designed specifically for exchange members and corporate executive travel.

### How the Business Works
- Timeshare owners have unused getaway weeks through RCI or Interval International
- We activate those getaway weeks and rent them out to corporate travelers
- Owners earn rental income from weeks they're not using
- We charge a one-time activation fee per week

### How Revenue is Generated
- Fronters qualify leads and transfer to closers (rental coordinators)
- Closers present the rental program, explain the app, and close the activation
- Verification admin confirms the deal on a recorded line
- Commission is calculated and paid weekly

### Key Points to Remember
- We are NOT a timeshare exit company
- We facilitate rentals of unused getaway weeks for corporate travel
- Owners keep their deeded weeks — we only activate getaway weeks
- Owners track everything through the Travel Enterprises mobile app
- One-time activation fee, guaranteed rental offers within 180 days
MD,
            'updated_at' => now(),
        ]);

        DB::table('training_modules')->where('slug', 'fronter-system')->update([
            'content' => <<<'MD'
## The Fronter System

### Your Role
You are the first point of contact. Your job is to:
1. Make the initial connection
2. Qualify the lead (do they own a timeshare with exchange membership?)
3. Build enough interest in the rental program to transfer

### Lead Qualification Checklist
- Do they own a timeshare? (must be YES)
- Are they a member of RCI or Interval International? (preferred YES)
- Do they have unused getaway weeks? (must be YES or OPEN TO IT)
- Would they be interested in earning rental income? (must be YES or MAYBE)
- Can they speak for 15 minutes? (preferred YES)

### Transfer Rules
- Only transfer qualified leads
- Always add notes before transferring: owner name, resort, number of weeks, interest level
- Select the right closer (rental coordinator) from the dropdown
- Use the transfer script to warm up the handoff

### Common Mistakes to Avoid
- Transferring unqualified leads
- Not adding notes
- Calling it a "timeshare exit" — we are a rental platform
- Being too aggressive too early
- Failing to mention the getaway weeks / corporate travel angle
MD,
            'updated_at' => now(),
        ]);

        DB::table('training_modules')->where('slug', 'closer-framework')->update([
            'content' => <<<'MD'
## Closer Domination Framework

### The 7-Step Close (Travel Enterprises)
1. **Introduction** — Introduce yourself, reference the vacation property
2. **Qualifying** — Gather property info, present rental income potential
3. **Education** — Explain the 3 entities: Resort, Exchange Company, Us
4. **Company Positioning + App** — Present the Travel Enterprises platform and mobile app
5. **Process** — Explain the 180-day guarantee, offer flow, and approval process
6. **Build Value** — Calculate how rental income offsets their fees/payments
7. **Close** — Present the activation fee and collect deal sheet info

### Micro-Closes Throughout the Call
- "Does that make sense?"
- "Would that be enough for you to rent out those unused weeks?"
- "Can you see how this would help offset your costs?"

### The Final Close
**Ask before collecting payment:**
"To get started, I'm going to verify your information and send you a Rental Release Form via email."

### Warm Down / Verification Transfer
After closing, transition to verification using the warm-down script.
Remind the client: YES or NO answers only, time-sensitive, word-sensitive.

### Tone Control
- Start **warm and friendly** — you're offering income
- Move to **professional and authoritative** — explain the platform
- Close with **confident and direct** — present the activation fee
- Never be rude — be confident in the value
MD,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Revert is_default column
        if (Schema::hasColumn('sales_scripts', 'is_default')) {
            Schema::table('sales_scripts', function (Blueprint $table) {
                $table->dropColumn('is_default');
            });
        }
    }
};
