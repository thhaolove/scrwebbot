<?php

if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

if (!class_exists('SecurityValidator')) {
    require_once __DIR__ . '/session.php';
}

$google2faLicenseSignatureAnchor = 'MjkyZjg2NTUzMTI4NWRmNGMxYjQzMjkwMmIyYmYwNWY=';
SecurityValidator::enforceSignature(
    $google2faLicenseSignatureAnchor,
    __DIR__ . '/../models/is_license.php',
    'GOOGLE2FA:init'
);

use PragmaRX\Google2FAQRCode\Google2FA;

// tạo mã google 2fa
function generateSecretKey_Google2FA()
{
    $google2fa = new Google2FA();
    return $google2fa->generateSecretKey();
}
