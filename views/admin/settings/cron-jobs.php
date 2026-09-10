<?php if (!defined('IN_SITE')) {
    die('The Request Not Found');
}
?>
<div class="tab-pane text-muted show active" id="cron-jobs" role="tabpanel">
    <h4><?= __('Cron Jobs'); ?></h4>
    <div class="alert alert-info border-0 mb-4">
        <div class="d-flex align-items-center">
            <div class="me-2">
                <i class="ri-information-line fs-16"></i>
            </div>
            <div>
                <strong><?= __('Hướng dẫn:'); ?></strong> <?= __('Thiết lập các Cron Jobs sau trên hosting/server để hệ thống hoạt động tự động. Nhấn nút Copy để sao chép link.'); ?>
                <br><small><?= __('Tham khảo hướng dẫn chi tiết tại:'); ?> <a href="https://help.cmsnt.co/huong-dan/huong-dan-xu-ly-khi-website-bao-loi-cron/" target="_blank" class="text-primary"><?= __('CMSNT Help'); ?></a></small>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card custom-card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th width="25%"><?= __('Tên Cron Job'); ?></th>
                                    <th width="35%"><?= __('Đường dẫn'); ?></th>
                                    <th width="15%" class="text-center"><?= __('Thời gian khuyến nghị'); ?></th>
                                    <th width="15%"><?= __('Lần chạy cuối'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $cronJobs = [
                                    [
                                        'name' => __('Cron Job Chính'),
                                        'description' => __('Xử lý hóa đơn, tính toán thời gian trung bình'),
                                        'path' => 'cron/cron.php',
                                        'recommended_time' => __('5 phút'),
                                        'setting_name' => 'check_time_cron_cron'
                                    ],
                                    [
                                        'name' => __('Cron Job Bank'),
                                        'description' => __('Xử lý nạp tiền tự động qua ngân hàng'),
                                        'path' => 'cron/bank.php',
                                        'recommended_time' => __('1 phút'),
                                        'setting_name' => 'check_time_cron_bank'
                                    ],

                                    [
                                        'name' => __('Task Automation'),
                                        'description' => __('Xử lý các tác vụ tự động'),
                                        'path' => 'cron/task.php',
                                        'recommended_time' => __('5 phút'),
                                        'setting_name' => 'check_time_cron_task'
                                    ],
                                    [
                                        'name' => __('Gửi Email'),
                                        'description' => __('Xử lý gửi email hàng loạt'),
                                        'path' => 'cron/sending_email.php',
                                        'recommended_time' => __('1 phút'),
                                        'setting_name' => 'check_time_cron_sending_email'
                                    ],
                                    [
                                        'name' => __('Email Queue - Đơn hàng'),
                                        'description' => __('Gửi email thông báo đơn hàng qua queue (không ảnh hưởng tốc độ)'),
                                        'path' => 'cron/process_email_queue.php',
                                        'recommended_time' => __('1 phút'),
                                        'setting_name' => 'check_time_cron_email_queue'
                                    ],
                                    [
                                        'name' => __('Telegram Queue'),
                                        'description' => __('Gửi thông báo Telegram qua queue (không ảnh hưởng tốc độ checkout)'),
                                        'path' => 'cron/process_telegram_queue.php',
                                        'recommended_time' => __('1 phút'),
                                        'setting_name' => 'check_time_cron_telegram_queue'
                                    ],
                                    [
                                        'name' => __('SHOPKEY API Supplier'),
                                        'description' => __('Đồng bộ sản phẩm từ API SHOPKEY'),
                                        'path' => 'cron/suppliers/shopkey.php',
                                        'recommended_time' => __('1 phút'),
                                        'setting_name' => 'time_cron_suppliers_shopkey'
                                    ],
                                    [
                                        'name' => __('SHOPCLONE7 API Supplier'),
                                        'description' => __('Đồng bộ sản phẩm từ API SHOPCLONE7'),
                                        'path' => 'cron/suppliers/shopclone7.php',
                                        'recommended_time' => __('1 phút'),
                                        'setting_name' => 'time_cron_suppliers_shopclone7'
                                    ],
                                    [
                                        'name' => __('SHOPCLONE6 API Supplier'),
                                        'description' => __('Đồng bộ sản phẩm từ API SHOPCLONE6'),
                                        'path' => 'cron/suppliers/shopclone6.php',
                                        'recommended_time' => __('1 phút'),
                                        'setting_name' => 'time_cron_suppliers_SHOPCLONE6'
                                    ]
                                ];

                                foreach ($cronJobs as $index => $job):
                                    $cronUrl = base_url($job['path'] . '?key=' . $CMSNT->site('key_cron_job'));
                                    $lastRun = $CMSNT->site($job['setting_name']);
                                    $lastRunFormatted = $lastRun && $lastRun > 0 ? timeAgo($lastRun) : __('Chưa chạy');

                                    // Kiểm tra cron job chạy trong 5 phút gần nhất (300 giây)
                                    $isActive = $lastRun && $lastRun > 0 && (time() - $lastRun) <= 300;
                                    $bgColor = $isActive ? 'rgba(25, 135, 84, 0.12)' : 'rgba(220, 53, 69, 0.12)';
                                ?>
                                    <tr>
                                        <td style="background-color: <?= $bgColor; ?>">
                                            <div>
                                                <strong class="text-primary"><?= $job['name']; ?></strong>
                                                <br><small class="text-muted"><?= $job['description']; ?></small>
                                            </div>
                                        </td>
                                        <td style="background-color: <?= $bgColor; ?>">
                                            <div class="input-group">
                                                <input type="text" class="form-control form-control-sm"
                                                    id="cronUrl<?= $index; ?>"
                                                    value="<?= $cronUrl; ?>"
                                                    readonly
                                                    style="font-size: 12px;">
                                                <button class="btn btn-outline-secondary btn-sm"
                                                    type="button"
                                                    onclick="copyToClipboard('cronUrl<?= $index; ?>')"
                                                    title="<?= __('Copy đường dẫn'); ?>">
                                                    <i class="ri-file-copy-line"></i>
                                                </button>
                                            </div>
                                        </td>
                                        <td class="text-center" style="background-color: <?= $bgColor; ?>">
                                            <span class="badge bg-danger"><?= $job['recommended_time']; ?></span>
                                        </td>
                                        <td style="background-color: <?= $bgColor; ?>">
                                            <small class="<?= ($lastRun && $lastRun > 0) ? 'text-success' : 'text-warning'; ?>">
                                                <?= $lastRunFormatted; ?>
                                            </small>
                                        </td>

                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        <div class="alert alert-warning border-0">
                            <div class="d-flex">
                                <div class="me-2">
                                    <i class="ri-alert-line fs-16"></i>
                                </div>
                                <div>
                                    <strong><?= __('Lưu ý quan trọng:'); ?></strong>
                                    <ul class="mb-0 mt-2">
                                        <li><?= __('Key Cron Job hiện tại:'); ?> <code class="text-danger"><?= $CMSNT->site('key_cron_job'); ?></code></li>
                                        <li><?= __('Không chia sẻ key này với người khác vì lý do bảo mật'); ?></li>
                                        <li><?= __('Nếu muốn thay đổi key, vui lòng cập nhật trong phần Cài đặt -> Bảo mật'); ?></li>
                                        <li><?= __('Thời gian "Lần chạy cuối" sẽ cập nhật sau mỗi lần cron job được thực thi'); ?></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>