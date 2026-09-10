<?php if (!defined('IN_SITE')) {
    die('The Request Not Found');
}
$body = [
    'title' => __('Đăng nhập').' | '.$CMSNT->site('title'),
    'desc'   => $CMSNT->site('description'),
    'keyword' => $CMSNT->site('keywords')
];
$body['header'] = '
<script src="https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.6/clipboard.min.js"></script>
<link rel="stylesheet" href="'.BASE_URL('public/client/').'css/wallet.css">
' . renderCaptchaScripts('login') . '
';
$body['footer'] = '

';
require_once(__DIR__.'/header.php');
require_once(__DIR__.'/nav.php');


  
?>

<section class="py-5 inner-section profile-part">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-12 col-md-12 col-lg-6 col-xl-6">

                <div class="user-form-card">
                    <div class="user-form-title">
                        <h2><?=__('Đăng Nhập');?></h2>
                        <p><?=__('Vui lòng nhập thông tin đăng nhập');?></p>
                    </div>
                    <div class="user-form-group">
                        
                        <form class="user-form">
                            <div class="form-group">
                                <input type="hidden" id="csrf_token" value="<?=generate_csrf_token();?>">
                                <input type="text" id="page-login-username" class="form-control" value="<?=$CMSNT->site('status_demo') == 1 ? 'admin' : '';?>"
                                    placeholder="<?=__('Vui lòng nhập username');?>" autocomplete="username">
                            </div>
                            <div class="form-group">
                                <input type="password" id="page-login-password" class="form-control" value="<?=$CMSNT->site('status_demo') == 1 ? 'admin' : '';?>"
                                    placeholder="<?=__('Vui lòng nhập mật khẩu');?>" autocomplete="current-password">
                            </div>
                            <?php if(isCaptchaEnabledForModule('login')): ?>
                            <center class="mb-3" id="captcha-container">
                                <?=renderCaptchaWidget('captcha-container', 'login');?>
                            </center>
                            <?php endif; ?>
                            <div class="form-button">
                            <button type="button" id="btnLoginPage"><?=__('Đăng Nhập');?></button>
                                <p><a href="<?=base_url('client/forgot-password');?>"><?=__('Quên mật khẩu?');?></a></p>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="user-form-remind">
                <p><?=__('Bạn chưa có tài khoản?');?> <a href="<?=base_url('client/register');?>"><?=__('Đăng Ký Ngay');?></a></p>
                </div>
            </div>
        </div>
    </div>
</section>


<?php
require_once(__DIR__.'/footer.php');
?>

<script type="text/javascript">
$("#btnLoginPage").on("click", function() {
    $('#btnLoginPage').html('<i class="fa fa-spinner fa-spin"></i> <?=__('Đang xử lý...');?>').prop('disabled',
        true);
    <?php if(isCaptchaEnabledForModule('login')): ?>
    var __captchaVal = (typeof getCaptchaResponse === 'function') ? getCaptchaResponse() : $("#g-recaptcha-response").val();
    if(!__captchaVal){
        Swal.fire('<?=__('Failure!');?>', '<?=__('Vui lòng xác nhận Captcha');?>', 'error');
        $('#btnLoginPage').html('<?=__('Đăng Nhập');?>').prop('disabled', false);
        return;
    }
    <?php endif; ?>
    var ajaxData = {
        action: 'Login',
        csrf_token: $("#csrf_token").val(),
        username: $("#page-login-username").val(),
        password: $("#page-login-password").val()
    };
    <?php if(isCaptchaEnabledForModule('login')): ?>
    ajaxData.captcha_response = (typeof getCaptchaResponse === 'function') ? getCaptchaResponse() : $("#g-recaptcha-response").val();
    ajaxData.recaptcha = (typeof getCaptchaResponse === 'function') ? getCaptchaResponse() : $("#g-recaptcha-response").val();
    ajaxData['cf-turnstile-response'] = (typeof getCaptchaResponse === 'function') ? getCaptchaResponse() : '';
    <?php endif; ?>
    
    $.ajax({
        url: "<?=base_url('ajaxs/client/auth.php');?>",
        method: "POST",
        dataType: "JSON",
        data: ajaxData,
        success: function(respone) {
            if (respone.status == 'success') {
                Swal.fire({
                    title: '<?=__('Successful!');?>',
                    text: respone.msg,
                    icon: 'success',
                    confirmButtonColor: '#3085d6',
                    confirmButtonText: 'OK'
                }).then((result) => {
                    if (result.isConfirmed) {
                        location.href = '<?=BASE_URL('');?>';
                    }
                });
                setTimeout("location.href = '<?=BASE_URL('');?>';", 10);
            } else if (respone.status == 'verify') {
                Swal.fire('<?=__('Warning!');?>', respone.msg, 'warning');
                setTimeout("location.href = '" + respone.url + "';", 2000);
            } else {
                Swal.fire('<?=__('Failure!');?>', respone.msg, 'error');
            }

            <?php if($CMSNT->site('google_analytics_status') == 1):?>
            gtag('event', 'login', {
            method: 'Website Form'
            });
            <?php endif?>
            $('#btnLoginPage').html('<?=__('Đăng Nhập');?>').prop('disabled', false);
        },
        error: function() {
            showMessage('<?=__('Vui lòng liên hệ Developer');?>', 'error');
            $('#btnLoginPage').html('<?=__('Đăng Nhập');?>').prop('disabled', false);
        }

    });
});
</script>
 