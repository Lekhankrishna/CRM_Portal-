<?php
// Shared tool registry for Locate Me (locateme.services) - mirrors
// Gas/lpg_web/locate_tools.py's TOOL_REGISTRY (label + input placeholder),
// used by both locate_me.php (tool picker UI) and locate_me_api.php
// (server-side allowlist) so the two can never drift out of sync with each
// other. whatsapp-dp is a known exception - it returns an image, not
// label/value fields, so it's left in for completeness but its result will
// just show as raw page text (see locate_tools.py's module docstring).
//
// rc-print and hp-gas-advanced (2026-08-17, per explicit instruction) are
// folded in here as tabs too, replacing the standalone RC Print/HP LPG
// Search sidebar pages - but each keeps its OWN pre-existing access flag
// and monthly-limit quota ('requiresAccess' below), rather than falling
// under locate_me_access's quota. Both were already separately granted per
// agent (Admin > Agents > "RC Print"/"HP LPG Search") before this change,
// and both spend far more credits per search (150 each) than a typical
// Locate Me tool - folding them into the shared locate_me_monthly_limit
// would either strip existing agents of access they already have, or let
// every Locate Me user suddenly burn through RC Print/HP Gas's expensive
// per-search budget. locate_me.php only shows these two tabs to agents who
// already have the specific matching access; locate_me_api.php enforces
// the same specific access + quota server-side, same as their old
// standalone pages did.
const LOCATEME_TOOLS = [
    'mobile-info'             => ['label' => 'Mobile Info',            'placeholder' => 'Enter Mobile Number'],
    'rc-print'                => ['label' => 'RC PRINT',               'placeholder' => 'Enter Vehicle Number', 'requiresAccess' => 'rc_print'],
    'hp-gas-advanced'         => ['label' => 'HP Gas Advanced',        'placeholder' => 'Enter Mobile Number',  'requiresAccess' => 'hp_gas'],
    'vehicle-info'            => ['label' => 'Vehicle Intelligence',   'placeholder' => 'Enter Vehicle Number'],
    'aadhaar-info'            => ['label' => 'Aadhaar Info',           'placeholder' => 'Enter Aadhaar Number'],
    'sms-header-decode'       => ['label' => 'SMS Header Decode',      'placeholder' => 'e.g. SGILTD'],
    'imei-info'               => ['label' => 'IMEI Info',              'placeholder' => 'Enter 15-digit IMEI'],
    'aadhaar-to-ration'       => ['label' => 'Aadhaar to Ration',      'placeholder' => 'Enter Aadhaar Number'],
    'number-to-name'          => ['label' => 'Number to Name',         'placeholder' => 'Enter Mobile Number'],
    'number-to-facebook'      => ['label' => 'Number to Facebook',     'placeholder' => 'Enter Mobile Number'],
    'whatsapp-dp'             => ['label' => 'WhatsApp DP Downloader', 'placeholder' => 'Enter Mobile Number'],
    'aadhaar-to-pan'          => ['label' => 'Aadhaar to PAN',         'placeholder' => 'Enter Aadhaar Number'],
    'pan-to-gst'              => ['label' => 'PAN to GST',             'placeholder' => 'e.g. ARCPV7418G'],
    'vehicle-to-number'       => ['label' => 'Vehicle to Number',      'placeholder' => 'e.g. UP70HQ2225'],
    'indane-gas-info'         => ['label' => 'Indane Gas Info',        'placeholder' => 'Enter 10-digit Number'],
    'indane-gas-verification' => ['label' => 'Indane Gas v2',          'placeholder' => 'Enter Mobile Number'],
    'bharat-gas-info'         => ['label' => 'Bharat Gas Info',        'placeholder' => 'Enter Number'],
    'gmail-info'              => ['label' => 'Gmail Info',             'placeholder' => 'example@gmail.com'],
    'pan-info'                => ['label' => 'PAN Info',               'placeholder' => 'Enter PAN Number'],
    'vehicle-fastag'          => ['label' => 'Vehicle Fastag',         'placeholder' => 'e.g. DL10C1234'],
    'upi-finder'              => ['label' => 'UPI Finder',             'placeholder' => 'e.g. 7982966659'],
    'gst-info'                => ['label' => 'GST Info',               'placeholder' => 'e.g. 09AAKCD6139J1Z8'],
    'ifsc-info'               => ['label' => 'IFSC Info',              'placeholder' => 'e.g. SBIN0005383'],
    'ip-info'                 => ['label' => 'IP Info',                'placeholder' => 'e.g. 8.8.8.8'],
    'email-leak-check'        => ['label' => 'Email Leak Check',       'placeholder' => 'user@example.com'],
    'sim-carrier-checker'     => ['label' => 'SIM Carrier Checker',    'placeholder' => 'Enter Mobile Number'],
];
