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
include_once("lib/dglibcommon");
include_once("lib/dglibpage");
include_once("lib/dglibsess");
include_once("lib/dglibdhcpadmin");
include_once("lib/dglibpostldapadmin");


/********************************************************
 * 各ページ毎の設定
 ********************************************************/

define("TMPLFILE_MOD", "admin_network_subnet_range_add.tmpl");
define("OPERATION", "Add range");
define("FLG_ADD", "1");
define("FLG_UPDATE", "2");

/***********************************************************
 * 初期処理
 **********************************************************/

$template = TMPLFILE_MOD;

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

/*変数の初期化*/
$subnet_data = array();
$range_data = array();
$range_update = "";
$sn = "";
$subnet = "";
$oldrange = "";

$newrangestart = "";
$newrangeend = "";
$type = "";

/* 設定ファイルやタブ管理ファイル読込、セッションのチェック */
$ret = init();
if ($ret === FALSE) {
    syserr_display();
    exit(1);
}

/*dhcp.confを分析を行う関数を呼び出す*/
$ret = analyze_dhcpd_conf($web_conf["dhcpadmin"]["dhcpdconfpath"], "IPv4");
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
    $log_msg = $msgarr['27005'][LOG_MSG];
    result_log($log_msg, LOG_ERR);
    syserr_display();
    exit(1);
}

/***********************************************************
 * main処理
 **********************************************************/

/*ボタンの種類を押した*/
if (isset($_POST["type"])) {
    if ($_POST["type"] == FLG_UPDATE) {
        /*選択したサブネットを習得する*/
        if (isset($_POST["subnet_netmask"]) && isset($_POST["sn"]) && isset($_POST["range"])) {
            $subnet = $_POST["subnet_netmask"];
            $sn = $_POST["sn"];
            $oldrange = $_POST["range"];
            /*範囲を分析する*/
            $exp_range = explode(",", $oldrange);
            /*範囲に値を設定する*/
            $newrangestart = $exp_range[0];
            $newrangeend = $exp_range[1];
            /*フラグを設定する*/
            $type = FLG_UPDATE;
        }
    } else if ($_POST["type"] == FLG_ADD) {
        if (isset($_POST["sn"]) && isset($_POST["subnet_netmask"])) {
            $sn = $_POST["sn"];
            $subnet = $_POST["subnet_netmask"];
            $type = FLG_ADD;
            /*フラグを設定する*/
        }
    } 
}

/*異常の場合*/ 
if ($type == "") {
    /*範囲設定画面に移動する*/
    dgp_location("index.php");
    exit (1);
}


/*戻るボタンを押されたら*/
if (isset($_POST["back"])) {
    /*範囲設定画面に移動する*/
    $hidden_data["subnet_netmask"] = $subnet;
    /*編集画面に移動する*/
    dgp_location_hidden("range.php", $hidden_data);
    exit (0); 
/*登録ボタンを押されたら*/
} else if (isset($_POST["rangemod"])) {
    /*範囲設定画面で追加ボタンを押す*/
    if ($type == FLG_UPDATE) { 
        /*範囲(前)を入力する場合*/
        if (isset($_POST["startrange"])) {
            $newrangestart = $_POST["startrange"];
         }
        /*範囲(後)を入力する場合*/
        if (isset($_POST["endrange"])) {
            $newrangeend = $_POST["endrange"];
        }

        /*入力をチェックする関数を呼び出す*/
        $ret = check_add_range($newrangestart, $newrangeend);
        if ($ret === FUNC_TRUE) {
            /*重複をチェックする*/
            $ret = check_duplicate_range($sn, $subnet, $newrangestart, $newrangeend, $oldrange);
            if ($ret === FUNC_TRUE) {
                $ret =  mod_range_session($sn, $subnet, $newrangestart, $newrangeend, $oldrange);
                if ($ret === FUNC_TRUE) {
                    $msg = sprintf($msgarr['31000'][SCREEN_MSG], $oldrange,
                                   $newrangestart . "," . $newrangeend);
                    $log = sprintf($msgarr['31000'][LOG_MSG], $oldrange,
                                   $newrangestart . "," . $newrangeend);
                    result_log(OPERATION . ":OK:" . $log);
                    /*範囲設定画面に移動する*/
                    $hidden_data["subnet_netmask"] = $subnet;
                    $hidden_data["msg_add_range"] = $msg;
                    /*編集画面に移動する*/
                    dgp_location_hidden("range.php", $hidden_data);
                    exit (0);
                } else {
                    result_log(OPERATION . ":NG:" . $log_msg);
                    $hidden_data["subnet_netmask"] = $subnet;
                    $hidden_data["msg_add_range"] = $err_msg;
                    /*範囲設定画面に移動する*/
                    dgp_location_hidden("range.php", $hidden_data);
 
                }
            } else {
                result_log(OPERATION . ":NG:" . $log_msg);
            }
        } else {
            result_log(OPERATION . ":NG:" . $log_msg);
        }
    } else if ($type == FLG_ADD){
        /*範囲(前)を入力する場合*/
        if (isset($_POST["startrange"])) {
            $newrangestart = $_POST["startrange"];
        }
        /*範囲(後)を入力する場合*/
        if (isset($_POST["endrange"])) {
            $newrangeend = $_POST["endrange"];
        }

        /*入力をチェックする関数を呼び出す*/
        $ret = check_add_range($newrangestart, $newrangeend);
        if ($ret === FUNC_TRUE) {
            /*重複をチェックする関数を呼び出す*/
            $ret = check_duplicate_range($sn, $subnet, $newrangestart, $newrangeend);
            if ($ret === FUNC_TRUE) {
                $ret =  add_range_session($sn, $subnet, $newrangestart, $newrangeend);
                if ($ret === FUNC_TRUE) {
                    $msg = sprintf($msgarr['31009'][SCREEN_MSG], $newrangestart . "," . $newrangeend);
                    $log = sprintf($msgarr['31009'][LOG_MSG], $newrangestart . "," . $newrangeend);
                    result_log(OPERATION . ":OK:" . $log);
                    /*範囲設定画面に移動する*/
                    $hidden_data["subnet_netmask"] = $subnet;
                    $hidden_data["msg_add_range"] = $msg;
                    /*編集画面に移動する*/
                    dgp_location_hidden("range.php", $hidden_data);
                    exit (0);
                } else {
                    result_log(OPERATION . ":NG:" . $log_msg);
                }
            } else {
                result_log(OPERATION . ":NG:" . $log_msg);
            }
        } else {
            result_log(OPERATION . ":NG:" . $log_msg);
        }
    }   
}
 
/***********************************************************
 * 表示処理
 **********************************************************/

/*全てサブネットを表示する*/
$tag["<<RANGESTART>>"] =  escape_html($newrangestart);
$tag["<<RANGEEND>>"] =  escape_html($newrangeend);

$tag["<<SN>>"] =  escape_html($sn);
$tag["<<SUBNET>>"] =  escape_html($subnet);
$tag["<<TYPE>>"] =  $type;
$tag["<<RANGE>>"] =  $oldrange;

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
