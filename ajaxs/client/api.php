<?php
/**
 * Client API Key Management Handler
 * Cho phép user tự quản lý API keys của họ
 * 
 * @package SHOPKEY
 * @author CMSNT
 */

define("IN_SITE", true);
require_once(__DIR__."/../../libs/db.php");
require_once(__DIR__."/../../libs/lang.php");
require_once(__DIR__."/../../libs/helper.php");
require_once(__DIR__."/../../config.php");
require_once(__DIR__.'/../../libs/services/ApiKeyService.php');

if ($CMSNT->site('status') != 1) {
    die(json_encode([
        'status' => 'error',
        'msg' => __('Hệ thống đang bảo trì!')
    ]));
}

if(!isset($_POST['action'])){
    die(json_encode([
        'status' => 'error',
        'msg' => __('The Request Not Found')
    ]));   
}

// Kiểm tra CSRF token
checkCSRFAjax();

if ($CMSNT->site('status_demo') != 0) {
    die(json_encode(['status' => 'error', 'msg' => __('Chức năng này không thể sử dụng trên website demo')]));
}

// Kiểm tra cài đặt cho phép user sử dụng API
if ($CMSNT->site('api_user_enabled') != 1) {
    die(json_encode(['status' => 'error', 'msg' => __('Chức năng API chưa được kích hoạt')]));
}

// Xác thực user
$token = isset($_POST['token']) ? validate_string($_POST['token'], 255) : false;
if(!$token){
    die(json_encode([
        'status' => 'error',
        'msg' => __('Vui lòng đăng nhập')
    ]));
}

$getUser = $CMSNT->get_row_safe("SELECT * FROM `users` WHERE `token` = ? AND `banned` = 0", [$token]);
if(!$getUser){
    checkBlockIP('SCAN_TOKEN', 1);
    die(json_encode([
        'status' => 'error',
        'msg' => __('Phiên đăng nhập không hợp lệ')
    ]));
}

/**
 * Tạo API Key mới cho user
 */
if($_POST['action'] == 'create_api_key'){
    // Giới hạn số API key mỗi user
    $max_api_keys = (int)($CMSNT->site('api_max_keys_per_user') ?: 3);
    $current_count = $CMSNT->num_rows_safe(
        "SELECT id FROM `api_keys` WHERE `user_id` = ?",
        [$getUser['id']]
    );
    
    if($current_count >= $max_api_keys){
        die(json_encode([
            'status' => 'error',
            'msg' => sprintf(__('Bạn chỉ được tạo tối đa %d API key'), $max_api_keys)
        ]));
    }
    
    // Lấy tên API key (tùy chọn)
    $key_name = isset($_POST['key_name']) ? validate_string($_POST['key_name'], 100) : '';
    if($key_name === false) $key_name = '';
    if(empty($key_name)){
        $key_name = 'API Key #' . ($current_count + 1);
    }
    
    // Lấy IP whitelist (tùy chọn)
    $ip_whitelist = isset($_POST['ip_whitelist']) ? validate_string($_POST['ip_whitelist'], 1000) : '';
    if($ip_whitelist === false) $ip_whitelist = '';
    
    // Validate IP whitelist format
    if(!empty($ip_whitelist)){
        $ips = array_map('trim', explode(',', $ip_whitelist));
        foreach($ips as $ip){
            if(!empty($ip) && !filter_var($ip, FILTER_VALIDATE_IP)){
                die(json_encode([
                    'status' => 'error',
                    'msg' => sprintf(__('Địa chỉ IP không hợp lệ: %s'), htmlspecialchars($ip))
                ]));
            }
        }
        $ip_whitelist = implode(',', array_filter($ips));
    }
    
    // Generate API key và secret
    $ApiService = new ApiKeyService();
    $api_key = $ApiService->generateApiKey();
    $api_secret = $ApiService->generateApiSecret();
    
    // Quyền mặc định cho user (hạn chế hơn admin)
    $default_permissions = ['orders.create', 'orders.list', 'orders.status', 'products.list', 'account.balance', 'account.info'];
    
    // Rate limit mặc định cho user
    $rate_limit_per_minute = (int)($CMSNT->site('api_user_rate_limit_minute') ?: 30);
    $rate_limit_per_day = (int)($CMSNT->site('api_user_rate_limit_day') ?: 1000);
    
    // Tạo API key
    $insert_id = $CMSNT->insert('api_keys', [
        'user_id' => $getUser['id'],
        'key_name' => $key_name,
        'api_key' => $api_key,
        'api_secret' => $api_secret, // Lưu trực tiếp, không hash (64 ký tự hex đủ bảo mật)
        'permissions' => json_encode($default_permissions),
        'ip_whitelist' => $ip_whitelist ?: null,
        'rate_limit_per_minute' => $rate_limit_per_minute,
        'rate_limit_per_day' => $rate_limit_per_day,
        'status' => 1,
        'created_at' => gettime(),
        'updated_at' => gettime()
    ]);
    
    if(!$insert_id){
        die(json_encode([
            'status' => 'error',
            'msg' => __('Không thể tạo API key. Vui lòng thử lại.')
        ]));
    }
    
    // Log hành động
    $CMSNT->insert("logs", [
        'user_id' => $getUser['id'],
        'ip' => myip(),
        'device' => getUserAgent(),
        'createdate' => gettime(),
        'action' => __('Tạo API Key') . " ({$key_name})"
    ]);
    
    die(json_encode([
        'status' => 'success',
        'msg' => __('Tạo API Key thành công! Hãy lưu lại Secret Key vì nó chỉ hiển thị một lần.'),
        'data' => [
            'id' => $insert_id,
            'key_name' => $key_name,
            'api_key' => $api_key,
            'api_secret' => $api_secret, // Chỉ hiển thị một lần
            'permissions' => $default_permissions,
            'rate_limit_per_minute' => $rate_limit_per_minute,
            'rate_limit_per_day' => $rate_limit_per_day
        ]
    ]));
}

/**
 * Cập nhật API Key
 */
if($_POST['action'] == 'update_api_key'){
    $key_id = isset($_POST['key_id']) ? validate_int($_POST['key_id'], 1) : 0;
    if(!$key_id){
        die(json_encode(['status' => 'error', 'msg' => __('ID không hợp lệ')]));
    }
    
    // Kiểm tra API key thuộc về user
    $api_key = $CMSNT->get_row_safe(
        "SELECT * FROM `api_keys` WHERE `id` = ? AND `user_id` = ?",
        [$key_id, $getUser['id']]
    );
    
    if(!$api_key){
        die(json_encode(['status' => 'error', 'msg' => __('API Key không tồn tại')]));
    }
    
    // Lấy dữ liệu cập nhật
    $key_name = isset($_POST['key_name']) ? validate_string($_POST['key_name'], 100) : $api_key['key_name'];
    if($key_name === false) $key_name = $api_key['key_name'];
    
    $ip_whitelist = isset($_POST['ip_whitelist']) ? validate_string($_POST['ip_whitelist'], 1000) : '';
    if($ip_whitelist === false) $ip_whitelist = '';
    
    // Validate IP whitelist
    if(!empty($ip_whitelist)){
        $ips = array_map('trim', explode(',', $ip_whitelist));
        foreach($ips as $ip){
            if(!empty($ip) && !filter_var($ip, FILTER_VALIDATE_IP)){
                die(json_encode([
                    'status' => 'error',
                    'msg' => sprintf(__('Địa chỉ IP không hợp lệ: %s'), htmlspecialchars($ip))
                ]));
            }
        }
        $ip_whitelist = implode(',', array_filter($ips));
    }
    
    // Cập nhật
    $isUpdate = $CMSNT->update('api_keys', [
        'key_name' => $key_name,
        'ip_whitelist' => $ip_whitelist ?: null,
        'updated_at' => gettime()
    ], "`id` = ?", [$key_id]);
    
    if(!$isUpdate){
        die(json_encode(['status' => 'error', 'msg' => __('Cập nhật thất bại')]));
    }
    
    // Log hành động
    $CMSNT->insert("logs", [
        'user_id' => $getUser['id'],
        'ip' => myip(),
        'device' => getUserAgent(),
        'createdate' => gettime(),
        'action' => __('Cập nhật API Key') . " ({$key_name})"
    ]);
    
    die(json_encode([
        'status' => 'success',
        'msg' => __('Cập nhật API Key thành công!')
    ]));
}

/**
 * Bật/Tắt trạng thái API Key
 */
if($_POST['action'] == 'toggle_api_key'){
    $key_id = isset($_POST['key_id']) ? validate_int($_POST['key_id'], 1) : 0;
    if(!$key_id){
        die(json_encode(['status' => 'error', 'msg' => __('ID không hợp lệ')]));
    }
    
    // Kiểm tra API key thuộc về user
    $api_key = $CMSNT->get_row_safe(
        "SELECT * FROM `api_keys` WHERE `id` = ? AND `user_id` = ?",
        [$key_id, $getUser['id']]
    );
    
    if(!$api_key){
        die(json_encode(['status' => 'error', 'msg' => __('API Key không tồn tại')]));
    }
    
    $new_status = $api_key['status'] == 1 ? 0 : 1;
    
    $CMSNT->update('api_keys', [
        'status' => $new_status,
        'updated_at' => gettime()
    ], "`id` = ?", [$key_id]);
    
    // Log hành động
    $status_text = $new_status == 1 ? __('Kích hoạt') : __('Vô hiệu hóa');
    $CMSNT->insert("logs", [
        'user_id' => $getUser['id'],
        'ip' => myip(),
        'device' => getUserAgent(),
        'createdate' => gettime(),
        'action' => $status_text . ' API Key (' . $api_key['key_name'] . ')'
    ]);
    
    die(json_encode([
        'status' => 'success',
        'msg' => sprintf(__('%s API Key thành công!'), $status_text),
        'new_status' => $new_status
    ]));
}

/**
 * Xóa API Key
 */
if($_POST['action'] == 'delete_api_key'){
    $key_id = isset($_POST['key_id']) ? validate_int($_POST['key_id'], 1) : 0;
    if(!$key_id){
        die(json_encode(['status' => 'error', 'msg' => __('ID không hợp lệ')]));
    }
    
    // Kiểm tra API key thuộc về user
    $api_key = $CMSNT->get_row_safe(
        "SELECT * FROM `api_keys` WHERE `id` = ? AND `user_id` = ?",
        [$key_id, $getUser['id']]
    );
    
    if(!$api_key){
        die(json_encode(['status' => 'error', 'msg' => __('API Key không tồn tại')]));
    }
    
    // Xóa API key
    $CMSNT->remove('api_keys', "`id` = ?", [$key_id]);
    
    // Xóa logs liên quan (tùy chọn - có thể giữ lại để audit)
    // $CMSNT->remove('api_logs', "`api_key_id` = ?", [$key_id]);
    
    // Log hành động
    $CMSNT->insert("logs", [
        'user_id' => $getUser['id'],
        'ip' => myip(),
        'device' => getUserAgent(),
        'createdate' => gettime(),
        'action' => __('Xóa API Key') . ' (' . $api_key['key_name'] . ')'
    ]);
    
    die(json_encode([
        'status' => 'success',
        'msg' => __('Xóa API Key thành công!')
    ]));
}

/**
 * Regenerate API Secret
 */
if($_POST['action'] == 'regenerate_secret'){
    $key_id = isset($_POST['key_id']) ? validate_int($_POST['key_id'], 1) : 0;
    if(!$key_id){
        die(json_encode(['status' => 'error', 'msg' => __('ID không hợp lệ')]));
    }
    
    // Kiểm tra API key thuộc về user
    $api_key = $CMSNT->get_row_safe(
        "SELECT * FROM `api_keys` WHERE `id` = ? AND `user_id` = ?",
        [$key_id, $getUser['id']]
    );
    
    if(!$api_key){
        die(json_encode(['status' => 'error', 'msg' => __('API Key không tồn tại')]));
    }
    
    // Generate secret mới
    $ApiService = new ApiKeyService();
    $new_secret = $ApiService->generateApiSecret();
    
    $CMSNT->update('api_keys', [
        'api_secret' => $new_secret, // Lưu trực tiếp, không hash
        'updated_at' => gettime()
    ], "`id` = ?", [$key_id]);
    
    // Log hành động
    $CMSNT->insert("logs", [
        'user_id' => $getUser['id'],
        'ip' => myip(),
        'device' => getUserAgent(),
        'createdate' => gettime(),
        'action' => __('Tạo lại API Secret') . ' (' . $api_key['key_name'] . ')'
    ]);
    
    die(json_encode([
        'status' => 'success',
        'msg' => __('Tạo lại API Secret thành công! Hãy lưu lại vì nó chỉ hiển thị một lần.'),
        'new_secret' => $new_secret
    ]));
}

/**
 * Lấy danh sách API Logs của user
 */
if($_POST['action'] == 'get_api_logs'){
    $key_id = isset($_POST['key_id']) ? validate_int($_POST['key_id'], 1) : 0;
    $page = isset($_POST['page']) ? validate_int($_POST['page'], 1) : 1;
    $limit = 20;
    $offset = ($page - 1) * $limit;
    
    $where = "`user_id` = ?";
    $params = [$getUser['id']];
    
    if($key_id > 0){
        // Kiểm tra API key thuộc về user
        $api_key = $CMSNT->get_row_safe(
            "SELECT * FROM `api_keys` WHERE `id` = ? AND `user_id` = ?",
            [$key_id, $getUser['id']]
        );
        
        if($api_key){
            $where .= " AND `api_key_id` = ?";
            $params[] = $key_id;
        }
    }
    
    $total = $CMSNT->num_rows_safe("SELECT id FROM `api_logs` WHERE {$where}", $params);
    
    $params[] = $offset;
    $params[] = $limit;
    
    $logs = $CMSNT->get_list_safe(
        "SELECT * FROM `api_logs` WHERE {$where} ORDER BY `id` DESC LIMIT ?, ?",
        $params
    );
    
    die(json_encode([
        'status' => 'success',
        'data' => [
            'logs' => $logs,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit)
        ]
    ]));
}

die(json_encode([
    'status' => 'error',
    'msg' => __('Request does not exist')
]));

