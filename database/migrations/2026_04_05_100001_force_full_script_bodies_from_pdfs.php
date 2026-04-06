<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Production fix: replace shortened/condensed script content with the
 * FULL script bodies from the actual PDF files:
 *
 *   - "Closer script (1).pdf"       → Main Closer Script (default)
 *   - "FRONT CLOSE pitch (1).pdf"   → Main Fronter Script (default)
 *   - "New closing script app.pdf"  → App Closer Script (alternate, NOT default)
 *
 * This migration is idempotent — safe to run multiple times.
 * Does NOT delete any records. Archives old shortened versions.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Ensure is_default column exists (from previous migration)
        if (! Schema::hasColumn('sales_scripts', 'is_default')) {
            Schema::table('sales_scripts', function ($table) {
                $table->boolean('is_default')->default(false)->after('is_active');
            });
        }

        // ── Step 1: Clear ALL defaults so we start clean ─────
        DB::table('sales_scripts')->update(['is_default' => false]);

        // ── Step 2: MAIN CLOSER SCRIPT — full body from "Closer script (1).pdf" ──
        $this->upsertScript(
            slug: 'main-closer-script',
            name: 'Main Closer Script',
            category: 'closer',
            stage: 'closer',
            isDefault: true,
            orderIndex: 0,
            content: self::fullCloserScript(),
        );

        // Archive old closer records
        $this->archiveOldSlugs(['closer-main'], 'closer');

        // ── Step 3: MAIN FRONTER SCRIPT — full body from "FRONT CLOSE pitch (1).pdf" ──
        $this->upsertScript(
            slug: 'main-fronter-script',
            name: 'Main Fronter Script',
            category: 'fronter',
            stage: 'fronter',
            isDefault: true,
            orderIndex: 0,
            content: self::fullFronterScript(),
        );

        // Archive old fronter records
        $this->archiveOldSlugs(['fronter-opening'], 'fronter');

        // ── Step 4: APP CLOSER SCRIPT — from "New closing script app.pdf" (ALTERNATE, not default) ──
        $this->upsertScript(
            slug: 'app-closer-script',
            name: 'App Closer Script',
            category: 'closer',
            stage: 'closer',
            isDefault: false,
            orderIndex: 10,
            content: self::appCloserScript(),
        );

        // ── Step 5: VERIFICATION / WARM DOWN — extracted from fronter PDF warm-down section ──
        $this->upsertScript(
            slug: 'main-verification-script',
            name: 'Verification / Warm Down Script',
            category: 'verification',
            stage: 'verification',
            isDefault: true,
            orderIndex: 0,
            content: self::fullVerificationScript(),
        );

        // Archive old verification records
        $this->archiveOldSlugs(['verification-script'], 'verification');
    }

    // ══════════════════════════════════════════════════════════
    // HELPER: upsert a script record safely
    // ══════════════════════════════════════════════════════════

    private function upsertScript(string $slug, string $name, string $category, string $stage, bool $isDefault, int $orderIndex, string $content): void
    {
        $exists = DB::table('sales_scripts')->where('slug', $slug)->exists();

        if ($exists) {
            DB::table('sales_scripts')->where('slug', $slug)->update([
                'name'        => $name,
                'category'    => $category,
                'stage'       => $stage,
                'content'     => $content,
                'is_active'   => true,
                'is_default'  => $isDefault,
                'order_index' => $orderIndex,
                'updated_at'  => now(),
            ]);
        } else {
            DB::table('sales_scripts')->insert([
                'name'        => $name,
                'slug'        => $slug,
                'category'    => $category,
                'stage'       => $stage,
                'content'     => $content,
                'is_active'   => true,
                'is_default'  => $isDefault,
                'order_index' => $orderIndex,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }

    // ══════════════════════════════════════════════════════════
    // HELPER: archive old slugs without deleting
    // ══════════════════════════════════════════════════════════

    private function archiveOldSlugs(array $slugs, string $stage): void
    {
        foreach ($slugs as $slug) {
            if (DB::table('sales_scripts')->where('slug', $slug)->exists()) {
                DB::table('sales_scripts')->where('slug', $slug)->update([
                    'name'        => '[Archived] ' . DB::table('sales_scripts')->where('slug', $slug)->value('name'),
                    'is_default'  => false,
                    'is_active'   => false,
                    'order_index' => 99,
                    'updated_at'  => now(),
                ]);
            }
        }
    }

    // ══════════════════════════════════════════════════════════════
    // FULL SCRIPT: Main Closer — from "Closer script (1).pdf"
    // ══════════════════════════════════════════════════════════════

    private static function fullCloserScript(): string
    {
        return <<<'SCRIPT'
Close:

My name is ……….go ahead and grab a pen and piece of paper, I have some
figures for you to write down. (Give name and number) Now I'm going to go over
all of the details with explain to you what the extra vacation weeks are and let you
know exactly what the corporations are offering for each week. Ok!

1. What we do here Mr/Mrs — we deal with major corporations on a yearly bases
and every year, what they do is reach out to us to request the weeks and that's
when we usually reach out to RCI/Interval International members like yourself to
see if they would like to rent out their extra vacation weeks to make some extra
money. We're not looking to rent out your time that you have with your timeshare
resort , that will still be available to you , so it doesn't interfere with that at all, it
wouldn't even interfere with anything that you may have banked with RCI/Interval.
These extra vacation weeks are completely separate.

Let me ask you this: Are you familiar with the extra vacation weeks?

No problem, I'll go over them with you and explain to you exactly what they are.

As you know RCI/II is your banking & exchange group. You become a member
with RCI/II by being a _____ resort owner and anyone that's a member with
RCI/Interval International has access to these extra vacation weeks.

2. They're one of the most popular RCI/II membership benefits. They're just
basically discounted vacations for members. You can use as many extra weeks
as you would like throughout the year for yourself and family and to go to any
resort that is affiliated with RCI/II you would Just have to pay the discounted rate
which they usually start as low as $399 per week, depending on the time of the
year and the size of the unit. You're also required to sit through those 90 min
presentations and that's why most owners never use these extra weeks, because
they don't want to sit through those presentations. So, what they usually do is
give them out to friends or family members or they rent them out for a profit
because out of those unlimited extra vacation weeks they allow you to rent out 6
per year for an actual profit. Anything over the 6 would be considered as
commercial use that's why they cap us at the 6 weeks.

3. So you do have those 6 weeks and all 6 weeks are being requested and right
now Mr/Mrs___, the corporations are offering anywhere from $1600-2000 so that's
$1600 minimum guaranteed times 6 weeks you would be looking at a minimum of
$9600 in income for this year for all 6 weeks and I want you to keep in mind, we
do already have the demand for the weeks so the only thing we have to do is fill
in the inventory. We don't just take your weeks and list them online hoping for
someone to rent them out. They won't even be available for the general public.
They are made available for corporations for corporate use only and that's just
another reason why we're able to guarantee you your offers.

So far, so good Mr/Mrs ___, any questions for me at all ?

4. Ok, now in order for us to rent out your weeks they would have to be activated.
That's the only thing you would be responsible for is activating your weeks just
as you would if you were to use them for yourself. Keep in mind, these are not
free vacations, they are discounted. So it would be the 399 per week to activate.
That covers the activation, transfer of the guest passes, insurance, and
Advertisement. So for all 6 weeks that would be $2394 but keep in mind you
would be looking to make back anywhere from 9600-12000 for all 6 weeks so you
would be looking to profit at least 1200 for each week you activate as long as the
weeks are activated and that 1600-2000 offer is acceptable for you. The offers are
100% guaranteed because once the weeks are activated they will be released into
our inventory and I'll be able to sign off on them to go ahead and get them booked
for the events.

5. TURN AROUND TIME : Now as far as the turnaround time, right now we are
averaging 90-180 days turn around time but, 180 days guaranteed. So what's
going to happen within 90 to 180 days we will contact you with all of your offers.
These events are big enough for us to rent out all your weeks together so you will
be receiving all of your offers at once and when you do receive your offers you
will not get anything less than 1600 for each week. Again, that's the minimum
guaranteed and they go as high as $2000 for each week so for any reason you are
not happy or satisfied with that $1600 to 2000 offers and of you want more you do
have the option to to decline your first offer to negotiate for a higher rate.
However, at that point we would have to re market for another 180 days to get you
a higher offer. Now, I don't recommend that but that is completely your choice. It
all depends on how fast you would like to see your return. Now, once we book the
weeks, you are paid in 72 hrs of that time in the form of a bank draft cashier's
check unless you choose direct deposit that will also be an option and that's 24
hours.

6. Not only will you receive a verbal verification today but, you will also receive
everything in writing. We do everything through a legal binding document.

7. So, I will go ahead and give you all of our info and I'll take some info from you
then get you over to verification so we can get that contract out to you ◦
Verification is a 90 second timed recording for your protection as well as ours to
make sure I haven't promised you anything outside our guidelines. After
verification they will email you out the contract via dropbox and go over it with
you. Hold for one moment.
SCRIPT;
    }

    // ══════════════════════════════════════════════════════════════
    // FULL SCRIPT: Main Fronter — from "FRONT CLOSE pitch (1).pdf"
    // ══════════════════════════════════════════════════════════════

    private static function fullFronterScript(): string
    {
        return <<<'SCRIPT'
OPEN Fronter Script

Hi, may I speak with Mr./Mrs.________ My name is________ I'm calling in regards to
your ________ vacation property, right now we're booking for some corporate events
and wanted to see if you have an interest in renting out some of the unused time you
have available? getaway weeks

(If Yes continue, if NO probe a little more and find the objection)

1.Great, What I'm going to do is take a little information from you to see what we would
be able to guarantee you based on our events coming up and if that's enough we will go
from there.

(Take Property info)

So right now I'm showing we would be able to take on 12 total weeks through your
(RCI/Interval) membership, each week guaranteed at $1600 per week in low season
High Season 1800 per weeks generating you $21,600 , Would that be enough money
for you to rent out those unused weeks?

(If YES continue, if NO, probe and find out why)

Great, now are you familiar with how your getaway weeks work?

2. When you purchase your (Blank) vacation property you have three different
companies you work with. First you have your resort, that's where you get your one
bedroom one bathroom every year, then you have RCI/II that is considered your bank
and exchange company, then you have us which is (our company's name) and we
facilitate the rentals for RCI/II and DAE members. Now by being a member of RCI/II you
get two different services provided to you through that program. First, they are a bank
and exchange company, meaning if you want to bank your week at the resort and use it
at a different time and location that they offer you worldwide you can. In addition to that
(RCI/II) members, if you own a hundred thousand dollar penthouse in Las Vegas or a
five thousand dollar outhouse in Tennessee you get unlimited access to what are called
getaway weeks every year. What these are designed to do, is let's say you have friends
or family that wanna take a vacation, you can activate a getaway send friends and family
on a vacation. Let's say you have already used your week this year but you
want to take an additional vacation this year, activate a getaway, take an additional
vacation in this case what we're doing is activating these and renting them out. That
allows you to not only get a return on investment with your property but also helps offset
the maintenance fees and taxes you have on a year to year basis as well. Bottom line
the four weeks are strictly getaways it would not affect any of your personal time. Does
that make sense? Great!

3. Now in case you're not familiar with our company (company's name) , what we do is
facilitate these rentals to corporations that come here to Orlando every year for their
annual trade shows and conventions. We deal with thousands of corporations that
attend these events alone. Typically what these corporations do is book their executives
in hotels within the surrounding area, most of these rooms cost an average of three
hundred and fifty dollars a night and they get a bed and a bathroom. What we do is sit
down face to face with them, similar to the timeshare tour you attended & say, "Look we
will put you over here in a (RCI/II) getaway week, give you a full kitchen, living room and
all the accommodations that come with a timeshare and we will do it for fifteen hundred
or two thousand!" Not only does this save them money but it houses their executives
more effectively and is a better experience for them. You know how much nicer a
timeshare is then just a regular resort, correct? Exactly!

4. The way the process will work is all four weeks are guaranteed to be rented within
180 days from today, however as long as the ($1,800 or per week price) per week is
acceptable to you then you will receive all offers within a 60 to 90 day period it's just up
to you to accept the offers, we do not take power of attorney from you so we can not
accept offers on your behalf. Once you accept the offers your job is pretty much over, all
you will be doing is confirming the availability and releasing liability meaning they put
down their CC just in case anything were to happen to the unit you're not held liable.
After you confirm the availability the company we are renting the week will then place a
deposit and that's when we book them into the resort and receive a confirmation of the
booking. Once we have the confirmation the funds will be released to you directly. Do
you have any questions as far as how the process works? Great!

5. Building Value

Now let me ask you, do you have your timeshare paid off? (if no) How much do you pay
a month? (take amount X 12) and how much do you pay in maintenance fees and taxes
every year? (add together) So the great thing is by utilizing these getaways not only do
you still get to use your deeded weeks but now instead of coming out of your pocket (X)
amount now you got $7,200 coming in. Use that to pay your monthly
payments and maintenance fees and taxes so not only do you still get to use your
weeks but now your timeshare is paying for itself! Does that make sense?

6. Now as I stated before these are weeks you have access through your exchange
company not weeks you are deeded to. If you wanted to rent all four weeks out it would
be $399 per week to activate, so if you activate all four weeks you're looking at an
activation fee of $1,596. That will guarantee you a minimum of $7,200 for this year alone
upon you accepting the offers. Does that make sense? Great!

7. So to get started, I'm going to take all of your information to make sure I have
everything correct. I will also give you all my information, then I'll take the credit card for
the $1596. I'm going to send you out a rental release form via email. This will lock in
your 4 rentals at a guaranteed minimum of $1800 each week each as well as the 180
day guarantee. We also added additional coverage for you that in any unlikely
circumstance you don't have an offer that is ACCEPTABLE to you within the 180 days,
we will re-market the property at our own expense until you have an ACCEPTABLE
offer. Remember, you will have an offer for the first four weeks within 60-90 days. Once
we both review and sign off on the rental release form, at that point we will activate your
membership and get started on these four weeks. What's the email address I can send
your confirmation package to?

(GET ALL INFORMATION ON THE DEAL SHEET)

GO TO WARM DOWN PAGE!!!!!
SCRIPT;
    }

    // ══════════════════════════════════════════════════════════════
    // FULL SCRIPT: Verification / Warm Down — from "FRONT CLOSE pitch (1).pdf"
    // ══════════════════════════════════════════════════════════════

    private static function fullVerificationScript(): string
    {
        return <<<'SCRIPT'
WARM DOWN

NOW WHAT I AM GOING TO DO NEXT IS GET YOU OVER TO OUR VERIFICATION
DEPARTMENT THEY'RE GONNA PUT EVERYTHING ON A DIGITAL RECORDING THAT'S
FOR YOUR PROTECTION AND MINE. (BRIEF PAUSE) THEIR MAKING SURE I AM DOING
MY JOB CORRECTLY AND DIDN'T PROMISE YOU ANYTHING WE CAN'T DELIVER
VERIFICATIONS IS A TIME SENSITIVE AND WORD SENSITIVE RECORDING THEY
CANNOT STOP AND ANSWER ANY QUESTIONS THEY'RE NOT LICENSED TO DO SO. SO
IF YOU THINK OF ANYTHING WRITE THEM DOWN AND THEY CAN TRANSFER YOU
BACK TO ME TO GO OVER ANY OF THOSE QUESTIONS AT THAT TIME..(BRIEF PAUSE)

DURING VERIFICATION THEY WILL VERIFY YOUR NAME, ADDRESS, PROPERTY
INFORMATION AND PAYMENT INFORMATION. THEY'RE GONNA ASK YOU A COUPLE
QUESTIONS FOR YOUR SECURITY YOU CAN ONLY ANSWER" YES OR NO" TO THESE
QUESTIONS. IF YOU SAY ANYTHING OTHER THAN "YES OR NO" THAN THEY WILL HAVE
TO STOP THE RECORDING & SEND YOU BACK TO ME AND WE WILL HAVE TO START
ALL OVER AGAIN.

AMONG THOSE QUESTIONS WILL BE "DO YOU AUTHORIZE THE CHARGE?'
THE ANSWER IS YES.

ANOTHER QUESTION IS : "DID I GIVE YOU THE NAME OF A RENTER AT THIS TIME?"
THE ANSWER IS NO I DON'T GET SPECIFICS UNTIL IT'S TIME TO BOOK.

THE NEXT QUESTION IS YOU DO UNDERSTAND WE AREN'T AFFILIATED WITH RCI
INTERVAL INTERNATIONAL OR ANY RESORT. THE ANSWER IS YES. YOUR HOME
RESORT IS WHERE YOU GET YOUR DEEDED TIME OR POINTS THEN YOU HAVE YOUR
EXCHANGE COMPANY (THEY DO THE BANKING AND EXCHANGING OF THE WEEKS)
AND THEN THERE IS US WE HANDLE THE RENTALS . SO THREE SEPARATE ENTITIES
WE JUST ALL WORK IN THE SAME FIELD

IT ONLY TAKES THEM ABOUT 90 SECONDS (A MINUTE & A HALF). ALSO, IF YOU SAY
ANYTHING OTHER THAN YES OR NO TO THE QUESTIONS IT WILL AUTOMATICALLY
STOP THE RECORDING IT WILL SEND YOU BACK TO ME AND YOU WILL HAVE TO START
ALL OVER AGAIN. SO AGAIN HOLD ALL YOUR QUESTIONS UNTIL THEY TRANSFER YOU
BACK TO ME.

FINALLY 2 THINGS THAT ARE VERY IMPORTANT TO YOU AS AN OWNER
1. YOU ARE GONNA GET PAID UP FRONT FOR THE WEEKS BEFORE THE RENTERS
CHECK IN SO IN THE EVENT THAT THEY CANCEL IT IS NON REFUNDABLE THE CHECKS
ARE YOURS TO KEEP EITHER WAY
2. WHEN THE RENTERS CHECK IN THEY ARE REQUIRED TO LEAVE A MAJOR CREDIT
CARD AND A VALID ID ON FILE FOR ANY TYPE OF INCIDENTALS SO IF THEY SPILL
SOME WINE ON THE CARPET (BREAK A LAMP OR TOTALLY TRASH THE PLACE) YOU
WON'T BE RESPONSIBLE THEY WILL.

CONGRATULATIONS AGAIN AND WELCOME TO THE GMS FAMILY!! GIVE ME A
MOMENT TO MESSAGE VERIFICATIONS & TRY AND GET YOU TO THE FRONT OF
THE LINE. IT'S ALWAYS PRETTY BUSY THERE.
SCRIPT;
    }

    // ══════════════════════════════════════════════════════════════
    // APP CLOSER SCRIPT — from "New closing script app.pdf" (ALTERNATE)
    // ══════════════════════════════════════════════════════════════

    private static function appCloserScript(): string
    {
        return <<<'SCRIPT'
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
    }

    public function down(): void
    {
        // No destructive rollback — scripts remain as-is
    }
};
