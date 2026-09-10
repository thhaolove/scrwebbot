<?php if (!defined('IN_SITE')) {
    die('The Request Not Found');
}
$body = [
    'title' => __('Đăng ký tài khoản').' | '.$CMSNT->site('title'),
    'desc'   => $CMSNT->site('description'),
    'keyword' => $CMSNT->site('keywords')
];
$body['header'] = '
<script src="https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.6/clipboard.min.js"></script>
<link rel="stylesheet" href="'.BASE_URL('public/client/').'css/wallet.css">
' . renderCaptchaScripts('register') . '
';
$body['footer'] = '

';
require_once(__DIR__.'/header.php');
require_once(__DIR__.'/nav.php');


  
?>
<style>
    #password-strength-status {
    margin-top: 10px;
    border-radius: 4px;
    font-weight: bold;
    text-align: center;
    font-size: 14px;
}
/* Màu nền và đường viền cho các mức độ mạnh của mật khẩu */
#password-strength-status.weak {
    color: #ff0000; /* Màu chữ đỏ cho mật khẩu yếu */
    background-color: #f8d7da; /* Màu nền đỏ nhạt */
    border: 1px solid #f5c6cb; /* Đường viền đỏ nhạt */
}

#password-strength-status.medium {
    color: #ff9800; /* Màu chữ cam cho mật khẩu trung bình */
    background-color: #fff3cd; /* Màu nền cam nhạt */
    border: 1px solid #ffeeba; /* Đường viền cam nhạt */
}

#password-strength-status.strong {
    color: #4caf50; /* Màu chữ xanh lá cho mật khẩu mạnh */
    background-color: #d4edda; /* Màu nền xanh lá nhạt */
    border: 1px solid #c3e6cb; /* Đường viền xanh lá nhạt */
}

</style>
<style>
/* Checkbox điều khoản đồng bộ style */
.agree-terms-row{display:flex;align-items:center;gap:10px}
#agree-terms{width:18px;height:18px;accent-color:#007bff}
.agree-terms-label{margin:0;cursor:pointer;user-select:none}
.terms-link{display:inline-block;margin-top:6px;color:#007bff;text-decoration:none}
.terms-link:hover{text-decoration:underline}
</style>
<section class="py-5 inner-section profile-part">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-12 col-md-12 col-lg-6 col-xl-6">
                <div class="user-form-card">
                    <div class="user-form-title">
                        <h2><?=__('Đăng ký tài khoản');?></h2>
                        <p><?=__('Vui lòng nhập thông tin đăng ký');?></p>
                    </div>
                    <div class="user-form-group">
                        <form class="user-form">
                            <div class="form-group">
                                <input type="hidden" id="csrf_token" value="<?=generate_csrf_token();?>">
                                <input type="text" class="form-control" id="register-username" autocomplete="username"
                                    placeholder="<?=__('Tài khoản đăng nhập');?>" minlength="3" maxlength="50" required>
                            </div>
                            <div class="form-group">
                                <input type="email" class="form-control" id="register-email" autocomplete="email"
                                    placeholder="<?=__('Địa chỉ Email');?>" maxlength="100" required>
                            </div>
                            <div class="form-group">
                                <input type="password" class="form-control" id="register-password" autocomplete="new-password"
                                    placeholder="<?=__('Mật khẩu');?>" minlength="6" maxlength="50" required>
                                <div id="password-strength-status"></div>
                            </div>
                            <div class="form-group">
                                <input type="password" class="form-control" id="register-password-confirm"
                                    placeholder="<?=__('Nhập lại mật khẩu');?>" minlength="6" maxlength="50" required>
                            </div>
                            <?php if(isCaptchaEnabledForModule('register')): ?>
                            <center class="mb-3" id="captcha-container">
                                <?=renderCaptchaWidget('captcha-container', 'register');?>
                            </center>
                            <?php endif; ?>
                            <?php if((int)$CMSNT->site('isConfirmPolicyRegister') == 1): ?>
                            <div class="form-group">
                                <div class="agree-terms-row">
                                    <input type="checkbox" id="agree-terms">
                                    <label class="agree-terms-label" for="agree-terms">
                                        <?=__('Tôi đồng ý với điều khoản/chính sách');?>
                                    </label>
                                </div>
                                <input type="hidden" id="agreed_policy" value="0">
                            </div>
                            <?php endif; ?>
                            <div class="form-button">
                                <button type="button" id="btnRegister"><?=__('Đăng Ký');?></button>
                                <p><?=__('Bạn đã có tài khoản?');?> <a href="<?=base_url('client/login');?>"><?=__('Đăng Nhập');?></a></p>
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


<?php if((int)$CMSNT->site('isConfirmPolicyRegister') == 1): ?>
<style>
/* Modal điều khoản */
.policy-modal{position:fixed;left:0;top:0;width:100%;height:100%;z-index:1050;display:none}
.policy-modal.show{display:block}
.policy-modal .policy-modal-overlay{position:absolute;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,0.5)}
.policy-modal .policy-modal-dialog{position:relative;background:#fff;max-width:800px;margin:5% auto;border-radius:6px;box-shadow:0 10px 30px rgba(0,0,0,.2);display:flex;flex-direction:column}
.policy-modal .policy-modal-header{padding:12px 16px;border-bottom:1px solid #eee;display:flex;align-items:center;justify-content:space-between}
.policy-modal .policy-modal-header h5{margin:0;font-size:18px}
.policy-modal .policy-modal-close{background:transparent;border:0;font-size:22px;line-height:1;cursor:pointer}
.policy-modal .policy-modal-body{padding:0 16px 0 16px;max-height:60vh;overflow:hidden}
.policy-modal .policy-content-scroll{padding:12px 0;max-height:60vh;overflow-y:auto}
.policy-modal .policy-modal-footer{padding:12px 16px;border-top:1px solid #eee;display:flex;gap:10px;justify-content:flex-end}
.policy-modal .btn{padding:8px 14px;border-radius:4px;border:1px solid transparent;cursor:pointer}
.policy-modal .btn-secondary{background:#e9ecef;color:#212529;border-color:#dee2e6}
.policy-modal .btn-primary{background:#007bff;color:#fff}
.policy-modal .btn-primary:disabled{background:#6c757d;border-color:#6c757d;cursor:not-allowed}
</style>

<div id="policy-modal" class="policy-modal" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="policy-modal-overlay"></div>
    <div class="policy-modal-dialog">
        <div class="policy-modal-header">
            <h5><?=__('Điều khoản và Chính sách');?></h5>
            <button type="button" class="policy-modal-close" aria-label="Close">&times;</button>
        </div>
        <div class="policy-modal-body">
            <div id="policy-content" class="policy-content-scroll">
                <?=$CMSNT->site('policy_register');?>
            </div>
        </div>
        <div class="policy-modal-footer">
            <button type="button" class="btn btn-secondary" id="btn-policy-cancel"><?=__('Hủy');?></button>
            <button type="button" class="btn btn-primary" id="btn-policy-accept" disabled><?=__('Tôi đã đọc và đồng ý');?></button>
        </div>
    </div>
    </div>
<?php endif; ?>


<?php
require_once(__DIR__.'/footer.php');
?>
<script>
document.getElementById('register-password').addEventListener('input', function() {
    var password = this.value;
    var strengthStatus = document.getElementById('password-strength-status');
    
    // Kiểm tra độ mạnh mật khẩu
    var strength = getPasswordStrength(password);
    
    // Hiển thị độ mạnh của mật khẩu
    strengthStatus.textContent = strength.message;
    strengthStatus.style.color = strength.color;
});

function getPasswordStrength(password) {
    var strength = { message: "❗ <?=__('Mật khẩu rất yếu');?>", color: "red" };
    var regexes = [
        /[A-Z]/,      // Chữ cái viết hoa
        /[a-z]/,      // Chữ cái viết thường
        /[0-9]/,      // Số
        /[\W_]/,      // Ký tự đặc biệt
        /.{8,}/      // Độ dài ít nhất 8 ký tự
    ];
    var passedChecks = regexes.reduce((acc, regex) => acc + regex.test(password), 0);
    if (passedChecks === 5) {
        strength.message = "🔰 <?=__('Mật khẩu mạnh');?>";
        strength.color = "green";
    } else if (passedChecks >= 3) {
        strength.message = "⚠️ <?=__('Mật khẩu trung bình');?>";
        strength.color = "orange";
    }

    return strength;
}

</script>
<?php if((int)$CMSNT->site('isConfirmPolicyRegister') == 1): ?>
<script>
// Logic điều khoản đăng ký
(function(){
    var requirePolicy = true;
    var agreedInput = document.getElementById('agreed_policy');
    var checkbox = document.getElementById('agree-terms');
    var openLink = null;
    var modal = document.getElementById('policy-modal');
    var modalOverlay = modal ? modal.querySelector('.policy-modal-overlay') : null;
    var btnClose = modal ? modal.querySelector('.policy-modal-close') : null;
    var btnCancel = document.getElementById('btn-policy-cancel');
    var btnAccept = document.getElementById('btn-policy-accept');
    var content = document.getElementById('policy-content');

    function refreshPolicyScroll(){
        if(!content) return;
        var h = Math.round(window.innerHeight * 0.60);
        content.style.maxHeight = h + 'px';
        // Ép reflow để khôi phục thanh trượt khi mở lại
        content.style.overflowY = 'hidden';
        void content.offsetHeight; // force reflow
        content.style.overflowY = 'auto';
        content.style.webkitOverflowScrolling = 'touch';
    }

    function openModal(){
        if(!modal) return;
        btnAccept.disabled = false;
        modal.classList.add('show');
        // Sau khi hiển thị modal, reset scroll và khởi tạo lại thanh trượt
        requestAnimationFrame(function(){
            refreshPolicyScroll();
            if(content){ content.scrollTop = 0; }
        });
    }
    function closeModal(){
        if(!modal) return;
        modal.classList.remove('show');
    }
    function resetAgreement(){
        if(agreedInput){ agreedInput.value = '0'; }
        if(checkbox){ checkbox.checked = false; }
    }
    if(checkbox){
        checkbox.addEventListener('change', function(e){
            if(this.checked){
                // Buộc xem điều khoản trước khi có thể đánh dấu
                e.preventDefault();
                this.checked = false;
                openModal();
            } else {
                resetAgreement();
            }
        });
    }
    if(openLink){
        openLink.addEventListener('click', function(){ openModal(); });
    }
    if(btnCancel){
        btnCancel.addEventListener('click', function(){
            resetAgreement();
            closeModal();
        });
    }
    if(btnClose){
        btnClose.addEventListener('click', function(){
            resetAgreement();
            closeModal();
        });
    }
    if(modalOverlay){
        modalOverlay.addEventListener('click', function(){
            resetAgreement();
            closeModal();
        });
    }
    // Không yêu cầu cuộn nội dung để bật nút đồng ý
    // Đảm bảo khi đổi kích thước, thanh trượt luôn hiển thị đúng
    window.addEventListener('resize', refreshPolicyScroll);
    if(btnAccept){
        btnAccept.addEventListener('click', function(){
            if(agreedInput){ agreedInput.value = '1'; }
            if(checkbox){ checkbox.checked = true; }
            closeModal();
        });
    }
})();
</script>
<?php endif; ?>
<script type="text/javascript">
$("#btnRegister").on("click", function() {
    // Validate registration inputs
    var username = $("#register-username").val();
    var email = $("#register-email").val();
    var password = $("#register-password").val();
    var repassword = $("#register-password-confirm").val();
    
    if (!username || username.length < 3) {
        Swal.fire('<?=__('Error');?>', '<?=__('Tên đăng nhập phải có ít nhất 3 ký tự');?>', 'error');
        return;
    }
    
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        Swal.fire('<?=__('Error');?>', '<?=__('Email không hợp lệ');?>', 'error');
        return;
    }
    
    if (!password || password.length < 6) {
        Swal.fire('<?=__('Error');?>', '<?=__('Mật khẩu phải có ít nhất 6 ký tự');?>', 'error');
        return;
    }
    
    if (password !== repassword) {
        Swal.fire('<?=__('Error');?>', '<?=__('Mật khẩu xác nhận không khớp');?>', 'error');
        return;
    }
    
    $('#btnRegister').html('<i class="fa fa-spinner fa-spin"></i> <?=__('Đang xử lý...');?>').prop('disabled',
        true);
    <?php if((int)$CMSNT->site('isConfirmPolicyRegister') == 1): ?>
    if($('#agreed_policy').val() != '1'){
        Swal.fire('<?=__('Failure!');?>', '<?=__('Vui lòng đọc và đồng ý điều khoản/chính sách trước khi đăng ký.');?>', 'error');
        $('#btnRegister').html('<?=__('Đăng Ký');?>').prop('disabled', false);
        return;
    }
    <?php endif; ?>
    <?php if(isCaptchaEnabledForModule('register')): ?>
    var __captchaVal = (typeof getCaptchaResponse === 'function') ? getCaptchaResponse() : $("#g-recaptcha-response").val();
    if(!__captchaVal){
        Swal.fire('<?=__('Failure!');?>', '<?=__('Vui lòng xác nhận Captcha');?>', 'error');
        $('#btnRegister').html('<?=__('Đăng Ký');?>').prop('disabled', false);
        return;
    }
    <?php endif; ?>
    var ajaxData = {
        action: 'Register',
        csrf_token: $("#csrf_token").val(),
        username: username,
        email: email,
        password: password,
        repassword: repassword
    };
    <?php if(isCaptchaEnabledForModule('register')): ?>
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

                    }
                });
                <?php if($CMSNT->site('google_analytics_status') == 1):?>
                // ✅ Gửi sự kiện về Google Analytics
                gtag('event', 'sign_up', {
                    method: 'Website Form'
                });
                <?php endif?>

                <?php if($CMSNT->site('google_ads_status') == 1):?>
                gtag('event', 'conversion', {
                    'send_to': '<?=$CMSNT->site('google_ads_id');?>'
                });
                <?php endif?>


                setTimeout("location.href = '<?=BASE_URL('');?>';", 1000);
            } else {
                Swal.fire('<?=__('Failure!');?>', respone.msg, 'error');
            }
            $('#btnRegister').html('<?=__('Đăng Ký');?>').prop('disabled', false);
        },
        error: function() {
            showMessage('<?=__('Không thể xử lý');?>', 'error');
            $('#btnRegister').html('<?=__('Đăng Ký');?>').prop('disabled', false);
        }

    });
});
</script>