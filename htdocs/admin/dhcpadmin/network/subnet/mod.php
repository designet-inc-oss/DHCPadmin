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
 * Shared-network管理画面
 *
 * $RCSfile: index.php,v $
 * $Revision: 0.9 $
 * $Date: 2014 $
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

define("TMPLFILE_MOD", "admin_network_subnet_add.tmpl");
define("OPERATION", "Updating subnet");

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
$tag["<<NETMASK>>"]    = "";
$tag["<<ROUTER>>"]     = "";
$tag["<<DOMAINNAME>>"] = "";
$tag["<<LEASETIME>>"]  = "";
$tag["<<MAXLEASETIME>>"] = "";
$tag["<<DNS>>"] = "";
$tag["<<OPTION>>"] = "";

/*変数の初期化*/
$subnet_data = array();
$router = "";
$domainname = "";
$leasetime = "";
$maxlease = "";
$dns = "";
$option = "";

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

/*現在登録されている全てのサブネットを習得する*/
$subnet_data = get_all_subnets();


/***********************************************************
 * main処理
 **********************************************************/
/*更新ボタンを押されたら*/
if (isset($_POST["subnet_netmask"])) {
    /*セションの中にサブネットをチェックする関数を呼び出す*/
    $ret = check_subnet_in_session($subnet_data, $_POST["subnet_netmask"]);
    /*返す値を判断する*/
    if ($ret === FUNC_FALSE) {
        /*メッセージを設定する*/
        $err_msg = sprintf($msgarr['29006'][SCREEN_MSG], $_POST["subnet_netmask"]);
        $log_msg = sprintf($msgarr['29006'][LOG_MSG], $_POST["subnet_netmask"]);
        result_log(OPERATION . ":NG:" . $log_msg);
        /*サブネット管理画面に移動する */
        dgp_location("index.php", $err_msg);
        exit (1);
    }

    $subnet_up = $_POST["subnet_netmask"];
    $sn = judge_sn($subnet_up);
    /*セッションからルータを取得する*/
    if (isset($_SESSION[STR_IP][$sn][$subnet_up]["router"])) {
        /*エスケープを行う*/
        $router = $_SESSION[STR_IP][$sn][$subnet_up]["router"];
    }
    /*セッションからドメイン名を取得する*/
    if (isset($_SESSION[STR_IP][$sn][$subnet_up]["domain"])) {
        /*エスケープを行う*/
        $domainname =  $_SESSION[STR_IP][$sn][$subnet_up]["domain"];
    }
    /*セッションから標準リース時間を取得する*/
    if (isset($_SESSION[STR_IP][$sn][$subnet_up]["leasetime"])) {
        /* 代入 */
        $leasetime =  $_SESSION[STR_IP][$sn][$subnet_up]["leasetime"];
    }
    /*セッションから最大リース時間を取得する*/
    if (isset($_SESSION[STR_IP][$sn][$subnet_up]["maxleasetime"])) {
        /* 代入 */
        $maxlease = $_SESSION[STR_IP][$sn][$subnet_up]["maxleasetime"]; 
    }
    /*セッションからDNSサーバアドレスを取得する*/
    if (isset($_SESSION[STR_IP][$sn][$subnet_up]["dns"])) {
        /* 代入 */
        $dns = $_SESSION[STR_IP][$sn][$subnet_up]["dns"];
    }
    /*セッションからExtraオプションを取得する*/
    if (isset($_SESSION[STR_IP][$sn][$subnet_up]["option"])) {
        /* 代入 */
        $option = $_SESSION[STR_IP][$sn][$subnet_up]["option"];
    }
/*追加ボタンを押されたら*/
} else if (isset($_POST["subnet"])) {
    /*サブネットは値を設定する*/
    $subnet_up = $_POST["subnet"]. "/". $_POST["netmask"];
    /*ドメインに値を設定する */
    $domainname = $web_conf["dhcpadmin"]["defdomain"];
    $leasetime = $web_conf["dhcpadmin"]["defleasetime"];
    $maxlease = $web_conf["dhcpadmin"]["defmaxleasetime"];

/*戻るボタンを押されたら*/
} else if (isset($_POST["back"])) {
    /*サブネット管理画面に移動する*/
    dgp_location("index.php");
/*登録ボタンを押されたら*/
} else if (isset($_POST["addition"])) {
    /*入力をチェックする*/
    $ret = check_update_subnet_data($_POST);
    if ($ret === FUNC_FALSE) {
        /*入力を保持する*/
        $subnet_up = $_POST["subnet_update"];
        $router = $_POST["router"];
        $domainname = $_POST["domainname"];
        $leasetime = $_POST["leasetime"];
        $maxlease = $_POST["maxleasetime"];
        $dns = $_POST["dnsserver"];
        $option = $_POST["exoption"];
        /*ログファイルに書き込む*/
        result_log(OPERATION . ":NG:" . $log_msg);
    } else {
        $subnet_up = $_POST["subnet_update"]; 
        $sn = judge_sn($subnet_up);
        /* S-netを見つけられなかったら_other内に配置 */
        if ($sn == "") {
            $sn = "_other";
        }
        /*セッションルータにを設定する*/
        $_SESSION[STR_IP][$sn][$subnet_up]["router"] = $_POST["router"];
        $_SESSION[STR_IP][$sn][$subnet_up]["domain"] = $_POST["domainname"];
        $_SESSION[STR_IP][$sn][$subnet_up]["leasetime"] = $_POST["leasetime"];
        $_SESSION[STR_IP][$sn][$subnet_up]["maxleasetime"] = $_POST["maxleasetime"];
        $_SESSION[STR_IP][$sn][$subnet_up]["dns"] = $_POST["dnsserver"];
        $_SESSION[STR_IP][$sn][$subnet_up]["option"] = $_POST["exoption"];
        
        /*メッセージを設定する*/
        $err_msg = sprintf($msgarr['29007'][SCREEN_MSG], $_POST["subnet_update"]);
        $log_msg = sprintf($msgarr['29007'][LOG_MSG], $_POST["subnet_update"]);
        result_log(OPERATION . ":OK:" . $log_msg);
        /*サブネット管理画面に移動する */
        dgp_location("index.php", $err_msg);
        exit (0);
    }
}

/***********************************************************
 * 表示処理
 **********************************************************/

$tag["<<SUBNET>>"] =  escape_html($subnet_up);
$tag["<<ROUTER>>"] =  escape_html($router);
$tag["<<DOMAINNAME>>"] =  escape_html($domainname);
$tag["<<LEASETIME>>"] =  escape_html($leasetime);
$tag["<<MAXLEASETIME>>"] = escape_html($maxlease);
$tag["<<DNS>>"] = escape_html($dns);
$tag["<<OPTION>>"] = escape_html($option);

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
