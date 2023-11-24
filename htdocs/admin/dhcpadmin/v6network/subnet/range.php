<?php

/*
 * postLDAPadmin
 *
 * Copyright (C) 2006,2014 DesigNET, INC.
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
 * IPv6 範囲設定画面
 *
 * $RCSfile: range.php $
 * $Revision: 1.0 $
 * $Date: 2014/07/16 $
 **********************************************************/

include_once("../../initial");
include_once("lib/dglibcommon");
include_once("lib/dglibpage");
include_once("lib/dglibsess");
include_once("lib/dglibdhcpadmin");
include_once("lib/dglibpostldapadmin");


/********************************************************
 * 各ページ毎の設定
 ********************************************************/

define("TMPLFILE_RANGE", "admin_v6network_subnet_range.tmpl");
define("OPERATION_DEL", "Deleting range_v6");
define("OPERATION_UP", "Updating range_v6");

/***********************************************************
 * 初期処理
 **********************************************************/

$template = TMPLFILE_RANGE;

/* タグ初期化 */
$tag["<<TITLE>>"]      = "";
$tag["<<JAVASCRIPT>>"] = "";
$tag["<<SK>>"]         = "";
$tag["<<TOPIC>>"]      = "";
$tag["<<MESSAGE>>"]    = "";
$tag["<<TAB>>"]        = "";
$tag["<<MENU>>"]       = "";

$tag["<<SUBNET>>"]     = "";
$tag["<<RANGELIST>>"]    = "";

/* 変数の初期化 */
$subnet_data = array();
$range_data = array();
$range_update = "";
$hiden_data = array();
$count_subnet = 0;
$sn = "";
$subnet = "";

/* 設定ファイルやタブ管理ファイル読込、セッションのチェック */
$ret = init();
if ($ret === FALSE) {
    syserr_display();
    exit(1);
}

/* dhcp.confを分析を行う関数を呼び出す */
$ret = analyze_dhcpd_conf($web_conf["dhcpadmin"]["dhcpd6confpath"], "IPv6");
if ($ret === FALSE) {
    $err_msg = $msgarr['27007'][SCREEN_MSG];
    $log_msg = $msgarr['27007'][LOG_MSG];
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
    $log_msg = sprintf($msgarr['27005'][LOG_MSG], $_SERVER["REMOTE_ADDR"]);
    result_log($log_msg, LOG_ERR);
    syserr_display();
    exit(1);
}

/* 現在登録されている全てのサブネットを取得する */
$subnet_data = get_all_subnets();

/* サブネットが存在しない場合 */
$count_subnet = count($subnet_data);
if ($count_subnet == 0) {
    $err_msg = sprintf($msgarr['29006'][SCREEN_MSG], $_POST["subnetlist"]);
    $log_msg = sprintf($msgarr['29006'][LOG_MSG], $_POST["subnetlist"]);
    result_log(OPERATION_UP . ":NG:" . $log_msg);
    /* 一覧画面に移動する */
    dgp_location("index.php", $err_msg);
    exit (1);
}

/***********************************************************
 * main処理
 **********************************************************/

/* サブネットを取得する */
if (isset($_POST["subnet_netmask"])) {
    /* 選択したサブネットを取得する */
    $subnet = $_POST["subnet_netmask"];
} else {
    /* サブネット一覧画面に移動する */
    dgp_location("index.php");
    exit (1);
}


/* 範囲設定編集画面からメッセージかつログを渡す */
if (isset($_POST["msg_add_range"])) {
    $err_msg = $_POST["msg_add_range"];
}

/* 範囲を取得する関数を呼び出す */
$ret = get_list_range($range_data, $sn, $subnet);
/* 返り値を判断する */
if ($ret === FUNC_FALSE) {
    $err_msg = sprintf($msgarr['29006'][SCREEN_MSG], $_POST["subnetlist"]);
    $log_msg = sprintf($msgarr['29006'][LOG_MSG], $_POST["subnetlist"]);
    result_log(OPERATION_UP . ":NG:" . $log_msg);
    /* 一覧画面に移動する */
    dgp_location("index.php", $err_msg);
    exit (1);
}

/* 戻るボタンを押されたら */
if (isset($_POST["back"])) {
    /* サブネット管理画面に移動する */
    dgp_location("index.php");
    exit (1); 
/* 更新ボタンを押された場合の処理 */
} else if (isset($_POST["modify"])) {
    /* 更新対象が選択されたかどうかを判断する */
    if (!isset($_POST["rangelist"])) {
        /* メッセージを設定する */
        $err_msg = $msgarr['30001'][SCREEN_MSG];
        /* ログにメッセージを設定する */
        $log_msg = $msgarr['30001'][LOG_MSG];
        result_log(OPERATION_UP . ":NG:" . $log_msg);
    /* 更新対象が選択されていた場合 */
    } else {
        $ret =  check_range_in_session($range_data, $_POST["rangelist"]);
        if ($ret === FUNC_FALSE) {
            $err_msg = sprintf($msgarr['30002'][SCREEN_MSG], $_POST["rangelist"]);
            $log_msg = sprintf($msgarr['30002'][LOG_MSG], $_POST["rangelist"]);
            result_log(OPERATION_UP . ":NG:" . $log_msg);
        } else {
            /* 渡す値を設定する */
            $hidden_data["type"] = "2"; 
            $hidden_data["sn"] = $sn; 
            $hidden_data["subnet_netmask"] = $subnet;
            $hidden_data["range"] = $_POST["rangelist"];
            /* 編集画面に移動する */
            dgp_location_hidden("range_mod.php", $hidden_data);
            exit (0);
        }
    }
/* 追加ボタンを押されたら */
} else if (isset($_POST["range_add"])) {
    /* 渡す値を設定する */
    $hidden_data["sn"] = $sn; 
    $hidden_data["type"] = "1"; 
    $hidden_data["subnet_netmask"] = $subnet;
    /* 編集画面に移動する */
    dgp_location_hidden("range_mod.php", $hidden_data);
    exit (1);
/* 削除ボタンを押されたら */
} else if (isset($_POST["delete"])) {
    /* 削除対象を選択かどうかを判断する */
    if (!isset($_POST["rangelist"])) {
        /* メッセージを設定する */
        $err_msg = $msgarr['30001'][SCREEN_MSG];
        /* ログにメッセージを設定する */
        $log_msg = $msgarr['30001'][LOG_MSG];
        result_log(OPERATION_DEL . ":NG:" . $log_msg);
    } else {
        $ret = TRUE;
        if (isset($_SESSION[STR_IP][$sn][$subnet]["host"])) {
           $ret = check_delete_range($_SESSION[STR_IP][$sn][$subnet]["host"], $_POST["rangelist"]);
        }
        if ($ret === FALSE) {
            /*メッセージを設定する*/
            $err_msg = sprintf($msgarr['33021'][SCREEN_MSG], $subnet);
            /*ログにメッセージを設定する*/
            $log_msg = sprintf($msgarr['33021'][LOG_MSG], $subnet);
            result_log(OPERATION_DEL . ":NG:" . $log_msg);
        } else {
            $ret =  check_range_in_session($range_data, $_POST["rangelist"]);
            if ($ret === FUNC_FALSE) {
                $err_msg = sprintf($msgarr['30002'][SCREEN_MSG],
                               $_POST["rangelist"]);
                $log_msg = sprintf($msgarr['30002'][LOG_MSG], $_POST["rangelist"]);
                result_log(OPERATION_DEL . ":NG:" . $log_msg);
            } else {
                /* 配列をunsetする */
                $key = array_search($_POST["rangelist"] ,$range_data);
                if ($key !== FALSE) {
                    unset($range_data[$key]);
                    /* セッションの範囲を変更する関数を呼び出す */
                    update_range_session($range_data, $sn, $subnet);
                    /* メッセージを設定する */
                    $err_msg = sprintf($msgarr['30000'][SCREEN_MSG],
                                   $_POST["rangelist"]);
                    /* ログにメッセージを設定する */
                    $log_msg = sprintf($msgarr['30000'][LOG_MSG],
                                   $_POST["rangelist"]);
                    result_log(OPERATION_DEL . ":OK:" . $log_msg);
                }
            }
        }
    }
}

/***********************************************************
 * 表示処理
 **********************************************************/

/* 全てのサブネットを表示する */
$tag["<<RANGELIST>>"] = set_range_list($range_data);
$tag["<<SUBNET>>"] =  escape_html($subnet);

/* タグをセット */
set_tag_common($tag);

/* ページの出力 */
$ret = display($template, $tag, array(), "", "");
if ($ret === FALSE) {
    result_log($log_msg, LOG_ERR);
    syserr_display();
    exit(1);
}
?>
