<?php

    define("IN_SITE", true);
    require_once(__DIR__.'/../libs/db.php');
    require_once(__DIR__.'/../config.php');
    require_once(__DIR__.'/../libs/lang.php');
    require_once(__DIR__.'/../libs/helper.php');
    require_once(__DIR__.'/../libs/database/users.php');
    $CMSNT = new DB();
    $user = new users();



    // if (time() > $CMSNT->site('check_time_cron_bank')) {
    //     if (time() - $CMSNT->site('check_time_cron_bank') < 5) {
    //         die('[ÉT O ÉT ]Thao tác quá nhanh, vui lòng đợi');
    //     }
    // }
    $CMSNT->update("settings", ['value' => time()], " `name` = 'check_time_cron_bank' ");


    foreach($CMSNT->get_list_safe(" SELECT * FROM `banks` WHERE `status` = ? ", [1]) as $bank){
        if(strtolower($bank['short_name']) == 'seab' || strtolower($bank['short_name']) == 'seabank'){
            $result = curl_thueapibank("https://thueapibank.vn/historyapiseabankv2/".$bank['token']);
            if($CMSNT->site('debug_auto_bank') == 1){
                echo $result;
            }
            $result = json_decode($result, true);
            foreach ($result['transactions'] as $data) {
                $tid            = check_string($data['transactionID']);
                $description    = str_replace(' ', '.', check_string($data['description']));
                $amount         = check_string($data['amount']);
                $type           = check_string($data['type']);
                $user_id        = parse_order_id($description, $CMSNT->site('prefix_autobank'));         // TÁCH NỘI DUNG CHUYỂN TIỀN
                if($type != 'IN'){
                    continue;
                }
                if($amount < $CMSNT->site('bank_min') || $amount > $CMSNT->site('bank_max')){
                    continue;
                }
                if($getUser = $CMSNT->get_row_safe(" SELECT * FROM `users` WHERE `id` = ? ", [$user_id])){
                    if($CMSNT->num_rows_safe(" SELECT * FROM `payment_bank` WHERE `tid` = ? AND `description` = ? ", [$tid, $description]) == 0){
                        $received = checkPromotion($amount);
                        $insertSv2 = $CMSNT->insert("payment_bank", array(
                            'tid'               => $tid,
                            'method'            => $bank['short_name'],
                            'user_id'           => $getUser['id'],
                            'description'       => $description,
                            'amount'            => $amount,
                            'received'          => $received,
                            'create_gettime'    => gettime(),
                            'create_time'       => time()
                        ));
                        if ($insertSv2){
                            $isCong = $user->AddCredits($getUser['id'], $received, "Nạp tiền tự động qua ".$bank['short_name']." (#$tid - $description - $amount)", 'TOPUP_'.$bank['accountNumber'].'_'.$tid);
                            if($isCong){
                                
                                // XỬ LÝ TIỀN NỢ NẾU CÓ
                                debit_processing($getUser['id']);
                                // TẠO LOG GIAO DỊCH GẦN ĐÂY
                                $CMSNT->insert('deposit_log',[
                                    'user_id'       => $getUser['id'],
                                    'method'        => $bank['short_name'],
                                    'amount'        => $amount,
                                    'received'      => $received,
                                    'create_time'   => time(),
                                    'is_virtual'    => 0
                                ]);
                                /** SEND NOTI CHO ADMIN */
                                $my_text = $CMSNT->site('noti_recharge');
                                $my_text = str_replace('{domain}', $_SERVER['SERVER_NAME'], $my_text);
                                $my_text = str_replace('{username}', $getUser['username'], $my_text);
                                $my_text = str_replace('{method}', $bank['short_name'], $my_text);
                                $my_text = str_replace('{amount}', format_currency($amount), $my_text);
                                $my_text = str_replace('{price}', format_currency($received), $my_text);
                                $my_text = str_replace('{time}', gettime(), $my_text);
                                sendMessAdmin($my_text);
                                echo '[<b style="color:green">-</b>] Xử lý thành công 1 hoá đơn.'.PHP_EOL;
                            }
                        }
                    }
                }
                if($CMSNT->get_row_safe(" SELECT COUNT(id) FROM `log_bank_auto` WHERE `tid` = ? AND `description` = ? AND `method` = ? ", [$tid, $description, $bank['short_name']])['COUNT(id)'] == 0){
                    $CMSNT->insert("log_bank_auto", array(
                        'tid'               => $tid,
                        'method'            => $bank['short_name'],
                        'description'       => $description,
                        'type'              => $type,
                        'amount'            => $amount,
                        'create_gettime'    => gettime()
                    ));
                }
            }
            continue;
        }
        if(strtolower($bank['short_name']) == 'acb'){
            $result = curl_thueapibank("https://thueapibank.vn/historyapiacbv3/".$bank['password']."/".$bank['accountNumber']."/".$bank['token']);
            if($CMSNT->site('debug_auto_bank') == 1){
                echo $result;
            }
            $result = json_decode($result, true);
            foreach ($result['transactions'] as $data) {
                $tid            = check_string($data['transactionID']);
                $description    = str_replace(' ', '.', check_string($data['description']));
                $amount         = check_string($data['amount']);
                $type           = check_string($data['type']);
                $user_id        = parse_order_id($description, $CMSNT->site('prefix_autobank'));         // TÁCH NỘI DUNG CHUYỂN TIỀN
                if($type != 'IN'){
                    continue;
                }
                if($amount < $CMSNT->site('bank_min') || $amount > $CMSNT->site('bank_max')){
                    continue;
                }
                if($getUser = $CMSNT->get_row_safe(" SELECT * FROM `users` WHERE `id` = ? ", [$user_id])){
                    if($CMSNT->num_rows_safe(" SELECT * FROM `payment_bank` WHERE `tid` = ? AND `description` = ? ", [$tid, $description]) == 0){
                        $received = checkPromotion($amount);
                        $insertSv2 = $CMSNT->insert("payment_bank", array(
                            'tid'               => $tid,
                            'method'            => $bank['short_name'],
                            'user_id'           => $getUser['id'],
                            'description'       => $description,
                            'amount'            => $amount,
                            'received'          => $received,
                            'create_gettime'    => gettime(),
                            'create_time'       => time()
                        ));
                        if ($insertSv2){
                            $isCong = $user->AddCredits($getUser['id'], $received, "Nạp tiền tự động qua ".$bank['short_name']." (#$tid - $description - $amount)", 'TOPUP_'.$bank['accountNumber'].'_'.$tid);
                            if($isCong){
                                
                                // XỬ LÝ TIỀN NỢ NẾU CÓ
                                debit_processing($getUser['id']);
                                // TẠO LOG GIAO DỊCH GẦN ĐÂY
                                $CMSNT->insert('deposit_log',[
                                    'user_id'       => $getUser['id'],
                                    'method'        => $bank['short_name'],
                                    'amount'        => $amount,
                                    'received'      => $received,
                                    'create_time'   => time(),
                                    'is_virtual'    => 0
                                ]);
                                /** SEND NOTI CHO ADMIN */
                                $my_text = $CMSNT->site('noti_recharge');
                                $my_text = str_replace('{domain}', $_SERVER['SERVER_NAME'], $my_text);
                                $my_text = str_replace('{username}', $getUser['username'], $my_text);
                                $my_text = str_replace('{method}', $bank['short_name'], $my_text);
                                $my_text = str_replace('{amount}', format_currency($amount), $my_text);
                                $my_text = str_replace('{price}', format_currency($received), $my_text);
                                $my_text = str_replace('{time}', gettime(), $my_text);
                                sendMessAdmin($my_text);
                                echo '[<b style="color:green">-</b>] Xử lý thành công 1 hoá đơn.'.PHP_EOL;
                            }
                        }
                    }
                }
                if($CMSNT->get_row_safe(" SELECT COUNT(id) FROM `log_bank_auto` WHERE `tid` = ? AND `description` = ? AND `method` = ? ", [$tid, $description, $bank['short_name']])['COUNT(id)'] == 0){
                    $CMSNT->insert("log_bank_auto", array(
                        'tid'               => $tid,
                        'method'            => $bank['short_name'],
                        'description'       => $description,
                        'type'              => $data['type'],
                        'amount'            => $amount,
                        'create_gettime'    => gettime()
                    ));
                }
            }
            continue;
        }

        if(strtolower($bank['short_name']) == 'vietinbank' || strtolower($bank['short_name']) == 'vtb'){
            $result = curl_thueapibank("https://thueapibank.vn/historyapiviettinv3/".$bank['password']."/".$bank['accountNumber']."/".$bank['token']);
            if($CMSNT->site('debug_auto_bank') == 1){
                echo $result;
            }
            $result = json_decode($result, true);
            foreach ($result['transactions'] as $data) {
                $tid            = check_string($data['transactionID']);
                $description    = str_replace(' ', '.', check_string($data['description']));
                $amount         = check_string($data['amount']);
                $type           = check_string($data['type']);
                $user_id        = parse_order_id($description, $CMSNT->site('prefix_autobank'));         // TÁCH NỘI DUNG CHUYỂN TIỀN
                if($type != 'IN'){
                    continue;
                }
                if($amount < $CMSNT->site('bank_min') || $amount > $CMSNT->site('bank_max')){
                    continue;
                }
                if($getUser = $CMSNT->get_row_safe(" SELECT * FROM `users` WHERE `id` = ? ", [$user_id])){
                    if($CMSNT->num_rows_safe(" SELECT * FROM `payment_bank` WHERE `tid` = ? AND `description` = ? ", [$tid, $description]) == 0){
                        $received = checkPromotion($amount);
                        $insertSv2 = $CMSNT->insert("payment_bank", array(
                            'tid'               => $tid,
                            'method'            => $bank['short_name'],
                            'user_id'           => $getUser['id'],
                            'description'       => $description,
                            'amount'            => $amount,
                            'received'          => $received,
                            'create_gettime'    => gettime(),
                            'create_time'       => time()
                        ));
                        if ($insertSv2){
                            $isCong = $user->AddCredits($getUser['id'], $received, "Nạp tiền tự động qua ".$bank['short_name']." (#$tid - $description - $amount)", 'TOPUP_'.$bank['accountNumber'].'_'.$tid);
                            if($isCong){
                                
                                // XỬ LÝ TIỀN NỢ NẾU CÓ
                                debit_processing($getUser['id']);
                                // TẠO LOG GIAO DỊCH GẦN ĐÂY
                                $CMSNT->insert('deposit_log',[
                                    'user_id'       => $getUser['id'],
                                    'method'        => $bank['short_name'],
                                    'amount'        => $amount,
                                    'received'      => $received,
                                    'create_time'   => time(),
                                    'is_virtual'    => 0
                                ]);
                                /** SEND NOTI CHO ADMIN */
                                $my_text = $CMSNT->site('noti_recharge');
                                $my_text = str_replace('{domain}', $_SERVER['SERVER_NAME'], $my_text);
                                $my_text = str_replace('{username}', $getUser['username'], $my_text);
                                $my_text = str_replace('{method}', $bank['short_name'], $my_text);
                                $my_text = str_replace('{amount}', format_currency($amount), $my_text);
                                $my_text = str_replace('{price}', format_currency($received), $my_text);
                                $my_text = str_replace('{time}', gettime(), $my_text);
                                sendMessAdmin($my_text);
                                echo '[<b style="color:green">-</b>] Xử lý thành công 1 hoá đơn.'.PHP_EOL;
                            }
                        }
                    }
                }
                if($CMSNT->get_row_safe(" SELECT COUNT(id) FROM `log_bank_auto` WHERE `tid` = ? AND `description` = ? AND `method` = ? ", [$tid, $description, $bank['short_name']])['COUNT(id)'] == 0){
                    $CMSNT->insert("log_bank_auto", array(
                        'tid'               => $tid,
                        'method'            => $bank['short_name'],
                        'description'       => $description,
                        'type'              => $data['type'],
                        'amount'            => $amount,
                        'create_gettime'    => gettime()
                    ));
                }
            }
            continue;
        }


        if(strtolower($bank['short_name']) == 'techcombank' || strtolower($bank['short_name']) == 'tcb'){
            $result = curl_thueapibank("https://thueapibank.vn/historyapitcbv2/".$bank['token']);
            if($CMSNT->site('debug_auto_bank') == 1){
                echo $result;
            }
            $result = json_decode($result, true);
            foreach ($result['transactions'] as $data) {
                $tid            = check_string($data['transactionID']);
                $description    = str_replace(' ', '.', check_string($data['description']));
                $amount         = check_string($data['amount']);
                $type           = check_string($data['type']);
                $user_id        = parse_order_id($description, $CMSNT->site('prefix_autobank'));         // TÁCH NỘI DUNG CHUYỂN TIỀN
                if($type != 'IN'){
                    continue;
                }
                if($amount < $CMSNT->site('bank_min') || $amount > $CMSNT->site('bank_max')){
                    continue;
                }
                if($getUser = $CMSNT->get_row_safe(" SELECT * FROM `users` WHERE `id` = ? ", [$user_id])){
                    if($CMSNT->num_rows_safe(" SELECT * FROM `payment_bank` WHERE `tid` = ? AND `description` = ? ", [$tid, $description]) == 0){
                        $received = checkPromotion($amount);
                        $insertSv2 = $CMSNT->insert("payment_bank", array(
                            'tid'               => $tid,
                            'method'            => $bank['short_name'],
                            'user_id'           => $getUser['id'],
                            'description'       => $description,
                            'amount'            => $amount,
                            'received'          => $received,
                            'create_gettime'    => gettime(),
                            'create_time'       => time()
                        ));
                        if ($insertSv2){
                            $isCong = $user->AddCredits($getUser['id'], $received, "Nạp tiền tự động qua ".$bank['short_name']." (#$tid - $description - $amount)", 'TOPUP_'.$bank['accountNumber'].'_'.$tid);
                            if($isCong){
                                
                                // XỬ LÝ TIỀN NỢ NẾU CÓ
                                debit_processing($getUser['id']);
                                // TẠO LOG GIAO DỊCH GẦN ĐÂY
                                $CMSNT->insert('deposit_log',[
                                    'user_id'       => $getUser['id'],
                                    'method'        => $bank['short_name'],
                                    'amount'        => $amount,
                                    'received'      => $received,
                                    'create_time'   => time(),
                                    'is_virtual'    => 0
                                ]);
                                /** SEND NOTI CHO ADMIN */
                                $my_text = $CMSNT->site('noti_recharge');
                                $my_text = str_replace('{domain}', $_SERVER['SERVER_NAME'], $my_text);
                                $my_text = str_replace('{username}', $getUser['username'], $my_text);
                                $my_text = str_replace('{method}', $bank['short_name'], $my_text);
                                $my_text = str_replace('{amount}', format_currency($amount), $my_text);
                                $my_text = str_replace('{price}', format_currency($received), $my_text);
                                $my_text = str_replace('{time}', gettime(), $my_text);
                                sendMessAdmin($my_text);
                                echo '[<b style="color:green">-</b>] Xử lý thành công 1 hoá đơn.'.PHP_EOL;
                            }
                        }
                    }
                }
                if($CMSNT->get_row_safe(" SELECT COUNT(id) FROM `log_bank_auto` WHERE `tid` = ? AND `description` = ? AND `method` = ? ", [$tid, $description, $bank['short_name']])['COUNT(id)'] == 0){
                    $CMSNT->insert("log_bank_auto", array(
                        'tid'               => $tid,
                        'method'            => $bank['short_name'],
                        'description'       => $description,
                        'type'              => $data['type'],
                        'amount'            => $amount,
                        'create_gettime'    => gettime()
                    ));
                }
            }
            continue;
        }


        if(strtolower($bank['short_name']) == 'vcb' || strtolower($bank['short_name']) == 'vietcombank'){
            $result = curl_thueapibank("https://thueapibank.vn/historyapivcbv3/".$bank['password']."/".$bank['accountNumber']."/".$bank['token']);
            if($CMSNT->site('debug_auto_bank') == 1){
                echo $result;
            }
            $result = json_decode($result, true);
            foreach ($result['transactions'] as $data) {
                $tid            = check_string($data['transactionID']);
                $description    = str_replace(' ', '.', check_string($data['description']));
                $amount         = check_string($data['amount']);
                $type           = check_string($data['type']);
                $user_id        = parse_order_id($description, $CMSNT->site('prefix_autobank'));         // TÁCH NỘI DUNG CHUYỂN TIỀN
                if($type != 'IN'){
                    continue;
                }
                if($amount < $CMSNT->site('bank_min') || $amount > $CMSNT->site('bank_max')){
                    continue;
                }
                if($getUser = $CMSNT->get_row_safe(" SELECT * FROM `users` WHERE `id` = ? ", [$user_id])){
                    if($CMSNT->num_rows_safe(" SELECT * FROM `payment_bank` WHERE `tid` = ? AND `description` = ? ", [$tid, $description]) == 0){
                        $received = checkPromotion($amount);
                        $insertSv2 = $CMSNT->insert("payment_bank", array(
                            'tid'               => $tid,
                            'method'            => $bank['short_name'],
                            'user_id'           => $getUser['id'],
                            'description'       => $description,
                            'amount'            => $amount,
                            'received'          => $received,
                            'create_gettime'    => gettime(),
                            'create_time'       => time()
                        ));
                        if ($insertSv2){
                            $isCong = $user->AddCredits($getUser['id'], $received, "Nạp tiền tự động qua ".$bank['short_name']." (#$tid - $description - $amount)", 'TOPUP_'.$bank['accountNumber'].'_'.$tid);
                            if($isCong){
                                
                                // XỬ LÝ TIỀN NỢ NẾU CÓ
                                debit_processing($getUser['id']);
                                // TẠO LOG GIAO DỊCH GẦN ĐÂY
                                $CMSNT->insert('deposit_log',[
                                    'user_id'       => $getUser['id'],
                                    'method'        => $bank['short_name'],
                                    'amount'        => $amount,
                                    'received'      => $received,
                                    'create_time'   => time(),
                                    'is_virtual'    => 0
                                ]);
                                /** SEND NOTI CHO ADMIN */
                                $my_text = $CMSNT->site('noti_recharge');
                                $my_text = str_replace('{domain}', $_SERVER['SERVER_NAME'], $my_text);
                                $my_text = str_replace('{username}', $getUser['username'], $my_text);
                                $my_text = str_replace('{method}', $bank['short_name'], $my_text);
                                $my_text = str_replace('{amount}', format_currency($amount), $my_text);
                                $my_text = str_replace('{price}', format_currency($received), $my_text);
                                $my_text = str_replace('{time}', gettime(), $my_text);
                                sendMessAdmin($my_text);
                                echo '[<b style="color:green">-</b>] Xử lý thành công 1 hoá đơn.'.PHP_EOL;
                            }
                        }
                    }
                }
                if($CMSNT->get_row_safe(" SELECT COUNT(id) FROM `log_bank_auto` WHERE `tid` = ? AND `description` = ? AND `method` = ? ", [$tid, $description, $bank['short_name']])['COUNT(id)'] == 0){
                    $CMSNT->insert("log_bank_auto", array(
                        'tid'               => $tid,
                        'method'            => $bank['short_name'],
                        'description'       => $description,
                        'type'              => $data['type'],
                        'amount'            => $amount,
                        'create_gettime'    => gettime()
                    ));
                }
            }
            continue;
        }


        if(strtolower($bank['short_name']) == 'vpbank' || strtolower($bank['short_name']) == 'vpb'){
            $result = curl_thueapibank("https://thueapibank.vn/historyapivpbv2/".$bank['token']);
            if($CMSNT->site('debug_auto_bank') == 1){
                echo $result;
            }
            $result = json_decode($result, true);
            foreach ($result['transactions'] as $data) {
                $tid            = check_string($data['transactionID']);
                $description    = str_replace(' ', '.', check_string($data['description']));
                $amount         = check_string($data['amount']);
                $type           = check_string($data['type']);
                $user_id        = parse_order_id($description, $CMSNT->site('prefix_autobank'));         // TÁCH NỘI DUNG CHUYỂN TIỀN
                if($type != 'IN'){
                    continue;
                }
                if($amount < $CMSNT->site('bank_min') || $amount > $CMSNT->site('bank_max')){
                    continue;
                }
                if($getUser = $CMSNT->get_row_safe(" SELECT * FROM `users` WHERE `id` = ? ", [$user_id])){
                    if($CMSNT->num_rows_safe(" SELECT * FROM `payment_bank` WHERE `tid` = ? AND `description` = ? ", [$tid, $description]) == 0){
                        $received = checkPromotion($amount);
                        $insertSv2 = $CMSNT->insert("payment_bank", array(
                            'tid'               => $tid,
                            'method'            => $bank['short_name'],
                            'user_id'           => $getUser['id'],
                            'description'       => $description,
                            'amount'            => $amount,
                            'received'          => $received,
                            'create_gettime'    => gettime(),
                            'create_time'       => time()
                        ));
                        if ($insertSv2){
                            $isCong = $user->AddCredits($getUser['id'], $received, "Nạp tiền tự động qua ".$bank['short_name']." (#$tid - $description - $amount)", 'TOPUP_'.$bank['accountNumber'].'_'.$tid);
                            if($isCong){
                                
                                // XỬ LÝ TIỀN NỢ NẾU CÓ
                                debit_processing($getUser['id']);
                                // TẠO LOG GIAO DỊCH GẦN ĐÂY
                                $CMSNT->insert('deposit_log',[
                                    'user_id'       => $getUser['id'],
                                    'method'        => $bank['short_name'],
                                    'amount'        => $amount,
                                    'received'      => $received,
                                    'create_time'   => time(),
                                    'is_virtual'    => 0
                                ]);
                                /** SEND NOTI CHO ADMIN */
                                $my_text = $CMSNT->site('noti_recharge');
                                $my_text = str_replace('{domain}', $_SERVER['SERVER_NAME'], $my_text);
                                $my_text = str_replace('{username}', $getUser['username'], $my_text);
                                $my_text = str_replace('{method}', $bank['short_name'], $my_text);
                                $my_text = str_replace('{amount}', format_currency($amount), $my_text);
                                $my_text = str_replace('{price}', format_currency($received), $my_text);
                                $my_text = str_replace('{time}', gettime(), $my_text);
                                sendMessAdmin($my_text);
                                echo '[<b style="color:green">-</b>] Xử lý thành công 1 hoá đơn.'.PHP_EOL;
                            }
                        }
                    }
                }
                if($CMSNT->get_row_safe(" SELECT COUNT(id) FROM `log_bank_auto` WHERE `tid` = ? AND `description` = ? AND `method` = ? ", [$tid, $description, $bank['short_name']])['COUNT(id)'] == 0){
                    $CMSNT->insert("log_bank_auto", array(
                        'tid'               => $tid,
                        'method'            => $bank['short_name'],
                        'description'       => $description,
                        'type'              => $data['type'],
                        'amount'            => $amount,
                        'create_gettime'    => gettime()
                    ));
                }
            }
            continue;
        }


        if(strtolower($bank['short_name']) == 'mb' || strtolower($bank['short_name']) == 'mbbank'){
            $result = curl_thueapibank("https://thueapibank.vn/historyapimbv3/".$bank['password']."/".$bank['accountNumber']."/".$bank['token']);
            
                echo $result;
            
            $result = json_decode($result, true);
            foreach ($result['transactions'] as $data) {
                $tid            = check_string($data['transactionID']);
                $description    = str_replace(' ', '.', check_string($data['description']));
                $amount         = check_string($data['amount']);
                $type           = check_string($data['type']);
                $user_id        = parse_order_id($description, $CMSNT->site('prefix_autobank'));         // TÁCH NỘI DUNG CHUYỂN TIỀN
                if($type != 'IN'){
                    continue;
                }
                if($amount < $CMSNT->site('bank_min') || $amount > $CMSNT->site('bank_max')){
                    continue;
                }
                if($getUser = $CMSNT->get_row_safe(" SELECT * FROM `users` WHERE `id` = ? ", [$user_id])){
                    if($CMSNT->num_rows_safe(" SELECT * FROM `payment_bank` WHERE `tid` = ? AND `description` = ? ", [$tid, $description]) == 0){
                        $received = checkPromotion($amount);
                        $insertSv2 = $CMSNT->insert("payment_bank", array(
                            'tid'               => $tid,
                            'method'            => $bank['short_name'],
                            'user_id'           => $getUser['id'],
                            'description'       => $description,
                            'amount'            => $amount,
                            'received'          => $received,
                            'create_gettime'    => gettime(),
                            'create_time'       => time()
                        ));
                        if ($insertSv2){
                            $isCong = $user->AddCredits($getUser['id'], $received, "Nạp tiền tự động qua ".$bank['short_name']." (#$tid - $description - $amount)", 'TOPUP_'.$bank['accountNumber'].'_'.$tid);
                            if($isCong){
                                
                                // XỬ LÝ TIỀN NỢ NẾU CÓ
                                debit_processing($getUser['id']);
                                // TẠO LOG GIAO DỊCH GẦN ĐÂY
                                $CMSNT->insert('deposit_log',[
                                    'user_id'       => $getUser['id'],
                                    'method'        => $bank['short_name'],
                                    'amount'        => $amount,
                                    'received'      => $received,
                                    'create_time'   => time(),
                                    'is_virtual'    => 0
                                ]);
                                /** SEND NOTI CHO ADMIN */
                                $my_text = $CMSNT->site('noti_recharge');
                                $my_text = str_replace('{domain}', $_SERVER['SERVER_NAME'], $my_text);
                                $my_text = str_replace('{username}', $getUser['username'], $my_text);
                                $my_text = str_replace('{method}', $bank['short_name'], $my_text);
                                $my_text = str_replace('{amount}', format_currency($amount), $my_text);
                                $my_text = str_replace('{price}', format_currency($received), $my_text);
                                $my_text = str_replace('{time}', gettime(), $my_text);
                                sendMessAdmin($my_text);
                                echo '[<b style="color:green">-</b>] Xử lý thành công 1 hoá đơn.'.PHP_EOL;
                            }
                        }
                    }
                }
                if($CMSNT->get_row_safe(" SELECT COUNT(id) FROM `log_bank_auto` WHERE `tid` = ? AND `description` = ? AND `method` = ? ", [$tid, $description, $bank['short_name']])['COUNT(id)'] == 0){
                    $CMSNT->insert("log_bank_auto", array(
                        'tid'               => $tid,
                        'method'            => $bank['short_name'],
                        'description'       => $description,
                        'type'              => $data['type'],
                        'amount'            => $amount,
                        'create_gettime'    => gettime()
                    ));
                }
            }
            continue;
        }

        if(strtolower($bank['short_name']) == 'tpbank' || strtolower($bank['short_name']) == 'tpb'){
            $result = curl_thueapibank("https://thueapibank.vn/historyapitpbv2/".$bank['token']);
            if($CMSNT->site('debug_auto_bank') == 1){
                echo $result;
            }
            $result = json_decode($result, true);
            foreach ($result['transactions'] as $data) {
                $tid            = check_string($data['transactionID']);
                $description    = str_replace(' ', '.', check_string($data['description']));
                $amount         = check_string($data['amount']);
                $type           = check_string($data['type']);
                $user_id        = parse_order_id($description, $CMSNT->site('prefix_autobank'));         // TÁCH NỘI DUNG CHUYỂN TIỀN
                if($type != 'IN'){
                    continue;
                }
                if($amount < $CMSNT->site('bank_min') || $amount > $CMSNT->site('bank_max')){
                    continue;
                }
                if($getUser = $CMSNT->get_row_safe(" SELECT * FROM `users` WHERE `id` = ? ", [$user_id])){
                    if($CMSNT->num_rows_safe(" SELECT * FROM `payment_bank` WHERE `tid` = ? AND `description` = ? ", [$tid, $description]) == 0){
                        $received = checkPromotion($amount);
                        $insertSv2 = $CMSNT->insert("payment_bank", array(
                            'tid'               => $tid,
                            'method'            => $bank['short_name'],
                            'user_id'           => $getUser['id'],
                            'description'       => $description,
                            'amount'            => $amount,
                            'received'          => $received,
                            'create_gettime'    => gettime(),
                            'create_time'       => time()
                        ));
                        if ($insertSv2){
                            $isCong = $user->AddCredits($getUser['id'], $received, "Nạp tiền tự động qua ".$bank['short_name']." (#$tid - $description - $amount)", 'TOPUP_'.$bank['accountNumber'].'_'.$tid);
                            if($isCong){
                                
                                // XỬ LÝ TIỀN NỢ NẾU CÓ
                                debit_processing($getUser['id']);
                                // TẠO LOG GIAO DỊCH GẦN ĐÂY
                                $CMSNT->insert('deposit_log',[
                                    'user_id'       => $getUser['id'],
                                    'method'        => $bank['short_name'],
                                    'amount'        => $amount,
                                    'received'      => $received,
                                    'create_time'   => time(),
                                    'is_virtual'    => 0
                                ]);
                                /** SEND NOTI CHO ADMIN */
                                $my_text = $CMSNT->site('noti_recharge');
                                $my_text = str_replace('{domain}', $_SERVER['SERVER_NAME'], $my_text);
                                $my_text = str_replace('{username}', $getUser['username'], $my_text);
                                $my_text = str_replace('{method}', $bank['short_name'], $my_text);
                                $my_text = str_replace('{amount}', format_currency($amount), $my_text);
                                $my_text = str_replace('{price}', format_currency($received), $my_text);
                                $my_text = str_replace('{time}', gettime(), $my_text);
                                sendMessAdmin($my_text);
                                echo '[<b style="color:green">-</b>] Xử lý thành công 1 hoá đơn.'.PHP_EOL;
                            }
                        }
                    }
                }
                if($CMSNT->get_row_safe(" SELECT COUNT(id) FROM `log_bank_auto` WHERE `tid` = ? AND `description` = ? AND `method` = ? ", [$tid, $description, $bank['short_name']])['COUNT(id)'] == 0){
                    $CMSNT->insert("log_bank_auto", array(
                        'tid'               => $tid,
                        'method'            => $bank['short_name'],
                        'description'       => $description,
                        'type'              => '',
                        'amount'            => $amount,
                        'create_gettime'    => gettime()
                    ));
                }
            }
            continue;
        }

        if(strtolower($bank['short_name']) == 'bidv'){
            $result = curl_thueapibank("https://thueapibank.vn/historyapibidvv3/".$bank['password']."/".$bank['accountNumber']."/".$bank['token']);
            if($CMSNT->site('debug_auto_bank') == 1){
                echo $result;
            }
            $result = json_decode($result, true);
            foreach ($result['transactions'] as $data) {
                $tid            = check_string($data['transactionID']);
                $description    = str_replace(' ', '.', check_string($data['description']));
                $amount         = check_string($data['amount']);
                $type           = check_string($data['type']);
                $user_id        = parse_order_id($description, $CMSNT->site('prefix_autobank'));         // TÁCH NỘI DUNG CHUYỂN TIỀN
                if($type != 'IN'){
                    continue;
                }
                if($amount < $CMSNT->site('bank_min') || $amount > $CMSNT->site('bank_max')){
                    continue;
                }
                if($getUser = $CMSNT->get_row_safe(" SELECT * FROM `users` WHERE `id` = ? ", [$user_id])){
                    if($CMSNT->num_rows_safe(" SELECT * FROM `payment_bank` WHERE `tid` = ? AND `description` = ? ", [$tid, $description]) == 0){
                        $received = checkPromotion($amount);
                        $insertSv2 = $CMSNT->insert("payment_bank", array(
                            'tid'               => $tid,
                            'method'            => $bank['short_name'],
                            'user_id'           => $getUser['id'],
                            'description'       => $description,
                            'amount'            => $amount,
                            'received'          => $received,
                            'create_gettime'    => gettime(),
                            'create_time'       => time()
                        ));
                        if ($insertSv2){
                            $isCong = $user->AddCredits($getUser['id'], $received, "Nạp tiền tự động qua ".$bank['short_name']." (#$tid - $description - $amount)", 'TOPUP_'.$bank['accountNumber'].'_'.$tid);
                            if($isCong){
                                
                                // XỬ LÝ TIỀN NỢ NẾU CÓ
                                debit_processing($getUser['id']);
                                // TẠO LOG GIAO DỊCH GẦN ĐÂY
                                $CMSNT->insert('deposit_log',[
                                    'user_id'       => $getUser['id'],
                                    'method'        => $bank['short_name'],
                                    'amount'        => $amount,
                                    'received'      => $received,
                                    'create_time'   => time(),
                                    'is_virtual'    => 0
                                ]);
                                /** SEND NOTI CHO ADMIN */
                                $my_text = $CMSNT->site('noti_recharge');
                                $my_text = str_replace('{domain}', $_SERVER['SERVER_NAME'], $my_text);
                                $my_text = str_replace('{username}', $getUser['username'], $my_text);
                                $my_text = str_replace('{method}', $bank['short_name'], $my_text);
                                $my_text = str_replace('{amount}', format_currency($amount), $my_text);
                                $my_text = str_replace('{price}', format_currency($received), $my_text);
                                $my_text = str_replace('{time}', gettime(), $my_text);
                                sendMessAdmin($my_text);
                                echo '[<b style="color:green">-</b>] Xử lý thành công 1 hoá đơn.'.PHP_EOL;
                            }
                        }
                    }
                }
                if($CMSNT->get_row_safe(" SELECT COUNT(id) FROM `log_bank_auto` WHERE `tid` = ? AND `description` = ? AND `method` = ? ", [$tid, $description, $bank['short_name']])['COUNT(id)'] == 0){
                    $CMSNT->insert("log_bank_auto", array(
                        'tid'               => $tid,
                        'method'            => $bank['short_name'],
                        'description'       => $description,
                        'type'              => $data['type'],
                        'amount'            => $amount,
                        'create_gettime'    => gettime()
                    ));
                }
            }
            continue;
        }

        if(strtolower($bank['short_name']) == 'seabank' || strtolower($bank['short_name']) == 'seab'){
            $result = curl_thueapibank("https://thueapibank.vn/historyapiseabankv2/".$bank['token']);
            if($CMSNT->site('debug_auto_bank') == 1){
                echo $result;
            }
            $result = json_decode($result, true);
            foreach ($result['transactions'] as $data) {
                $tid            = check_string($data['transactionID']);
                $description    = str_replace(' ', '.', check_string($data['description']));
                $amount         = check_string($data['amount']);
                $type           = check_string($data['type']);
                $user_id        = parse_order_id($description, $CMSNT->site('prefix_autobank'));         // TÁCH NỘI DUNG CHUYỂN TIỀN
                if($type != 'IN'){
                    continue;
                }
                if($amount < $CMSNT->site('bank_min') || $amount > $CMSNT->site('bank_max')){
                    continue;
                }
                if($getUser = $CMSNT->get_row_safe(" SELECT * FROM `users` WHERE `id` = ? ", [$user_id])){
                    if($CMSNT->num_rows_safe(" SELECT * FROM `payment_bank` WHERE `tid` = ? AND `description` = ? ", [$tid, $description]) == 0){
                        $received = checkPromotion($amount);
                        $insertSv2 = $CMSNT->insert("payment_bank", array(
                            'tid'               => $tid,
                            'method'            => $bank['short_name'],
                            'user_id'           => $getUser['id'],
                            'description'       => $description,
                            'amount'            => $amount,
                            'received'          => $received,
                            'create_gettime'    => gettime(),
                            'create_time'       => time()
                        ));
                        if ($insertSv2){
                            $isCong = $user->AddCredits($getUser['id'], $received, "Nạp tiền tự động qua ".$bank['short_name']." (#$tid - $description - $amount)", 'TOPUP_'.$bank['accountNumber'].'_'.$tid);
                            if($isCong){
                                
                                // XỬ LÝ TIỀN NỢ NẾU CÓ
                                debit_processing($getUser['id']);
                                // TẠO LOG GIAO DỊCH GẦN ĐÂY
                                $CMSNT->insert('deposit_log',[
                                    'user_id'       => $getUser['id'],
                                    'method'        => $bank['short_name'],
                                    'amount'        => $amount,
                                    'received'      => $received,
                                    'create_time'   => time(),
                                    'is_virtual'    => 0
                                ]);
                                /** SEND NOTI CHO ADMIN */
                                $my_text = $CMSNT->site('noti_recharge');
                                $my_text = str_replace('{domain}', $_SERVER['SERVER_NAME'], $my_text);
                                $my_text = str_replace('{username}', $getUser['username'], $my_text);
                                $my_text = str_replace('{method}', $bank['short_name'], $my_text);
                                $my_text = str_replace('{amount}', format_currency($amount), $my_text);
                                $my_text = str_replace('{price}', format_currency($received), $my_text);
                                $my_text = str_replace('{time}', gettime(), $my_text);
                                sendMessAdmin($my_text);
                                echo '[<b style="color:green">-</b>] Xử lý thành công 1 hoá đơn.'.PHP_EOL;
                            }
                        }
                    }
                }
                if($CMSNT->get_row_safe(" SELECT COUNT(id) FROM `log_bank_auto` WHERE `tid` = ? AND `description` = ? AND `method` = ? ", [$tid, $description, $bank['short_name']])['COUNT(id)'] == 0){
                    $CMSNT->insert("log_bank_auto", array(
                        'tid'               => $tid,
                        'method'            => $bank['short_name'],
                        'description'       => $description,
                        'type'              => $data['type'],
                        'amount'            => $amount,
                        'create_gettime'    => gettime()
                    ));
                }
            }
            continue;
        }

    }
    
    function curl_thueapibank($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_ENCODING, "");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $result = curl_exec($ch);
        curl_close($ch);
        
        return $result;
    }

    curl_thueapibank(base_url('cron/cron.php'));