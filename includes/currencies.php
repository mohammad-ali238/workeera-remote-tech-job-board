<?php

defined('ABSPATH') || exit; // Exit if accessed directly

/**
 * Registry of supported currencies indexed by ISO 4217 code.
 *
 * Each entry exposes the HTML-entity `symbol` and human-readable `name` used
 * by the formatted-salary helpers throughout the plugin.
 */
global $workeera_currencies_list;

$workeera_currencies_list = [
    'AED' => [
        'symbol' => '&#x62f;.&#x625;',
        'name' => 'UAE dirham',
    ],
    'AUD' => [
        'symbol' => '&#036;',
        'name' => 'Australian dollar',
    ],
    'BDT' => [
        'symbol' => '&#2547;&nbsp;',
        'name' => 'Bangladeshi taka',
    ],
    'BRL' => [
        'symbol' => '&#082;&#036;',
        'name' => 'Brazilian real',
    ],
    'BTC' => [
        'symbol' => '&#3647;',
        'name' => 'Bitcoin',
    ],
    'CAD' => [
        'symbol' => '&#036;',
        'name' => 'Canadian dollar',
    ],
    'CHF' => [
        'symbol' => '&#067;&#072;&#070;',
        'name' => 'Swiss franc',
    ],
    'CNY' => [
        'symbol' => '&yen;',
        'name' => 'Chinese yuan',
    ],
    'DKK' => [
        'symbol' => 'kr.',
        'name' => 'Danish krone',
    ],
    'EGP' => [
        'symbol' => 'EGP',
        'name' => 'Egyptian pound',
    ],
    'EUR' => [
        'symbol' => '&euro;',
        'name' => 'Euro',
    ],
    'GBP' => [
        'symbol' => '&pound;',
        'name' => 'Pound sterling',
    ],
    'HKD' => [
        'symbol' => '&#036;',
        'name' => 'Hong Kong dollar',
    ],
    'IDR' => [
        'symbol' => 'Rp',
        'name' => 'Indonesian rupiah',
    ],
    'ILS' => [
        'symbol' => '&#8362;',
        'name' => 'Israeli new shekel',
    ],
    'INR' => [
        'symbol' => '&#8377;',
        'name' => 'Indian rupee',
    ],
    'IQD' => [
        'symbol' => '&#x62f;.&#x639;',
        'name' => 'Iraqi dinar',
    ],
    'IRR' => [
        'symbol' => '&#xfdfc;',
        'name' => 'Iranian rial',
    ],
    'JPY' => [
        'symbol' => '&yen;',
        'name' => 'Japanese yen',
    ],
    'KRW' => [
        'symbol' => '&#8361;',
        'name' => 'South Korean won',
    ],
    'KWD' => [
        'symbol' => '&#x62f;.&#x643;',
        'name' => 'Kuwaiti dinar',
    ],
    'LBP' => [
        'symbol' => '&#x644;.&#x644;',
        'name' => 'Lebanese pound',
    ],
    'LKR' => [
        'symbol' => '&#xdbb;&#xdd4;',
        'name' => 'Sri Lankan rupee',
    ],
    'MXN' => [
        'symbol' => '&#036;',
        'name' => 'Mexican peso',
    ],
    'MYR' => [
        'symbol' => '&#082;&#077;',
        'name' => 'Malaysian ringgit',
    ],
    'NGN' => [
        'symbol' => '&#8358;',
        'name' => 'Nigerian naira',
    ],
    'NOK' => [
        'symbol' => '&#107;&#114;',
        'name' => 'Norwegian krone',
    ],
    'NPR' => [
        'symbol' => '&#8360;',
        'name' => 'Nepalese rupee',
    ],
    'NZD' => [
        'symbol' => '&#036;',
        'name' => 'New Zealand dollar',
    ],
    'OMR' => [
        'symbol' => '&#x631;.&#x639;.',
        'name' => 'Omani rial',
    ],
    'PHP' => [
        'symbol' => '&#8369;',
        'name' => 'Philippine peso',
    ],
    'PKR' => [
        'symbol' => '&#8360;',
        'name' => 'Pakistani rupee',
    ],
    'PLN' => [
        'symbol' => '&#122;&#322;',
        'name' => 'Polish złoty',
    ],
    'QAR' => [
        'symbol' => '&#x631;.&#x642;',
        'name' => 'Qatari riyal',
    ],
    'RUB' => [
        'symbol' => '&#8381;',
        'name' => 'Russian ruble',
    ],
    'SAR' => [
        'symbol' => '&#x631;.&#x633;',
        'name' => 'Saudi riyal',
    ],
    'SEK' => [
        'symbol' => '&#107;&#114;',
        'name' => 'Swedish krona',
    ],
    'SGD' => [
        'symbol' => '&#036;',
        'name' => 'Singapore dollar',
    ],
    'THB' => [
        'symbol' => '&#3647;',
        'name' => 'Thai baht',
    ],
    'TRY' => [
        'symbol' => '&#8378;',
        'name' => 'Turkish lira',
    ],
    'TWD' => [
        'symbol' => '&#078;&#084;&#036;',
        'name' => 'New Taiwan dollar',
    ],
    'UAH' => [
        'symbol' => '&#8372;',
        'name' => 'Ukrainian hryvnia',
    ],
    'USD' => [
        'symbol' => '&#036;',
        'name' => 'United States (US) dollar',
    ],
    'VND' => [
        'symbol' => '&#8363;',
        'name' => 'Vietnamese đồng',
    ],
    'ZAR' => [
        'symbol' => '&#082;',
        'name' => 'South African rand',
    ],
];
