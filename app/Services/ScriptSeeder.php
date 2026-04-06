<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Self-healing script seeder. Ensures the correct full-body
 * scripts exist in the sales_scripts table on every page load.
 * Idempotent — safe to call repeatedly.
 */
class ScriptSeeder
{
    private static bool $checked = false;

    public static function ensureScriptsExist(): void
    {
        if (self::$checked) return;
        self::$checked = true;

        try {
            if (! Schema::hasTable('sales_scripts')) return;

            $hasDefault = Schema::hasColumn('sales_scripts', 'is_default');

            // Use fingerprints to detect wrong content — always overwrite if mismatch
            self::ensureScript('main-closer-script', 'Main Closer Script', 'closer', 'closer', self::closerScript(), $hasDefault, 0);
            self::ensureScript('main-fronter-script', 'Main Fronter Script', 'fronter', 'fronter', self::fronterScript(), $hasDefault, 0);
            self::ensureScript('main-verification-script', 'Verification / Warm Down Script', 'verification', 'verification', self::verificationScript(), $hasDefault, 0);
            self::ensureScript('app-closer-script', 'App Closer Script', 'closer', 'closer', self::appCloserScript(), false, 10);

            // Archive old placeholder scripts
            foreach (['closer-main', 'fronter-opening', 'verification-script'] as $oldSlug) {
                $old = DB::table('sales_scripts')->where('slug', $oldSlug)->first();
                if ($old && ! str_starts_with($old->name, '[Archived]')) {
                    $update = ['name' => '[Archived] ' . $old->name, 'is_active' => false, 'order_index' => 99, 'updated_at' => now()];
                    if ($hasDefault) $update['is_default'] = false;
                    DB::table('sales_scripts')->where('slug', $oldSlug)->update($update);
                }
            }
        } catch (\Throwable $e) {
            // Never break the page
        }
    }

    /**
     * Compare hash of what's in DB vs what should be there.
     * If different or missing, write the correct full content.
     */
    private static function ensureScript(string $slug, string $name, string $category, string $stage, string $content, bool $isDefault, int $order): void
    {
        $correctHash = md5($content);
        $existing = DB::table('sales_scripts')->where('slug', $slug)->first();

        if ($existing && md5($existing->content ?? '') === $correctHash) {
            return; // Already correct — skip
        }

        // Content is wrong or missing — write the full version
        $hasDefaultCol = Schema::hasColumn('sales_scripts', 'is_default');

        $data = [
            'name' => $name,
            'category' => $category,
            'stage' => $stage,
            'content' => $content,
            'is_active' => true,
            'order_index' => $order,
            'updated_at' => now(),
        ];
        if ($hasDefaultCol) {
            $data['is_default'] = $isDefault;
        }

        if ($existing) {
            DB::table('sales_scripts')->where('slug', $slug)->update($data);
        } else {
            $data['slug'] = $slug;
            $data['created_at'] = now();
            DB::table('sales_scripts')->insert($data);
        }
    }

    // ══════════════════════════════════════════════════════════
    // FULL SCRIPT: Closer — from "Closer script (1).pdf"
    // ══════════════════════════════════════════════════════════

    public static function closerScript(): string
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

    // ══════════════════════════════════════════════════════════
    // FULL SCRIPT: Fronter — exact text from user's PDF
    // ══════════════════════════════════════════════════════════

    public static function fronterScript(): string
    {
        return <<<'SCRIPT'
OPEN FRONTER SCRIPT

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

    // ══════════════════════════════════════════════════════════
    // FULL SCRIPT: Verification / Warm Down
    // ══════════════════════════════════════════════════════════

    public static function verificationScript(): string
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

    // ══════════════════════════════════════════════════════════
    // APP CLOSER SCRIPT — from "New closing script app.pdf"
    // ══════════════════════════════════════════════════════════

    public static function appCloserScript(): string
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
}
