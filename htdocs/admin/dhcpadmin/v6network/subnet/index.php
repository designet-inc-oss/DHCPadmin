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
 * Dhcpv6-Shared-network管理画面
 *
 * $RCSfile: index.php,v $
 * $Revision: 1.9 $
 * $Date: 2012/09/19 00:02:52 $
 **********************************************************/

include_once("../../initial");
include_once("lib/dglibldap");
include_once("lib/dglibcommon");
include_once("lib/dglibpage");
include_once("lib/dglibsess");
include_once("lib/dglibdhcpadmin");
include_once("lib/dglibpostldapadmin");

/********************************************************
 * 各ページ毎の設定
 ********************************************************/

define("TMPLFILE_LIST", "admin_v6network_subnet_list.tmpl");

define("OPERATION_ADD", "Adding subnet");
define("OPERATION_UP", "Updating subnet");
define("OPERATION_DEL", "Deleting subnet");
define("OPERATION_RANGE", "Setting range subnet");

/***********************************************************
 * 初期処理
 **********************************************************/

$template = TMPLFILE_LIST;

/* タグ初期化 */
$tag["<<TITLE>>"]      = "";
$tag["<<JAVASCRIPT>>"] = "";
$tag["<<SK>>"]         = "";
$tag["<<TOPIC>>"]      = "";
$tag["<<MESSAGE>>"]    = "";
$tag["<<TAB>>"]        = "";
$tag["<<MENU>>"]       = "";
$tag["<<SUBNET>>"]     = "";
$tag["<<NETMASK>>"]    = "";

/* 設定ファイルやタブ管理ファイル読込、セッションのチェック */
$ret = init();
if ($ret === FALSE) {
    syserr_display();
    exit(1);
}
$ret = analyze_dhcpd_conf($web_conf["dhcpadmin"]["dhcpd6confpath"], "IPv6");
if ($ret === FALSE) {
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
    $log_msg = sprintf($msgarr['27005'][LOG_MSG], $_SERVER["REMOTE_ADDR"]);
    result_log($log_msg, LOG_ERR);
    syserr_display();
    exit(1);
}

/*現在登録されている全てのサブネットを習得する*/
$subnet_data = get_all_subnets();

/***********************************************************
 * main処理
 **********************************************************/

/* 登録ボタンを押した場合 */
if (isset($_POST["add"])) {
    /* 入力データのチェック */
    $ret = check_add_subnet_data_v6($_POST);
    if ($ret === FUNC_FALSE) {
        result_log(OPERATION_ADD . ":NG:" . $log_msg);
    } else {
        /*サブネットが存在するかどうかをチェックする*/
        $ret = check_subnet_in_session($subnet_data, $_POST["subnet"]. "/". $_POST["netmask"]);
        if ($ret === FUNC_FALSE) {
            /*渡す値を設定する*/
            $hidden_data["subnet"] = $_POST["subnet"];
            $hidden_data["netmask"] = $_POST["netmask"];
            /*編集画面に移動する*/
            dgp_location_hidden("mod.php", $hidden_data);
            exit (0);
        } else {
            $err_msg = sprintf($msgarr['29018'][SCREEN_MSG], $_POST["subnet"]. "/". $_POST["netmask"]);
            $log_msg = sprintf($msgarr['29018'][LOG_MSG], $_POST["subnet"]. "/". $_POST["netmask"]);
            result_log(OPERATION_ADD . ":NG:" . $log_msg);
        }
    }
}

/* 更新ボタンを押した場合 */
if (isset($_POST["modify"])) {
    /*サブネットを選択するかどうかをチェックする*/
    if (!isset($_POST["subnetlist"])) {
        $err_msg = $msgarr['29005'][SCREEN_MSG];
        $log_msg = $msgarr['29005'][LOG_MSG];
        result_log(OPERATION_UP . ":NG:" . $log_msg);
    } else {
        /*セッションにサブネットが存在するかどうかチェックする関数を呼び出す*/
        $ret = check_subnet_in_session($subnet_data, $_POST["subnetlist"]);
        if ($ret === FUNC_TRUE) {
            /*渡す値を設定する*/
            $hidden_data["subnet_netmask"] = $_POST["subnetlist"];
            /*編集画面に移動する*/
            dgp_location_hidden("mod.php", $hidden_data);
            exit (0);
        } else {
            $err_msg = sprintf($msgarr['29006'][SCREEN_MSG], $_POST["subnetlist"]);
            $log_msg = sprintf($msgarr['29006'][LOG_MSG], $_POST["subnetlist"]);
            result_log(OPERATION_UP . ":NG:" . $log_msg);
        }
    }
}

/* 削除ボタンを押した場合 */
if (isset($_POST["delete"])) {
    /*サブネットを選択するかどうかをチェックする*/
    if (!isset($_POST["subnetlist"])) {
        $err_msg = $msgarr['29005'][SCREEN_MSG];
        $log_msg = $msgarr['29005'][LOG_MSG];
        result_log(OPERATION_DEL . ":NG:" . $log_msg);
    } else {
    
        $sn = judge_sn($_POST["subnetlist"]);
        if ($sn == "") {
            $sn = "_other";
        }

        if (isset($_SESSION[STR_IP][$sn][$_POST["subnetlist"]]["host"])) {
            /*メッセージを設定する*/
            $err_msg = sprintf($msgarr['29020'][SCREEN_MSG], $_POST["subnetlist"]);
            /*ログにメッセージを設定する*/
            $log_msg = sprintf($msgarr['29020'][LOG_MSG], $_POST["subnetlist"]);

            result_log(OPERATION_DEL . ":NG:" . $log_msg);
        } else {
            /*セッションにサブネットが存在するかどうかチェックする関数を呼び出す*/
            $ret = check_subnet_in_session($subnet_data, $_POST["subnetlist"]);
            if ($ret === FUNC_TRUE) {
                /*shared-networkを見つける*/
                $sn_ret = judge_sn($_POST["subnetlist"]);
                /*サブネットをunsetする*/
                unset($_SESSION[STR_IP]["$sn_ret"][$_POST["subnetlist"]]);
                /*配列をunsetする*/
                $key = array_search($_POST["subnetlist"] ,$subnet_data);
                if ($key !== FALSE) {
                    unset($subnet_data[$key]);
                }
                $err_msg = sprintf($msgarr['29000'][SCREEN_MSG], $_POST["subnetlist"]);
                $log_msg = sprintf($msgarr['29000'][LOG_MSG], $_POST["subnetlist"]);
                result_log(OPERATION_DEL . ":OK:" . $log_msg);
            } else {
                $err_msg = sprintf($msgarr['29006'][SCREEN_MSG], $_POST["subnetlist"]);
                 $log_msg = sprintf($msgarr['29006'][LOG_MSG], $_POST["subnetlist"]);
                result_log(OPERATION_UP . ":NG:" . $log_msg);
            }
        }
    }
}

/* 範囲の設定ボタンを押した場合 */
if (isset($_POST["range"])) {
    /*サブネットを選択するかどうかをチェックする*/
    if (!isset($_POST["subnetlist"])) {
        $err_msg = $msgarr['29005'][SCREEN_MSG];
        $log_msg = $msgarr['29005'][LOG_MSG];
        result_log(OPERATION_RANGE . ":NG:" . $log_msg);
    } else {
        /*セッションにサブネットが存在するかどうかチェックする関数を呼び出す*/
        $ret = check_subnet_in_session($subnet_data, $_POST["subnetlist"]);
        if ($ret === FUNC_TRUE) {
            /*渡す値を設定する*/
            $hidden_data["subnet_netmask"] = $_POST["subnetlist"];
            /*編集画面に移動する*/
            dgp_location_hidden("range.php", $hidden_data);
            exit (0);
        } else {
            $err_msg = sprintf($msgarr['29006'][SCREEN_MSG], $_POST["subnetlist"]);
            $log_msg = sprintf($msgarr['29006'][LOG_MSG], $_POST["subnetlist"]);
            result_log(OPERATION_RANGE . ":NG:" . $log_msg);
        }
    }
}

/***********************************************************
 * 表示処理
 **********************************************************

/*サブネットを保持する*/
if (isset($_POST["subnet"])) {
    $tag["<<SUBNET>>"] = $_POST["subnet"];
}

/*ネットマスクを保持する*/
if (isset($_POST["netmask"])) {
    $tag["<<NETMASK>>"] = $_POST["netmask"];
}

/*全てサブネットを表示する*/
$tag["<<SUBNETLIST>>"] = set_subnet_list($subnet_data);

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
