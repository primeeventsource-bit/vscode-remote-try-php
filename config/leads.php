<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Bulk action cap
    |--------------------------------------------------------------------------
    |
    | Maximum number of leads a single bulk action (assign / disposition /
    | export / delete / round-robin distribute) can affect when the user picks
    | "select all matching filter". Prevents a runaway query from chewing
    | through millions of rows. Override per env via LEADS_BULK_ACTION_CAP.
    |
    */
    'bulk_action_cap' => (int) env('LEADS_BULK_ACTION_CAP', 10000),
];
