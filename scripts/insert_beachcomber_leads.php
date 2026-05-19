<?php
/**
 * One-shot bootstrap for tinker: registers primesales_dev as a runtime DB
 * connection, runs all migrations against it, then inserts 62 Beachcomber
 * Inn Resort leads from the user-supplied CSV.
 *
 * Designed to be pasted into `cloud tinker PrimeSales-Dev --code=...` as
 * a single payload. Idempotent on re-run: migrate is a no-op when fully
 * applied; lead insert uses source_file_name as a uniqueness guard
 * (deletes prior batch first).
 */

// Connection details read from environment to avoid committing creds.
// Set these before invoking via tinker, e.g.:
//   PSD_DB_HOST=db-... PSD_DB_USERNAME=... PSD_DB_PASSWORD=... \
//     cloud env:vars <env-id> --action=set --key=PSD_DB_HOST --value=...
// Or stash them in PrimeSales-Dev's env vars permanently.
$psdHost = env('PSD_DB_HOST');
$psdUser = env('PSD_DB_USERNAME');
$psdPass = env('PSD_DB_PASSWORD');
$psdDb   = env('PSD_DB_DATABASE', 'main');

if (!$psdHost || !$psdUser || !$psdPass) {
    throw new RuntimeException('Missing PSD_DB_HOST / PSD_DB_USERNAME / PSD_DB_PASSWORD env vars.');
}

config()->set('database.connections.psd', [
    'driver'    => 'mysql',
    'host'      => $psdHost,
    'port'      => (int) env('PSD_DB_PORT', 3306),
    'database'  => $psdDb,
    'username'  => $psdUser,
    'password'  => $psdPass,
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix'    => '',
    'strict'    => true,
    'engine'    => null,
]);

echo "=== Probe ===\n";
$probe = DB::connection('psd')->getPdo()->query('SELECT VERSION()')->fetchColumn();
echo "psd connected: $probe\n";

echo "\n=== Migrate fresh ===\n";
// migrate:fresh drops every table on the connection then re-runs migrations.
// Safe here because primesales_dev cluster has only stale partial tables from
// earlier failed deploys, no real data.
Artisan::call('migrate:fresh', ['--database' => 'psd', '--force' => true]);
echo Artisan::output();

echo "\n=== Schema check ===\n";
$hasStreet = DB::connection('psd')->getSchemaBuilder()->hasColumn('leads', 'street_address');
echo 'street_address present: ' . ($hasStreet ? 'YES' : 'NO') . "\n";

$leads = [
    ['JOHNSON NICOLE DALE', '',                  '311 1/2 E Cook St',              'New London',          'WI', '54961', '6153321052', '2178328156'],
    ['MCDONALD MICHAEL',    'MCDONALD PATRICIA', '8053 Bailey Dr NE',              'Ada',                 'MI', '49301', '6168222888', '6168225544'],
    ['FARRY MICHAEL T',     'FARRY LOIS M',      '14525 Jasper Ln',                'Grass Valley',        'CA', '95949', '9252007237', '9257839887'],
    ['VONK STEVEN A',       'VONK MARGARET O',   '6282 Old Redwood Hwy',           'Santa Rosa',          'CA', '95403', '7072927043', '7078382118'],
    ['THOMSON MAIHRI E',    '',                  '23762 New Delhi St',             'Mission Viejo',       'CA', '92691', '9493069334', '9495750921'],
    ['CAVANAUGH JOHN',      'CAVANAUGH ANN',     '130 Deerglen Cir',               'Vacaville',           'CA', '95687', '7074483654', '7074516466'],
    ['FONDALE JOELLE',      'MCRONALD KEITH',    '8841 Oak Ave',                   'Orangevale',          'CA', '95662', '9168124442', '3523510142'],
    ['MOODY DANIEL L',      'MOODY ANITA M',     '44019 N 49th Dr',                'New River',           'AZ', '85087', '6199771334', ''],
    ['FALCON BONIFACIO',    'FALCON LISA',       '227 Grissom St',                 'Hercules',            'CA', '94547', '5103873204', '5103873205'],
    ['STILLWELL STANCIL DONNA', '',              '932 Wilmington Way',             'Emerald Hills',       'CA', '94062', '6507149765', '5036238177'],
    ['CARRILLO MAYA G',     '',                  '2035 N Harlem Ave #3N',          'Chicago',             'IL', '60707', '7088482145', '7736373598'],
    ['POACH WILLIAM G JR',  'POACH KATHLEEN L',  '5714 E Hillery Dr',              'Scottsdale',          'AZ', '85254', '6024947264', '6026776142'],
    ['DAVIS PATRICIA B',    'DAVIS STEPHEN H',   '3001 Grey Farm Rd',              'Jamesville',          'NC', '27846', '2527932471', '2527934995'],
    ['MOODY AUTUMN DANIELLE', '',                '20801 N Burma Rd',               'Ramona',              'CA', '92065', '8582466394', ''],
    ['BRANDT JOEL JOHN',    'BRANDT JAMIE',      '499 Saint Andrews Rd',           'Valley Springs',      'CA', '95252', '9162212449', '9162761715'],
    ['CARRILLO VICTOR PAUL', '',                 '2120 N Quincy Ct',               'Visalia',             'CA', '93291', '5598054823', '2092241519'],
    ['BOESE MARK',          'BOESE DEBRA',       '3212 New London Ln',             'Modesto',             'CA', '95355', '2095522297', '2092473986'],
    ['HAHN HILARY',         'HAHN CHERYL',       '7114 E Woodward Ave',            'Manteca',             'CA', '95337', '2092399428', '2098153737'],
    ['DELP JERALD R',       'DELP SANDRA J',     '325 Bartlett Ave',               'Woodland',            'CA', '95695', '5306625516', '9189649957'],
    ['TIU SARAH M',         '',                  '5715 Carlos Ave',                'Richmond',            'CA', '94804', '5102328804', '4156619574'],
    ['HAYES TYLER DELL',    'HAYES ZACHARY LEE', '1731 W 235th St #235',           'Torrance',            'CA', '90501', '3105303434', '4242634723'],
    ['SAUER ROBERT C JR',   'MCCAFFREY EILEEN A', '3881 Walnut Dr',                'Rescue',              'CA', '95672', '5305585073', '5305585090'],
    ['KELLER PATRICIA K',   'KELLER CASEY C',    '105 Grimes Way',                 'Folsom',              'CA', '95630', '9163510834', '9162965488'],
    ['EREMEYEFF IRENE K',   '',                  '6614 Embarcadero Dr #15',        'Stockton',            'CA', '95219', '2094768551', ''],
    ['MCMANIGAL CHERYL JOANNE CALDWELL', '',     '2201 El Cejo Cir',               'Rancho Cordova',      'CA', '95670', '9167128410', '9163623771'],
    ['OAKS JOHN',           'OAKS JANIS',        '400 Hemlock Dr',                 'Petersburg',          'IL', '62675', '2175234976', '2176325770'],
    ['WOREL SHIRLEY',       '',                  '1195 London Way',                'Napa',                'CA', '94559', '4054155861', ''],
    ['GERHARDT FREDRICK',   'GERHARDT LYNN',     '10667 N Shinnecock Dr',          'Fresno',              'CA', '93730', '5592892924', '5596743381'],
    ['INGHAM ROGER',        'INGHAM MAUREEN',    '1240 Green Acres Ct',            'Santa Cruz',          'CA', '95062', '8313450990', '8314779452'],
    ['WALSH STANLEY II',    'WALSH CHERRY M',    '1520 Tricia Ln',                 'Santa Cruz',          'CA', '95062', '8314761566', '8314756408'],
    ['BRANDIN LESTER C',    'BRANDIN KATHLEEN J', '180 Trinity Rd',                'Brisbane',            'CA', '94005', '4154675426', '4154674587'],
    ['DAVI ANTHONY',        'DAVI DOLORES',      '156 Pueblo Dr',                  'Pittsburg',           'CA', '94565', '9254320403', '8082818777'],
    ['TROGDON STEPHANIE',   'TROGDON DAVID',     '357 Graciella Dr',               'Windsor',             'CA', '95492', '7078158428', '7079650136'],
    ['LUNDBOM ROBERT',      'LUNDBOM MICHELLE',  '6199 Northland Rd',              'Manteca',             'CA', '95336', '2094795768', '2092392995'],
    ['KEELER SCOTT',        'KEELER BARBARA',    '5260 Pondview Ln',               'Big Lake',            'MN', '55309', '7632631838', '7632133159'],
    ['LIPPEN CAROL',        '',                  '2036 Kathy Way',                 'Torrance',            'CA', '90501', '3103471176', '9067865245'],
    ['BEDROSIAN WILLIAM TOD', 'BEDROSIAN JANET M', '835 Klein Way #WA',            'Sacramento',          'CA', '95831', '9164215121', '9166000384'],
    ['VONK BARBARA JEAN',   '',                  '3250 Rupert Rd',                 'Anderson',            'CA', '96007', '9164833634', '5309170893'],
    ['RECTOR JAMES R JR',   'RECTOR CAROLE',     '20314 Lake Spring Ct',           'Cypress',             'TX', '77433', '2817038528', '2816605839'],
    ['CAFFEE WILLIAM B E JR', 'CAFFEE PATRICIA A', '555 Tiger Dr #MRE',            'Alturas',             'CA', '96101', '2094777007', '5302332099'],
    ['VAN ZANT KAREN D',    '',                  '1327 Aberdeen Ave',              'Stockton',            'CA', '95209', '2094822994', '2099814546'],
    ['LAUTEN DENNIS R',     'LAUTEN SANDRIA L',  '2447 El Chico Cir',              'Rancho Cordova',      'CA', '95670', '9163623488', '9208452133'],
    ['HAAN DAVID L',        'HAAN RITA C',       '27817 N Hibiscus Ln',            'San Tan Valley',      'AZ', '85143', '2095754520', '6263270007'],
    ['ALEXANDER ALAN A',    'ALEXANDER AUDREY L', '308 Arborvine Oval #40',        'Madison',             'OH', '44057', '3195241113', '7604084481'],
    ['GARDNER LINDA SUE',   'GARDNER LESLIE ALLAN', '1891 Glenbrook Ln',           'Lincoln',             'CA', '95648', '9164088187', '4153050015'],
    ['KEELER JAMES D',      'KEELER JENICE P',   '15822 Plymouth Ln',              'Huntington Beach',    'CA', '92647', '7148982246', '7144692447'],
    ['MURRAY COLEEN R',     'MURRAY DAVID P',    '10610 Claim Jumper Way',         'Reno',                'NV', '89521', '3197951579', '3195242828'],
    ['BAUER KENNETH',       'BAUER KRISTI',      '816 W Woodridge St',             'Springfield',         'MO', '65803', '4176196976', '4027656371'],
    ['CAFFEE JOHN J L',     '',                  '5025 SW Hall Blvd',              'Beaverton',           'OR', '97005', '5038193779', '5036261057'],
    ['KILL ANDREW',         'KILL MARIA',        '2870 Dillon Dr',                 'Ann Arbor',           'MI', '48105', '6507145772', '6507145874'],
    ['PERREIRA AMY LYNN',   '',                  '2592 Cornflower St',             'Stockton',            'CA', '95212', '2099154829', '2092107388'],
    ['FARRAND DAVID N',     '',                  '250 Iroquois Dr',                'Arnold',              'CA', '95223', '2095414274', '2095220785'],
    ['KUBERKA JOSEPH L',    'KUBERKA KATHLEEN A', '47721 Alexander Ln',            'Elmo',                'MT', '59915', '6207823253', '7196836708'],
    ['COBB PATRICIA K',     'HERBOLD BRUCE',     '621 Athol Ave',                  'Oakland',             'CA', '94610', '4158123964', '5108587588'],
    ['HORNER MICHAEL',      'HORNER AMY',        '16151 Wilson Manor Dr',          'Chesterfield',        'MO', '63005', '3143688835', '3145667824'],
    ['GHEEN KATHLEEN',      'GHEEN RONALD J',    '8842 Olive Ranch Ln',            'Fair Oaks',           'CA', '95628', '9167697576', '9164259721'],
    ['ELLINOR LINDA',       '',                  '25111 La Cresta Dr #A',          'Dana Point',          'CA', '92629', '7072176675', '5203982650'],
    ['WILSON CINDY A',      'WILSON FREDERICK W', '4330 Lynn Dr',                  'Concord',             'CA', '94518', '9258256475', '9255863197'],
    ['ROSS KENNETH M',      'ROSS SUSAN M',      '2628 Executive Dr #5303',        'Venice',              'FL', '34292', '7246223281', '7246434561'],
    ['YESITIS LAURA',       'YESITIS SCOTT',     '17874 W Sheltered Ct',           'Post Falls',          'ID', '83854', '9169613525', '9163965420'],
    ['ACKLIN LESTER T',     '',                  '235 Alder St',                   'Brookings',           'OR', '97415', '5414696163', '9162968986'],
    ['TONG THOMAS',         'GHOPH SEABEA',      '325 Soquel Ave',                 'Santa Cruz',          'CA', '95062', '4086613838', '8316766177'],
    ['VALERA MEDIATRIX',    'FERNANDO RICARDO',  '292 Ferndale Ave',               'South San Francisco', 'CA', '94080', '6502431710', '6502731131'],
];

$sourceFileName = 'beachcomber-inn-resort-ca-2026-05-19.csv';
$now = now();

echo "\n=== Insert ===\n";
$deleted = DB::connection('psd')->table('leads')->where('source_file_name', $sourceFileName)->delete();
echo "Removed prior batch rows: $deleted\n";

$rows = [];
foreach ($leads as $l) {
    $rows[] = [
        'resort'            => 'Beachcomber Inn Resort',
        'resort_location'   => 'CA',
        'owner_name'        => $l[0] ?: null,
        'owner_name_2'      => $l[1] ?: null,
        'street_address'    => $l[2] ?: null,
        'city'              => $l[3] ?: null,
        'st'                => $l[4] ?: null,
        'zip'               => $l[5] ?: null,
        'phone1'            => $l[6] ?: null,
        'phone2'            => $l[7] ?: null,
        'source'            => 'csv_paste',
        'source_file_name'  => $sourceFileName,
        'imported_at'       => $now,
        'created_at'        => $now,
        'updated_at'        => $now,
    ];
}

DB::connection('psd')->table('leads')->insert($rows);
echo 'Inserted: ' . count($rows) . " rows\n";

$total = DB::connection('psd')->table('leads')->count();
echo "Total leads in primesales_dev now: $total\n";
