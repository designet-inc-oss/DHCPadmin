<?php

/*
 * postLDAPadmin
 *
 * Copyright (C) 2006,2007 DesigNET, INC.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.

 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.

 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
 */

/***********************************************************
 * Shared-network管理画面
 *
 * $RCSfile: index.php,v $
 * $Revision: 1.9 $
 * $Date: 2012/09/19 00:02:52 $
 **********************************************************/

include_once("../../initial");
include_once("lib/dglibpostldapadmin");
include_once("lib/dglibcommon");
include_once("lib/dglibpage");
include_once("lib/dglibsess");
include_once("lib/dglibdhcpadmin");

/********************************************************
 * 各ページ毎の設定
 ********************************************************/

define("TMPLFILE_MOD", "admin_network_sn_mod.tmpl");
define("OPERATION_UP", "Updating shared-network");
define("OPERATION_DEL", "Deleting shared-network");

/***********************************************************
 * 初期処理
 **********************************************************/
$template = TMPLFILE_MOD;

/* タグ初期化 */
$tag["<<TITLE>>"]       = "";
$tag["<<JAVASCRIPT>>"]  = "";
$tag["<<SK>>"]          = "";
$tag["<<TOPIC>>"]       = "";
$tag["<<MESSAGE>>"]     = "";
$tag["<<TAB>>"]         = "";
$tag["<<MENU>>"]        = "";
$tag["<<SNLIST>>"]      = "";
$tag["<<SN>>"]          = "";
$tag["<<OTHERSUBNET>>"] = "";
$tag["<<OLDNAME>>"]     = "";
$tag["<<SUBNET>>"]     = "";

$duplication = FALSE;
$before_sh = FALSE;
$delete_sh = FALSE;
$in_subnet = FALSE;
$ok        = FALSE;

/* 設定ファイルやタブ管理ファイル読込、セッションのチェック */
$ret = init();
if ($ret === FALSE) {
    syserr_display();
    exit(1);
}

/* dhcpd.confの解析 */
$ret = analyze_dhcpd_conf($web_conf["dhcpadmin"]["dhcpdconfpath"], "IPv4");
/* dhcpd.conf読み込みエラー */
if ($ret == FALSE) {
    $err_msg = $msgarr['27004'][SCREEN_MSG];
    $log_msg = $msgarr['27004'][LOG_MSG];
    result_log($log_msg, LOG_ERR);
    syserr_display();
    exit(1);
}

/* 二重ログインのチェック */
$ret = dhcpadmin_login_check($lock_file);
if ($ret === FUNC_FALSE) {
    $err_msg = sprintf($msgarr['27006'][SCREEN_MSG], $lock_file);
    $log_msg = sprintf($msgarr['27006'][LOG_MSG], $lock_file);
    result_log($log_msg, LOG_ERR);
    syserr_display();
    exit(1);
} elseif ($ret === LOCK_FALSE) {
    $err_msg = $msgarr['27005'][SCREEN_MSG];
    $log_msg = $msgarr['27005'][LOG_MSG];
    result_log($log_msg, LOG_ERR);
    syserr_display();
    exit(1);
}

/***********************************************************
 * main処理
 **********************************************************/

/* 更新ボタンが押されたら */
if (isset($_POST["mod"])) {
    $input   = isset($_POST["networkname"]) ? $_POST["networkname"] : "";
    $left    = isset($_POST["selectleft"])  ? $_POST["selectleft"]  : "";
    $right   = isset($_POST["selectright"]) ? $_POST["selectright"] : "";
    $oldname = isset($_POST["oldname"])     ? $_POST["oldname"]     : "";
    /* 入力値チェック */
    $ret = check_add_shnet($input);
    switch ($ret) {
        case 1:
            /* 入力値エラー */
            $err_msg = sprintf($msgarr['28002'][SCREEN_MSG]);
            $log_msg = $msgarr['28002'][LOG_MSG];
            result_log(OPERATION_UP . ":NG:" . $log_msg, LOG_ERR);
            /* サブネットを再表示 */
            re_display($left, $right);
            $tag["<<OLDNAME>>"] = $oldname;
            break;
        case 2:
            /* 入力なしエラー */
            $err_msg = sprintf($msgarr['28001'][SCREEN_MSG]);
            $log_msg = $msgarr['28001'][LOG_MSG];
            result_log(OPERATION_UP . ":NG:" . $log_msg, LOG_ERR);
            /* サブネットを再表示 */
            re_display($left, $right);
            $tag["<<OLDNAME>>"] = $oldname;
            break;
        case 0:
            /* 正常 */
            /* shared-network名が変更されている場合 */
            if ($oldname != $input) {
                /* 同名チェック */
                $ret = check_same_name($_SESSION[STR_IP], $input);
                if ($ret == TRUE) {
                    /* 重複が見つかった場合 */
                    $err_msg = sprintf($msgarr['28003'][SCREEN_MSG]);
                    $log_msg = $msgarr['28003'][LOG_MSG];
                    result_log(OPERATION_UP . ":NG:" . $log_msg, LOG_ERR);
                    /* サブネットを再表示 */
                    re_display($left, $right);
                    $tag["<<OLDNAME>>"] = $oldname;
                } else {
                    /* 同名がなければ */
                    /* $_SESSIONにshared-network名が存在するかチェック */
                    $ret = check_same_name($_SESSION[STR_IP], $oldname);
                    if ($ret == FALSE) {
                        /* 変更前のshared-network名がない */
                        $err_msg = sprintf($msgarr['28006'][SCREEN_MSG], $oldname);
                        $log_msg = sprintf($msgarr['28006'][LOG_MSG], $oldname);
                        result_log(OPERATION_UP . ":NG:" . $log_msg, LOG_ERR);
                        /* サブネットを再表示 */
                        re_display($left, $right);
                        $tag["<<OLDNAME>>"] = $oldname;
                    } else {
                        /* 元SESSIONに同名があれば */
                        /* shared-network名を変更 */
                        $_SESSION[STR_IP]["$input"] = $_SESSION[STR_IP]["$oldname"];
                        unset($_SESSION[STR_IP]["$oldname"]);
                        /* 成功メッセージをセットして一覧画面へ遷移 */
                        $err_msg = sprintf($msgarr['28000'][SCREEN_MSG], "$oldname->$input");
                        $log_msg = sprintf($msgarr['28000'][LOG_MSG], "$oldname->$input");
                        $ok = TRUE;
                    }
                }
            } else {
                /* Shared-network名が変更されていない場合 */
                /* $_SESSIONにshared-network名が存在するかチェック */
                $ret = check_same_name($_SESSION[STR_IP], $oldname);
                if ($ret == FALSE) {
                    /* ループを抜けて元SESSIONに同名がなければ */
                    /* 変更前のshared-network名がない */
                    $err_msg = sprintf($msgarr['28006'][SCREEN_MSG], $oldname);
                    $log_msg = sprintf($msgarr['28006'][LOG_MSG], $oldname);
                    result_log(OPERATION_UP . ":NG:" . $log_msg, LOG_ERR);
                    /* サブネットを再表示 */
                    re_display($left, $right);
                    $tag["<<OLDNAME>>"] = $oldname;
                } else {
                    /* 成功メッセージをセットして一覧画面へ遷移 */
                    $err_msg = sprintf($msgarr['28000'][SCREEN_MSG], "$oldname");
                    $log_msg = sprintf($msgarr['28000'][LOG_MSG], "$oldname");
                    $input = $oldname;
                    $ok = TRUE;
                }
            }
            if ($ok == TRUE) {
                /* サブネットを取得しセット */
                if (is_array($left)) {
                    Move_Subnets($left, "_other", $input);
                }
                if (is_array($right)) {
                    Move_Subnets($right, $input, "_other");
                }
                result_log(OPERATION_UP . ":OK:" . $log_msg, LOG_ERR);
                dgp_location("index.php", $err_msg);
                exit(0);
            }
    }
/* 削除ボタンが押されたら */
} else if (isset($_POST["delete"])) {
    $input   = isset($_POST["networkname"]) ? $_POST["networkname"] : "";
    $left    = isset($_POST["selectleft"])  ? $_POST["selectleft"]  : "";
    $right   = isset($_POST["selectright"]) ? $_POST["selectright"] : "";
    $oldname = isset($_POST["oldname"])     ? $_POST["oldname"]     : "";
    /* $_SESSIONにshared-network名が存在するかチェック */
    $ret = check_same_name($_SESSION[STR_IP], $oldname);
    if ($ret == FALSE) {
        /* SESSIONに同名がなければ */
        /* Shared-network名がないので再表示 */
        $err_msg = sprintf($msgarr['28006'][SCREEN_MSG], $oldname);
        $log_msg = sprintf($msgarr['28006'][LOG_MSG], $oldname);
        result_log(OPERATION_DEL . ":NG:" . $log_msg, LOG_ERR);
        /* サブネットを再表示 */
        re_display($left, $right);
        $tag["<<OLDNAME>>"] = $oldname;
    } else {
        /* ループを抜けてSESSIONに同名あれば */
        /* サブネットが所属していないか確認 */
        foreach ($_SESSION[STR_IP]["$oldname"] as $key => $value) {
            /* 所属していた場合 */
            if (isset($key)) {
                $err_msg = sprintf($msgarr['28007'][SCREEN_MSG]);
                $log_msg = sprintf($msgarr['28007'][LOG_MSG]);
                result_log(OPERATION_DEL . ":NG:" . $log_msg, LOG_ERR);
                /* サブネットを再表示 */
                re_display($left, $right);
                $tag["<<OLDNAME>>"] = $oldname;
                $in_subnet = TRUE;
                break;
            }
        }
        /* ループを抜けてサブネットが所属していなければ削除 */
        if ($in_subnet != TRUE) {
            unset($_SESSION[STR_IP]["$oldname"]);
            /* 成功メッセージをセットして一覧画面へ遷移 */
            $err_msg = sprintf($msgarr['28004'][SCREEN_MSG], $oldname);
            $log_msg = sprintf($msgarr['28004'][LOG_MSG], $oldname);
            result_log(OPERATION_DEL . ":OK:" . $log_msg, LOG_ERR);
            dgp_location("index.php", $err_msg);
            exit(0);
        }
    }
/* 戻るボタンが押されたら一覧画面へ遷移 */
} else if (isset($_POST["back"])) {
    dgp_location("index.php");
    exit(0);
} else {
    /* 初期表示 */
    /* 一覧画面で押されたShared-network名を表示 */
    $input = $_POST["sn"];
    /* hiddenのOLDNAMEタグに一覧から来たShared-network名をいれておく */
    $tag["<<OLDNAME>>"] = $input;
    /* $_SESSIONから一覧画面で押されたShared-networkのサブネットを取得 */
    if (is_array($_SESSION[STR_IP]["$input"])) {
        foreach ($_SESSION[STR_IP]["$input"] as $key => $value) {
            /* 所属のサブネットに表示させる */
            $tag["<<SUBNET>>"] .= "<option value=\"$key\">$key</option>\n";
        }
    }
    /* $_SESSIONから_otherのサブネットを取得 */
    if (isset($_SESSION[STR_IP]["_other"]) &&
        $_SESSION[STR_IP]["_other"] != "") {
        foreach ($_SESSION[STR_IP]["_other"] as $other_key => $value) {
            /* 未所属のサブネットに表示させる */
            $tag["<<OTHERSUBNET>>"] .= "<option value=\"$other_key\">$other_key</option>\n";
        }
    }
}

/***********************************************************
 * 表示処理
 **********************************************************/

$tag["<<SN>>"] = escape_html($input);
/* タグ 設定 */
set_tag_common($tag);

/* ページの出力 */
$ret = display($template, $tag, array(), "", "");
if ($ret === FALSE) {
    result_log($log_msg, LOG_ERR);
    syserr_display();
    exit(1);
}
?>
