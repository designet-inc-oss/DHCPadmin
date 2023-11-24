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
 * クライアント一括登録画面
 *
 * $RCSfile: index.php,v $
 * $Revision: $
 * $Date: 2014 $
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

define("TMPLFILE_UPLOAD",   "admin_v6client_upload.tmpl");
define("UPLOAD_CLIENT",     "Upload client_v6");
define("MAX_SUBNET_LENGTH", "43");


/*********************************************************
 * check_file_duplication()
 *
 * ファイルの中身の重複チェック
 *
 * [引数]
 *      $file_data   ファイルの中身が入った配列
 * [返り値]
 *      0            正常
 *      1            ホスト名エラー
 *      2            DUIDエラー
 *      3            IPアドレスエラー
 **********************************************************/
function check_file_duplication($file_data, &$error_line)
{
    $i = 0;
    /* 1行ずつそれぞれ$checkの配列に番号をつけて入れ直す */
    foreach ($file_data as $data) {
        $data = rtrim($data);
        list($subnet, $hostname, $duid, $ip, $select) = explode(",", $data);

        $check[$i]["subnet"] = $subnet;
        $check[$i]["duid"] = $duid;
        $check[$i]["ip"] = $ip;
        $check[$i]["hostname"] = $hostname;
        $i++;
    }
    $count = $i;
    /* ファイル内で重複が無いかチェックする */
    for ($i = 0; $i < $count; $i++) {
        /* 今見ている行の次の行と順番に比較していく */
        for ($j = $i + 1; $j < $count; $j++) {
            /* サブネットが同じの場合のみ重複チェック */
            if ($check[$i]["subnet"] == $check[$j]["subnet"]) {
                if ($check[$i]["hostname"] == $check[$j]["hostname"]) {
                    $error_line = $j;
                    return 1;
                }
                if ($check[$i]["duid"] == $check[$j]["duid"]) {
                    $error_line = $j;
                    return 2;
                }
                if ($check[$i]["ip"] == $check[$j]["ip"]) {
                    $error_line = $j;
                    return 3;
                }
            }
        }
    }
    return 0;
}


/*********************************************************
 * check_update_in()
 *
 * クライアント一括登録画面入力値チェック
 *
 * [引数]
 *      $file_data   ファイルの中身が入った配列
 * [返り値]
 *      0            正常
 *      1            サブネット未入力
 *      2            ホスト名未入力
 *      3            DUID未入力
 *      4            IP貸出設定未入力
 *      5            サブネットエラー
 *      6            ホスト名エラー
 *      7            DUIDエラー
 *      8            IPアドレスエラー
 *      9            IP貸出設定エラー
 *     10            サブネットが存在しない
 *     11            IPアドレスがサブネットからはみ出す
 **********************************************************/

function check_update_in($file_data, &$line)
{
    foreach ($file_data as $data) {
        $data = rtrim($data);
        /* カンマで区切る */
        list($subnet, $hostname, $duid, $ip, $select) = explode(",", $data);

        $line++;
        /* 必須項目チェック */
        $must = check_in_must($subnet, $hostname, $duid, $select);
        if ($must != 0) {
            /* 入力エラー */
            return $must;
        }
        /* サブネットの入力がエラー */
        $ret = check_subnet($subnet);
        if ($ret == FALSE) {
            return 5;
        }
        /* ホスト名の入力がエラー */
        $ret = check_hostname($hostname);
        if ($ret == FALSE) {
            return 6;
        }
        /* DUIDの入力がエラー */
        $ret = check_duid($duid);
        if ($ret == FALSE) {
            return 7;
        }
        /* IPアドレスの入力がエラー */
        $ret = check_ip($ip);
        if ($ret == FALSE) {
            return 8;
        }
        /* IP貸出設定の入力がエラー */
        $ret = check_select($select);
        if ($ret == FALSE) {
            return 9;
        }
        /* セッション内に同じサブネットがあるか */
        $judge = judge_sn($subnet);
        if ($judge == "") {
            return 10;
        }
        /* IPアドレスがサブネット内に収まっているかどうか */
        if (isset($ip) && $ip != "") {
            $ret = in_range_ipv6($subnet, $ip);
            if ($ret === FALSE) {
                return 11;
            }
        }
    }
    return 0;
}

/*********************************************************
 * check_in_must()
 *
 * 必須項目が入力されているかチェック
 *
 * [引数]
 *      $subnet      1行ごとのサブネット
 *      $hostname    1行ごとのホスト名
 *      $duid         1行ごとのduidアドレス
 *      $select      1行ごとのIP貸出設定
 * [返り値]
 *      0            正常
 *      1            サブネットエラー
 *      2            ホスト名エラー
 *      3            DUIDエラー
 *      4            IPアドレスエラー
 **********************************************************/
function check_in_must($subnet, $hostname, $duid, $select)
{
    /* サブネットの入力があるか */
    if ($subnet == "") {
        return 1;
    } 
    /* ホスト名の入力があるか */
    if ($hostname == "") {
        return 2;
    } 
    /* DUIDの入力があるか */
    if ($duid == "") {
        return 3;
    }
    /* IP貸出設定が選択されているか */
    if ($select == "") {
        return 4;
    }
    return 0;
}

/*********************************************************
 * check_subnet()
 *
 * サブネットの入力値チェック
 *
 * [引数]
 *      $subnet      サブネットの値
 * [返り値]
 *      TRUE         正常
 *      FALSE        異常
 **********************************************************/
function check_subnet($subnet)
{
    /* 文字数チェック */
    /* subnetに値があるか調べる */
    $length = strlen($subnet);
    if ($length > MAX_SUBNET_LENGTH) {
        return FALSE;
    }
    /* /で前後に分ける */
    $piece = explode("/", $subnet);
    if (count($piece) != 2) {
        return FALSE;
    }
    /* /の前後をそれぞれチェック */
    $ret = check_ip($piece[0]);
    if ($ret == FALSE) {
        return FALSE;
    }
    /* /の後ろはマスク値で、1から128の整数が許可値 */
    $var = strspn($piece[1], "1234567890");
    if (strlen($piece[1]) != $var) {
        return FALSE;
    }
    if ($piece[1] < 1 || 128 < $piece[1]) {
        return FALSE;
    }
    return TRUE;
}

/*********************************************************
 * check_select()
 *
 * IP貸出設定チェック 
 *
 * [引数]
 *      $select      IP貸出設定
 * [返り値]
 *      TRUE         正常
 *      FALSE        異常
 **********************************************************/
function check_select($select)
{
    /*入力された値がallowもしくはdenyか*/
    $select = mb_convert_encoding($select, "EUC-JP", "SJIS");
    if ($select == "許可" || $select == "拒否") {
        return TRUE;
    }
    return FALSE;
}


/*********************************************************
 * add_host_session()
 *
 * 新規登録関数 
 *
 * [引数]
 *      $file_data    ファイルの中身 
 * [返り値]
 *      TRUE         正常
 *      FALSE        異常
 **********************************************************/
function add_host_session($file_data)
{
    foreach ($file_data as $data) {
        $data = rtrim($data);
        /* dataの中身が空ならcontinue */
        if ($data == "") {
            continue;
        }
        /* カンマで区切る */
        list($subnet, $hostname, $duid, $ip, $select) = explode(",", $data);
        /* DUIDを2桁に揃える */
        $duid = check_macaddr($duid);
        /* IP貸出設定を変換する */
        $select = mb_convert_encoding($select, "EUC-JP", "SJIS");
        if ($select == "許可") {
            $select = "allow";
        } else {
            $select = "deny";
        }

        /* SESSIONに入れる文字列に並べる */
        $line = "$hostname,$duid,$ip,\"$hostname\",$select";
        /* Shared-networkを調べる */
        $sn = judge_sn($subnet);
        if ($sn == "") {
            return FALSE;
        }
        if (isset($_SESSION[STR_IP]["$sn"]["$subnet"]["host"])) {
            /* hostの中身あればつなげる */
            $hostline = $_SESSION[STR_IP]["$sn"]["$subnet"]["host"];
            $hostline = $hostline . $line . "\n";
        } else {
            /* hostの中身なければ代入 */
            $hostline = $line . "\n";
        }
        /* SESSIONに登録 */
        $_SESSION[STR_IP]["$sn"]["$subnet"]["host"] = $hostline;
    }
    return TRUE;
}
/*********************************************************
 * check_column()
 *
 * カラム数チェック関数 
 *
 * [引数]
 *      $file_data    ファイルの中身が入った配列
 * [返り値]
 *      $line         エラーの行目 
 **********************************************************/
/*カラム数チェック*/
function check_column($file_data) 
{
    $line = 0;
    foreach ($file_data as $data) {
        $line++;
        $data = rtrim($data);
        /* カンマで区切る */
        $column = explode(",", $data);
        /*カラム数チェック*/
        if (count($column) != 5) {
            /* エラーの行数を返す */
            return $line;
        }
    }
    /* 正しければ0を返す */
    return 0;
}

/***********************************************************
 * 初期処理
 **********************************************************/

$template = TMPLFILE_UPLOAD;

/* タグ初期化 */
$tag["<<TITLE>>"]        = "";
$tag["<<JAVASCRIPT>>"]   = "";
$tag["<<SK>>"]           = "";
$tag["<<SN>>"]           = "";
$tag["<<TOPIC>>"]        = "";
$tag["<<MESSAGE>>"]      = "";
$tag["<<TAB>>"]          = "";
$tag["<<MENU>>"]         = "";
$newhostline             = "";
$addhostline             = "";

/* 設定ファイルやタブ管理ファイル読込、セッションのチェック */
$ret = init();
if ($ret === FALSE) {
    syserr_display();
    exit(1);
}

/* dhcpd.confの解析 */
$ret = analyze_dhcpd_conf($web_conf["dhcpadmin"]["dhcpd6confpath"], "IPv6");
/* dhcpd.conf読み込みエラー */
if ($ret == FALSE) {
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

/***********************************************************
 * main処理
 **********************************************************/

if (isset($_POST["csvupload"])) {
    /* $_FILES変数の tmp_name にサーバ上のファイル名がある */
    $csv_file = $_FILES["csv_upload"]["tmp_name"];

    /* UPLOADされたファイルかチェックをする */
    if (is_uploaded_file($csv_file) === FALSE) {
        $err_msg = sprintf($msgarr['34008'][SCREEN_MSG]);
        $log_msg = $msgarr['34008'][LOG_MSG];
        result_log(UPLOAD_CLIENT . ":NG:" . $log_msg, LOG_ERR);
    } else {
        $file_data = file($csv_file);
        /* ファイルの中身が空ならエラー */
        if (empty($file_data)) {
            $err_msg = sprintf($msgarr['34013'][SCREEN_MSG]);
            $log_msg = $msgarr['34013'][LOG_MSG];
            result_log(UPLOAD_CLIENT . ":NG:" . $log_msg, LOG_ERR);
        } else {
            /*カラム数チェック*/
            $line = check_column($file_data);
            if ($line != 0) {
                /* カラム数が不正 */
                $err_msg = sprintf($msgarr['34001'][SCREEN_MSG], $line);
                $log_msg = sprintf($msgarr['34001'][LOG_MSG], $line);
                result_log(UPLOAD_CLIENT . ":NG:" . $log_msg, LOG_ERR);
            } else {
                /*入力チェック*/
                $line = 0;
                $ret = check_update_in($file_data, $line);
                switch ($ret) {
                case 1:
                    /* サブネットの入力がない */
                    $err_msg = sprintf($msgarr['34002'][SCREEN_MSG], $line);
                    $log_msg = sprintf($msgarr['34002'][LOG_MSG], $line);
                    result_log(UPLOAD_CLIENT . ":NG:" . $log_msg, LOG_ERR);
                    break;
                case 2:
                    /* ホスト名の入力がない */
                    $err_msg = sprintf($msgarr['34003'][SCREEN_MSG], $line);
                    $log_msg = sprintf($msgarr['34003'][LOG_MSG], $line);
                    result_log(UPLOAD_CLIENT . ":NG:" . $log_msg, LOG_ERR);
                    break;
                case 3:
                /* DUIDの入力がない */
                    $err_msg = sprintf($msgarr['34023'][SCREEN_MSG], $line);
                    $log_msg = sprintf($msgarr['34023'][LOG_MSG], $line);
                    result_log(UPLOAD_CLIENT . ":NG:" . $log_msg, LOG_ERR);
                    break;
                case 4:
                /* IP貸出設定が選択されているか */
                    $err_msg = sprintf($msgarr['34010'][SCREEN_MSG], $line);
                    $log_msg = sprintf($msgarr['34010'][LOG_MSG], $line);
                    result_log(UPLOAD_CLIENT . ":NG:" . $log_msg, LOG_ERR);
                    break;
                /* サブネットの入力チェックエラー */
                case 5:
                    $err_msg = sprintf($msgarr['34009'][SCREEN_MSG], $line);
                    $log_msg = sprintf($msgarr['34009'][LOG_MSG], $line);
                    result_log(UPLOAD_CLIENT . ":NG:" . $log_msg, LOG_ERR);
                    break;
                /* ホスト名の入力チェックエラー */
                case 6:
                    $err_msg = sprintf($msgarr['34004'][SCREEN_MSG], $line);
                    $log_msg = sprintf($msgarr['34004'][LOG_MSG], $line);
                    result_log(UPLOAD_CLIENT . ":NG:" . $log_msg, LOG_ERR);
                    break;
                /* DUIDの入力チェックエラー */
                case 7:
                    $err_msg = sprintf($msgarr['34024'][SCREEN_MSG], $line);
                    $log_msg = sprintf($msgarr['34024'][LOG_MSG], $line);
                    result_log(UPLOAD_CLIENT . ":NG:" . $log_msg, LOG_ERR);
                    break;
                /* IPアドレスの入力チェックエラー */
                case 8:
                    $err_msg = sprintf($msgarr['34025'][SCREEN_MSG], $line);
                    $log_msg = sprintf($msgarr['34025'][LOG_MSG], $line);
                    result_log(UPLOAD_CLIENT . ":NG:" . $log_msg, LOG_ERR);
                    break;
                /* IP貸出設定の入力チェックエラー */
                case 9:
                    $err_msg = sprintf($msgarr['34012'][SCREEN_MSG], $line);
                    $log_msg = sprintf($msgarr['34012'][LOG_MSG], $line);
                    result_log(UPLOAD_CLIENT . ":NG:" . $log_msg, LOG_ERR);
                    break;
                /* サブネット存在エラー */
                case 10:
                    $err_msg = sprintf($msgarr['34011'][SCREEN_MSG], $line);
                    $log_msg = sprintf($msgarr['34011'][LOG_MSG], $line);
                    result_log(UPLOAD_CLIENT . ":NG:" . $log_msg, LOG_ERR);
                    break;
                /* IPアドレスの入力チェックエラー */
                case 11:
                    $err_msg = sprintf($msgarr['33019'][SCREEN_MSG], $line);
                    $log_msg = sprintf($msgarr['33019'][LOG_MSG], $line);
                    result_log(UPLOAD_CLIENT . ":NG:" . $log_msg, LOG_ERR);
                    break;
                /* 正常の場合 */
                case 0:
                    /* ファイルの中身重複チェック */
                    $ret = check_file_duplication($file_data, $line);
                    switch ($ret) {
                    /* ホスト名重複 */
                    case 1:
                        $err_msg = sprintf($msgarr['34018'][SCREEN_MSG], $line + 1);
                        $log_msg = sprintf($msgarr['34018'][LOG_MSG], $line + 1);
                        result_log(UPLOAD_CLIENT . ":NG:" . $log_msg, LOG_ERR);
                        break;
                    /* DUID重複 */
                    case 2:
                        $err_msg = sprintf($msgarr['34026'][SCREEN_MSG], $line + 1);
                        $log_msg = sprintf($msgarr['34026'][LOG_MSG], $line + 1);
                        result_log(UPLOAD_CLIENT . ":NG:" . $log_msg, LOG_ERR);
                        break;
                    /* IPアドレス重複 */
                    case 3:
                        $err_msg = sprintf($msgarr['34027'][SCREEN_MSG], $line + 1);
                        $log_msg = sprintf($msgarr['34027'][LOG_MSG], $line + 1);
                        result_log(UPLOAD_CLIENT . ":NG:" . $log_msg, LOG_ERR);
                        break;
                    /* 正常の場合 */
                    case 0:
                        /* 重複チェック */
                        $line = 0;
                        $dup_flag = 0;
                        foreach ($file_data as $data) {
                            $line++;
                            $ret = check_duplication_data($data);
                            switch ($ret) {
                            /* ホスト名重複 */
                            case 1:
                                $err_msg = sprintf($msgarr['34015'][SCREEN_MSG], $line);
                                $log_msg = sprintf($msgarr['34015'][LOG_MSG], $line);
                                result_log(UPLOAD_CLIENT . ":NG:" . $log_msg, LOG_ERR);
                                $dup_flag = 1;
                                break 2;
                            /* DUID重複 */
                            case 2:
                                $err_msg = sprintf($msgarr['34028'][SCREEN_MSG], $line);
                                $log_msg = sprintf($msgarr['34028'][LOG_MSG], $line);
                                result_log(UPLOAD_CLIENT . ":NG:" . $log_msg, LOG_ERR);
                                $dup_flag = 1;
                                break 2;
                            /* IPアドレス重複 */
                            case 3:
                                $err_msg = sprintf($msgarr['34029'][SCREEN_MSG], $line);
                                $log_msg = sprintf($msgarr['34029'][LOG_MSG], $line);
                                result_log(UPLOAD_CLIENT . ":NG:" . $log_msg, LOG_ERR);
                                $dup_flag = 1;
                                break 2;
                            /* Shared-networkが存在しない */
                            case 4:
                                $err_msg = sprintf($msgarr['34022'][SCREEN_MSG], $line);
                                $log_msg = sprintf($msgarr['34022'][LOG_MSG], $line);
                                result_log(UPLOAD_CLIENT . ":NG:" . $log_msg, LOG_ERR);
                                $dup_flag = 1;
                                break 2;
                            /* 正常の場合 */
                            case 0:
                                break;
                            }
                        }
                        /* 全て正常の場合 */
                        if ($dup_flag == 0) {
                            /* 登録処理 */
                            $ret = add_host_session($file_data);
                            if ($ret == FALSE) {
                                /* Shared-networkが存在しない */
                                $err_msg = sprintf($msgarr['34022'][SCREEN_MSG], $line);
                                $log_msg = sprintf($msgarr['34022'][LOG_MSG], $line);
                                result_log(UPLOAD_CLIENT . ":NG:" . $log_msg, LOG_ERR);
                            } else {
                                /* 登録成功 */
                                $err_msg = sprintf($msgarr['34021'][SCREEN_MSG], $line);
                                $log_msg = sprintf($msgarr['34021'][LOG_MSG], $line);
                                result_log(UPLOAD_CLIENT . ":OK:" . $log_msg, LOG_ERR);
                            }
                        }
                    }
                }
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
