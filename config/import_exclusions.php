<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Excluded Product Codes for Daily Sales Import
    |--------------------------------------------------------------------------
    |
    | Any product code listed here will be skipped when importing a daily
    | sales CSV. Useful for housekeeping items (drinks, snacks, non-medicine
    | items) that should never enter the medicines / daily_sales tables.
    |
    | Matching is case-insensitive and ignores surrounding whitespace.
    |
    */
    'product_codes' => [
        'ML0194',    // Coke 1.5L
        '0101035',   // coke 350ml
        '0347',      // Omore Stick 50rs
        'W192183',   // Candy
        'H000186',   // Coke half
        '12',        // Coke 2L
        '995906', //oreo
        '998979', //lalaei hit cake
        '0328', //omore cone 100rs
        '227084', //omore stick
        '888145', //milo
        'ML0338', //olpers
        '086312', //dasani 1.5
        'ML0690', //lays 30
        '880007', //lays 70
        '086316', //dasani 500
        '998711', //lemon sandwich
        '998710', //prince 
        '0342', //omore cup 100
        'W192177', //spark chocolate
        'W1922634', //nesfruita
        '078', //candy
        '770218', //kitchen cuisine peanuts
        '0335', //omore bar
        '883912', //fatta chatt
        '880099', //cadbury biscuit
        '226987', //gluco biscuit
        '884757', // sooper
        'w192201', //toshiba cell
        'ML0357', // care baby
        'H000182', //pulpy ornage
        '990976', //khatai
        '101038', //breakfast cake
        '885488', //imli
        '883168', //nimko
        '887199', //kitchen cuision chips
        '884750', //butter puff
        'ML0689', //lays 50
        

    ],
];