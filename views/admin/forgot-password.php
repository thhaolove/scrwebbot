<?php
//thanhvucoder
if (!defined("IN_SITE")) {
    exit("The Request Not Found");
}
$body = ["title" => __("Quên mật khẩu") . " | " . $CMSNT->site("title"), "desc" => $CMSNT->site("description"), "keyword" => $CMSNT->site("keywords")];
$body["header"] = "\n\n";
$body["footer"] = "\n\n";
require_once __DIR__ . "/header.php";
require_once __DIR__ . "/nav.php";
echo "\n<section class=\"py-5 inner-section profile-part\">\n    <div class=\"container\">\n        <div class=\"row justify-content-center\">\n            <div class=\"col-12 col-sm-10 col-md-8 col-lg-6 col-xl-5\">\n                \n                <div class=\"user-form-card\">\n                    <div class=\"user-form-title\">\n                        <h2>";
echo __("Bạn quên mật khẩu?");
echo "</h2>\n                        <p>";
echo __("Vui lòng nhập thông tin vào ô dưới đây để xác minh");
echo "</p>\n                    </div>\n                    <form class=\"user-form\">\n                        <div class=\"form-group\">\n                            <input type=\"hidden\" id=\"csrf_token\" value=\"";
echo generate_csrf_token();
echo "\">\n                            <input type=\"email\" id=\"email\" class=\"form-control\"\n                                placeholder=\"";
echo __("Vui lòng nhập địa chỉ Email");
echo "\"></div>\n                        <div class=\"form-button\"><button type=\"button\"\n                                id=\"btnForgotPassword\">";
echo __("Xác minh");
echo "</button></div>\n                    </form>\n                </div>\n                <div class=\"user-form-remind\">\n                    <p>";
echo __("Bạn đã có tài khoản?");
echo " <a href=\"";
echo BASE_URL("client/login");
echo "\">";
echo __("Đăng Nhập");
echo "</a></p>\n                </div>\n            </div>\n        </div>\n    </div>\n</section>\n";
require_once __DIR__ . "/footer.php";
echo "\n<script type=\"text/javascript\">\n\$(\"#btnForgotPassword\").on(\"click\", function() {\n    \$('#btnForgotPassword').html('<i class=\"fa fa-spinner fa-spin\"></i> ";
echo __("Processing...");
echo "').prop(\n        'disabled',\n        true);\n    \$.ajax({\n        url: \"";
echo base_url("ajaxs/client/auth.php");
echo "\",\n        method: \"POST\",\n        dataType: \"JSON\",\n        data: {\n            action: 'ForgotPassword',\n            csrf_token: \$(\"#csrf_token\").val(),\n            email: \$(\"#email\").val()\n        },\n        success: function(respone) {\n            if (respone.status == 'success') {\n                Swal.fire({\n                    title: '";
echo __("Successful !");
echo "',\n                    text: respone.msg,\n                    icon: 'success',\n                    confirmButtonColor: '#3085d6',\n                    confirmButtonText: 'OK'\n                }).then((result) => {\n                    if (result.isConfirmed) {\n                        \n                    }\n                });\n            } else {\n                Swal.fire('";
echo __("Failure!");
echo "', respone.msg, 'error');\n            }\n            \$('#btnForgotPassword').html(\n                    '";
echo __("Xác minh");
echo "')\n                .prop('disabled', false);\n        },\n        error: function() {\n            showMessage('";
echo __("Không thể xử lý");
echo "', 'error');\n            \$('#btnForgotPassword').html(\n                    '";
echo __("Xác minh");
echo "')\n                .prop('disabled', false);\n        }\n\n    });\n});\n</script>";

?>