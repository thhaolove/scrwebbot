<?php

if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

if (!class_exists('SecurityValidator')) {
    require_once __DIR__ . '/session.php';
}

$supplierLicenseSignatureAnchor = 'MjkyZjg2NTUzMTI4NWRmNGMxYjQzMjkwMmIyYmYwNWY=';
SecurityValidator::enforceSignature(
    $supplierLicenseSignatureAnchor,
    __DIR__ . '/../models/is_license.php',
    'SUPPLIERS:init'
);

function getInvoiceAPI_33($domain, $token, $trans_id, $proxy = '')
{
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => "{$domain}api/partner/code?invoice_code={$trans_id}",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
            'X-Partner-Token: ' . $token
        ),
    ));

    // Thêm proxy nếu có và hợp lệ
    if (!empty($proxy)) {
        $proxy_parts = explode(':', $proxy);
        if (count($proxy_parts) == 4 && !empty($proxy_parts[0]) && !empty($proxy_parts[1]) && !empty($proxy_parts[2]) && !empty($proxy_parts[3])) {
            curl_setopt($curl, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
            curl_setopt($curl, CURLOPT_PROXYUSERPWD, $proxy_parts[2] . ':' . $proxy_parts[3]);
        } elseif (count($proxy_parts) == 2 && !empty($proxy_parts[0]) && !empty($proxy_parts[1])) {
            curl_setopt($curl, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
        }
    }

    $response = curl_exec($curl);
    curl_close($curl);
    return $response;
}
function buy_API_33($domain, $token, $id_api, $amount, $proxy = '')
{
    $curl = curl_init();

    $postData = json_encode([
        'price_id' => intval($id_api),
        'quantity' => intval($amount)
    ]);

    curl_setopt_array($curl, array(
        CURLOPT_URL => "{$domain}api/partner/invoice",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'X-Partner-Token: ' . $token,
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36'
        ),
    ));

    // Thêm proxy nếu có và hợp lệ
    if (!empty($proxy)) {
        $proxy_parts = explode(':', $proxy);
        if (count($proxy_parts) == 4 && !empty($proxy_parts[0]) && !empty($proxy_parts[1]) && !empty($proxy_parts[2]) && !empty($proxy_parts[3])) {
            curl_setopt($curl, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
            curl_setopt($curl, CURLOPT_PROXYUSERPWD, $proxy_parts[2] . ':' . $proxy_parts[3]);
        } elseif (count($proxy_parts) == 2 && !empty($proxy_parts[0]) && !empty($proxy_parts[1])) {
            curl_setopt($curl, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
        }
    }

    $response = curl_exec($curl);
    curl_close($curl);

    return $response;
}


function listProduct_API_33($domain, $proxy = '')
{
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => "{$domain}api/plan",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36'
        ),
    ));

    // Thêm proxy nếu có và hợp lệ
    if (!empty($proxy)) {
        $proxy_parts = explode(':', $proxy);
        if (count($proxy_parts) == 4 && !empty($proxy_parts[0]) && !empty($proxy_parts[1]) && !empty($proxy_parts[2]) && !empty($proxy_parts[3])) {
            curl_setopt($curl, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
            curl_setopt($curl, CURLOPT_PROXYUSERPWD, $proxy_parts[2] . ':' . $proxy_parts[3]);
        } elseif (count($proxy_parts) == 2 && !empty($proxy_parts[0]) && !empty($proxy_parts[1])) {
            curl_setopt($curl, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
        }
    }

    $response = curl_exec($curl);

    curl_close($curl);
    return $response;
}
function getToken_API_33($domain, $username, $password, $proxy = '')
{
    // Bước 1: Đăng nhập để lấy access_token
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "{$domain}api/user/login",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => "username={$username}&password={$password}",
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/x-www-form-urlencoded'
        ),
    ));

    // Thêm proxy nếu có và hợp lệ
    if (!empty($proxy)) {
        $proxy_parts = explode(':', $proxy);
        if (count($proxy_parts) == 4 && !empty($proxy_parts[0]) && !empty($proxy_parts[1]) && !empty($proxy_parts[2]) && !empty($proxy_parts[3])) {
            curl_setopt($curl, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
            curl_setopt($curl, CURLOPT_PROXYUSERPWD, $proxy_parts[2] . ':' . $proxy_parts[3]);
        } elseif (count($proxy_parts) == 2 && !empty($proxy_parts[0]) && !empty($proxy_parts[1])) {
            curl_setopt($curl, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
        }
    }

    $response = curl_exec($curl);
    curl_close($curl);

    $loginData = json_decode($response, true);
    if (!isset($loginData['access_token'])) {
        return json_encode([
            'code' => '400000',
            'message' => 'Đăng nhập thất bại. Vui lòng kiểm tra lại username và password.',
            'data' => null
        ]);
    }

    $access_token = $loginData['access_token'];

    // Bước 2: Lấy Partner Token
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "{$domain}api/partner/token",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer ' . $access_token
        ),
    ));

    // Thêm proxy nếu có và hợp lệ
    if (!empty($proxy)) {
        $proxy_parts = explode(':', $proxy);
        if (count($proxy_parts) == 4 && !empty($proxy_parts[0]) && !empty($proxy_parts[1]) && !empty($proxy_parts[2]) && !empty($proxy_parts[3])) {
            curl_setopt($curl, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
            curl_setopt($curl, CURLOPT_PROXYUSERPWD, $proxy_parts[2] . ':' . $proxy_parts[3]);
        } elseif (count($proxy_parts) == 2 && !empty($proxy_parts[0]) && !empty($proxy_parts[1])) {
            curl_setopt($curl, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
        }
    }

    $response = curl_exec($curl);
    curl_close($curl);

    $partnerData = json_decode($response, true);
    if (!isset($partnerData['code']) || $partnerData['code'] != '200000') {
        return json_encode([
            'code' => isset($partnerData['code']) ? $partnerData['code'] : '400000',
            'message' => isset($partnerData['message']) ? $partnerData['message'] : 'Không thể lấy partner token',
            'data' => null
        ]);
    }

    $partner_token = $partnerData['data'];

    // Bước 3: Lấy số dư tài khoản
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "{$domain}api/user/balance",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer ' . $access_token
        ),
    ));

    // Thêm proxy nếu có và hợp lệ
    if (!empty($proxy)) {
        $proxy_parts = explode(':', $proxy);
        if (count($proxy_parts) == 4 && !empty($proxy_parts[0]) && !empty($proxy_parts[1]) && !empty($proxy_parts[2]) && !empty($proxy_parts[3])) {
            curl_setopt($curl, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
            curl_setopt($curl, CURLOPT_PROXYUSERPWD, $proxy_parts[2] . ':' . $proxy_parts[3]);
        } elseif (count($proxy_parts) == 2 && !empty($proxy_parts[0]) && !empty($proxy_parts[1])) {
            curl_setopt($curl, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
        }
    }

    $response = curl_exec($curl);
    curl_close($curl);

    $balanceData = json_decode($response, true);
    if (!isset($balanceData['code']) || $balanceData['code'] != '200000') {
        return json_encode([
            'code' => isset($balanceData['code']) ? $balanceData['code'] : '400000',
            'message' => isset($balanceData['message']) ? $balanceData['message'] : 'Không thể lấy số dư tài khoản',
            'data' => null
        ]);
    }

    // Trả về partner token từ data (theo format mà product-api-add.php đang sử dụng)
    // Lưu ý: api/user/balance trả về data trực tiếp là số dư (integer), không phải object
    return json_encode([
        'code' => '200000',
        'message' => 'Success',
        'data' => $partner_token,
        'balance' => isset($balanceData['data']) ? $balanceData['data'] : 0
    ]);
}
function buy_API_32($domain, $api_key, $id_api, $amount, $proxy = '')
{
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "{$domain}api/BuyGmail/BuyProduct?apikey={$api_key}&product_id={$id_api}&quantity={$amount}",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36'
        ),
    ));

    // Thêm proxy nếu có và hợp lệ
    if (!empty($proxy)) {
        $proxy_parts = explode(':', $proxy);
        if (count($proxy_parts) == 4 && !empty($proxy_parts[0]) && !empty($proxy_parts[1]) && !empty($proxy_parts[2]) && !empty($proxy_parts[3])) {
            curl_setopt($curl, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
            curl_setopt($curl, CURLOPT_PROXYUSERPWD, $proxy_parts[2] . ':' . $proxy_parts[3]);
        } elseif (count($proxy_parts) == 2 && !empty($proxy_parts[0]) && !empty($proxy_parts[1])) {
            curl_setopt($curl, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
        }
    }

    $response = curl_exec($curl);
    curl_close($curl);
    return $response;
}
function getStock_API_32($domain, $api_key, $id, $proxy = '')
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "{$domain}api/BuyGmail/GetstockGmail?apikey={$api_key}&id={$id}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36'
    ));
    // Thêm proxy nếu có và hợp lệ
    if (!empty($proxy)) {
        $proxy_parts = explode(':', $proxy);
        if (count($proxy_parts) == 4 && !empty($proxy_parts[0]) && !empty($proxy_parts[1]) && !empty($proxy_parts[2]) && !empty($proxy_parts[3])) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy_parts[2] . ':' . $proxy_parts[3]);
        } elseif (count($proxy_parts) == 2 && !empty($proxy_parts[0]) && !empty($proxy_parts[1])) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
        }
    }
    $data = curl_exec($ch);
    curl_close($ch);

    // Parse response và chỉ return stock
    $response = json_decode($data, true);

    // Return stock number hoặc 0 nếu không có
    if (isset($response['success']) && $response['success'] == true) {
        return intval($response['data']['stock']);
    } else {
        return 0;
    }
}
function listProduct_API_32($domain, $api_key, $proxy = '')
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "{$domain}api/BuyGmail/GetListGmailProduct?apikey={$api_key}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36'
    ));
    // Thêm proxy nếu có và hợp lệ
    if (!empty($proxy)) {
        $proxy_parts = explode(':', $proxy);
        if (count($proxy_parts) == 4 && !empty($proxy_parts[0]) && !empty($proxy_parts[1]) && !empty($proxy_parts[2]) && !empty($proxy_parts[3])) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy_parts[2] . ':' . $proxy_parts[3]);
        } elseif (count($proxy_parts) == 2 && !empty($proxy_parts[0]) && !empty($proxy_parts[1])) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
        }
    }
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}
function balance_API_32($domain, $api_key, $proxy = '')
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "{$domain}api/ApiV2/GetUserInfo?apikey={$api_key}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36'
    ));
    // Thêm proxy nếu có và hợp lệ
    if (!empty($proxy)) {
        $proxy_parts = explode(':', $proxy);
        if (count($proxy_parts) == 4 && !empty($proxy_parts[0]) && !empty($proxy_parts[1]) && !empty($proxy_parts[2]) && !empty($proxy_parts[3])) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy_parts[2] . ':' . $proxy_parts[3]);
        } elseif (count($proxy_parts) == 2 && !empty($proxy_parts[0]) && !empty($proxy_parts[1])) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
        }
    }
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}

function balance_API_31($domain, $api_key, $proxy = '')
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "{$domain}/api/profile.php?api_key={$api_key}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36'
    ));
    // Thêm proxy nếu có và hợp lệ
    if (!empty($proxy)) {
        $proxy_parts = explode(':', $proxy);
        if (count($proxy_parts) == 4 && !empty($proxy_parts[0]) && !empty($proxy_parts[1]) && !empty($proxy_parts[2]) && !empty($proxy_parts[3])) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy_parts[2] . ':' . $proxy_parts[3]);
        } elseif (count($proxy_parts) == 2 && !empty($proxy_parts[0]) && !empty($proxy_parts[1])) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
        }
    }
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}
function listProduct_API_31($domain, $api_key, $proxy = '')
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "{$domain}/api/products.php?api_key={$api_key}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36'
    ));
    // Thêm proxy nếu có và hợp lệ
    if (!empty($proxy)) {
        $proxy_parts = explode(':', $proxy);
        if (count($proxy_parts) == 4 && !empty($proxy_parts[0]) && !empty($proxy_parts[1]) && !empty($proxy_parts[2]) && !empty($proxy_parts[3])) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy_parts[2] . ':' . $proxy_parts[3]);
        } elseif (count($proxy_parts) == 2 && !empty($proxy_parts[0]) && !empty($proxy_parts[1])) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
        }
    }
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}
function buy_API_31($domain, $coupon, $api_key, $id_api, $amount, $proxy = '')
{
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "{$domain}/api/buy_product",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => array('action' => 'buyProduct', 'id' => $id_api, 'amount' => $amount, 'coupon' => $coupon, 'api_key' => $api_key),
        CURLOPT_HTTPHEADER => array(
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36'
        ),
    ));

    // Thêm proxy nếu có và hợp lệ
    if (!empty($proxy)) {
        $proxy_parts = explode(':', $proxy);
        if (count($proxy_parts) == 4 && !empty($proxy_parts[0]) && !empty($proxy_parts[1]) && !empty($proxy_parts[2]) && !empty($proxy_parts[3])) {
            curl_setopt($curl, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
            curl_setopt($curl, CURLOPT_PROXYUSERPWD, $proxy_parts[2] . ':' . $proxy_parts[3]);
        } elseif (count($proxy_parts) == 2 && !empty($proxy_parts[0]) && !empty($proxy_parts[1])) {
            curl_setopt($curl, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
        }
    }

    $response = curl_exec($curl);
    curl_close($curl);
    return $response;
}
function buy_API_30($domain, $api_key, $id_api, $amount)
{
    return curl_get($domain . "huoqu?shuliang={$amount}&leixing={$id_api}&card={$api_key}");
}
function listProduct_API_30($domain)
{
    return curl_get2($domain . 'kucun');
}
function balance_API_30($domain, $apikey)
{
    return curl_get($domain . "yue?card=$apikey");
}
//
function buy_API_29($domain, $api_key, $id_api, $amount)
{
    return curl_get($domain . "api/mail/getMail?clientKey={$api_key}&mailType={$id_api}&quantity={$amount}");
}
function listProduct_API_29($domain, $type)
{
    return curl_get2($domain . 'api/mail/getStock?mailType=' . $type);
}
function balance_API_29($domain, $apikey)
{
    return curl_get($domain . "api/user/balance?clientKey=$apikey");
}
//
function buy_API_28($domain, $token, $api_id, $amount)
{
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $domain . 'api/order',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => array(
            'id' => $api_id,
            'quantity' => $amount,
            'user_token' => $token
        ),
    ));
    $response = curl_exec($curl);
    curl_close($curl);
    return $response;
}
function listProduct_API_28($domain)
{
    return curl_get2($domain . 'api/get-account');
}
function balance_API_28($domain, $username, $password)
{
    return curl_get("{$domain}api/get-info?username=$username&password=$password");
}
function getOrder_API_26($domain, $api_key, $token, $invoice)
{
    global $CMSNT;

    $allowed_domains = explode(',', $CMSNT->site('domains'));
    $api_key = explode('|', $api_key);
    $token = explode('|', $token);

    $public_key = $token[0];
    $private_key = $token[1];
    $email = $api_key[0];
    $token_pay = $api_key[1];
    $key = $api_key[2];
    $key_createorder = $api_key[3];
    $host = $token[2];

    $curl = curl_init("{$domain}api/downloadtxt/{$invoice}");
    curl_setopt_array($curl, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => array(
            "LEQUE-KEY-API-PUB: $public_key",
            "LEQUE-KEY-API-PRIV: $private_key",
            "HOST: $host"
        ),
        CURLOPT_SSL_VERIFYPEER => false, // Lưu ý: Tắt xác minh SSL có thể không an toàn
        CURLOPT_SSL_VERIFYHOST => false  // Lưu ý: Tắt xác minh SSL có thể không an toàn
    ));
    $response = curl_exec($curl);
    curl_close($curl);
    // Hiển thị phản hồi dưới dạng văn bản thuần túy, giữ nguyên định dạng
    if ($response && strlen($response) > 0) {
        // Sử dụng htmlspecialchars để tránh XSS nếu nội dung có thể chứa HTML/JS
        // Sử dụng nl2br để chuyển đổi dấu xuống dòng thành thẻ <br> khi hiển thị trên web
        return htmlspecialchars($response);
    } else {
        return __('Please contact Admin to get order');
    }
}
function buy_API_26($domain, $api_key, $token, $api_id, $amount)
{
    global $CMSNT;

    $allowed_domains = explode(',', $CMSNT->site('domains'));
    $api_key = explode('|', $api_key);
    $token = explode('|', $token);

    $public_key = $token[0];
    $private_key = $token[1];
    $email = $api_key[0];
    $token_pay = $api_key[1];
    $key = $api_key[2];
    $key_createorder = $api_key[3];
    $host = $token[2];

    $curl = curl_init();
    $data = array(
        "email" => $email,
        "key" => $key_createorder,
        "count" => $amount,
        "type" => $api_id,
        "fund" => "13",
        "success_url" => basename(''),
        "token_pay" => $token_pay
    );

    curl_setopt_array($curl, array(
        CURLOPT_URL => "{$domain}api/createorder",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => "webApi",
        CURLOPT_SSL_VERIFYPEER => false, // Lưu ý: Tắt xác minh SSL có thể không an toàn
        CURLOPT_SSL_VERIFYHOST => false, // Lưu ý: Tắt xác minh SSL có thể không an toàn
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_HTTPHEADER => array(
            "LEQUE-KEY-API-PUB: $public_key",
            "LEQUE-KEY-API-PRIV: $private_key",
            "HOST: $host"
        ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);

    $response = json_decode($response, true);
    if (isset($response['ok']) && $response['ok'] == 'TRUE') {
        $invoice = $response['invoice'];
        $curl = curl_init();
        $data = array(
            "pay" => "yes",
            "email_pay" => $email,
            "token_pay" => $token_pay
        );
        curl_setopt_array($curl, array(
            CURLOPT_URL => "{$domain}api/paybalance/{$invoice}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => "webApi",
            CURLOPT_SSL_VERIFYPEER => false, // Lưu ý: Tắt xác minh SSL có thể không an toàn
            CURLOPT_SSL_VERIFYHOST => false, // Lưu ý: Tắt xác minh SSL có thể không an toàn
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_HTTPHEADER => array(
                "LEQUE-KEY-API-PUB: $public_key",
                "LEQUE-KEY-API-PRIV: $private_key",
                "HOST: $host"
            ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    } else {
        return json_encode($response);
    }
}
function listProduct_API_26($domain, $api_key, $token)
{
    global $CMSNT;

    $allowed_domains = explode(',', $CMSNT->site('domains'));
    $api_key = explode('|', $api_key);
    $token = explode('|', $token);

    $public_key = $token[0];
    $private_key = $token[1];
    $email = $api_key[0];
    $token_pay = $api_key[1];
    $key = $api_key[2];
    $host = $token[2];

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "{$domain}api/goods?key={$key}",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => "webApi",
        CURLOPT_SSL_VERIFYPEER => false, // Lưu ý: Tắt xác minh SSL có thể không an toàn
        CURLOPT_SSL_VERIFYHOST => false, // Lưu ý: Tắt xác minh SSL có thể không an toàn
        CURLOPT_HTTPHEADER => array(
            "LEQUE-KEY-API-PUB: " . $public_key,
            "LEQUE-KEY-API-PRIV: " . $private_key,
            "HOST: $host"
        ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);

    return $response;
}
function balance_API_26($domain, $api_key, $token)
{
    global $CMSNT;

    // Lấy danh sách domains từ database
    $allowed_domains = explode(',', $CMSNT->site('domains'));
    $api_key = explode('|', $api_key);
    $token = explode('|', $token);
    //
    $public_key = $token[0];
    $private_key = $token[1];
    $email = $api_key[0];
    $token_pay = $api_key[1];
    $host = $token[2];

    $curl = curl_init();
    $data = array(
        "email" => $email,
        "token_pay" => $token_pay,
    );
    curl_setopt_array($curl, array(
        CURLOPT_URL => "{$domain}api/balanceuser",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => "webApi",
        CURLOPT_SSL_VERIFYPEER => false, // Lưu ý: Tắt xác minh SSL có thể không an toàn
        CURLOPT_SSL_VERIFYHOST => false, // Lưu ý: Tắt xác minh SSL có thể không an toàn
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_HTTPHEADER => array(
            "LEQUE-KEY-API-PUB: " . $public_key,
            "LEQUE-KEY-API-PRIV: " . $private_key,
            "HOST: $host"
        ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);

    return $response;
}
//
function buy_API_25($domain, $api_key, $id_api, $amount)
{
    return curl_get($domain . "purchase?apikey=$api_key&accountcode=$id_api&quantity=$amount");
}
function listProduct_API_25($domain)
{
    return curl_get($domain . "instock");
}
function balance_API_25($domain, $apikey)
{
    return curl_get($domain . "balance?apikey=$apikey");
}

function balance_API_24($domain, $api_key)
{
    return curl_get2($domain . "api/checkapikey=$api_key");
}
function buy_API_24($domain, $api_key, $api_id, $amount)
{
    return curl_get($domain . "api/byproduct/apikey=$api_key&product_id=$api_id&quality=$amount");
}
function listProduct_API_24($domain, $api_key)
{
    return curl_get($domain . "api/checkprice=$api_key");
}

function buy_API_23($domain, $api_key, $api_id, $amount)
{
    return curl_get($domain . "purchase?api_key=$api_key&accountcode=$api_id&quantity=$amount");
}
function listProduct_API_23($domain)
{
    return curl_get($domain . "instock");
}
function balance_API_23($domain, $api_key)
{
    return curl_get2($domain . "balance?api_key=$api_key");
}
function buy_API_22($domain, $token, $product_id, $amount)
{
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $domain . 'api/buyHotMailUd',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => array(
            'quantity' => $amount,
            'token' => $token,
            'product_id' => $product_id
        ),
    ));
    $response = curl_exec($curl);
    curl_close($curl);
    return $response;
}
function listProduct_API_22($domain, $token)
{
    return curl_get($domain . 'api/quantity?token=' . $token);
}
function buy_API_17($domain, $username, $password, $api_id, $amount)
{
    return curl_get2("$domain/api/BResource.php?username=$username&password=$password&id=$api_id&amount=$amount");
}
function listProduct_API_17($domain, $username, $password)
{
    return curl_get2($domain . '/api/CategoryList.php?username=' . $username . '&password=' . $password);
}
function balance_API_17($domain, $username, $password)
{
    return curl_get("{$domain}api/GetBalance.php?username=$username&password=$password");
}
function buy_API_21($domain, $token, $product_id, $amount)
{
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $domain . 'api/buy-products',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => array(
            'quantity' => $amount,
            'token' => $token,
            'product_id' => $product_id
        ),
    ));
    $response = curl_exec($curl);
    curl_close($curl);
    return $response;
}
function listProduct_API_21($domain, $token)
{
    return curl_get($domain . 'api/quantity?token=' . $token);
}
function buy_API_9($domain, $password, $dataPost)
{
    $data = json_encode($dataPost);
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $domain . 'v1/api/buy?api_key=' . $password,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json'
        ),
    ));
    $response = curl_exec($curl);
    curl_close($curl);
    return $response;
}
function listProduct_API_9($domain, $password)
{
    return curl_get($domain . 'v1/api/categories?api_key=' . $password);
}
function balance_API_9($domain, $password)
{
    return curl_get($domain . 'v1/api/me?api_key=' . $password);
}

function buy_API_4($domain, $token, $id_product, $amount)
{
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $domain . 'v1/user/partnerbuy',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => array('amount' => $amount, 'categoryId' => $id_product),
        CURLOPT_HTTPHEADER => array(
            'authorization: ' . $token
        ),
    ));
    $response = curl_exec($curl);
    curl_close($curl);
    return $response;
}
function balance_API_4($domain, $username, $password)
{
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $domain . 'v1/user/login',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => array(
            'username' => $username,
            'password'  => $password
        ),
    ));
    $response = curl_exec($curl);
    curl_close($curl);
    return $response;
}
function listProduct_API_4($domain)
{
    return curl_get2($domain . "v1/public/category/list");
}
function buy_API_19($domain, $api_key, $id_api, $amount)
{
    return curl_get2($domain . "user/buy?apikey=$api_key&account_type=$id_api&quality=$amount&type=null");
}
function listProduct_API_19($domain, $api_key)
{
    return curl_get2($domain . "user/account_type?apikey=$api_key");
}
function balance_API_19($domain, $api_key)
{
    return curl_get2($domain . "user/balance?apikey=$api_key");
}
function buy_API_18($domain, $api_key, $id_api, $amount)
{
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $domain . 'mail/buy?mailcode=' . $id_api . '&quantity=' . $amount,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer ' . $api_key
        ),
    ));
    $response = curl_exec($curl);
    curl_close($curl);
    return $response;
}
function listProduct_API_18($domain)
{
    return curl_get($domain . "mail/currentstock");
}
function balance_API_18($domain, $apikey)
{
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $domain . 'auth/me',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer ' . $apikey
        ),
    ));
    $response = curl_exec($curl);
    curl_close($curl);
    return $response;
}

function buy_API_SHOPCLONE7($domain, $coupon, $api_key, $id_api, $amount, $proxy = '')
{
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "{$domain}/api/buy_product",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => array('action' => 'buyProduct', 'id' => $id_api, 'amount' => $amount, 'coupon' => $coupon, 'api_key' => $api_key),
        CURLOPT_HTTPHEADER => array(
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36'
        ),
    ));

    // Thêm proxy nếu có và hợp lệ
    if (!empty($proxy)) {
        $proxy_parts = explode(':', $proxy);
        if (count($proxy_parts) == 4 && !empty($proxy_parts[0]) && !empty($proxy_parts[1]) && !empty($proxy_parts[2]) && !empty($proxy_parts[3])) {
            curl_setopt($curl, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
            curl_setopt($curl, CURLOPT_PROXYUSERPWD, $proxy_parts[2] . ':' . $proxy_parts[3]);
        } elseif (count($proxy_parts) == 2 && !empty($proxy_parts[0]) && !empty($proxy_parts[1])) {
            curl_setopt($curl, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
        }
    }

    $response = curl_exec($curl);

    // Lấy HTTP status code
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    curl_close($curl);

    // Xử lý response JSON và thêm HTTP code vào
    if ($response) {
        $result = json_decode($response, true);
        if (is_array($result)) {
            $result['http_code'] = $http_code;
            return json_encode($result);
        }
    }

    // Nếu response không phải là JSON hợp lệ hoặc rỗng, trả về cấu trúc mới
    return json_encode([
        'status' => 'error2',
        'msg' => __('Mất kết nối đến kho hàng'),
        'http_code' => $http_code
    ]);
}

function listProduct_API_SHOPCLONE7($domain, $api_key, $proxy = '', $use_child = false)
{
    $ch = curl_init();
    // Sử dụng API products_child.php nếu use_child = true, ngược lại dùng products.php
    $api_endpoint = $use_child ? "products_child.php" : "products.php";
    curl_setopt($ch, CURLOPT_URL, "{$domain}/api/{$api_endpoint}?api_key={$api_key}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36'
    ));
    // Thêm proxy nếu có và hợp lệ
    if (!empty($proxy)) {
        $proxy_parts = explode(':', $proxy);
        if (count($proxy_parts) == 4 && !empty($proxy_parts[0]) && !empty($proxy_parts[1]) && !empty($proxy_parts[2]) && !empty($proxy_parts[3])) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy_parts[2] . ':' . $proxy_parts[3]);
        } elseif (count($proxy_parts) == 2 && !empty($proxy_parts[0]) && !empty($proxy_parts[1])) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
        }
    }
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}
function balance_API_SHOPCLONE7($domain, $api_key, $proxy = '')
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "{$domain}/api/profile.php?api_key={$api_key}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36'
    ));
    // Thêm proxy nếu có và hợp lệ
    if (!empty($proxy)) {
        $proxy_parts = explode(':', $proxy);
        if (count($proxy_parts) == 4 && !empty($proxy_parts[0]) && !empty($proxy_parts[1]) && !empty($proxy_parts[2]) && !empty($proxy_parts[3])) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy_parts[2] . ':' . $proxy_parts[3]);
        } elseif (count($proxy_parts) == 2 && !empty($proxy_parts[0]) && !empty($proxy_parts[1])) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
        }
    }
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}

/**
 * SHOPKEY API Functions
 * API SHOPKEY sử dụng header-based authentication với X-API-Key và X-API-Secret
 */

/**
 * Lấy số dư tài khoản SHOPKEY
 * @param string $domain Domain của API (VD: https://shopkey.io/)
 * @param string $api_key API Key
 * @param string $secret_key Secret Key
 * @param string $proxy Proxy nếu có (format: ip:port hoặc ip:port:user:pass)
 * @return string JSON response từ API
 */
function balance_API_SHOPKEY($domain, $api_key, $secret_key, $proxy = '')
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, rtrim($domain, '/') . "/api/v1/account/balance");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'X-API-Key: ' . $api_key,
        'X-API-Secret: ' . $secret_key,
        'Content-Type: application/json',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36'
    ));

    // Thêm proxy nếu có và hợp lệ
    if (!empty($proxy)) {
        $proxy_parts = explode(':', $proxy);
        if (count($proxy_parts) == 4 && !empty($proxy_parts[0]) && !empty($proxy_parts[1]) && !empty($proxy_parts[2]) && !empty($proxy_parts[3])) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy_parts[2] . ':' . $proxy_parts[3]);
        } elseif (count($proxy_parts) == 2 && !empty($proxy_parts[0]) && !empty($proxy_parts[1])) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
        }
    }

    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}

/**
 * Lấy danh sách sản phẩm SHOPKEY
 * @param string $domain Domain của API
 * @param string $api_key API Key
 * @param string $secret_key Secret Key
 * @param string $proxy Proxy nếu có
 * @param int $page Số trang (mặc định = 1)
 * @param int $per_page Số sản phẩm mỗi trang (mặc định = 100)
 * @return string JSON response từ API
 */
function listProduct_API_SHOPKEY($domain, $api_key, $secret_key, $proxy = '', $page = 1, $per_page = 100)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, rtrim($domain, '/') . "/api/v1/products/list?page=" . intval($page) . "&per_page=" . intval($per_page));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'X-API-Key: ' . $api_key,
        'X-API-Secret: ' . $secret_key,
        'Content-Type: application/json',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36'
    ));

    // Thêm proxy nếu có và hợp lệ
    if (!empty($proxy)) {
        $proxy_parts = explode(':', $proxy);
        if (count($proxy_parts) == 4 && !empty($proxy_parts[0]) && !empty($proxy_parts[1]) && !empty($proxy_parts[2]) && !empty($proxy_parts[3])) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy_parts[2] . ':' . $proxy_parts[3]);
        } elseif (count($proxy_parts) == 2 && !empty($proxy_parts[0]) && !empty($proxy_parts[1])) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
        }
    }

    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}

/**
 * Mua sản phẩm từ SHOPKEY
 * @param string $domain Domain của API
 * @param string $coupon Mã giảm giá
 * @param string $api_key API Key
 * @param string $secret_key Secret Key
 * @param int $plan_id ID của plan cần mua
 * @param int $quantity Số lượng mua
 * @param string $proxy Proxy nếu có
 * @return string JSON response từ API
 */
function buy_API_SHOPKEY($domain, $coupon, $api_key, $secret_key, $plan_id, $quantity, $proxy = '')
{
    // Chuẩn bị dữ liệu POST
    $postData = json_encode([
        'items' => [
            [
                'plan_id' => intval($plan_id),
                'quantity' => intval($quantity)
            ]
        ],
        'coupon_code' => !empty($coupon) ? $coupon : ''
    ]);

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => rtrim($domain, '/') . "/api/v1/orders/create",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_HTTPHEADER => array(
            'X-API-Key: ' . $api_key,
            'X-API-Secret: ' . $secret_key,
            'Content-Type: application/json',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36'
        ),
    ));

    // Thêm proxy nếu có và hợp lệ
    if (!empty($proxy)) {
        $proxy_parts = explode(':', $proxy);
        if (count($proxy_parts) == 4 && !empty($proxy_parts[0]) && !empty($proxy_parts[1]) && !empty($proxy_parts[2]) && !empty($proxy_parts[3])) {
            curl_setopt($curl, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
            curl_setopt($curl, CURLOPT_PROXYUSERPWD, $proxy_parts[2] . ':' . $proxy_parts[3]);
        } elseif (count($proxy_parts) == 2 && !empty($proxy_parts[0]) && !empty($proxy_parts[1])) {
            curl_setopt($curl, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
        }
    }

    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    // Xử lý response JSON và thêm HTTP code
    if ($response) {
        $result = json_decode($response, true);
        if (is_array($result)) {
            $result['http_code'] = $http_code;
            return json_encode($result);
        }
    }

    return json_encode([
        'success' => false,
        'message' => __('Mất kết nối đến kho hàng'),
        'http_code' => $http_code
    ]);
}

/**
 * Lấy trạng thái đơn hàng SHOPKEY
 * @param string $domain Domain của API
 * @param string $api_key API Key
 * @param string $secret_key Secret Key
 * @param string $trans_id Mã giao dịch
 * @param string $proxy Proxy nếu có
 * @return string JSON response từ API
 */
function getOrder_API_SHOPKEY($domain, $api_key, $secret_key, $trans_id, $proxy = '')
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, rtrim($domain, '/') . "/api/v1/orders/status?trans_id=" . urlencode($trans_id));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'X-API-Key: ' . $api_key,
        'X-API-Secret: ' . $secret_key,
        'Content-Type: application/json',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36'
    ));

    // Thêm proxy nếu có và hợp lệ
    if (!empty($proxy)) {
        $proxy_parts = explode(':', $proxy);
        if (count($proxy_parts) == 4 && !empty($proxy_parts[0]) && !empty($proxy_parts[1]) && !empty($proxy_parts[2]) && !empty($proxy_parts[3])) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy_parts[2] . ':' . $proxy_parts[3]);
        } elseif (count($proxy_parts) == 2 && !empty($proxy_parts[0]) && !empty($proxy_parts[1])) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
        }
    }

    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}

function buy_API_SHOPCLONE6($domain, $username, $password, $api_id, $amount, $proxy = '')
{
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "{$domain}/api/BResource.php?username={$username}&password={$password}&id={$api_id}&amount={$amount}",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36'
        ),
    ));
    // Thêm proxy nếu có và hợp lệ
    if (!empty($proxy)) {
        $proxy_parts = explode(':', $proxy);
        if (count($proxy_parts) == 4 && !empty($proxy_parts[0]) && !empty($proxy_parts[1]) && !empty($proxy_parts[2]) && !empty($proxy_parts[3])) {
            curl_setopt($curl, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
            curl_setopt($curl, CURLOPT_PROXYUSERPWD, $proxy_parts[2] . ':' . $proxy_parts[3]);
        } elseif (count($proxy_parts) == 2 && !empty($proxy_parts[0]) && !empty($proxy_parts[1])) {
            curl_setopt($curl, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
        }
    }
    $response = curl_exec($curl);

    // Lấy HTTP status code
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    curl_close($curl);

    // Xử lý response JSON và thêm HTTP code vào
    if ($response) {
        $result = json_decode($response, true);
        if (is_array($result)) {
            $result['http_code'] = $http_code;
            return json_encode($result);
        }
    }

    // Nếu response không phải là JSON hợp lệ hoặc rỗng, trả về cấu trúc mới
    return json_encode([
        'status' => 'error2',
        'msg' => __('Mất kết nối đến kho hàng'),
        'http_code' => $http_code
    ]);
}
function listProduct_API_SHOPCLONE6($domain, $username, $password, $proxy = '')
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "{$domain}/api/ListResource.php?username={$username}&password={$password}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36'
    ));
    // Thêm proxy nếu có và hợp lệ
    if (!empty($proxy)) {
        $proxy_parts = explode(':', $proxy);
        if (count($proxy_parts) == 4 && !empty($proxy_parts[0]) && !empty($proxy_parts[1]) && !empty($proxy_parts[2]) && !empty($proxy_parts[3])) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy_parts[2] . ':' . $proxy_parts[3]);
        } elseif (count($proxy_parts) == 2 && !empty($proxy_parts[0]) && !empty($proxy_parts[1])) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
        }
    }
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}
function balance_API_SHOPCLONE6($domain, $username, $password, $proxy = '')
{
    $url = "{$domain}/api/GetBalance.php?username={$username}&password={$password}";

    $opts = array(
        "ssl" => array(
            "verify_peer" => false,
            "verify_peer_name" => false,
        ),
        "http" => array(
            "header" => array(
                "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36"
            )
        )
    );

    // Nếu có proxy, thêm cấu hình proxy
    if (!empty($proxy)) {
        $proxy_parts = explode(':', $proxy);
        if (count($proxy_parts) == 4) {
            $proxy = "tcp://{$proxy_parts[0]}:{$proxy_parts[1]}";
            $opts['http']['proxy'] = $proxy;
            $opts['http']['request_fulluri'] = true;
            $opts['http']['header'][] = 'Proxy-Authorization: Basic ' . base64_encode($proxy_parts[2] . ':' . $proxy_parts[3]);
        } elseif (count($proxy_parts) == 2) {
            $proxy = "tcp://{$proxy_parts[0]}:{$proxy_parts[1]}";
            $opts['http']['proxy'] = $proxy;
            $opts['http']['request_fulluri'] = true;
        }
    }

    return file_get_contents($url, false, stream_context_create($opts));
}
function getOrder_API_14($domain, $token, $order_id)
{
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $domain . 'api',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_HTTPHEADER => array(
            'Authorization: ' . $token
        ),
        CURLOPT_POSTFIELDS => '{
            "act": "Get-Order",
            "data": {
                "order_id": ' . $order_id . '
            }
        }',
    ));
    $response = curl_exec($curl);
    curl_close($curl);
    return $response;
}
function buy_API_14($domain, $token, $id_api, $amount)
{
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $domain . 'api',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_HTTPHEADER => array(
            'Authorization: ' . $token
        ),
        CURLOPT_POSTFIELDS => '{
        "act": "Create-Order",
        "data": {
            "service_id": ' . $id_api . ',
            "quantity": ' . $amount . '
        }
    }',
    ));
    $response = curl_exec($curl);
    curl_close($curl);
    return $response;
}
function listProduct_API_14($domain, $token)
{
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $domain . 'api',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => array('act' => 'Get-Products'),
        CURLOPT_HTTPHEADER => array(
            'Authorization: ' . $token
        ),
    ));
    $response = curl_exec($curl);
    curl_close($curl);
    return $response;
}
function balance_API_14($domain, $token)
{
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $domain . 'api',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => array('act' => 'Me'),
        CURLOPT_HTTPHEADER => array(
            'Authorization: ' . $token
        ),
    ));
    $response = curl_exec($curl);
    curl_close($curl);
    return $response;
}

function balance_API_6($domain, $api_key)
{
    return curl_get("$domain/api.php?apikey=$api_key&action=get-balance");
}

// API_35 Functions - Xác thực bằng Email + Token
function balance_API_35($domain, $email, $token, $proxy = '')
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "{$domain}?action=balance&email={$email}&token={$token}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36'
    ));
    // Thêm proxy nếu có và hợp lệ
    if (!empty($proxy)) {
        $proxy_parts = explode(':', $proxy);
        if (count($proxy_parts) == 4 && !empty($proxy_parts[0]) && !empty($proxy_parts[1]) && !empty($proxy_parts[2]) && !empty($proxy_parts[3])) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy_parts[2] . ':' . $proxy_parts[3]);
        } elseif (count($proxy_parts) == 2 && !empty($proxy_parts[0]) && !empty($proxy_parts[1])) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
        }
    }
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}

function listProduct_API_35($domain, $proxy = '')
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "{$domain}?action=list");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36'
    ));
    // Thêm proxy nếu có và hợp lệ
    if (!empty($proxy)) {
        $proxy_parts = explode(':', $proxy);
        if (count($proxy_parts) == 4 && !empty($proxy_parts[0]) && !empty($proxy_parts[1]) && !empty($proxy_parts[2]) && !empty($proxy_parts[3])) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy_parts[2] . ':' . $proxy_parts[3]);
        } elseif (count($proxy_parts) == 2 && !empty($proxy_parts[0]) && !empty($proxy_parts[1])) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
        }
    }
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}

function buy_API_35($domain, $email, $token, $product_id, $quantity, $proxy = '')
{
    $ch = curl_init();
    // buy=13 là tham số cố định theo tài liệu API
    curl_setopt($ch, CURLOPT_URL, "{$domain}?email={$email}&token={$token}&buy=13&action={$product_id}&quantity={$quantity}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36'
    ));
    // Thêm proxy nếu có và hợp lệ
    if (!empty($proxy)) {
        $proxy_parts = explode(':', $proxy);
        if (count($proxy_parts) == 4 && !empty($proxy_parts[0]) && !empty($proxy_parts[1]) && !empty($proxy_parts[2]) && !empty($proxy_parts[3])) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy_parts[2] . ':' . $proxy_parts[3]);
        } elseif (count($proxy_parts) == 2 && !empty($proxy_parts[0]) && !empty($proxy_parts[1])) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
        }
    }
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}

// API_34 Functions
function balance_API_34($domain, $api_key, $proxy = '')
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "{$domain}api/v1/users/balance?apikey={$api_key}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36'
    ));
    // Thêm proxy nếu có và hợp lệ
    if (!empty($proxy)) {
        $proxy_parts = explode(':', $proxy);
        if (count($proxy_parts) == 4 && !empty($proxy_parts[0]) && !empty($proxy_parts[1]) && !empty($proxy_parts[2]) && !empty($proxy_parts[3])) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy_parts[2] . ':' . $proxy_parts[3]);
        } elseif (count($proxy_parts) == 2 && !empty($proxy_parts[0]) && !empty($proxy_parts[1])) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
        }
    }
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}

function listProduct_API_34($domain, $api_key, $proxy = '')
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "{$domain}api/v1/products/get-all?apikey={$api_key}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36'
    ));
    // Thêm proxy nếu có và hợp lệ
    if (!empty($proxy)) {
        $proxy_parts = explode(':', $proxy);
        if (count($proxy_parts) == 4 && !empty($proxy_parts[0]) && !empty($proxy_parts[1]) && !empty($proxy_parts[2]) && !empty($proxy_parts[3])) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy_parts[2] . ':' . $proxy_parts[3]);
        } elseif (count($proxy_parts) == 2 && !empty($proxy_parts[0]) && !empty($proxy_parts[1])) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
        }
    }
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}

function buy_API_34($domain, $api_key, $product_id, $quantity, $proxy = '')
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "{$domain}api/v1/orders/buy?productId={$product_id}&quantity={$quantity}&apikey={$api_key}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36'
    ));
    // Thêm proxy nếu có và hợp lệ
    if (!empty($proxy)) {
        $proxy_parts = explode(':', $proxy);
        if (count($proxy_parts) == 4 && !empty($proxy_parts[0]) && !empty($proxy_parts[1]) && !empty($proxy_parts[2]) && !empty($proxy_parts[3])) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy_parts[2] . ':' . $proxy_parts[3]);
        } elseif (count($proxy_parts) == 2 && !empty($proxy_parts[0]) && !empty($proxy_parts[1])) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
        }
    }
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}
function balance_API_1($domain, $token)
{
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $domain . 'api/v1/balance',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => array('api_key' => $token),
    ));
    $response = curl_exec($curl);
    curl_close($curl);
    return $response;
}
function listProduct_API_1($domain)
{
    return curl_get2($domain . 'api/v1/categories');
}
function buy_API_1($domain, $dataPost)
{
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $domain . "api/v1/buy",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => $dataPost,
    ));
    $response = curl_exec($curl);
    curl_close($curl);
    return $response;
}
function order_API_1($domain, $api_key, $order_id)
{
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $domain . 'api/v1/order',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => array('api_key' => $api_key, 'order_id' => $order_id),
    ));
    $response = curl_exec($curl);
    curl_close($curl);
    return $response;
}

// API_36 Functions - humkt.com
function balance_API_36($domain, $token, $proxy = '')
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "{$domain}api/v1/balance?token={$token}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36'
    ));
    // Thêm proxy nếu có và hợp lệ
    if (!empty($proxy)) {
        $proxy_parts = explode(':', $proxy);
        if (count($proxy_parts) == 4 && !empty($proxy_parts[0]) && !empty($proxy_parts[1]) && !empty($proxy_parts[2]) && !empty($proxy_parts[3])) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy_parts[2] . ':' . $proxy_parts[3]);
        } elseif (count($proxy_parts) == 2 && !empty($proxy_parts[0]) && !empty($proxy_parts[1])) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
        }
    }
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}

function listProduct_API_36($domain, $token, $proxy = '', $page = 1)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "{$domain}api/v1/products?token={$token}&page={$page}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36'
    ));
    // Thêm proxy nếu có và hợp lệ
    if (!empty($proxy)) {
        $proxy_parts = explode(':', $proxy);
        if (count($proxy_parts) == 4 && !empty($proxy_parts[0]) && !empty($proxy_parts[1]) && !empty($proxy_parts[2]) && !empty($proxy_parts[3])) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy_parts[2] . ':' . $proxy_parts[3]);
        } elseif (count($proxy_parts) == 2 && !empty($proxy_parts[0]) && !empty($proxy_parts[1])) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
        }
    }
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}

function buy_API_36($domain, $token, $product_id, $quantity, $proxy = '')
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "{$domain}api/v1/orders");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'token' => $token,
        'id' => intval($product_id),
        'qty' => intval($quantity)
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36'
    ));
    // Thêm proxy nếu có và hợp lệ
    if (!empty($proxy)) {
        $proxy_parts = explode(':', $proxy);
        if (count($proxy_parts) == 4 && !empty($proxy_parts[0]) && !empty($proxy_parts[1]) && !empty($proxy_parts[2]) && !empty($proxy_parts[3])) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy_parts[2] . ':' . $proxy_parts[3]);
        } elseif (count($proxy_parts) == 2 && !empty($proxy_parts[0]) && !empty($proxy_parts[1])) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
        }
    }
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}

function getOrder_API_36($domain, $token, $order_id, $proxy = '')
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "{$domain}api/v1/orders/{$order_id}?token={$token}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36'
    ));
    // Thêm proxy nếu có và hợp lệ
    if (!empty($proxy)) {
        $proxy_parts = explode(':', $proxy);
        if (count($proxy_parts) == 4 && !empty($proxy_parts[0]) && !empty($proxy_parts[1]) && !empty($proxy_parts[2]) && !empty($proxy_parts[3])) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy_parts[2] . ':' . $proxy_parts[3]);
        } elseif (count($proxy_parts) == 2 && !empty($proxy_parts[0]) && !empty($proxy_parts[1])) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
        }
    }
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}

// ==================== API_37 - sieuthikey.io.vn ====================

function balance_API_37($domain, $token, $proxy = '')
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "{$domain}api/get_balance.php?token={$token}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36'
    ));
    // Thêm proxy nếu có và hợp lệ
    if (!empty($proxy)) {
        $proxy_parts = explode(':', $proxy);
        if (count($proxy_parts) == 4 && !empty($proxy_parts[0]) && !empty($proxy_parts[1]) && !empty($proxy_parts[2]) && !empty($proxy_parts[3])) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy_parts[2] . ':' . $proxy_parts[3]);
        } elseif (count($proxy_parts) == 2 && !empty($proxy_parts[0]) && !empty($proxy_parts[1])) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
        }
    }
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}

function listProduct_API_37($domain, $token, $proxy = '')
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "{$domain}api/get_products.php?token={$token}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36'
    ));
    // Thêm proxy nếu có và hợp lệ
    if (!empty($proxy)) {
        $proxy_parts = explode(':', $proxy);
        if (count($proxy_parts) == 4 && !empty($proxy_parts[0]) && !empty($proxy_parts[1]) && !empty($proxy_parts[2]) && !empty($proxy_parts[3])) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy_parts[2] . ':' . $proxy_parts[3]);
        } elseif (count($proxy_parts) == 2 && !empty($proxy_parts[0]) && !empty($proxy_parts[1])) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
        }
    }
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}

function buy_API_37($domain, $token, $product_id, $quantity, $proxy = '')
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "{$domain}api/buy_product.php?token={$token}&product_id={$product_id}&soluong={$quantity}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36'
    ));
    // Thêm proxy nếu có và hợp lệ
    if (!empty($proxy)) {
        $proxy_parts = explode(':', $proxy);
        if (count($proxy_parts) == 4 && !empty($proxy_parts[0]) && !empty($proxy_parts[1]) && !empty($proxy_parts[2]) && !empty($proxy_parts[3])) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy_parts[2] . ':' . $proxy_parts[3]);
        } elseif (count($proxy_parts) == 2 && !empty($proxy_parts[0]) && !empty($proxy_parts[1])) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
        }
    }
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}

// ==================== API_38 - API Shared (Partner API with MD5 Signature) ====================

/**
 * Tạo chữ ký MD5 cho API Shared
 * Quy tắc: Sắp xếp params theo key A-Z -> Ghép query string -> Thêm &key=app_key -> MD5
 */
function build_sign_API_38($data, $app_key)
{
    unset($data['sign']);
    ksort($data);
    foreach ($data as $k => $v) {
        if ($v === '') unset($data[$k]);
    }
    $raw = http_build_query($data) . "&key=" . $app_key;
    return md5(urldecode($raw));
}

/**
 * Gọi API Shared với chữ ký MD5
 */
function call_API_38($domain, $endpoint, $data, $proxy = '')
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "{$domain}index.php?s={$endpoint}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
        'Content-Type: application/x-www-form-urlencoded'
    ));
    // Thêm proxy nếu có và hợp lệ
    if (!empty($proxy)) {
        $proxy_parts = explode(':', $proxy);
        if (count($proxy_parts) == 4 && !empty($proxy_parts[0]) && !empty($proxy_parts[1]) && !empty($proxy_parts[2]) && !empty($proxy_parts[3])) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy_parts[2] . ':' . $proxy_parts[3]);
        } elseif (count($proxy_parts) == 2 && !empty($proxy_parts[0]) && !empty($proxy_parts[1])) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
        }
    }
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

/**
 * Kiểm tra kết nối và lấy số dư - /shared/authentication/connect
 */
function balance_API_38($domain, $app_id, $app_key, $proxy = '')
{
    $data = [
        'app_id' => $app_id,
        'app_key' => $app_key
    ];
    $data['sign'] = build_sign_API_38($data, $app_key);
    return call_API_38($domain, '/shared/authentication/connect', $data, $proxy);
}

/**
 * Lấy danh sách sản phẩm - /shared/commodity/items
 */
function listProduct_API_38($domain, $app_id, $app_key, $proxy = '')
{
    $data = [
        'app_id' => $app_id,
        'app_key' => $app_key
    ];
    $data['sign'] = build_sign_API_38($data, $app_key);
    return call_API_38($domain, '/shared/commodity/items', $data, $proxy);
}

/**
 * Lấy thông tin tồn kho sản phẩm - /shared/commodity/inventory
 * @param string $domain Domain API
 * @param int $app_id App ID 
 * @param string $app_key App Key
 * @param string $sharedCode Mã sản phẩm
 * @param string $race Loại sản phẩm (nếu có)
 * @param string $proxy Proxy (nếu có)
 * @return string JSON response với count là số lượng tồn kho
 */
function inventory_API_38($domain, $app_id, $app_key, $sharedCode, $race = '', $proxy = '')
{
    $data = [
        'app_id' => $app_id,
        'app_key' => $app_key,
        'sharedCode' => $sharedCode
    ];
    if (!empty($race)) {
        $data['race'] = $race;
    }
    $data['sign'] = build_sign_API_38($data, $app_key);
    return call_API_38($domain, '/shared/commodity/inventory', $data, $proxy);
}

/**
 * Kiểm tra tồn kho - /shared/commodity/inventoryState
 */
function inventoryState_API_38($domain, $app_id, $app_key, $shared_code, $num, $proxy = '')
{
    $data = [
        'app_id' => $app_id,
        'app_key' => $app_key,
        'shared_code' => $shared_code,
        'num' => $num
    ];
    $data['sign'] = build_sign_API_38($data, $app_key);
    return call_API_38($domain, '/shared/commodity/inventoryState', $data, $proxy);
}

/**
 * Tạo đơn hàng - /shared/commodity/trade
 */
function buy_API_38($domain, $app_id, $app_key, $shared_code, $num, $proxy = '')
{
    $data = [
        'app_id' => $app_id,
        'app_key' => $app_key,
        'shared_code' => $shared_code,
        'num' => $num
    ];
    $data['sign'] = build_sign_API_38($data, $app_key);
    return call_API_38($domain, '/shared/commodity/trade', $data, $proxy);
}

/**
 * Tra cứu đơn hàng - /shared/commodity/query
 */
function getOrder_API_38($domain, $app_id, $app_key, $tradeNo, $proxy = '')
{
    $data = [
        'app_id' => $app_id,
        'app_key' => $app_key,
        'tradeNo' => $tradeNo
    ];
    $data['sign'] = build_sign_API_38($data, $app_key);
    return call_API_38($domain, '/shared/commodity/query', $data, $proxy);
}
