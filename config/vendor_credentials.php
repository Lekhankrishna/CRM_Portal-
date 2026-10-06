<?php
// Real logins for each third-party search tool's shared vendor account -
// gitignored, see vendor_credentials.php.example for the template. Found
// hardcoded directly in includes/eagleeye_client.php,
// includes/pan_india_pro_client.php, and includes/tracekart_client.php;
// moved out here, same reasoning as Gas/lpg_web/config.py for the SDMS
// and LOCATEME credentials.
$EAGLEEYE_USERNAME = 'dhanushkodia@gmail.com';
$EAGLEEYE_PASSWORD = 'Ashwanth@789@5@77';

$PAN_INDIA_PRO_EMAIL    = 'holydesk548@gmail.com';
$PAN_INDIA_PRO_PASSWORD = 'Y9#R1@L6!X3';

$TRACEKART_USERNAME = 'Lucky16';
$TRACEKART_PASSWORD = 'Aug@2026#';

// Nexora API key (includes/nexora_client.php) - powers Aadhaar to Family
// Advanced, Mobile to Address (+ Advanced), and Indian/HP/Bharat Gas
// Advanced. From the Partner Dashboard's "API Credentials" tab
// (nexoraapi.in/dashboard, Partner ID PART-1789817715056).
$NEXORA_API_KEY = '59fea8c44f98116c48ed642ebb13bba439f5e0171270090af4d31be68e4e7bc9';
