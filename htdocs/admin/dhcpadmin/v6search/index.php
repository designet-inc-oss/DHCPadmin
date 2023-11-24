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
 * Dhcpv6_貸し出し情報検索画面
 *
 * $RCSfile: index.php,v $
 * $Revision: 1.9 $
 * $Date: 2012/09/19 00:02:52 $
 **********************************************************/

include_once("../initial");
include_once("lib/dglibldap");
include_once("lib/dglibcommon");
include_once("lib/dglibpage");
include_once("lib/dglibsess");
include_once("lib/dglibpostldapadmin");
include_once("lib/dglibdhcpadmin");

/********************************************************
 * 各ページ毎の設定
 ********************************************************/

define("TMPLFILE_LIST",   "admin_v6search.tmpl");

/*********************************************************
 * check_input_searchdata_v6()
 *
 * 検索画面入力値チェック
 *
 * [引数]
 *      $data         入力値
 *
 * [返り値]
 *      TRUE          正常
 *      FALSE         異常
 **********************************************************/
function check_input_searchdata_v6($data)
{
    global $msgarr;
    global $err_msg;
    global $log_msg;

    // 入力値(日付)チェック
    $ret = check_input_searchdata($data);
    if ($ret === FALSE) {
        return FALSE;
    }

    // 検索値チェック
    if (strlen($data["searchword"]) > 128) {
        $err_msg = $msgarr['35006'][SCREEN_MSG];
        $log_msg = $msgarr['35006'][LOG_MSG];
        return FALSE;
    }

    return TRUE;
}

/*********************************************************
 * get_lease_data()
 *
 * リースデータの読み込みと整形
 *
 * [引数]
 *      $postdata     入力値
 *      $looptag      HTML整形用データ(参照渡し)
 *
 * [返り値]
 *      TRUE          正常
 *      FALSE         異常
 **********************************************************/
function get_lease_data($postdata, &$looptag)
{
    global $web_conf;
    global $err_msg;
    global $log_msg;
    global $msgarr;

    $count = 0;
    $data = array();
    $tmpdisphtml = "";
    $leasehead = '^ia-na "';   // リース検索用データヘッダ
    $starthead = 'cltt [0-6]'; // 貸出日検索用ヘッダ
    $endhead   = 'ends [0-6]'; // 貸出期限検索用ヘッダ
    $iphead    = 'iaaddr';    // IPアドレス検索用ヘッダ
    $stats = "";
    $ends = "";
    $duid = "";
    $ip = "";

    // leaseファイルの読み込みチェック
    if (is_readable_file($web_conf['dhcpadmin']['dhcpd6leasespath']) === FALSE) {
        return FALSE;
    }

    // 検索用フォーマット作成
    make_search_format($starthead, $endhead, $iphead, $format_data);

    // leaseファイルの読み込み(下から)
    $lease_data_tmp = file($web_conf['dhcpadmin']['dhcpd6leasespath']);
    $lease_data = array_reverse($lease_data_tmp);

    foreach ($lease_data as $line) {

        // 行の頭が#のコメント行であれば無視
        if (substr($line, 0, 1) == "#") {
            continue;
        }

        // 空行であれば無視
        if (strlen($line) == 0) {
            continue;
        }

        // 表示カウント数に達したら終了
        if ($count == $web_conf['dhcpadmin']['leaseslistnum']) {
            break;
        }

        // 改行を取り除く
        $line = rtrim($line);

        // 先頭の空白・タブを削除
        $line = trim($line);

        // 貸出日の検索
        if (preg_match($format_data["start"], $line, $tmp)) {
            $starts = preg_replace("/$starthead /", "", $tmp[0]);
            $starts = preg_replace("/:[0-9]{1,2}$/", "", $starts);

        // 貸出期限の検索
        } elseif (preg_match($format_data["end"], $line, $tmp)) {
            $ends = preg_replace("/$endhead /", "",$tmp[0]);
            $ends = preg_replace("/:[0-9]{1,2}$/", "",$ends);

        // IPアドレスの検索
        } elseif (preg_match($format_data["ip"], $line, $tmp)) {
            $ip = preg_replace("/$iphead/", "",$tmp[0]);

        // ia-naがあった場合、表示処理を実行
        } elseif (preg_match("/$leasehead/", $line)) {

            // DUIDの検索
            if (preg_match("/\".+\"/", $line, $tmp)) {
                $duid_tmp = ltrim($tmp[0]);
                $duid_tmp = rtrim($duid_tmp);
                          
                // DUIDを16進数に戻し、整形を行う
                make_duid_data($duid_tmp, $duid);
            }

            // 検索条件にマッチするか確認
            $ret = check_lease_data($postdata, $starts, $ends, $duid, $ip);
            if ($ret === FALSE) {
                continue;
            }

            // 値を代入
            $looptag[$count]["<<LEASETIME>>"]   = $starts;
            $looptag[$count]["<<DUID>>"]        = $duid;
            $looptag[$count]["<<IPADDR>>"]      = $ip;
            $looptag[$count]["<<LEASEPERIOD>>"] = $ends;

            // 表示カウント増加
            $count++;
        }
    }

    // 1件も表示項目がない場合
    if ($count == 0) {
        $err_msg = $msgarr['35005'][SCREEN_MSG];
        $log_msg = $msgarr['35005'][LOG_MSG];
    }

    return TRUE;
}

/*********************************************************
 * make_duid_data()
 *
 * 検索用フォーマット作成
 *
 * [引数]
 *      $duid_tmp      DUIDの情報が入った文字列
 *      $duid          DUID(参照渡し)
 *
 * [返り値]
 *      TRUE          正常
 *      FALSE         異常
 **********************************************************/
function make_duid_data($duid_tmp, &$duid = "")
{

    // カラムを分ける
    $duid_list = explode('\\', $duid_tmp);
    if ($duid_list === FALSE) {
        return FALSE;
    }

    // カラムの数を数える
    $list_num = count($duid_list);
    // 14より小さいとき変換できない
    if ($list_num < 14) {
        return FALSE;
    }

    $top_num = $list_num - 14;

    // 末尾の14カラムだけ取り出す
    $duid_list = array_slice($duid_list, $top_num, 14);
    // 全ての要素を16進数へ変換
    $duid_list = array_map("dec_hex", $duid_list);
    // -を付けて整形する
    $duid = implode("-", $duid_list);

    return TRUE;
}

/*********************************************************
 * dec_hex()
 *
 * 8進数から16進数へ変換(array_mapで使用)
 **********************************************************/
function dec_hex($num)
{
    return sprintf("%02X", octdec($num));
}

/*********************************************************
 * make_search_format()
 *
 * 検索用フォーマット作成
 *
 * [引数]
 *      $starthead     貸出日ヘッダ
 *      $endhead       貸出期限ヘッダ
 *      $iphead        IPアドレスヘッダ
 *      $format_data   フォーマットデータ(参照渡し)
 *
 * [返り値]
 *      なし
 **********************************************************/
function make_search_format($starthead, $endhead, $iphead, &$format_data)
{
    $preg_date = '[0-9]{4}\/[0-9]{1,2}\/[0-9]{1,2}';
    $preg_time = '[0-9]{1,2}:[0-9]{1,2}:[0-9]{1,2}';
    $preg_ip   = '[0-9a-fA-F:]+';
    $preg_duid = '".+"';

    // 貸出日フォーマット作成
    $format_data["start"] = "/$starthead $preg_date $preg_time/";

    // 貸出期限フォーマット作成
    $format_data["end"] = "/$endhead $preg_date $preg_time/";

    // IPアドレスのフォーマット作成
    $format_data["ip"] = "/$iphead $preg_ip/";

    return TRUE;
}

/*********************************************************
 * check_lease_data()
 *
 * 検索内容にマッチするかをチェック
 *
 * [引数]
 *      $postdata      入力値
 *      $starts        貸出日
 *      $ends          貸出期限
 *      $duid          DUID
 *      $ip            IPアドレス
 *
 * [返り値]
 *      なし
 **********************************************************/
function check_lease_data($postdata, $starts, $ends, $duid, $ip)
{
    // 貸出日チェック
    $ret = check_lease_time($postdata["ssyear"], $postdata["ssmon"], $postdata["ssday"],
                            $postdata["seyear"], $postdata["semon"], $postdata["seday"],
                            $starts);
    if ($ret === FALSE) {
        return FALSE;
    }

    // 貸出期限チェック
    $ret = check_lease_time($postdata["esyear"], $postdata["esmon"], $postdata["esday"],
                            $postdata["eeyear"], $postdata["eemon"], $postdata["eeday"],
                            $ends);
    if ($ret === FALSE) {
        return FALSE;
    }

    // DUID及びIPアドレスのチェック
    $ret = check_lease_ip_duid($postdata["searchword"], $duid, $ip);
    if ($ret === FALSE) {
        return FALSE;
    }

    return TRUE;
}

/*********************************************************
 * check_lease_ip_duid()
 *
 * DUID or IPアドレスチェック
 *
 * [引数]
 *      $word         検索値
 *      $duid         duid
 *      $ip           IPアドレス
 *
 * [返り値]
 *      TRUE          正常
 *      FALSE         異常
 **********************************************************/
function check_lease_ip_duid($word, $duid, $ip)
{
    // 日付が指定されていないときは無視
    if ($word == "") {
        return TRUE;
    }

    // DUIDチェック
    if (preg_match("/". preg_quote($word) . "/", $duid)) {
        return TRUE;
    }

    // IPアドレスチェック
    if (preg_match("/" . preg_quote($word) . "/", $ip)) {
        return TRUE;
    }

    return FALSE;
}

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
$tag["<<SSYEAR>>"]      = "";
$tag["<<SSMON>>"]       = "";
$tag["<<SSDAY>>"]       = "";
$tag["<<SEYEAR>>"]      = "";
$tag["<<SEMON>>"]       = "";
$tag["<<SEDAY>>"]       = "";
$tag["<<ESYEAR>>"]      = "";
$tag["<<ESMON>>"]       = "";
$tag["<<ESDAY>>"]       = "";
$tag["<<EEYEAR>>"]      = "";
$tag["<<EEMON>>"]       = "";
$tag["<<EEDAY>>"]       = "";
$tag["<<SEARCHWORD>>"] = "";
$looptag = array();

/* 設定ファイルやタブ管理ファイル読込、セッションのチェック */
$ret = init();
if ($ret === FALSE) {
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

/***********************************************************
 * main処理
 **********************************************************/

if (isset($_POST["search"])) {

    /* 入力値チェック */
    $ret = check_input_searchdata_v6($_POST);
    if ($ret === FALSE) {
        result_log($log_msg, LOG_ERR);
    } else {
        // 検索処理
        $ret = get_lease_data($_POST, $looptag);
        if ($ret === FALSE) {
            result_log($log_msg, LOG_ERR);
            syserr_display();
            exit(1);
        }
    }

    /* 値の保持 */
    $tag["<<SSYEAR>>"]     = escape_html($_POST["ssyear"]);
    $tag["<<SSMON>>"]      = escape_html($_POST["ssmon"]);
    $tag["<<SSDAY>>"]      = escape_html($_POST["ssday"]);
    $tag["<<SEYEAR>>"]     = escape_html($_POST["seyear"]);
    $tag["<<SEMON>>"]      = escape_html($_POST["semon"]);
    $tag["<<SEDAY>>"]      = escape_html($_POST["seday"]);
    $tag["<<ESYEAR>>"]     = escape_html($_POST["esyear"]);
    $tag["<<ESMON>>"]      = escape_html($_POST["esmon"]);
    $tag["<<ESDAY>>"]      = escape_html($_POST["esday"]);
    $tag["<<EEYEAR>>"]     = escape_html($_POST["eeyear"]);
    $tag["<<EEMON>>"]      = escape_html($_POST["eemon"]);
    $tag["<<EEDAY>>"]      = escape_html($_POST["eeday"]);
    $tag["<<SEARCHWORD>>"] = escape_html($_POST["searchword"]);
}

/***********************************************************
 * 表示処理
 **********************************************************/

/* タグ 設定 */
set_tag_common($tag);

/* ページの出力 */
$ret = display($template, $tag, $looptag, "<<STARTLOOP>>", "<<ENDLOOP>>");
if ($ret === FALSE) {
    result_log($log_msg, LOG_ERR);
    syserr_display();
    exit(1);
}
?>
