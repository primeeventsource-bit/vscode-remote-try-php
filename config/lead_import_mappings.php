<?php

/*
|--------------------------------------------------------------------------
| Lead Import — Header Synonym Dictionary
|--------------------------------------------------------------------------
|
| Maps raw spreadsheet column headers to Lead model fields. Editable
| without code changes; LeadImportWizard normalizes headers (lowercase,
| strip non-alphanumeric) before matching against these synonyms.
|
| Keys MUST be real columns on the leads table. Anything else is a dead
| mapping. See app/Models/Lead.php $fillable for the authoritative list.
|
| Matching is case-insensitive and punctuation-insensitive: "Phone #1",
| "phone_number_1", and "PHONE NUMBER 1" all normalize to "phonenumber1".
|
*/

return [
    'resort' => [
        'resort', 'resortname', 'property', 'club', 'timeshare', 'brand',
    ],
    'owner_name' => [
        'ownername', 'owner', 'name', 'fullname', 'primaryowner',
        'contactname', 'customer', 'customername', 'lead', 'leadname',
    ],
    'phone1' => [
        'phone1', 'phonenumber1', 'phone', 'phonenumber', 'primaryphone',
        'mobile', 'cell', 'cellphone', 'contactnumber', 'tel', 'telephone',
        'homephone', 'workphone',
    ],
    'phone2' => [
        'phone2', 'phonenumber2', 'secondaryphone', 'altphone',
        'alternatephone', 'altphonenumber', 'otherphone',
    ],
    'email' => [
        'email', 'emailaddress', 'mail', 'emailid', 'contactemail',
    ],
    'city' => [
        'city', 'town', 'municipality',
    ],
    'st' => [
        'st', 'state', 'stateprovince', 'province', 'region',
    ],
    'zip' => [
        'zip', 'zipcode', 'postal', 'postalcode', 'postcode',
    ],
    'resort_location' => [
        'resortlocation', 'location', 'resortcity', 'resortcitystate',
        'resortaddress', 'propertylocation',
    ],
    // Combined helper: County/State "ORANGE, FL" → city + st
    'countystate' => [
        'countystate', 'county_state', 'countyandstate', 'countystatecombo',
    ],
];
