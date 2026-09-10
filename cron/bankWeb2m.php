<?php

define("IN_SITE", true);
require_once __DIR__ . "/../libs/db.php";
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../libs/lang.php";
require_once __DIR__ . "/../libs/helper.php";
require_once __DIR__ . "/../libs/database/users.php";
$CMSNT = new DB();
$user = new users();
if($CMSNT->site("check_time_cron_bank") < time() && time() - $CMSNT->site("check_time_cron_bank") < 5) {
    exit("[ÉT O ÉT ]Thao tác quá nhanh, vui lòng đợi");
}
$CMSNT->update("settings", ["value" => time()], " `name` = 'check_time_cron_bank' ");
foreach ($CMSNT->get_list(" SELECT * FROM `banks` WHERE `status` = 1 ") as $bank) {
    if($bank["short_name"] == "ACB") {
        $result = curl_get2("https://api.web2m.com/historyapiacb/" . $bank["password"] . "/" . $bank["accountNumber"] . "/" . $bank["token"]);
        if($CMSNT->site("debug_auto_bank") == 1) {
            echo $result;
        }
        $result = json_decode($result, true);
        foreach ($result["transactions"] as $data) {
            if($data["type"] == "OUT") {
            } else {
                $tid = check_string($data["transactionNumber"]);
                $description = str_replace(" ", ".", check_string($data["description"]));
                $amount = check_string($data["amount"]);
                $user_id = parse_order_id($description, $CMSNT->site("prefix_autobank"));
                if($amount < $CMSNT->site("bank_min") || $CMSNT->site("bank_max") < $amount) {
                } else {
                    if(($getUser = $CMSNT->get_row(" SELECT * FROM `users` WHERE `id` = '" . $user_id . "' ")) && $CMSNT->num_rows(" SELECT * FROM `payment_bank` WHERE `tid` = '" . $tid . "' AND `description` = '" . $description . "'  ") == 0) {
                        $received = checkPromotion($amount);
                        $insertSv2 = $CMSNT->insert("payment_bank", ["tid" => $tid, "method" => $bank["short_name"], "user_id" => $getUser["id"], "description" => $description, "amount" => $amount, "received" => $received, "create_gettime" => gettime(), "create_time" => time()]);
                        if($insertSv2) {
                            $isCong = $user->AddCredits($getUser["id"], $received, "Nạp tiền tự động qua " . $bank["short_name"] . " (#" . $tid . " - " . $description . " - " . $amount . ")", "TOPUP_" . $bank["accountNumber"] . "_" . $tid);
                            if($isCong) {
                                debit_processing($getUser["id"]);
                                $CMSNT->insert("deposit_log", ["user_id" => $getUser["id"], "method" => $bank["short_name"], "amount" => $amount, "received" => $received, "create_time" => time(), "is_virtual" => 0]);
                                $my_text = $CMSNT->site("noti_recharge");
                                $my_text = str_replace("{domain}", $_SERVER["SERVER_NAME"], $my_text);
                                $my_text = str_replace("{username}", $getUser["username"], $my_text);
                                $my_text = str_replace("{method}", $bank["short_name"], $my_text);
                                $my_text = str_replace("{amount}", format_currency($amount), $my_text);
                                $my_text = str_replace("{price}", format_currency($received), $my_text);
                                $my_text = str_replace("{time}", gettime(), $my_text);
                                sendMessAdmin($my_text);
                                echo "[<b style=\"color:green\">-</b>] Xử lý thành công 1 hoá đơn." . PHP_EOL;
                            }
                        }
                    }
                    if($CMSNT->get_row(" SELECT COUNT(id) FROM `log_bank_auto` WHERE `tid` = '" . $tid . "' AND `description` = '" . $description . "' AND `method` = '" . $bank["short_name"] . "'  ")["COUNT(id)"] == 0) {
                        $CMSNT->insert("log_bank_auto", ["tid" => $tid, "method" => $bank["short_name"], "description" => $description, "type" => $data["type"], "amount" => $amount, "create_gettime" => gettime()]);
                    }
                }
            }
        }
    } elseif($bank["short_name"] == "VietinBank" || $bank["short_name"] == "Vietinbank" || $bank["short_name"] == "VTB") {
        $result = curl_get2("https://api.web2m.com/historyapivtb/" . $bank["password"] . "/" . $bank["accountNumber"] . "/" . $bank["token"]);
        if($CMSNT->site("debug_auto_bank") == 1) {
            echo $result;
        }
        $result = json_decode($result, true);
        foreach ($result["transactions"] as $data) {
            $tid = check_string($data["trxId"]);
            $description = check_string($data["remark"]);
            $amount = check_string((int) $data["amount"]);
            $user_id = parse_order_id($description, $CMSNT->site("prefix_autobank"));
            if($amount < $CMSNT->site("bank_min") || $CMSNT->site("bank_max") < $amount) {
            } else {
                if(($getUser = $CMSNT->get_row(" SELECT * FROM `users` WHERE `id` = '" . $user_id . "' ")) && $CMSNT->num_rows(" SELECT * FROM `payment_bank` WHERE `tid` = '" . $tid . "' AND `description` = '" . $description . "'  ") == 0) {
                    $received = checkPromotion($amount);
                    $insertSv2 = $CMSNT->insert("payment_bank", ["tid" => $tid, "method" => $bank["short_name"], "user_id" => $getUser["id"], "description" => $description, "amount" => $amount, "received" => $received, "create_gettime" => gettime(), "create_time" => time()]);
                    if($insertSv2) {
                        $isCong = $user->AddCredits($getUser["id"], $received, "Nạp tiền tự động qua " . $bank["short_name"] . " (#" . $tid . " - " . $description . " - " . $amount . ")", "TOPUP_" . $bank["accountNumber"] . "_" . $tid);
                        if($isCong) {
                            debit_processing($getUser["id"]);
                            $CMSNT->insert("deposit_log", ["user_id" => $getUser["id"], "method" => $bank["short_name"], "amount" => $amount, "received" => $received, "create_time" => time(), "is_virtual" => 0]);
                            $my_text = $CMSNT->site("noti_recharge");
                            $my_text = str_replace("{domain}", $_SERVER["SERVER_NAME"], $my_text);
                            $my_text = str_replace("{username}", $getUser["username"], $my_text);
                            $my_text = str_replace("{method}", $bank["short_name"], $my_text);
                            $my_text = str_replace("{amount}", format_currency($amount), $my_text);
                            $my_text = str_replace("{price}", format_currency($received), $my_text);
                            $my_text = str_replace("{time}", gettime(), $my_text);
                            sendMessAdmin($my_text);
                            echo "[<b style=\"color:green\">-</b>] Xử lý thành công 1 hoá đơn." . PHP_EOL;
                        }
                    }
                }
                if($CMSNT->get_row(" SELECT COUNT(id) FROM `log_bank_auto` WHERE `tid` = '" . $tid . "' AND `description` = '" . $description . "' AND `method` = '" . $bank["short_name"] . "'  ")["COUNT(id)"] == 0) {
                    $CMSNT->insert("log_bank_auto", ["tid" => $tid, "method" => $bank["short_name"], "description" => $description, "type" => $data["type"], "amount" => $amount, "create_gettime" => gettime()]);
                }
            }
        }
    } elseif($bank["short_name"] == "Techcombank" || $bank["short_name"] == "TCB") {
        $result = curl_get2("https://api.web2m.com/historyapitcb/" . $bank["password"] . "/" . $bank["accountNumber"] . "/" . $bank["token"]);
        if($CMSNT->site("debug_auto_bank") == 1) {
            echo $result;
        }
        $result = json_decode($result, true);
        foreach ($result["transactions"] as $data) {
            $tid = check_string($data["TransID"]);
            $description = check_string($data["Description"]);
            $amount = check_string(str_replace(",", "", $data["Amount"]));
            $user_id = parse_order_id($description, $CMSNT->site("prefix_autobank"));
            if($amount < $CMSNT->site("bank_min") || $CMSNT->site("bank_max") < $amount) {
            } else {
                if(($getUser = $CMSNT->get_row(" SELECT * FROM `users` WHERE `id` = '" . $user_id . "' ")) && $CMSNT->num_rows(" SELECT * FROM `payment_bank` WHERE `tid` = '" . $tid . "' AND `description` = '" . $description . "'  ") == 0) {
                    $received = checkPromotion($amount);
                    $insertSv2 = $CMSNT->insert("payment_bank", ["tid" => $tid, "method" => $bank["short_name"], "user_id" => $getUser["id"], "description" => $description, "amount" => $amount, "received" => $received, "create_gettime" => gettime(), "create_time" => time()]);
                    if($insertSv2) {
                        $isCong = $user->AddCredits($getUser["id"], $received, "Nạp tiền tự động qua " . $bank["short_name"] . " (#" . $tid . " - " . $description . " - " . $amount . ")", "TOPUP_" . $bank["accountNumber"] . "_" . $tid);
                        if($isCong) {
                            debit_processing($getUser["id"]);
                            $CMSNT->insert("deposit_log", ["user_id" => $getUser["id"], "method" => $bank["short_name"], "amount" => $amount, "received" => $received, "create_time" => time(), "is_virtual" => 0]);
                            $my_text = $CMSNT->site("noti_recharge");
                            $my_text = str_replace("{domain}", $_SERVER["SERVER_NAME"], $my_text);
                            $my_text = str_replace("{username}", $getUser["username"], $my_text);
                            $my_text = str_replace("{method}", $bank["short_name"], $my_text);
                            $my_text = str_replace("{amount}", format_currency($amount), $my_text);
                            $my_text = str_replace("{price}", format_currency($received), $my_text);
                            $my_text = str_replace("{time}", gettime(), $my_text);
                            sendMessAdmin($my_text);
                            echo "[<b style=\"color:green\">-</b>] Xử lý thành công 1 hoá đơn." . PHP_EOL;
                        }
                    }
                }
                if($CMSNT->get_row(" SELECT COUNT(id) FROM `log_bank_auto` WHERE `tid` = '" . $tid . "' AND `description` = '" . $description . "' AND `method` = '" . $bank["short_name"] . "'  ")["COUNT(id)"] == 0) {
                    $CMSNT->insert("log_bank_auto", ["tid" => $tid, "method" => $bank["short_name"], "description" => $description, "type" => $data["type"], "amount" => $amount, "create_gettime" => gettime()]);
                }
            }
        }
    } elseif($bank["short_name"] == "VCB" || $bank["short_name"] == "Vietcombank" || $bank["short_name"] == "VIETCOMBANK") {
        $result = curl_get2("https://api.web2m.com/historyapivcb/" . $bank["password"] . "/" . $bank["accountNumber"] . "/" . $bank["token"]);
        if($CMSNT->site("debug_auto_bank") == 1) {
            echo $result;
        }
        $result = json_decode($result, true);
        foreach ($result["data"]["ChiTietGiaoDich"] as $data) {
            $tid = check_string($data["SoThamChieu"]);
            $description = check_string($data["MoTa"]);
            $amount = check_string(str_replace(",", "", $data["SoTienGhiCo"]));
            $user_id = parse_order_id($description, $CMSNT->site("prefix_autobank"));
            if($amount < $CMSNT->site("bank_min") || $CMSNT->site("bank_max") < $amount) {
            } else {
                if(($getUser = $CMSNT->get_row(" SELECT * FROM `users` WHERE `id` = '" . $user_id . "' ")) && $CMSNT->num_rows(" SELECT * FROM `payment_bank` WHERE `tid` = '" . $tid . "' AND `description` = '" . $description . "'  ") == 0) {
                    $received = checkPromotion($amount);
                    $insertSv2 = $CMSNT->insert("payment_bank", ["tid" => $tid, "method" => $bank["short_name"], "user_id" => $getUser["id"], "description" => $description, "amount" => $amount, "received" => $received, "create_gettime" => gettime(), "create_time" => time()]);
                    if($insertSv2) {
                        $isCong = $user->AddCredits($getUser["id"], $received, "Nạp tiền tự động qua " . $bank["short_name"] . " (#" . $tid . " - " . $description . " - " . $amount . ")", "TOPUP_" . $bank["accountNumber"] . "_" . $tid);
                        if($isCong) {
                            debit_processing($getUser["id"]);
                            $CMSNT->insert("deposit_log", ["user_id" => $getUser["id"], "method" => $bank["short_name"], "amount" => $amount, "received" => $received, "create_time" => time(), "is_virtual" => 0]);
                            $my_text = $CMSNT->site("noti_recharge");
                            $my_text = str_replace("{domain}", $_SERVER["SERVER_NAME"], $my_text);
                            $my_text = str_replace("{username}", $getUser["username"], $my_text);
                            $my_text = str_replace("{method}", $bank["short_name"], $my_text);
                            $my_text = str_replace("{amount}", format_currency($amount), $my_text);
                            $my_text = str_replace("{price}", format_currency($received), $my_text);
                            $my_text = str_replace("{time}", gettime(), $my_text);
                            sendMessAdmin($my_text);
                            echo "[<b style=\"color:green\">-</b>] Xử lý thành công 1 hoá đơn." . PHP_EOL;
                        }
                    }
                }
                if($CMSNT->get_row(" SELECT COUNT(id) FROM `log_bank_auto` WHERE `tid` = '" . $tid . "' AND `description` = '" . $description . "' AND `method` = '" . $bank["short_name"] . "'  ")["COUNT(id)"] == 0) {
                    $CMSNT->insert("log_bank_auto", ["tid" => $tid, "method" => $bank["short_name"], "description" => $description, "type" => $data["type"], "amount" => $amount, "create_gettime" => gettime()]);
                }
            }
        }
    } elseif($bank["short_name"] == "VPBank" || $bank["short_name"] == "VPB") {
        $result = curl_get2("https://api.web2m.com/historyapivpb/" . $bank["password"] . "/" . $bank["accountNumber"] . "/" . $bank["token"]);
        if($CMSNT->site("debug_auto_bank") == 1) {
            echo $result;
        }
        $result = json_decode($result, true);
        foreach ($result["d"]["DepositAccountTransactions"]["results"] as $data) {
            $tid = check_string($data["ReferenceNumber"]);
            $description = check_string($data["Description"]);
            $amount = check_string($data["Amount"]);
            $user_id = parse_order_id($description, $CMSNT->site("prefix_autobank"));
            if($amount < $CMSNT->site("bank_min") || $CMSNT->site("bank_max") < $amount) {
            } else {
                if(($getUser = $CMSNT->get_row(" SELECT * FROM `users` WHERE `id` = '" . $user_id . "' ")) && $CMSNT->num_rows(" SELECT * FROM `payment_bank` WHERE `tid` = '" . $tid . "' AND `description` = '" . $description . "'  ") == 0) {
                    $received = checkPromotion($amount);
                    $insertSv2 = $CMSNT->insert("payment_bank", ["tid" => $tid, "method" => $bank["short_name"], "user_id" => $getUser["id"], "description" => $description, "amount" => $amount, "received" => $received, "create_gettime" => gettime(), "create_time" => time()]);
                    if($insertSv2) {
                        $isCong = $user->AddCredits($getUser["id"], $received, "Nạp tiền tự động qua " . $bank["short_name"] . " (#" . $tid . " - " . $description . " - " . $amount . ")", "TOPUP_" . $bank["accountNumber"] . "_" . $tid);
                        if($isCong) {
                            debit_processing($getUser["id"]);
                            $CMSNT->insert("deposit_log", ["user_id" => $getUser["id"], "method" => $bank["short_name"], "amount" => $amount, "received" => $received, "create_time" => time(), "is_virtual" => 0]);
                            $my_text = $CMSNT->site("noti_recharge");
                            $my_text = str_replace("{domain}", $_SERVER["SERVER_NAME"], $my_text);
                            $my_text = str_replace("{username}", $getUser["username"], $my_text);
                            $my_text = str_replace("{method}", $bank["short_name"], $my_text);
                            $my_text = str_replace("{amount}", format_currency($amount), $my_text);
                            $my_text = str_replace("{price}", format_currency($received), $my_text);
                            $my_text = str_replace("{time}", gettime(), $my_text);
                            sendMessAdmin($my_text);
                            echo "[<b style=\"color:green\">-</b>] Xử lý thành công 1 hoá đơn." . PHP_EOL;
                        }
                    }
                }
                if($CMSNT->get_row(" SELECT COUNT(id) FROM `log_bank_auto` WHERE `tid` = '" . $tid . "' AND `description` = '" . $description . "' AND `method` = '" . $bank["short_name"] . "'  ")["COUNT(id)"] == 0) {
                    $CMSNT->insert("log_bank_auto", ["tid" => $tid, "method" => $bank["short_name"], "description" => $description, "type" => $data["type"], "amount" => $amount, "create_gettime" => gettime()]);
                }
            }
        }
    } elseif($bank["short_name"] == "MB" || $bank["short_name"] == "MBBank") {
        $result = curl_get2("https://api.web2m.com/historyapimbnoti/" . $bank["password"] . "/" . $bank["accountNumber"] . "/" . $bank["token"]);
        if($CMSNT->site("debug_auto_bank") == 1) {
            echo $result;
        }
        $result = json_decode($result, true);
        foreach ($result["data"] as $data) {
            $tid = check_string($data["refNo"]);
            $description = str_replace(" ", ".", check_string($data["description"]));
            $amount = check_string($data["creditAmount"]);
            $user_id = parse_order_id($description, $CMSNT->site("prefix_autobank"));
            if($amount < $CMSNT->site("bank_min") || $CMSNT->site("bank_max") < $amount) {
            } else {
                if(($getUser = $CMSNT->get_row(" SELECT * FROM `users` WHERE `id` = '" . $user_id . "' ")) && $CMSNT->num_rows(" SELECT * FROM `payment_bank` WHERE `tid` = '" . $tid . "' AND `description` = '" . $description . "'  ") == 0) {
                    $received = checkPromotion($amount);
                    $insertSv2 = $CMSNT->insert("payment_bank", ["tid" => $tid, "method" => $bank["short_name"], "user_id" => $getUser["id"], "description" => $description, "amount" => $amount, "received" => $received, "create_gettime" => gettime(), "create_time" => time()]);
                    if($insertSv2) {
                        $isCong = $user->AddCredits($getUser["id"], $received, "Nạp tiền tự động qua " . $bank["short_name"] . " (#" . $tid . " - " . $description . " - " . $amount . ")", "TOPUP_" . $bank["accountNumber"] . "_" . $tid);
                        if($isCong) {
                            debit_processing($getUser["id"]);
                            $CMSNT->insert("deposit_log", ["user_id" => $getUser["id"], "method" => $bank["short_name"], "amount" => $amount, "received" => $received, "create_time" => time(), "is_virtual" => 0]);
                            $my_text = $CMSNT->site("noti_recharge");
                            $my_text = str_replace("{domain}", $_SERVER["SERVER_NAME"], $my_text);
                            $my_text = str_replace("{username}", $getUser["username"], $my_text);
                            $my_text = str_replace("{method}", $bank["short_name"], $my_text);
                            $my_text = str_replace("{amount}", format_currency($amount), $my_text);
                            $my_text = str_replace("{price}", format_currency($received), $my_text);
                            $my_text = str_replace("{time}", gettime(), $my_text);
                            sendMessAdmin($my_text);
                            echo "[<b style=\"color:green\">-</b>] Xử lý thành công 1 hoá đơn." . PHP_EOL;
                        }
                    }
                }
                if($CMSNT->get_row(" SELECT COUNT(id) FROM `log_bank_auto` WHERE `tid` = '" . $tid . "' AND `description` = '" . $description . "' AND `method` = '" . $bank["short_name"] . "'  ")["COUNT(id)"] == 0) {
                    $CMSNT->insert("log_bank_auto", ["tid" => $tid, "method" => $bank["short_name"], "description" => $description, "type" => $data["type"], "amount" => $amount, "create_gettime" => gettime()]);
                }
            }
        }
    } elseif($bank["short_name"] == "TPBank" || $bank["short_name"] == "TPB") {
        $result = curl_get2("https://api.web2m.com/historyapitpb/" . $bank["token"]);
        if($CMSNT->site("debug_auto_bank") == 1) {
            echo $result;
        }
        $result = json_decode($result, true);
        foreach ($result["transactionInfos"] as $data) {
            $tid = check_string($data["id"]);
            $description = check_string($data["description"]);
            $amount = check_string($data["amount"]);
            $user_id = parse_order_id($description, $CMSNT->site("prefix_autobank"));
            if($amount < $CMSNT->site("bank_min") || $CMSNT->site("bank_max") < $amount) {
            } else {
                if(($getUser = $CMSNT->get_row(" SELECT * FROM `users` WHERE `id` = '" . $user_id . "' ")) && $CMSNT->num_rows(" SELECT * FROM `payment_bank` WHERE `tid` = '" . $tid . "' AND `description` = '" . $description . "'  ") == 0) {
                    $received = checkPromotion($amount);
                    $insertSv2 = $CMSNT->insert("payment_bank", ["tid" => $tid, "method" => $bank["short_name"], "user_id" => $getUser["id"], "description" => $description, "amount" => $amount, "received" => $received, "create_gettime" => gettime(), "create_time" => time()]);
                    if($insertSv2) {
                        $isCong = $user->AddCredits($getUser["id"], $received, "Nạp tiền tự động qua " . $bank["short_name"] . " (#" . $tid . " - " . $description . " - " . $amount . ")", "TOPUP_" . $bank["accountNumber"] . "_" . $tid);
                        if($isCong) {
                            debit_processing($getUser["id"]);
                            $CMSNT->insert("deposit_log", ["user_id" => $getUser["id"], "method" => $bank["short_name"], "amount" => $amount, "received" => $received, "create_time" => time(), "is_virtual" => 0]);
                            $my_text = $CMSNT->site("noti_recharge");
                            $my_text = str_replace("{domain}", $_SERVER["SERVER_NAME"], $my_text);
                            $my_text = str_replace("{username}", $getUser["username"], $my_text);
                            $my_text = str_replace("{method}", $bank["short_name"], $my_text);
                            $my_text = str_replace("{amount}", format_currency($amount), $my_text);
                            $my_text = str_replace("{price}", format_currency($received), $my_text);
                            $my_text = str_replace("{time}", gettime(), $my_text);
                            sendMessAdmin($my_text);
                            echo "[<b style=\"color:green\">-</b>] Xử lý thành công 1 hoá đơn." . PHP_EOL;
                        }
                    }
                }
                if($CMSNT->get_row(" SELECT COUNT(id) FROM `log_bank_auto` WHERE `tid` = '" . $tid . "' AND `description` = '" . $description . "' AND `method` = '" . $bank["short_name"] . "'  ")["COUNT(id)"] == 0) {
                    $CMSNT->insert("log_bank_auto", ["tid" => $tid, "method" => $bank["short_name"], "description" => $description, "type" => "", "amount" => $amount, "create_gettime" => gettime()]);
                }
            }
        }
    } elseif($bank["short_name"] == "BIDV") {
        $result = curl_get2("https://api.web2m.com/historyapibidv/" . $bank["password"] . "/" . $bank["accountNumber"] . "/" . $bank["token"]);
        if($CMSNT->site("debug_auto_bank") == 1) {
            echo $result;
        }
        $result = json_decode($result, true);
        foreach ($result["data"]["ChiTietGiaoDich"] as $data) {
            $tid = check_string($data["SoThamChieu"]);
            $description = check_string($data["MoTa"]);
            $amount = check_string(str_replace(",", "", $data["SoTienGhiCo"]));
            $user_id = parse_order_id($description, $CMSNT->site("prefix_autobank"));
            if($amount < $CMSNT->site("bank_min") || $CMSNT->site("bank_max") < $amount) {
            } else {
                if(($getUser = $CMSNT->get_row(" SELECT * FROM `users` WHERE `id` = '" . $user_id . "' ")) && $CMSNT->num_rows(" SELECT * FROM `payment_bank` WHERE `tid` = '" . $tid . "' AND `description` = '" . $description . "'  ") == 0) {
                    $received = checkPromotion($amount);
                    $insertSv2 = $CMSNT->insert("payment_bank", ["tid" => $tid, "method" => $bank["short_name"], "user_id" => $getUser["id"], "description" => $description, "amount" => $amount, "received" => $received, "create_gettime" => gettime(), "create_time" => time()]);
                    if($insertSv2) {
                        $isCong = $user->AddCredits($getUser["id"], $received, "Nạp tiền tự động qua " . $bank["short_name"] . " (#" . $tid . " - " . $description . " - " . $amount . ")", "TOPUP_" . $bank["accountNumber"] . "_" . $tid);
                        if($isCong) {
                            debit_processing($getUser["id"]);
                            $CMSNT->insert("deposit_log", ["user_id" => $getUser["id"], "method" => $bank["short_name"], "amount" => $amount, "received" => $received, "create_time" => time(), "is_virtual" => 0]);
                            $my_text = $CMSNT->site("noti_recharge");
                            $my_text = str_replace("{domain}", $_SERVER["SERVER_NAME"], $my_text);
                            $my_text = str_replace("{username}", $getUser["username"], $my_text);
                            $my_text = str_replace("{method}", $bank["short_name"], $my_text);
                            $my_text = str_replace("{amount}", format_currency($amount), $my_text);
                            $my_text = str_replace("{price}", format_currency($received), $my_text);
                            $my_text = str_replace("{time}", gettime(), $my_text);
                            sendMessAdmin($my_text);
                            echo "[<b style=\"color:green\">-</b>] Xử lý thành công 1 hoá đơn." . PHP_EOL;
                        }
                    }
                }
                if($CMSNT->get_row(" SELECT COUNT(id) FROM `log_bank_auto` WHERE `tid` = '" . $tid . "' AND `description` = '" . $description . "' AND `method` = '" . $bank["short_name"] . "'  ")["COUNT(id)"] == 0) {
                    $CMSNT->insert("log_bank_auto", ["tid" => $tid, "method" => $bank["short_name"], "description" => $description, "type" => $data["type"], "amount" => $amount, "create_gettime" => gettime()]);
                }
            }
        }
    } elseif($bank["short_name"] == "SEABANK" || $bank["short_name"] == "SeABank" || $bank["short_name"] == "SEAB") {
        $result = curl_get2("https://api.web2m.com/historyapiseabank/" . $bank["token"]);
        if($CMSNT->site("debug_auto_bank") == 1) {
            echo $result;
        }
        $result = json_decode($result, true);
        foreach ($result["data"] as $data) {
            $tid = check_string($data["transID"]);
            $description = check_string($data["description"]);
            $amount = check_string($data["totalAmount"]);
            $user_id = parse_order_id($description, $CMSNT->site("prefix_autobank"));
            if($amount < $CMSNT->site("bank_min") || $CMSNT->site("bank_max") < $amount) {
            } else {
                if(($getUser = $CMSNT->get_row(" SELECT * FROM `users` WHERE `id` = '" . $user_id . "' ")) && $CMSNT->num_rows(" SELECT * FROM `payment_bank` WHERE `tid` = '" . $tid . "' AND `description` = '" . $description . "'  ") == 0) {
                    $received = checkPromotion($amount);
                    $insertSv2 = $CMSNT->insert("payment_bank", ["tid" => $tid, "method" => $bank["short_name"], "user_id" => $getUser["id"], "description" => $description, "amount" => $amount, "received" => $received, "create_gettime" => gettime(), "create_time" => time()]);
                    if($insertSv2) {
                        $isCong = $user->AddCredits($getUser["id"], $received, "Nạp tiền tự động qua " . $bank["short_name"] . " (#" . $tid . " - " . $description . " - " . $amount . ")", "TOPUP_" . $bank["accountNumber"] . "_" . $tid);
                        if($isCong) {
                            debit_processing($getUser["id"]);
                            $CMSNT->insert("deposit_log", ["user_id" => $getUser["id"], "method" => $bank["short_name"], "amount" => $amount, "received" => $received, "create_time" => time(), "is_virtual" => 0]);
                            $my_text = $CMSNT->site("noti_recharge");
                            $my_text = str_replace("{domain}", $_SERVER["SERVER_NAME"], $my_text);
                            $my_text = str_replace("{username}", $getUser["username"], $my_text);
                            $my_text = str_replace("{method}", $bank["short_name"], $my_text);
                            $my_text = str_replace("{amount}", format_currency($amount), $my_text);
                            $my_text = str_replace("{price}", format_currency($received), $my_text);
                            $my_text = str_replace("{time}", gettime(), $my_text);
                            sendMessAdmin($my_text);
                            echo "[<b style=\"color:green\">-</b>] Xử lý thành công 1 hoá đơn." . PHP_EOL;
                        }
                    }
                }
                if($CMSNT->get_row(" SELECT COUNT(id) FROM `log_bank_auto` WHERE `tid` = '" . $tid . "' AND `description` = '" . $description . "' AND `method` = '" . $bank["short_name"] . "'  ")["COUNT(id)"] == 0) {
                    $CMSNT->insert("log_bank_auto", ["tid" => $tid, "method" => $bank["short_name"], "description" => $description, "type" => $data["type"], "amount" => $amount, "create_gettime" => gettime()]);
                }
            }
        }
    }
}
curl_get2(base_url("cron/cron.php"));

?>