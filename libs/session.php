<?php
if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

class SecurityValidator
{
    private static $coreSignatureValue = 'MjkyZjg2NTUzMTI4NWRmNGMxYjQzMjkwMmIyYmYwNWY=';
    private static $fp = 'bW9kZWxzL2lzX2xpY2Vuc2UucGhw';
    private static $initialized = false;
    private static $signatureCache = null;
    private static $tamperEndpointCache = null;
    private static $tamperReported = false;
    private static $configResolved = false;
    private static $lastTargetCandidate = null;
    private static $lastValidatedTarget = null;

    public static function init()
    {
        if (self::$initialized) {
            return;
        }

        self::$initialized = true;
        self::validateTarget(self::corePath(), 'CORE:init');
    }

    public static function enforce($path, $context = 'CORE:enforce')
    {
        self::init();
        self::validateTarget($path, $context);
    }

    public static function enforceSignature($base64Signature, $path, $context = 'CORE:enforce')
    {
        self::init();
        $expected = self::normalizedSignature();
        if (!hash_equals($expected, (string)$base64Signature)) {
            self::renderError('SIG-MISMATCH', 'Signature mismatch at ' . $context);
        }
        self::validateTarget($path, $context);
    }

    public static function panic($code, $context)
    {
        self::renderError($code, $context);
    }

    private static function normalizedSignature()
    {
        if (self::$signatureCache !== null) {
            return self::$signatureCache;
        }

        $signature = trim(self::$coreSignatureValue);
        if (strlen($signature) !== 44) {
            self::renderError('SIG-LEN', 'Signature length invalid');
        }

        self::$signatureCache = $signature;
        return self::$signatureCache;
    }

    private static function expectedHexDigest()
    {
        $decoded = base64_decode(self::normalizedSignature(), true);
        if ($decoded === false || strlen($decoded) !== 32) {
            self::renderError('SIG-DECODE', 'Signature decode failed');
        }

        return $decoded;
    }

    private static function corePath()
    {
        return __DIR__ . '/../' . base64_decode(self::$fp);
    }

    private static function resolveRealPath($path)
    {
        $candidate = $path === '__CORE__' ? self::corePath() : $path;
        self::$lastTargetCandidate = $candidate;
        $real = realpath($candidate);
        if ($real === false) {
            return null;
        }

        return $real;
    }

    private static function validateTarget($path, $context)
    {
        $targetPath = self::resolveRealPath($path);
        self::$lastValidatedTarget = $targetPath !== null ? $targetPath : self::$lastTargetCandidate;
        if ($targetPath === null) {
            self::renderError('FILE-NOTFOUND', 'Missing target: ' . $context);
        }

        if (!is_readable($targetPath)) {
            self::renderError('FILE-UNREAD', 'Unreadable target: ' . $context);
        }

        $hash = @md5_file($targetPath);
        if ($hash === false) {
            self::renderError('HASH-FAIL', 'Unable to hash target: ' . $context);
        }

        $expectedHex = self::expectedHexDigest();
        if (!hash_equals($expectedHex, $hash)) {
            self::renderError('HASH-MISMATCH', 'Signature verification failed: ' . $context);
        }

        $size = @filesize($targetPath);
        if ($size === false) {
            self::renderError('FILE-SIZE', 'Unable to read size: ' . $context);
        }

        $fingerprint = substr(hash('sha256', $hash . $size), 8, 24);
        $baseline = substr(hash('sha256', $expectedHex . $size), 8, 24);
        if (!hash_equals($baseline, $fingerprint)) {
            self::renderError('FINGERPRINT', 'Fingerprint mismatch: ' . $context);
        }

        if ((mt_rand(1, 10) % 3) === 0) {
            self::deepProbe($targetPath, $hash, $context);
        }
    }

    private static function deepProbe($path, $hash, $context)
    {
        $handle = @fopen($path, 'rb');
        if (!$handle) {
            self::renderError('PROBE-OPEN', 'Cannot open target: ' . $context);
        }

        $chunk = @fread($handle, 256);
        @fclose($handle);

        if ($chunk === false || $chunk === '') {
            self::renderError('PROBE-READ', 'Cannot read target: ' . $context);
        }

        $expectedHex = self::expectedHexDigest();
        $reference = substr(hash('sha1', $chunk . $expectedHex), 6, 24);
        $candidate = substr(hash('sha1', $chunk . $hash), 6, 24);
        if (!hash_equals($reference, $candidate)) {
            self::renderError('PROBE-MISMATCH', 'Probe mismatch: ' . $context);
        }
    }

    private static function reportTamper($code, $context)
    {
        if (self::$tamperReported) {
            return;
        }

        if (!self::isLicenseTargetContext($context)) {
            return;
        }

        $endpoint = self::getTamperEndpoint();
        if ($endpoint === '') {
            return;
        }

        self::$tamperReported = true;

        $targetPath = is_string(self::$lastValidatedTarget) ? self::$lastValidatedTarget : '';
        $payload = [
            'code'        => (string)$code,
            'context'     => (string)$context,
            'file'        => $targetPath,
            'file_md5'    => ($targetPath && is_file($targetPath)) ? (@md5_file($targetPath) ?: '') : '',
            'domain'      => $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? ''),
            'server_ip'   => $_SERVER['SERVER_ADDR'] ?? '',
            'client_ip'   => $_SERVER['REMOTE_ADDR'] ?? '',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'referer'     => $_SERVER['HTTP_REFERER'] ?? '',
            'php_version' => PHP_VERSION,
            'timestamp'   => gmdate('c'),
        ];

        $signature = self::buildTamperSignature($payload);
        if ($signature !== '') {
            $payload['signature'] = $signature;
        }

        self::dispatchTamperReport($endpoint, $payload);
    }

    private static function isLicenseTargetContext($context)
    {
        $path = self::$lastValidatedTarget;
        if (is_string($path) && substr($path, -strlen('is_license.php')) === 'is_license.php') {
            return true;
        }

        return strpos((string)$context, 'LICENSE') !== false;
    }

    private static function getTamperEndpoint()
    {
        if (self::$tamperEndpointCache !== null) {
            return self::$tamperEndpointCache;
        }

        self::ensureConfigLoaded();

        $endpoint = '';
        global $config;
        if (isset($config) && is_array($config) && !empty($config['tamper_report_url'])) {
            $endpoint = trim((string)$config['tamper_report_url']);
        }

        if ($endpoint === '' && getenv('LICENSE_TAMPER_ENDPOINT')) {
            $endpoint = trim((string)getenv('LICENSE_TAMPER_ENDPOINT'));
        }

        self::$tamperEndpointCache = $endpoint;
        return self::$tamperEndpointCache;
    }

    private static function ensureConfigLoaded()
    {
        if (self::$configResolved) {
            return;
        }

        global $config;
        if (!isset($config) || !is_array($config)) {
            $configPath = __DIR__ . '/../config.php';
            if (is_file($configPath)) {
                include_once $configPath;
            }
        }

        self::$configResolved = true;
    }

    private static function buildTamperSignature(array $payload)
    {
        self::ensureConfigLoaded();

        $secret = '';
        global $config;
        if (isset($config) && is_array($config) && !empty($config['project'])) {
            $secret = (string)$config['project'];
        }

        if ($secret === '') {
            return '';
        }

        $data = ($payload['domain'] ?? '') . '|' .
            ($payload['client_ip'] ?? '') . '|' .
            ($payload['timestamp'] ?? '') . '|' .
            ($payload['code'] ?? '') . '|' .
            ($payload['context'] ?? '');

        return hash_hmac('sha256', $data, $secret);
    }

    private static function dispatchTamperReport($endpoint, array $payload)
    {
        $body = json_encode($payload);
        if ($body === false) {
            $body = '{}';
        }

        if (function_exists('curl_init')) {
            $ch = curl_init();
            if ($ch) {
                @curl_setopt($ch, CURLOPT_URL, $endpoint);
                @curl_setopt($ch, CURLOPT_POST, true);
                @curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                @curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($body),
                ]);
                @curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                @curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
                @curl_setopt($ch, CURLOPT_TIMEOUT, 4);
                @curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                @curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                @curl_exec($ch);
                @curl_close($ch);
                return;
            }
        }

        $context = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n",
                'content' => $body,
                'timeout' => 4,
            ],
            'ssl' => [
                'verify_peer'      => false,
                'verify_peer_name' => false,
            ],
        ]);

        @file_get_contents($endpoint, false, $context);
    }

    private static function renderError($code, $context)
    {
        self::reportTamper($code, $context);

        $safeCode = htmlspecialchars((string)$code, ENT_QUOTES, 'UTF-8');
        $safeContext = htmlspecialchars((string)$context, ENT_QUOTES, 'UTF-8');

        $lt = chr(60);
        $gt = chr(62);

        $html = '';
        $html .= $lt . '!DOCTYPE html' . $gt;
        $html .= $lt . 'html lang="vi"' . $gt;
        $html .= $lt . 'head' . $gt;
        $html .= $lt . 'meta charset="utf-8"' . $gt;
        $html .= $lt . 'title' . $gt . 'License Alert' . $lt . '/title' . $gt;
        $html .= $lt . 'style' . $gt;
        $html .= 'body{margin:0;background:#0f172a;color:#e2e8f0;font-family:monospace;display:flex;align-items:center;justify-content:center;height:100vh;}';
        $html .= '.wrap{max-width:460px;padding:28px;border-radius:16px;background:rgba(15,23,42,0.92);box-shadow:0 20px 45px rgba(15,23,42,0.35);border:1px solid rgba(148,163,184,0.35);}';
        $html .= '.tag{display:inline-block;padding:4px 12px;border-radius:999px;background:#b91c1c;color:#fee2e2;text-transform:uppercase;font-size:12px;letter-spacing:.14em;}';
        $html .= 'h1{margin:18px 0 12px 0;font-size:20px;color:#f8fafc;}';
        $html .= 'p{margin:0;font-size:14px;line-height:1.7;color:#cbd5f5;}';
        $html .= '.ctx{margin-top:18px;font-size:13px;color:#94a3b8;word-break:break-word;}';
        $html .= '.foot{margin-top:24px;font-size:11px;text-transform:uppercase;letter-spacing:.18em;color:#64748b;}';
        $html .= $lt . '/style' . $gt;
        $html .= $lt . '/head' . $gt;
        $html .= $lt . 'body' . $gt;
        $html .= $lt . 'div class="wrap"' . $gt;
        $html .= $lt . 'span class="tag"' . $gt . $safeCode . $lt . '/span' . $gt;
        $html .= $lt . 'h1' . $gt . 'Giấy phép'.' không hợp lệ' . $lt . '/h1' . $gt;
        $html .= $lt . 'p' . $gt . 'Hệ thống phát'.' hiện tập tin quan'.' trọng đã bị thay đổi'.' hoặc bị thiếu.' . $lt . '/p' . $gt;
        $html .= $lt . 'div class="ctx"' . $gt . str_replace("\n", $lt . 'br' . $gt, $safeContext) . $lt . '/div' . $gt;
        $html .= $lt . 'div class="foot"' . $gt . 'cmsnt.co'.' security'.' layer' . $lt . '/div' . $gt;
        $html .= $lt . '/div' . $gt;
        $html .= $lt . '/body' . $gt;
        $html .= $lt . '/html' . $gt;

        echo $html;
        exit;
    }
}

SecurityValidator::init();

