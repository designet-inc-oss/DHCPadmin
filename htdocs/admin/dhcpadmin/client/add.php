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
 * クライアント編集画面
 *
 * $RCSfile: index.php,v $
 * $Revision: 1.9 $
 * $Date: 2012/09/19 00:02:52 $
 **********************************************************/

include_once("../initial");
include_once("lib/dglibpostldapadmin");
include_once("lib/dglibcommon");
include_once("lib/dglibpage");
include_once("lib/dglibsess");
include_once("lib/dglibdhcpadmin");

/********************************************************
 * 各ページ毎の設定
 ********************************************************/

define("TMPLFILE_ADD",   "admin_client_add.tmpl");
define("ADD_SEARCH",    "Add client");

/***********************************************************
 * 初期処理
 **********************************************************/

$template = TMPLFILE_ADD;

/* タグ初期化 */
$tag["<<TITLE>>"]        = "";
$tag["<<JAVASCRIPT>>"]   = "";
$tag["<<SK>>"]           = "";
$tag["<<SN>>"]           = "";
$tag["<<TOPIC>>"]        = "";
$tag["<<MESSAGE>>"]      = "";
$tag["<<TAB>>"]          = "";
$tag["<<MENU>>"]         = "";
$tag["<<SUBNET>>"]       = "";
$tag["<<INSUBNET>>"]     = "";
$tag["<<OLDSN>>"]      = "";
$tag["<<OLDHOST>>"]      = "";
$tag["<<ESCAPEHOST>>"]   = "";
$tag["<<HOST>>"]         = "";
$tag["<<OLDMACADDR>>"]   = "";
$tag["<<MAC>>"]          = "";
$tag["<<OLDIPADDR>>"]    = "";
$tag["<<OLDIPSELECT>>"]    = "";
$tag["<<IP>>"]           = "";
$tag["<<LEASE>>"]        = ""; 
$tag["<<LEASE_ALLOW>>"]  = "";
$tag["<<LEASE_DENY>>"]   = "";

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

/* 登録ボタンが押されたら */
if (isset($_POST["add"])) {
    $in_sub = escape_html($_POST["subnet"]);
    /* 入力チェック */
    $ret = check_add_in($_POST);
    switch ($ret) {
    case 1:
        /* ホスト名の入力がない */
        $err_msg = sprintf($msgarr['33002'][SCREEN_MSG]);
        $log_msg = $msgarr['33002'][LOG_MSG];
        result_log(ADD_SEARCH . ":NG:" . $log_msg, LOG_ERR);
        break;
    case 2:
    /* MACアドレスの入力がない */
        $err_msg = sprintf($msgarr['33004'][SCREEN_MSG]);
        $log_msg = $msgarr['33004'][LOG_MSG];
        result_log(ADD_SEARCH . ":NG:" . $log_msg, LOG_ERR);
        break;
    case 3:
    /* IP貸出設定が選択されているか */
        $err_msg = sprintf($msgarr['33009'][SCREEN_MSG]);
        $log_msg = $msgarr['33009'][LOG_MSG];
        result_log(ADD_SEARCH . ":NG:" . $log_msg, LOG_ERR);
        break;
    /* ホスト名の入力チェックエラー */
    case 4:
        $err_msg = sprintf($msgarr['33003'][SCREEN_MSG]);
        $log_msg = $msgarr['33003'][LOG_MSG];
        result_log(ADD_SEARCH . ":NG:" . $log_msg, LOG_ERR);
        break;
    /* MACアドレスの入力チェックエラー */
    case 5:
        $err_msg = sprintf($msgarr['33005'][SCREEN_MSG]);
        $log_msg = $msgarr['33005'][LOG_MSG];
        result_log(ADD_SEARCH . ":NG:" . $log_msg, LOG_ERR);
        break;
    /* IPアドレスの入力チェックエラー */
    case 6:
        $err_msg = sprintf($msgarr['33006'][SCREEN_MSG]);
        $log_msg = $msgarr['33006'][LOG_MSG];
        result_log(ADD_SEARCH . ":NG:" . $log_msg, LOG_ERR);
        break;
    /* 正常の場合 */
    case 0:
        /* IPv6アドレスがサブネットの範囲内かチェックする */
        if (isset($_POST["ipaddr"]) && $_POST["ipaddr"] != "") {
            $range_ret = in_range_ipv4($in_sub, $_POST["ipaddr"]);
            if ($range_ret == FALSE) {
                $err_msg = sprintf($msgarr['33020'][SCREEN_MSG]);
                $log_msg = $msgarr['33020'][LOG_MSG];
                result_log(ADD_SEARCH . ":NG:" . $log_msg, LOG_ERR);
                break;
            }
        }
        /* サブネットの存在をチェックしshared-networkを返す */
        $sn = search_sn($in_sub);
        if ($sn == "") {
            $err_msg = sprintf($msgarr['33008'][SCREEN_MSG]);
            $log_msg = $msgarr['33008'][LOG_MSG];
            result_log(ADD_SEARCH . ":NG:" . $log_msg, LOG_ERR);
            break;
        }
        /* 重複チェック */
        /*新規登録の場合 */
        if ($_POST["oldhost"] == "") {
            /* ホストの中身があれば重複チェック */
            if (isset($_SESSION[STR_IP]["$sn"]["$in_sub"]["host"])) {
                $hostline = $_SESSION[STR_IP]["$sn"]["$in_sub"]["host"];
                $ret = check_add_duplication($hostline, $_POST);
            } else {
            /* ホストの中身がなければ0を代入 */
                $hostline = "";
                $ret = 0;
            }
            switch ($ret) {
            /* ホスト名重複 */
            case 1:
                $err_msg = sprintf($msgarr['33010'][SCREEN_MSG]);
                $log_msg = $msgarr['33010'][LOG_MSG];
                result_log(ADD_SEARCH . ":NG:" . $log_msg, LOG_ERR);
                break;
            /* MACアドレス重複 */
            case 2:
                $err_msg = sprintf($msgarr['33011'][SCREEN_MSG]);
                $log_msg = $msgarr['33011'][LOG_MSG];
                result_log(ADD_SEARCH . ":NG:" . $log_msg, LOG_ERR);
                break;
            /* IPアドレス重複 */
            case 3:
                $err_msg = sprintf($msgarr['33012'][SCREEN_MSG]);
                $log_msg = $msgarr['33012'][LOG_MSG];
                result_log(ADD_SEARCH . ":NG:" . $log_msg, LOG_ERR);
                break;
            /* 正常の場合 */
            case 0:
                /* 新規登録 */
                $newhostline = new_add_host($_POST, $hostline);
                $_SESSION[STR_IP]["$sn"]["$in_sub"]["host"] = $newhostline;
                $err_msg = sprintf($msgarr['33007'][SCREEN_MSG]);
                $log_msg = $msgarr['33007'][LOG_MSG];
                result_log(ADD_SEARCH . ":OK:" . $log_msg, LOG_ERR);
                dgp_location("index.php", $err_msg);
                exit(0);
            }
        } else { 
            /* 編集の場合 */
            $old_sn = $_POST["oldsn"];
            $hostline = $_SESSION[STR_IP]["$old_sn"]["$in_sub"]["host"];
            $ret = check_mod_duplication($hostline, $_POST);
            switch ($ret) {
            /* ホスト名重複 */
            case 1:
                $err_msg = sprintf($msgarr['33010'][SCREEN_MSG]);
                $log_msg = $msgarr['33010'][LOG_MSG];
                result_log(ADD_SEARCH . ":NG:" . $log_msg, LOG_ERR);
                break;
            /* MACアドレス重複 */
            case 2:
                $err_msg = sprintf($msgarr['33011'][SCREEN_MSG]);
                $log_msg = $msgarr['33011'][LOG_MSG];
                result_log(ADD_SEARCH . ":NG:" . $log_msg, LOG_ERR);
                break;
            /* IPアドレス重複 */
            case 3:
                $err_msg = sprintf($msgarr['33012'][SCREEN_MSG]);
                $log_msg = $msgarr['33012'][LOG_MSG];
                result_log(ADD_SEARCH . ":NG:" . $log_msg, LOG_ERR);
                break;
            /* 正常の場合 */
            case 0:
                /* 編集 */
                $ret = mod_client($hostline, $_POST);
                $_SESSION[STR_IP]["$old_sn"]["$in_sub"]["host"] = $ret;
                $err_msg = sprintf($msgarr['33007'][SCREEN_MSG]);
                $log_msg = $msgarr['33007'][LOG_MSG];
                result_log(ADD_SEARCH . ":OK:" . $log_msg, LOG_ERR);
                dgp_location("index.php", $err_msg);
                exit(0);
            }
        }
    }
/* 戻るボタンが押されたら */
} else if (isset($_POST["back"])) {
    /* 画面遷移 */
    dgp_location("index.php");
    exit(0);
}

/***********************************************************
 * 表示処理
 **********************************************************/
$host = "";
$mac = "";
$ip = "";
$oldsn = "";
$oldhost = "";
$oldmac = "";
$oldip = "";
$oldipselect = "";

/* 渡ってきたサブネットを表示 */
if (!isset($_POST["modsubnet"])) {
    $subnet = $_POST["subnet"];
    $host = escape_html($_POST["host"]);
    $mac = escape_html($_POST["macaddr"]);
    $ip = escape_html($_POST["ipaddr"]);
    if (isset($_POST["ipselect"])) {
        $select = $_POST["ipselect"];
    } else {
        $select = "";
    }
    /* 2回目以降のエラーのためhiddenタグに値を入れておく */
    if (isset($_POST["oldhost"]) && $_POST["oldhost"] != ""){
        $oldsn = $_POST["oldsn"];
        $oldhost = escape_html($_POST["oldhost"]);
        $oldmac = ($_POST["oldmacaddr"]);
        $oldip = ($_POST["oldipaddr"]);
        $select = ($_POST["oldipselect"]);
    }
} else {
    /* 初期表示 */
    $subnet = $_POST["modsubnet"];
    if (isset($_POST["modhost"])) {
        $oldsn = $_POST["mode"];
        $host = base64_decode($_POST["modhost"]);
        $host = escape_html($host);
        $oldhost = $host;
        $mac = escape_html($_POST["modmacaddr"]);
        $oldmac = escape_html($_POST["modmacaddr"]);
        $ip = escape_html($_POST["modipaddr"]);
        $oldip = escape_html($_POST["modipaddr"]);
        $select = $_POST["modipselect"];
    } else {
        $select = "";
    }
}
$tag["<<INSUBNET>>"] = $subnet;
$tag["<<SUBNET>>"] = "<option value=\"$subnet\">$subnet</option>";
$tag["<<HOST>>"] = $host;
$tag["<<MAC>>"] = $mac;
$tag["<<IP>>"] = $ip;
$tag["<<OLDSN>>"] = $oldsn;
$tag["<<OLDHOST>>"] = $oldhost;
$tag["<<OLDMACADDR>>"] = $oldmac;
$tag["<<OLDIPADDR>>"] = $oldip;
$tag["<<OLDIPSELECT>>"] = $select;

if (empty($select)) {
    $tag["<<LEASE_ALLOW>>"] = "";
    $tag["<<LEASE_DENY>>"] = "";
} else if ($select == "allow") {
    $tag["<<LEASE_ALLOW>>"] = "checked";
    $tag["<<LEASE_DENY>>"] = "";
} else if ($select == "deny") {
    $tag["<<LEASE_ALLOW>>"] = "";
    $tag["<<LEASE_DENY>>"] = "checked";
}

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
