<?php
// Shared tool registry for Locate Me (locateme.services) - mirrors
// Gas/lpg_web/locate_tools.py's TOOL_REGISTRY (label + input placeholder),
// used by both locate_me.php (tool picker UI) and locate_me_api.php
// (server-side allowlist) so the two can never drift out of sync with each
// other. whatsapp-dp is a known exception - it returns an image, not
// label/value fields, so it's left in for completeness but its result will
// just show as raw page text (see locate_tools.py's module docstring).
const LOCATEME_TOOLS = [
    'mobile-info'             => ['label' => 'Mobile Info',            'placeholder' => 'Enter Mobile Number'],
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
