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
 * v6クライアント設定画面
 *
 * $RCSfile: index.php,v $
 * $Revision: 1.0 $
 * $Date: 2014// $
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

define("TMPLFILE_LIST",   "admin_v6client_search.tmpl");
define("OPERATION_SEARCH", "Search client_v6");
define("DELETE_SEARCH",    "Delete client_v6");
define("ADD_SEARCH",    "Add client_v6");

/*********************************************************
 * set_tag_search_result()
 *
 * 検索結果表示 
 *
 * [引数]
 *      $hosts        条件を通った結果の配列 
 *
 * [返り値]
 *      なし 
 **********************************************************/

function set_tag_search_result($hosts)
{ 

    global $looptag;
    $i = 0;

    foreach ($hosts as $host) {
        $pieces = explode(",", $host);
        $hostname = ltrim($pieces[3], "\"");
        $sub = rtrim($hostname, "\"");

        $looptag[$i]["<<SUBNET>>"] = $pieces[5];
        $encodehost = base64_encode($sub);
        $escapehost = escape_html($sub);
        $looptag[$i]["<<ESCAPEHOST>>"] = $escapehost;
        $looptag[$i]["<<HOST>>"] = "<a href=\"#\" onClick=\"snSubmit('add.php', '$encodehost', '$pieces[5]', '$pieces[1]', '$pieces[2]', '$pieces[4]', '$pieces[6]')\">$escapehost</a>";
        $looptag[$i]["<<DUID>>"] = $pieces[1];
        $looptag[$i]["<<IPV6>>"] = $pieces[2];
        $looptag[$i]["<<CHECK>>"] = $pieces[4];
        if ($pieces[4] == "allow") {
            $looptag[$i]["<<LEASE>>"] = '許可';
        } else {
            $looptag[$i]["<<LEASE>>"] = '拒否';
        }
        $looptag[$i]["<<SN>>"] = $pieces[6];
        $i ++;
    }
}

/*********************************************************
 * print_csv()
 *
 * 検索結果表示 
 *
 * [引数]
 *      $hosts        条件を通った結果の配列 
 *
 * [返り値]
 *      なし 
 **********************************************************/

function print_csv($hosts)
{

    $down = "";

    foreach ($hosts as $host) {
        $pieces = explode(",", $host);
        $hostname = ltrim($pieces[3], "\"");
        $hostname = rtrim($hostname, "\"");

        if ($pieces[4] == "allow") {
            $select = '許可';
        } else {
            $select = '拒否';
        }
        $select = mb_convert_encoding($select, "SJIS", "EUC-JP");
        $down = $pieces[5] . "," . $hostname . "," . $pieces[1] . "," . $pieces[2] . "," . $select . "\n";
        print($down);
    }
}

/*********************************************************
 * search_client()
 *
 * クライアントの条件検索 
 *
 * [引数]
 *      $sn           $_SESSIONにあるshared-network 
 *      $subnet       $_SESSIONにあるサブネット 
 *      $hoststr      $_SESSIONの中身 
 *      $post         画面から渡ってきた値 
 *
 * [返り値]
 *      $retval       条件を通った結果の配列 
 **********************************************************/
function search_client($sn, $subnet, $hoststr, $post)
{ 

    $retval = array();
    $hosts = explode("\n", $hoststr);
    foreach ($hosts as $host) {
        /* [host]の中身が空ならcontinue */
        if ($host == "") {
            continue;
        }
        /* サブネットがあるか */
        if (isset($post["subnet"])) {
            /* サブネットが同じか */
            if ($subnet != $post["subnet"]) {
                continue;
            }
        }

        /* カンマで区切る */
        $pieces = explode(",", $host);
        $hostname = ltrim($pieces[3], "\"");
        $hostname = rtrim($hostname, "\"");
        $duid = $pieces[1];
        $ip = $pieces[2];
        $select = $pieces[4];

        /* ホスト名があるか */
        if ($post["host"] != "") {
            /* 一致なら */
            if ($post["hostsearch"] == "same"){
                if ($post["host"] != $hostname) {
                    continue;
                }
            /* 含むなら */
            } else {
                if (strpos($hostname, $post["host"]) === FALSE) {
                    continue;
                }
            }
        }
        /* DUIDアドレスがあるか */
        if ($post["duid"] != "") {
            /* 一致なら */
            if ($post["duidsearch"] == "same"){
                $formed_duid = check_macaddr($post["duid"]);
                if ($formed_duid != $duid) {
                    continue;
                }
            /* 含むなら */
            } else {
                if (strpos($duid, strtolower($post["duid"])) === FALSE) {
                    continue;
                }
            }
        }
        /* IPアドレスがあるか */
        if ($post["ipaddr"] != "") {
            if (strpos($ip, $post["ipaddr"]) === FALSE) {
                continue;
            }
        }
        /* IP貸出があるか */
        if (isset($post["ipselect"])) {
            if ($post["ipselect"] != "noselect") {
                if ($post["ipselect"] != $select) {
                    continue;
                }
            }
        }
        /* 条件通れば配列に代入 */
        $host = $host . "," . $subnet . "," . $sn;
        $retval[] = $host;
    }
    return $retval;
}

/*********************************************************
 * delete_client()
 *
 * クライアントの条件検索 
 *
 * [引数]
 *      $sn           $_SESSIONにあるshared-network 
 *      $subnet       $_SESSIONにあるサブネット 
 *      $hoststr      選択された$_SESSION["host"]の中身 
 *      $del         画面から渡ってきた値 
 *
 * [返り値]
 *      $retval       条件を通った結果の配列 
 **********************************************************/
function delete_client($sn, $subnet, &$hoststr, $del)
{

    /* [host]の中を改行で分割 */
    $hosts = explode("\n", $hoststr);

    /* カンマで区切る */
    $delpieces = explode(",", $del);

    /* snが同じか */
    $new_host = "";
    foreach ($hosts as $host) {
        $pieces = explode(",", $host);
        $not_match_flag = 0;

        /* [host]の中身が空ならcontinue */
        if ($host == "") {
            continue;
        }
        $hostname = ltrim($pieces[3], "\"");
        $hostname = rtrim($hostname, "\"");

        /* ホスト名が一致するか */
        if ($delpieces[2] != $hostname) {
            $not_match_flag = 1;
        }
        if ($not_match_flag == 0) {
            /* DUIDが一致するか */
            if ($delpieces[3] != $pieces[1]) {
                $not_match_flag = 1;
            }
        }
        if ($not_match_flag == 0) {
            /* IPアドレスがあるか */
            if ($delpieces[4] != $pieces[2]) {
                $not_match_flag = 1;
            }
        }
        if ($not_match_flag == 0) {
            /* IP貸出があるか */
            if ($delpieces[5] != $pieces[4]) {
                $not_match_flag = 1;
            }
        }
        /* 一致しなかったら変数に代入 */
        if ($not_match_flag == 1){
            $new_host .= $host . "\n";
        }
    }
    /* new_hostとセッションのホストが完全に同じならエラー */
    if ($_SESSION[STR_IP]["$sn"]["$subnet"]["host"] == $new_host) {
        return FALSE;
    }
    /* 変数に代入があれば代入 */
    if ($new_host != ""){
        $_SESSION[STR_IP]["$sn"]["$subnet"]["host"] = $new_host; 
    } else {
        /* 変数に代入がなければ削除 */
        unset($_SESSION[STR_IP]["$sn"]["$subnet"]["host"]);
    }
    $hoststr = $new_host; 
    return TRUE;
}



/***********************************************************
 * 初期処理
 **********************************************************/

$template = TMPLFILE_LIST;
$i = 0;

/* タグ初期化 */
$looptag                 = array();
$tag["<<TITLE>>"]        = "";
$tag["<<JAVASCRIPT>>"]   = "";
$tag["<<SK>>"]           = "";
$tag["<<SN>>"]           = "";
$tag["<<TOPIC>>"]        = "";
$tag["<<MESSAGE>>"]      = "";
$tag["<<TAB>>"]          = "";
$tag["<<MENU>>"]         = "";
$tag["<<SEARCHSUBNET>>"] = "";
$tag["<<SUBNET>>"]       = "";
$tag["<<SEARCHHOST>>"]   = "";
$tag["<<ESCAPEHOST>>"]   = "";
$tag["<<HOST>>"]         = "";
$tag["<<HOST_MATCH>>"]   = "";
$tag["<<SEARCHDUID>>"]    = "";
$tag["<<DUID>>"]          = "";
$tag["<<DUID_MATCH>>"]    = "";
$tag["<<SEARCHIPV6>>"]     = "";
$tag["<<IPV6>>"]           = "";
$tag["<<SEARCHLEASE>>"]  = ""; 
$tag["<<LEASE>>"]        = ""; 
$tag["<<CHECK>>"]        = ""; 

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



/***********************************************************
 * main処理
 **********************************************************/

/* 検索ボタンが押されたら */
if (isset($_POST["search"]) || isset($_POST["download"])) {
    $in_host = escape_html($_POST["host"]);
    $select_host = $_POST["hostsearch"];
    $in_duid = escape_html($_POST["duid"]);
    $select_duid = $_POST["duidsearch"];
    $in_ip = escape_html($_POST["ipaddr"]);
    $in_case = $_POST["ipselect"];
    /* 入力値の入力チェック */
    $ret = check_search_in($in_host, $in_duid, $in_ip);
    switch ($ret) {
    /* 入力値エラーの場合 */
    /* ホスト名の入力値チェック */
    case 1:
        $err_msg = sprintf($msgarr['32001'][SCREEN_MSG]);
        $log_msg = $msgarr['32001'][LOG_MSG];
        result_log(OPERATION_SEARCH . ":NG:" . $log_msg, LOG_ERR);
        break;
    /* DUIDの入力値チェック */
    case 2:
        $err_msg = sprintf($msgarr['32007'][SCREEN_MSG]);
        $log_msg = $msgarr['32007'][LOG_MSG];
        result_log(OPERATION_SEARCH . ":NG:" . $log_msg, LOG_ERR);
        break;
    /* IPアドレスの入力値チェック */
    case 3:
        $err_msg = sprintf($msgarr['32008'][SCREEN_MSG]);
        $log_msg = $msgarr['32008'][LOG_MSG];
        result_log(OPERATION_SEARCH . ":NG:" . $log_msg, LOG_ERR);
        break;
    /* 正常の場合 */
    case 0:
        /* 検索 */
        $search_result_array = array();
        foreach ($_SESSION[STR_IP] as $sn => $value) {
            /* サブネットを見つける */
            if (is_array($_SESSION[STR_IP]["$sn"])) {
                foreach ($_SESSION[STR_IP]["$sn"] as $sub => $value) {
                    if (isset($_SESSION[STR_IP]["$sn"]["$sub"]["host"])) {
                        $hostline = $_SESSION[STR_IP]["$sn"]["$sub"]["host"];
                        /* 条件検索の関数を呼ぶ */
                        $search_result = search_client($sn, $sub, $hostline,
                                                       $_POST);
                        $search_result_array = array_merge($search_result_array,
                                                           $search_result);
                    }
                }
            }
        }
        /* 検索ボタンの時 */
        if (isset($_POST["search"])) {
            /* 結果を画面に表示 */
            set_tag_search_result($search_result_array);
            client_re_display($in_case, $select_host, $select_duid);
        /* 検索結果ダウンロードボタンの時 */
        } else if (isset($_POST["download"])) {
            /* 結果を文字列にセット */
            header("Content-Disposition: attachment; filename=\"search.csv\"");
            header("Content-Type: application/octet-stream");
            print_csv($search_result_array);
            exit(0);
        }
    }
/* クライアント登録ボタンが押されたら */
} else if (isset($_POST["add_client"])) {
    /* サブネットが選択されているか */
    if (empty($_POST["subnet"])) {
        $err_msg = sprintf($msgarr['32006'][SCREEN_MSG]);
        $log_msg = $msgarr['32006'][LOG_MSG];
        result_log(ADD_SEARCH . ":NG:" . $log_msg, LOG_ERR);
    } else {
        /* 画面遷移 */
        $array["modsubnet"] = $_POST["subnet"];
        dgp_location_hidden("add.php", $array);
        exit(0);
    }
/* クライアント一括登録ボタンが押されたら */
} else if (isset($_POST["upload"])) {
    /* 画面遷移 */
    dgp_location("upload.php");
    exit(0);
/* 削除ボタンが押されたら */
} else if (isset($_POST["delete"])) {
    $in_case = $_POST["ipselect"];
    /* チェックボックスにチェックがあれば */
    if (empty($_POST["alldel"]) || $_POST["alldel"] == "on") {
        $err_msg = sprintf($msgarr['32005'][SCREEN_MSG]);
        $log_msg = $msgarr['32005'][LOG_MSG];
        result_log(DELETE_SEARCH . ":NG:" . $log_msg, LOG_ERR);
    } else {
        $alldel = $_POST["alldel"];
        $delete_flag = 0;
        foreach ($alldel as $del) {
            /* チェックされた値を,で区切る */
            $deletepiece = explode(",", $del);
            if (isset($_SESSION[STR_IP]["$deletepiece[0]"]["$deletepiece[1]"]["host"])) {
                $hostline = $_SESSION[STR_IP]["$deletepiece[0]"]["$deletepiece[1]"]["host"];
                /* 選択された1行ずつ削除処理 */
                $ret = delete_client($deletepiece[0], $deletepiece[1], $hostline, $del);
                if ($ret == FALSE) {
                    $delete_flag = 1;
                }
            } else {
                $delete_flag = 1;
            }
        }
        if ($delete_flag == 1) {
            $err_msg = sprintf($msgarr['32004'][SCREEN_MSG]);
            $log_msg = $msgarr['32004'][LOG_MSG];
            result_log(DELETE_SEARCH . ":NG:" . $log_msg, LOG_ERR);
        } else {
            $err_msg = sprintf($msgarr['32000'][SCREEN_MSG]);
            $log_msg = $msgarr['32000'][LOG_MSG];
            result_log(DELETE_SEARCH . ":OK:" . $log_msg, LOG_ERR);
        }
    }
}

/***********************************************************
 * 表示処理
 **********************************************************/
/* 初期表示 */
/* 登録されているサブネットの一覧を表示 */
foreach ($_SESSION[STR_IP] as $sn => $value){
    if ($sn != "_common" && is_array($_SESSION[STR_IP]["$sn"])) {
        foreach ($_SESSION[STR_IP]["$sn"] as $key => $value){
            /* 所属のサブネットに表示させる */
            if ((isset($_POST["subnet"]) && $_POST["subnet"] == $key)) {
                $tag["<<SEARCHSUBNET>>"] .= "<option value=\"$key\" selected>$key</option>\n";
            } else {
                $tag["<<SEARCHSUBNET>>"] .= "<option value=\"$key\">$key</option>\n";
            }
        }
    }
}

/* 初期表示 */
if (empty($_POST["hostsearch"])) {
    $in_host = "";
    $select_host = "same";
    $in_duid = "";
    $select_duid = "same";
    $in_ip = "";
    $in_case = "noselect";
} else {
/* 再表示 */
    $in_host = escape_html($_POST["host"]);
    $select_host = $_POST["hostsearch"];
    $in_duid = escape_html($_POST["duid"]);
    $select_duid = $_POST["duidsearch"];
    $in_ip = escape_html($_POST["ipaddr"]);
    $in_case = $_POST["ipselect"];
}
$tag["<<SEARCHHOST>>"] = "$in_host";
$tag["<<SEARCHDUID>>"] = "$in_duid";
$tag["<<SEARCHIPV6>>"] = "$in_ip";
/* IP貸出設定の再表示 */
client_re_display($in_case, $select_host, $select_duid);
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
