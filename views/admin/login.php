<?php
//thanhvucoder
if (!defined("IN_SITE")) {
    exit("The Request Not Found");
}
$body = ["title" => __("Đăng nhập") . " | " . $CMSNT->site("title"), "desc" => $CMSNT->site("description"), "keyword" => $CMSNT->site("keywords")];
$body["header"] = "\n<script src=\"https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.6/clipboard.min.js\"></script>\n<link rel=\"stylesheet\" href=\"" . BASE_URL("public/client/") . "css/wallet.css\">\n";
$body["footer"] = "\n\n";
require_once __DIR__ . "/header.php";
require_once __DIR__ . "/nav.php";
echo "\n<section class=\"py-5 inner-section profile-part\">\n    <div class=\"container\">\n        <div class=\"row justify-content-center\">\n            <div class=\"col-12 col-sm-12 col-md-12 col-lg-6 col-xl-6\">\n\n                <div class=\"user-form-card\">\n                    <div class=\"user-form-title\">\n                        <h2>";
echo __("Đăng Nhập");
echo "</h2>\n                        <p>";
echo __("Vui lòng nhập thông tin đăng nhập");
echo "</p>\n                    </div>\n                    <div class=\"user-form-group\">\n                        \n                        <form class=\"user-form\">\n                            <div class=\"form-group\">\n                                <input type=\"hidden\" id=\"csrf_token\" value=\"";
echo generate_csrf_token();
echo "\">\n                                <input type=\"text\" id=\"page-login-username\" class=\"form-control\" value=\"";
echo $CMSNT->site("status_demo") == 1 ? "admin" : "";
echo "\"\n                                    placeholder=\"";
echo __("Vui lòng nhập username");
echo "\">\n                            </div>\n                            <div class=\"form-group\">\n                                <input type=\"password\" id=\"page-login-password\" class=\"form-control\" value=\"";
echo $CMSNT->site("status_demo") == 1 ? "admin" : "";
echo "\"\n                                    placeholder=\"";
echo __("Vui lòng nhập mật khẩu");
echo "\">\n                            </div>\n                            <center class=\"mb-3\"\n                                ";
echo $CMSNT->site("reCAPTCHA_status") == 1 ? "" : "style=\"display:none;\"";
echo ">\n                                <div class=\"g-recaptcha\" id=\"g-recaptcha-response\"  \n                                    data-sitekey=\"";
echo $CMSNT->site("reCAPTCHA_site_key");
echo "\"></div>\n                            </center>\n                            <div class=\"form-button\">\n                            <button type=\"button\" id=\"btnLoginPage\">";
echo __("Đăng Nhập");
echo "</button>\n                                <p><a href=\"";
echo base_url("client/forgot-password");
echo "\">";
echo __("Quên mật khẩu?");
echo "</a></p>\n                            </div>\n                        </form>\n                    </div>\n                </div>\n                <div class=\"user-form-remind\">\n                <p>";
echo __("Bạn chưa có tài khoản?");
echo " <a href=\"";
echo base_url("client/register");
echo "\">";
echo __("Đăng Ký Ngay");
echo "</a></p>\n                </div>\n            </div>\n        </div>\n    </div>\n</section>\n\n\n";
require_once __DIR__ . "/footer.php";
echo "\n<script type=\"text/javascript\">\n\$(\"#btnLoginPage\").on(\"click\", function() {\n    \$('#btnLoginPage').html('<i class=\"fa fa-spinner fa-spin\"></i> ";
echo __("Đang xử lý...");
echo "').prop('disabled',\n        true);\n    \$.ajax({\n        url: \"";
echo base_url("ajaxs/client/auth.php");
echo "\",\n        method: \"POST\",\n        dataType: \"JSON\",\n        data: {\n            action: 'Login',\n            csrf_token: \$(\"#csrf_token\").val(),\n            recaptcha: \$(\"#g-recaptcha-response\").val(),\n            username: \$(\"#page-login-username\").val(),\n            password: \$(\"#page-login-password\").val()\n        },\n        success: function(respone) {\n            if (respone.status == 'success') {\n                Swal.fire({\n                    title: '";
echo __("Successful!");
echo "',\n                    text: respone.msg,\n                    icon: 'success',\n                    confirmButtonColor: '#3085d6',\n                    confirmButtonText: 'OK'\n                }).then((result) => {\n                    if (result.isConfirmed) {\n                        location.href = '";
echo BASE_URL("");
echo "';\n                    }\n                });\n                setTimeout(\"location.href = '";
echo BASE_URL("");
echo "';\", 2000);\n            } else if (respone.status == 'verify') {\n                Swal.fire('";
echo __("Warning!");
echo "', respone.msg, 'warning');\n                setTimeout(\"location.href = '\" + respone.url + \"';\", 2000);\n            } else {\n                Swal.fire('";
echo __("Failure!");
echo "', respone.msg, 'error');\n            }\n            \$('#btnLoginPage').html('";
echo __("Đăng Nhập");
echo "').prop('disabled', false);\n        },\n        error: function() {\n            showMessage('";
echo __("Vui lòng liên hệ Developer");
echo "', 'error');\n            \$('#btnLoginPage').html('";
echo __("Đăng Nhập");
echo "').prop('disabled', false);\n        }\n\n    });\n});\n</script>\n ";

?>