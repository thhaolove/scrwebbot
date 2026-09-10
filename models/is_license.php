<?php
if (!defined('IN_SITE')) {
    die('The Request Not Found');
} 
 
if (!class_exists('SecurityValidator')) {
    require_once __DIR__ . '/../libs/session.php';
}

SecurityValidator::enforce('__CORE__', 'LICENSE:bootstrap');

$CMSNT = new DB();

/**
 * Đảm bảo session đã được khởi tạo.
 */
if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
    session_start();
}

/**
 * Đọc dữ liệu cache kiểm tra license từ session.
 */
function loadLicenseCache()
{
    if (!isset($_SESSION) || !is_array($_SESSION)) {
        return null;
    }

    return isset($_SESSION['__license_cache']) && is_array($_SESSION['__license_cache'])
        ? $_SESSION['__license_cache']
        : null;
}

/**
 * Lưu dữ liệu cache kiểm tra license vào session.
 */
function saveLicenseCache($licenseKey, array $rawResult, $checkedAt = null)
{
    if (!isset($_SESSION) || !is_array($_SESSION)) {
        return;
    }

    $checkedAt = $checkedAt ?: time();

    $payload = [
        'license_key' => (string)$licenseKey,
        'checked_at'  => (int)$checkedAt,
        'result'      => $rawResult,
    ];

    $_SESSION['__license_cache'] = $payload;
}

/**
 * Hàm kiểm tra giấy phép kích hoạt.
 */
function v3pX9sLic($licensekey)
{
    $domainWhiteList = [
        // Ví dụ: 'localhost', 'yourdomain.com'
    ];

    $domain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
    if ($domain !== '' && in_array($domain, $domainWhiteList, true)) {
        return [
            'msg'    => '',
            'status' => true,
        ];
    }

    $cacheData        = loadLicenseCache();
    $cachedLicenseKey = $cacheData['license_key'] ?? null;
    $cachedCheckedAt  = isset($cacheData['checked_at']) ? (int)$cacheData['checked_at'] : 0;
    $cachedResult     = isset($cacheData['result']) && is_array($cacheData['result']) ? $cacheData['result'] : null;
    $cacheTtl         = 60 * 60; // 60 phút
    $now              = time();

    $needsFreshCheck = true;
    $normalizedLicense = (string)$licensekey;

    if ($cachedResult !== null && $cachedLicenseKey === $normalizedLicense) {
        if (($now - $cachedCheckedAt) < $cacheTtl) {
            $needsFreshCheck = false;
        }
    }

    if ($needsFreshCheck) {
        $results = u2dK7mToken($licensekey, '');

        if (isset($results['status']) && $results['status'] === 'Active') {
            saveLicenseCache($normalizedLicense, $results, $now);
        } else {
            if (isset($_SESSION) && isset($_SESSION['__license_cache'])) {
                unset($_SESSION['__license_cache']);
            }
        }
    } else {
        $results = $cachedResult;
    }

    $statusMessages = [
        'Active'          => ['Kích hoạt giấy phép thành công!', true],
        'Invalid'         => ['Giấy phép kích hoạt không hợp lệ', false],
        'Expired'         => ['Giấy phép đã hết hạn, vui lòng gia hạn ngay', false],
        'Suspended'       => ['Giấy phép của bạn đã bị tạm ngưng', false],
        'timeout'         => ['Yêu cầu kiểm tra giấy phép đã hết thời gian chờ', true],
        'ConnectionError' => ['Không thể kết nối tới máy chủ cấp phép. Vui lòng thử lại sau.', false],
        'ServerError'     => ['Máy chủ cấp phép phản hồi không hợp lệ. Vui lòng thao tác lại sau.', false],
    ];

    if (isset($statusMessages[$results['status']])) {
        list($results['msg'], $results['status']) = $statusMessages[$results['status']];
    } else {
        $results['msg'] = '';
        $results['status'] = true;
    }

    if (!empty($results['error_detail'])) {
        $results['msg'] = trim($results['msg'] . ' (' . $results['error_detail'] . ')');
    }

    return $results;
}

/**
 * Hàm kiểm tra giấy phép CMSNT thật.
 */
function u2dK7mToken($licensekey, $localkey = '')
{
    global $config;
    $whmcsUrl            = 'https://client.cmsnt.co/';
    $verifyFilePath      = 'modules/servers/licensing/verify.php';
    $licensingSecretKey  = $config['project'];
    $localKeyDays        = 15;
    $allowCheckFailDays  = 5;
    $checkToken          = time() . md5(mt_rand(100000000, mt_getrandmax()) . $licensekey);
    $checkDate           = date("Ymd");
    $domain              = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '';
    $userIp              = isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : (isset($_SERVER['LOCAL_ADDR']) ? $_SERVER['LOCAL_ADDR'] : '');
    $dirPath             = dirname(__FILE__);
    $localKeyValid       = false;
    $localKeyResults     = [];
    $originalCheckDate   = null;

    if (!empty($localkey)) {
        $localkey = str_replace("\n", '', $localkey);
        $localData = substr($localkey, 0, strlen($localkey) - 32);
        $localMd5  = substr($localkey, strlen($localkey) - 32);

        if ($localMd5 === md5($localData . $licensingSecretKey)) {
            $localData          = strrev($localData);
            $localDataMd5       = substr($localData, 0, 32);
            $localData          = substr($localData, 32);
            $localData          = base64_decode($localData);
            $localKeyResults    = json_decode($localData, true);
            $originalCheckDate  = isset($localKeyResults['checkdate']) ? $localKeyResults['checkdate'] : null;

            if ($localDataMd5 === md5($originalCheckDate . $licensingSecretKey)) {
                $localExpiry = date("Ymd", mktime(0, 0, 0, date("m"), date("d") - $localKeyDays, date("Y")));

                if ($originalCheckDate > $localExpiry) {
                    $localKeyValid = true;
                    $results       = $localKeyResults;

                    $validDomains = isset($results['validdomain']) ? explode(',', $results['validdomain']) : [];
                    if (!empty($domain) && !in_array($domain, $validDomains, true)) {
                        $localKeyValid = false;
                        $results       = [];
                    }

                    $validIps = isset($results['validip']) ? explode(',', $results['validip']) : [];
                    if (!empty($userIp) && !in_array($userIp, $validIps, true)) {
                        $localKeyValid = false;
                        $results       = [];
                    }

                    $validDirs = isset($results['validdirectory']) ? explode(',', $results['validdirectory']) : [];
                    if (!in_array($dirPath, $validDirs, true)) {
                        $localKeyValid = false;
                        $results       = [];
                    }
                }
            }
        }
    }

    if ($localKeyValid) {
        return $results;
    }

    $postFields = [
        'licensekey' => $licensekey,
        'domain'     => $domain,
        'ip'         => $userIp,
        'dir'        => $dirPath,
    ];

    if (!empty($checkToken)) {
        $postFields['check_token'] = $checkToken;
    }

    $queryString  = http_build_query($postFields);
    $responseCode = 0;
    $rawResponse  = '';
    $errorDetail  = '';

    if (function_exists('curl_exec')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $whmcsUrl . $verifyFilePath);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $queryString);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        $rawResponse = curl_exec($ch);

        if ($rawResponse === false) {
            $errorDetail = 'cURL error: ' . curl_error($ch);
        } else {
            $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        }
        curl_close($ch);
    } else {
        $responseCodePattern = '/^HTTP\/\d+\.\d+\s+(\d+)/';
        $fp = @fsockopen(parse_url($whmcsUrl, PHP_URL_HOST), 80, $errno, $errstr, 10);
        if (!$fp) {
            $errorDetail = "Socket error: {$errno} - {$errstr}";
        } else {
            $newlineFeed = "\r\n";
            $path        = parse_url($whmcsUrl, PHP_URL_PATH);
            $path        = rtrim($path, '/') . '/' . $verifyFilePath;

            $header  = "POST {$path} HTTP/1.0{$newlineFeed}";
            $header .= "Host: " . parse_url($whmcsUrl, PHP_URL_HOST) . $newlineFeed;
            $header .= "Content-type: application/x-www-form-urlencoded" . $newlineFeed;
            $header .= "Content-length: " . strlen($queryString) . $newlineFeed;
            $header .= "Connection: close{$newlineFeed}{$newlineFeed}";
            $header .= $queryString;

            $data = '';
            @stream_set_timeout($fp, 20);
            @fputs($fp, $header);
            $status = @socket_get_status($fp);

            while (!@feof($fp) && $status) {
                $line = @fgets($fp, 1024);

                if (!$responseCode && preg_match($responseCodePattern, trim($line), $matches)) {
                    $responseCode = !empty($matches[1]) ? (int)$matches[1] : 0;
                }

                $data .= $line;
                $status = @socket_get_status($fp);
            }

            @fclose($fp);
            $rawResponse = $data;
        }
    }

    if ($rawResponse === false || $rawResponse === '') {
        return [
            'status'       => 'ConnectionError',
            'error_detail' => $errorDetail !== '' ? $errorDetail : 'Không nhận được phản hồi từ máy chủ.'
        ];
    }

    if ($responseCode && $responseCode !== 200) {
        return [
            'status'       => 'ServerError',
            'error_detail' => 'HTTP ' . $responseCode
        ];
    }

    $parsedResponse = [];
    preg_match_all('/<(.*?)>([^<]+)<\/\\1>/i', $rawResponse, $matches);
    foreach ($matches[1] as $key => $value) {
        $parsedResponse[$value] = $matches[2][$key];
    }

    if (!is_array($parsedResponse) || empty($parsedResponse)) {
        return [
            'status'       => 'ServerError',
            'error_detail' => 'Dữ liệu phản hồi không hợp lệ'
        ];
    }

    if (isset($parsedResponse['md5hash'])) {
        if ($parsedResponse['md5hash'] !== md5($licensingSecretKey . $checkToken)) {
            return [
                'status'       => 'Invalid',
                'description'  => 'MD5 Checksum Verification Failed',
                'error_detail' => 'Chữ ký phản hồi không trùng khớp'
            ];
        }
    }

    if (isset($parsedResponse['status']) && $parsedResponse['status'] === 'Active') {
        $parsedResponse['checkdate'] = $checkDate;

        $dataEncoded = json_encode($parsedResponse);
        $dataEncoded = base64_encode($dataEncoded);
        $dataEncoded = md5($checkDate . $licensingSecretKey) . $dataEncoded;
        $dataEncoded = strrev($dataEncoded);
        $dataEncoded = $dataEncoded . md5($dataEncoded . $licensingSecretKey);
        $dataEncoded = wordwrap($dataEncoded, 80, "\n", true);

        $parsedResponse['localkey'] = $dataEncoded;
    }

$parsedResponse['remotecheck'] = true;

    return $parsedResponse;
}


$licenseCheck = v3pX9sLic($CMSNT->site('license_key'));

if($CMSNT->site('license_key') == '' || $licenseCheck['status'] != true){
    $licenseMessage = '';
    $licenseMessageType = 'danger';

    if ($licenseCheck['status'] != true && !empty($licenseCheck['msg'])) {
        $licenseMessage = $licenseCheck['msg'];
    }

    if (isset($_POST['btnSaveLicense'])) {
        if ($CMSNT->site('status_demo') != 0) {
            die('<script type="text/javascript">if(!alert("Không được dùng chức năng này vì đây là trang web demo.")){window.history.back().location.reload();}</script>');
        }
        foreach ($_POST as $key => $value) {
            $setting_name = validate_alphanumeric($key);
            $setting_value = validate_string($value, 500);
            if ($setting_name !== false && $setting_value !== false) {
                $CMSNT->update("settings", array(
                    'value' => $setting_value
                ), " `name` = ? ", [$setting_name]);
            }
        }
        // Xoá cache session để buộc kiểm tra lại ngay lập tức
        if (isset($_SESSION) && isset($_SESSION['__license_cache'])) {
            unset($_SESSION['__license_cache']);
        }
        $licenseCheck = v3pX9sLic($CMSNT->site('license_key'));
        if($licenseCheck['status'] != true){
            $licenseMessage = $licenseCheck['msg'];
            $licenseMessageType = 'danger';
        } else {
            $currentUrl = base_url_admin('home');
            if (!headers_sent()) {
                redirect($currentUrl);
            }
            echo '<script type="text/javascript">window.location.href = '.json_encode($currentUrl, JSON_UNESCAPED_SLASHES).';</script>';
            exit();
        }
    } ?>

<div class="main-content app-content">
    <div class="container-fluid">
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0">License</h1>
            <div class="ms-md-1 ms-0">

            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                <div class="card custom-card">
                    <div class="card-header justify-content-between">
                        <h3 class="card-title">THÔNG TIN BẢN QUYỀN CODE</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($licenseMessage)): ?>
                            <div class="alert alert-<?=$licenseMessageType == 'success' ? 'success' : 'danger';?> mb-3" role="alert">
                                <?=htmlspecialchars($licenseMessage);?>
                            </div>
                        <?php endif; ?>
                        <form action="" method="POST">
                            <div class="form-group row mb-3">
                                <label class="col-sm-4 col-form-label">Mã bản quyền (license key)</label>
                                <div class="col-sm-8">
                                    <div class="form-line">
                                        <input type="text" name="license_key"
                                            placeholder="Nhập mã bản quyền của bạn để sử dụng chức năng này"
                                            value="<?=$CMSNT->site('license_key');?>" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                            <center>
                                <button type="submit" name="btnSaveLicense" class="btn btn-primary btn-block">
                                    <span>Save</span></button>
                            </center>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card custom-card">
                    <div class="card-header justify-content-between">
                        <h3 class="card-title">HƯỚNG DẪN</h3>
                    </div>
                    <div class="card-body">
                        <p>Quý khách có thể lấy License key tại đây: <a target="_blank"
                                href="https://client.cmsnt.co/clientarea.php?action=products&module=licensing">https://client.cmsnt.co/clientarea.php?action=products&module=licensing</a>
                        </p>
                        <p>Chỉ áp dúng cho những ai mua chính hãng, không hỗ trợ những trường hợp mua lại hay sử dụng mã nguồn
                            lậu.</p>
                        <p>Nếu bạn chưa mua code tại CMSNT.CO, bạn có thể mua giấy phép tại đây: <a target="_blank"
                                href="https://www.cmsnt.co/">CLIENT
                                CMSNT</a></p>
                                <p>Việc mua chính hãng sẽ giúp website bạn uy tín hơn trong mắt khách hàng và đối tác.</p>
                        <img src="https://i.imgur.com/VzDVIx0.png" width="100%">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<?php 
    require_once(__DIR__."/../views/admin/footer.php");
?>
<?php die(); }  ?>

<?php
if (!function_exists('checkLicenseKey')) {
    function checkLicenseKey($licensekey)
    {
        return v3pX9sLic($licensekey);
    }
}

if (!function_exists('CMSNT_check_license')) {
    function CMSNT_check_license($licensekey, $localkey = '')
    {
        return u2dK7mToken($licensekey, $localkey);
    }
}
 