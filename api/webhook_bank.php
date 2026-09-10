<?php

/**
 * Webhook nhận giao dịch ngân hàng từ Web2M
 * 
 * API Documentation:
 * - Method: POST
 * - Content-Type: application/json
 * - Authorization: Bearer {access_token}
 * 
 * @package SHOPKEY
 * @version 1.0.0
 */

define("IN_SITE", true);
require_once(__DIR__ . '/../libs/db.php');
require_once(__DIR__ . '/../libs/lang.php');
require_once(__DIR__ . '/../libs/helper.php');
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../libs/database/users.php');
require_once(__DIR__ . '/../libs/database/affiliate.php');

// Khởi tạo các class cần thiết
$CMSNT = new DB();
$user = new users();
$AffiliateHandler = new AffiliateHandler();

// Đặt Content-Type response là JSON
header('Content-Type: application/json; charset=utf-8');

/**
 * Trả về response JSON
 * 
 * @param bool $status Trạng thái thành công hay thất bại
 * @param string $msg Thông báo
 * @param int $httpCode HTTP status code
 */
function jsonResponse($status, $msg, $httpCode = 200)
{
    http_response_code($httpCode);
    echo json_encode([
        'status' => $status,
        'msg' => $msg
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Ghi log webhook (nếu debug được bật)
 * 
 * @param string $message Nội dung log
 */
function webhookLog($message)
{
    global $CMSNT;
    if ($CMSNT->site('debug_auto_bank') == 1) {
        error_log('[WEBHOOK_BANK] ' . date('Y-m-d H:i:s') . ' - ' . $message);
    }
}

// Lấy Access Token từ cài đặt trang web
$accessToken = $CMSNT->site('token_webhook_web2m');
if (empty($accessToken)) {
    webhookLog('token_webhook_web2m is not configured');
    jsonResponse(false, 'Webhook chưa được cấu hình', 500);
}

// Kiểm tra phương thức request - chỉ chấp nhận POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    webhookLog('Invalid request method: ' . $_SERVER['REQUEST_METHOD']);
    jsonResponse(false, 'Method not allowed', 405);
}

// Kiểm tra Authorization header
$bearerToken = '';

// Lấy Authorization header từ nhiều nguồn có thể
if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
} elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
} elseif (function_exists('apache_request_headers')) {
    $headers = apache_request_headers();
    $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
} else {
    $authHeader = '';
}

// Kiểm tra Bearer Token
if (!empty($authHeader) && strpos($authHeader, 'Bearer ') === 0) {
    $bearerToken = substr($authHeader, 7); // Lấy chuỗi token sau 'Bearer '
} else {
    webhookLog('Authorization header missing or invalid');
    jsonResponse(false, 'Access Token không được cung cấp hoặc không hợp lệ.', 401);
}

// Xác thực token
if ($accessToken !== $bearerToken) {
    webhookLog('Token mismatch');
    jsonResponse(false, 'Chữ ký không hợp lệ.', 401);
}

// Nhận dữ liệu JSON từ body
$receivedData = file_get_contents('php://input');
$data = json_decode($receivedData, true);

// Kiểm tra dữ liệu hợp lệ
if (json_last_error() !== JSON_ERROR_NONE) {
    webhookLog('Invalid JSON data: ' . json_last_error_msg());
    jsonResponse(false, 'Dữ liệu không hợp lệ', 400);
}

// Kiểm tra status và data từ webhook
if (!isset($data['status']) || $data['status'] !== true) {
    webhookLog('Webhook status is not true');
    jsonResponse(false, 'Status không hợp lệ', 400);
}

if (!isset($data['data']) || !is_array($data['data']) || empty($data['data'])) {
    webhookLog('No transaction data received');
    // Vẫn trả về success để Web2M không gửi lại
    jsonResponse(true, 'OK');
}

webhookLog('Received ' . count($data['data']) . ' transactions');

$processedCount = 0;

// Xử lý từng giao dịch
foreach ($data['data'] as $transaction) {
    // Validate dữ liệu giao dịch
    $tid_raw = isset($transaction['transactionID']) ? $transaction['transactionID'] : '';
    $tid = validate_alphanumeric($tid_raw, 255);
    $description = validate_string(isset($transaction['description']) ? $transaction['description'] : '', 1000);
    $amount = validate_float(isset($transaction['amount']) ? $transaction['amount'] : 0, 0.0);
    $type = validate_string(isset($transaction['type']) ? $transaction['type'] : '', 10);
    $bankName = validate_string(isset($transaction['bank']) ? $transaction['bank'] : '', 50);
    $id = isset($transaction['id']) ? $transaction['id'] : '';

    webhookLog("Processing transaction: ID={$id}, TID={$tid}, Amount={$amount}, Type={$type}, Bank={$bankName}");

    // Bỏ qua giao dịch rút tiền (OUT)
    if (strtoupper($type) === 'OUT') {
        webhookLog("Skipped OUT transaction: {$tid}");
        continue;
    }

    // Bỏ qua nếu không có thông tin cần thiết
    if (empty($tid) || $amount <= 0 || empty($bankName)) {
        webhookLog("Skipped invalid transaction: TID={$tid}, Amount={$amount}, Bank={$bankName}");
        continue;
    }

    // Kiểm tra giao dịch đã được xử lý chưa
    if ($CMSNT->num_rows_safe(
        "SELECT * FROM `payment_bank_invoice` WHERE `api_tid` = ? AND `api_desc` = ? AND `short_name` = ?",
        [$tid, $description, $bankName]
    ) > 0) {
        webhookLog("Transaction already processed: {$tid}");
        continue;
    }

    // Tìm các hóa đơn đang chờ thanh toán phù hợp
    $waiting_invoices = whereInvoiceWaiting($bankName, $amount);

    if (empty($waiting_invoices)) {
        webhookLog("No matching invoice found for amount: {$amount}, bank: {$bankName}");
        continue;
    }

    webhookLog("Found " . count($waiting_invoices) . " waiting invoices for amount: {$amount}");

    foreach ($waiting_invoices as $invoice) {
        // Tìm trans_id trong nội dung chuyển khoản
        $exploded = explode(
            mb_strtoupper($invoice['trans_id']),
            mb_strtoupper(str_replace(' ', '', $description))
        );

        if (isset($exploded[1])) {
            webhookLog("Found matching trans_id: {$invoice['trans_id']} in description");

            // Cập nhật trạng thái hóa đơn với retry mechanism
            $isUpdate = false;
            $retryCount = 0;
            $maxRetries = 3;

            while ($retryCount < $maxRetries && !$isUpdate) {
                if ($retryCount > 0) {
                    webhookLog("Retry attempt {$retryCount} after 2 seconds...");
                    sleep(2);
                }

                $isUpdate = $CMSNT->update("payment_bank_invoice", [
                    'status' => 'completed',
                    'api_tid' => $tid,
                    'api_desc' => $description,
                    'api_type' => 'WEB2M_WEBHOOK',
                    'updated_at' => gettime()
                ], " `id` = ? AND `status` = 'waiting' ", [$invoice['id']]);

                $retryCount++;
            }

            if ($isUpdate) {
                webhookLog("Invoice {$invoice['id']} updated successfully");

                // Cộng tiền cho user
                $isCong = $user->AddCredits(
                    $invoice['user_id'],
                    $invoice['received'],
                    __('Thanh toán hoá đơn nạp tiền') . ' #' . $invoice['trans_id'],
                    'INVOICE_' . $invoice['trans_id']
                );

                if ($isCong) {
                    webhookLog("Credits added for user {$invoice['user_id']}: " . $invoice['received']);

                    // Lấy thông tin user
                    $getUser = $CMSNT->get_row_safe("SELECT * FROM `users` WHERE `id` = ?", [$invoice['user_id']]);

                    if ($getUser) {
                        // Cộng hoa hồng affiliate
                        $AffiliateHandler->processRechargeCommission(
                            $getUser['id'],
                            $invoice['received'],
                            'INVOICE_' . $invoice['trans_id']
                        );

                        // Xử lý tiền nợ nếu có
                        debit_processing($getUser['id']);

                        // Tạo log giao dịch
                        $CMSNT->insert('deposit_log', [
                            'user_id' => $invoice['user_id'],
                            'method' => $bankName,
                            'amount' => $invoice['amount'],
                            'received' => $invoice['received'],
                            'create_time' => time(),
                            'is_virtual' => 0
                        ]);

                        // Gửi thông báo cho admin
                        $my_text = $CMSNT->site('noti_recharge');
                        $my_text = str_replace('{domain}', check_string($_SERVER['SERVER_NAME'] ?? ''), $my_text);
                        $my_text = str_replace('{title}', $CMSNT->site('title'), $my_text);
                        $my_text = str_replace('{trans_id}', $invoice['trans_id'], $my_text);
                        $my_text = str_replace('{username}', getRowRealtime('users', $invoice['user_id'], 'username'), $my_text);
                        $my_text = str_replace('{method}', $bankName, $my_text);
                        $my_text = str_replace('{amount}', format_currency($invoice['amount']), $my_text);
                        $my_text = str_replace('{price}', format_currency($invoice['received']), $my_text);
                        $my_text = str_replace('{time}', gettime(), $my_text);
                        sendMessAdmin($my_text);

                        $processedCount++;
                        webhookLog("Transaction processed successfully: {$tid}");
                    }
                } else {
                    webhookLog("Failed to add credits for user {$invoice['user_id']}");
                }

                // Thoát khỏi vòng lặp invoice sau khi xử lý thành công
                break;
            } else {
                webhookLog("Failed to update invoice {$invoice['id']} after {$retryCount} retries");
                break;
            }
        }
    }
}

webhookLog("Webhook processing completed. Processed {$processedCount} transactions.");

// Trả về response thành công (bắt buộc phải có để Web2M không gửi lại)
jsonResponse(true, 'OK');
