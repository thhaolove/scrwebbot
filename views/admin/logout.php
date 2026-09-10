<?php
//thanhvucoder
if (!defined("IN_SITE")) {
    exit("The Request Not Found");
}
$CMSNT = new DB();
setcookie("token", NULL, -1, "/");
session_destroy();
redirect(base_url("client/"));
echo "\n";

?>