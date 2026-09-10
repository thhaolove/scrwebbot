<?php

if(!defined("IN_SITE")) {
    exit("The Request Not Found");
}
$body = ["title" => __("Add Campaign"), "desc" => "CMSNT Panel", "keyword" => "cmsnt, CMSNT, cmsnt.co,"];
$body["header"] = "\n\n";
$body["footer"] = "\n\n";
require_once __DIR__ . "/../../models/is_admin.php";
require_once __DIR__ . "/header.php";
require_once __DIR__ . "/sidebar.php";
if(!checkPermission($getUser["admin"], "edit_email_campaigns")) {
    exit("<script type=\"text/javascript\">if(!alert(\"Bạn không có quyền sử dụng tính năng này\")){window.history.back();}</script>");
}
if(isset($_POST["submit"])) {
    if($CMSNT->site("status_demo") != 0) {
        exit("<script type=\"text/javascript\">if(!alert(\"Không được dùng chức năng này vì đây là trang web demo.\")){window.history.back().location.reload();}</script>");
    }
    if(!checkPermission($getUser["admin"], "edit_email_campaigns")) {
        exit("<script type=\"text/javascript\">if(!alert(\"Bạn không có quyền sử dụng tính năng này\")){window.history.back();}</script>");
    }
    $isInsert = $CMSNT->insert("email_campaigns", ["name" => check_string($_POST["name"]), "subject" => $_POST["subject"], "cc" => !empty($_POST["cc"]) ? check_string($_POST["cc"]) : NULL, "bcc" => !empty($_POST["bcc"]) ? check_string($_POST["bcc"]) : NULL, "content" => $_POST["content"], "create_gettime" => gettime(), "update_gettime" => gettime(), "status" => 0]);
    if(empty($_POST["listUser"])) {
        foreach ($CMSNT->get_list("SELECT * FROM `users` WHERE `banned` = 0 AND `email` IS NOT NULL ") as $user) {
            $CMSNT->insert("email_sending", ["camp_id" => $CMSNT->get_row(" SELECT `id` FROM `email_campaigns` ORDER BY id DESC LIMIT 1 ")["id"], "user_id" => $user["id"], "status" => 0, "create_gettime" => gettime(), "update_gettime" => gettime()]);
        }
    } else {
        foreach ($_POST["listUser"] as $user) {
            $user = $CMSNT->get_row("SELECT * FROM `users` WHERE `id` = '" . $user . "' ");
            $CMSNT->insert("email_sending", ["camp_id" => $CMSNT->get_row(" SELECT `id` FROM `email_campaigns` ORDER BY id DESC LIMIT 1 ")["id"], "user_id" => $user["id"], "status" => 0, "create_gettime" => gettime(), "update_gettime" => gettime()]);
        }
    }
    if($isInsert) {
        $Mobile_Detect = new Mobile_Detect();
        $CMSNT->insert("logs", ["user_id" => $getUser["id"], "ip" => myip(), "device" => $Mobile_Detect->getUserAgent(), "createdate" => gettime(), "action" => __("Tạo chiến dịch Email Makreting") . " (" . check_string($_POST["name"]) . ")"]);
        $my_text = $CMSNT->site("noti_action");
        $my_text = str_replace("{domain}", $_SERVER["SERVER_NAME"], $my_text);
        $my_text = str_replace("{username}", $getUser["username"], $my_text);
        $my_text = str_replace("{action}", __("Tạo chiến dịch Email Makreting") . " (" . check_string($_POST["name"]) . ")", $my_text);
        $my_text = str_replace("{ip}", myip(), $my_text);
        $my_text = str_replace("{time}", gettime(), $my_text);
        sendMessAdmin($my_text);
        exit("<script type=\"text/javascript\">if(!alert(\"Successful !\")){location.href = \"" . base_url_admin("email-campaigns") . "\";}</script>");
    }
    exit("<script type=\"text/javascript\">if(!alert(\"Failure !\")){window.history.back().location.reload();}</script>");
} else {
    echo "\n<div class=\"main-content app-content\">\n    <div class=\"container-fluid\">\n        <div class=\"d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb\">\n            <h1 class=\"page-title fw-semibold fs-18 mb-0\"><i class=\"fa-solid fa-inbox\"></i> Tạo chiến dịch Email\n                Marketing</h1>\n        </div>\n        <div class=\"row\">\n            <div class=\"col-xl-12\">\n                <div class=\"card custom-card\">\n                    <div class=\"card-header justify-content-between\">\n                        <div class=\"card-title\">\n                            TẠO CHIẾN DỊCH EMAIL MARKETING\n                        </div>\n                    </div>\n                    <div class=\"card-body\">\n                        <form action=\"\" method=\"POST\">\n                            <div class=\"row mb-4\">\n                                <label class=\"col-sm-4 col-form-label\" for=\"example-hf-email\">";
    echo __("Tên chiến dịch");
    echo "                                    <span class=\"text-danger\">*</span></label>\n                                <div class=\"col-sm-8\">\n                                    <input class=\"form-control\" type=\"text\" placeholder=\"Nhập tên cho chiến dịch\"\n                                        name=\"name\" required>\n                                </div>\n                            </div>\n                            <div class=\"row mb-4\">\n                                <label class=\"col-sm-4 col-form-label\"\n                                    for=\"example-hf-email\">";
    echo __("Người nhận");
    echo "</label>\n                                <div class=\"col-sm-8\">\n                                    <select class=\"form-control\" name=\"listUser[]\" id=\"listUser\" multiple>\n                                        <option value=\"\">Mặc định sẽ áp dụng cho toàn bộ sản phẩm nếu không chọn\n                                        </option>\n                                        ";
    foreach ($CMSNT->get_list("SELECT * FROM `users` ") as $user) {
        echo "                                        <option value=\"";
        echo $user["id"];
        echo "\">ID: ";
        echo $user["id"];
        echo " | Username:\n                                            ";
        echo $user["username"];
        echo " | Email: ";
        echo $user["email"];
        echo "</option>\n                                        ";
    }
    echo "                                    </select>\n                                    <i>";
    echo __("Mặc định sẽ gửi toàn bộ thành viên nếu không chọn");
    echo "</i>\n                                </div>\n                                <script>\n                                const multipleCancelButton = new Choices(\n                                    '#listUser', {\n                                        allowHTML: true,\n                                        removeItemButton: true,\n                                    }\n                                );\n                                </script>\n                            </div>\n                            <div class=\"row mb-4\">\n                                <label class=\"col-sm-4 col-form-label\" for=\"example-hf-email\">";
    echo __("Tiêu đề Mail");
    echo " <span\n                                        class=\"text-danger\">*</span></label>\n                                <div class=\"col-sm-8\">\n                                    <input class=\"form-control\" type=\"text\" name=\"subject\" required>\n                                </div>\n                            </div>\n                            <div class=\"row mb-4\">\n                                <label class=\"col-sm-4 col-form-label\" for=\"example-hf-email\">";
    echo __("CC");
    echo "</label>\n                                <div class=\"col-sm-8\">\n                                    <input class=\"form-control\" type=\"text\" name=\"cc\">\n                                </div>\n                            </div>\n                            <div class=\"row mb-4\">\n                                <label class=\"col-sm-4 col-form-label\" for=\"example-hf-email\">";
    echo __("BCC");
    echo "</label>\n                                <div class=\"col-sm-8\">\n                                    <input class=\"form-control\" type=\"text\" name=\"bcc\">\n                                </div>\n                            </div>\n                            <div class=\"row mb-4\">\n                                <label class=\"col-sm-4 col-form-label\" for=\"example-hf-email\">";
    echo __("Nội dung Email");
    echo "                                    <span class=\"text-danger\">*</span></label>\n                                <div class=\"col-sm-12\">\n                                    <textarea class=\"content\" id=\"content\" name=\"content\" required></textarea>\n                                </div>\n                            </div>\n                            <a type=\"button\" class=\"btn btn-danger\" href=\"";
    echo base_url_admin("email-campaigns");
    echo "\"><i\n                                    class=\"fa fa-fw fa-undo me-1\"></i> ";
    echo __("Back");
    echo "</a>\n                            <button type=\"submit\" name=\"submit\" class=\"btn btn-primary\"><i\n                                    class=\"fa fa-fw fa-plus me-1\"></i>\n                                ";
    echo __("Submit");
    echo "</button>\n                        </form>\n                    </div>\n                </div>\n            </div>\n        </div>\n\n    </div>\n</div>\n\n\n\n";
    require_once __DIR__ . "/footer.php";
    echo "\n<script>\nCKEDITOR.replace(\"content\");\n</script>";
}

?>