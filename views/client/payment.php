<?php

if(!defined("IN_SITE")) {
    exit("The Request Not Found");
}
$body = ["title" => __("Payment") . " | " . $CMSNT->site("title"), "desc" => $CMSNT->site("description"), "keyword" => $CMSNT->site("keywords")];
$body["header"] = "\n<script src=\"https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.6/clipboard.min.js\"></script>\n";
$body["footer"] = "\n\n";
if(isset($_GET["trans_id"])) {
    $trans_id = check_string($_GET["trans_id"]);
    $row = $CMSNT->get_row("SELECT * FROM `invoices` WHERE `trans_id` = '" . $trans_id . "'  ");
    if(!$row) {
        redirect(base_url(""));
    }
} else {
    redirect(base_url(""));
}
$invoice_life_time = time() - strtotime($row["create_gettime"]);
$invoice_life_time = $CMSNT->site("invoice_life_time") - $invoice_life_time;
require_once __DIR__ . "/header.php";
echo "\n<body>\n    <div id=\"page-container\">\n        <!-- Main Container -->\n        <main id=\"main-container\">\n            <!-- Page Content -->\n            <div class=\"content content-boxed content-full overflow-hidden\">\n                <!-- Header -->\n                <div class=\"py-5 text-center\">\n                    <h1 class=\"fs-2 fw-bold mt-4 mb-2\">\n                        ";
echo $row["name"];
echo "                    </h1>\n                    <h2 class=\"fs-5 fw-medium text-muted mb-0\">\n                        ";
echo $row["description"];
echo "                    </h2>\n                </div>\n                <!-- END Header -->\n\n\n                <div class=\"row\">\n                    <center>\n                        <div class=\"col-xl-6 order-xl-last\">\n                            <div class=\"block block-rounded\">\n                                <div class=\"block-header block-header-default\">\n                                    <h3 class=\"block-title\">\n                                        ";
echo mb_strtoupper(__("Do not close this page during checkout"), "UTF-8");
echo "                                    </h3>\n                                </div>\n                                <div class=\"block-content block-content-full\">\n                                    <div id=\"display_waiting\">\n                                        <img width=\"200px\" class=\"mb-3\"\n                                            src=\"";
echo getRowRealtime("wallets", $row["wallet_id"], "qrcode");
echo "\">\n                                        <h3><b style=\"color:red;\" id=\"copyAmount\">";
echo $row["received"];
echo "</b> <b>USDT</b>\n                                            <button type=\"button\" onclick=\"copy()\" data-clipboard-target=\"#copyAmount\"\n                                                class=\"btn btn-sm btn-alt-secondary copy\" data-bs-toggle=\"tooltip\"\n                                                title=\"";
echo __("Copy");
echo "\">\n                                                <i class=\"fa fa-clipboard\"></i>\n                                            </button></h3>\n                                        <p><b>";
echo __("Please send correctly");
echo "</b> <b style=\"color:red;\">";
echo $row["received"];
echo "</b>\n                                            ";
echo __("USDT (in ONE payment) to dont include transaction fee in this amount");
echo "                                        </p>\n                                        <p>\n                                            <span id=\"copyAddress\" class=\"fw-bold\"\n                                                style=\"color:blue;\">";
echo $row["address"];
echo "</span> <button type=\"button\"\n                                                onclick=\"copy()\" data-clipboard-target=\"#copyAddress\"\n                                                class=\"btn btn-sm btn-alt-secondary copy\" data-bs-toggle=\"tooltip\"\n                                                title=\"";
echo __("Copy");
echo "\">\n                                                <i class=\"fa fa-clipboard\"></i>\n                                            </button>\n                                        </p>\n                                        <p><b>";
echo __("Network:");
echo "</b> <b style=\"color:green;\">";
echo $row["network"];
echo "</b>\n                                        </p>\n                                        <p><b>";
echo __("Time left to pay:");
echo "</b> \n                                            <span id=\"timer\"><i class=\"fa fa-spinner fa-spin\"></i></span>\n                                        </p>\n                                    </div>\n                                    <div id=\"display_completed\">\n                                        <img width=\"200px\" src=\"";
echo base_url("assets/img/success-payment-coin.gif");
echo "\">\n                                        <h3>";
echo __("Invoice has been successfully paid.");
echo "</h3>\n                                    </div>\n                                    <div id=\"display_expired\">\n                                        <img width=\"200px\" src=\"";
echo base_url("assets/img/expired.png");
echo "\">\n                                        <h3>";
echo __("Invoice has expired, please create a new invoice.");
echo "</h3>\n                                    </div>\n                                </div>\n                            </div>\n                            <button type=\"button\" id=\"button_waiting_payment\"\n                                class=\"btn btn-hero btn-primary w-100 py-3 push\">\n                                <i class=\"fa fa-spinner fa-spin\"></i> ";
echo __("Awaiting Payment From You");
echo "                            </button>\n                            <a type=\"button\" id=\"button_back_to_website\" href=\"";
echo $row["return_url"];
echo "\"\n                                class=\"btn btn-hero btn-danger w-100 py-3 push\">\n                                <i class=\"fa fa-chevron-left\"></i> ";
echo __("Back to website");
echo "                            </a>\n                            <p>";
echo __("Do not close this page during checkout");
echo "</p>\n                        </div>\n                    </center>\n                </div>\n\n\n            </div>\n            <!-- END Page Content -->\n        </main>\n        <!-- END Main Container -->\n    </div>\n    <script src=\"";
echo BASE_URL("public/theme/");
echo "assets/js/dashmix.app.min.js\"></script>\n</body>\n\n</html>\n\n\n<script>\nnew ClipboardJS(\".copy\");\n\nfunction copy() {\n    cuteToast({\n        type: \"success\",\n        message: \"";
echo __("Copied to clipboard");
echo "\",\n        timer: 5000\n    });\n}\n\nvar timer = document.getElementById(\"timer\");\nvar count = ";
echo $invoice_life_time;
echo ";\nvar end = 0;\nvar interval = setInterval(function() {\n    if (count >= end) {\n        var minutes = Math.floor(count / 60);\n        var seconds = count % 60;\n        if (seconds < 10) {\n            seconds = \"0\" + seconds;\n        }\n        timer.innerHTML = minutes + ':' + seconds + 's'\n        count--;\n    } else {\n        clearInterval(interval);\n    }\n}, 1000);\n</script>\n<script>\n\$(\"#display_completed\").hide();\n\$(\"#display_expired\").hide();\n\$(document).ready(function() {\n    var loadStatusInvoice = setInterval(function() {\n        \$.ajax({\n            type: \"POST\",\n            dataType: \"JSON\",\n            url: \"";
echo base_url("ajaxs/client/view.php");
echo "\",\n            data: {\n                action: 'loadStatusInvoice',\n                trans_id: '";
echo $row["trans_id"];
echo "'\n            },\n            success: function(data) {\n                if (data.data.status == 'completed') {\n                    \$(\"#display_waiting\").hide();\n                    \$(\"#display_completed\").show();\n                    \$(\"#button_waiting_payment\").hide();\n                    clearInterval(loadStatusInvoice);\n                }\n                if (data.data.status == 'expired') {\n                    \$(\"#display_waiting\").hide();\n                    \$(\"#display_expired\").show();\n                    \$(\"#button_waiting_payment\").hide();\n                    clearInterval(loadStatusInvoice);\n                }\n\n            }\n        });\n    }, 3000);\n});\n\n\$(document).ready(function() {\n    var updateStatusInvoice = setInterval(function() {\n        \$.ajax({\n            type: \"POST\",\n            dataType: \"JSON\",\n            url: \"";
echo base_url("ajaxs/client/update.php");
echo "\",\n            data: {\n                action: 'updateStatusInvoice',\n                trans_id: '";
echo $row["trans_id"];
echo "'\n            },\n            success: function(data) {\n                if (data.status == 'error') {\n                    clearInterval(updateStatusInvoice);\n                }\n            }\n        });\n    }, 6000);\n});\n</script>";

?>