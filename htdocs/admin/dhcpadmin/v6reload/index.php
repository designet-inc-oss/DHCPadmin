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
 * 設定適用画面
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
include_once("lib/dglibdhcpadmin");
define("FUNC_FALSE", FALSE);
define("FUNC_TRUE", TRUE);


/********************************************************
 * 各ページ毎の設定
 ********************************************************/

define("TMPLFILE",      "admin_v6reload.tmpl");
define("RUNNING",       "起動中");
define("STOPPED",       "停止中");

define("OPERATION_RESTART", "Restart(v6)");

/***********************************************************
 * 初期処理
 **********************************************************/

/* 状態確認ボタンが押されたら */
//if (isset($_POST["status"])) {
//}
$template = TMPLFILE;

/* タグ初期化 */
$tag["<<TITLE>>"]      = "";
$tag["<<JAVASCRIPT>>"] = "";
$tag["<<SK>>"]         = "";
$tag["<<TOPIC>>"]      = "";
$tag["<<MESSAGE>>"]    = "";
$tag["<<TAB>>"]        = "";
$tag["<<STATUS>>"]       = "";
global $msgarr;

/* 処理判定フラグ */
$status = FALSE;

/* 設定ファイルやタブ管理ファイル読込、セッションのチェック */
$ret = init();
if ($ret === FALSE) {
    syserr_display();
    exit(1);
}


/* dhcp6.confの解析を行う */
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


/*************************************************************
 * 再起動ボタン
 *************************************************************/
if (isset($_POST["restart"])) {
    /* バックアップファイルのパスを作成 */
    $backup_path = $web_conf["dhcpadmin"]["dhcpd6confpath"] . ".backup";
    $ret = create_back_file($web_conf["dhcpadmin"]["dhcpd6confpath"],
                            $backup_path);
    /* 返値を判断する */
    if ($ret === FUNC_FALSE) {
         $err_msg = sprintf($msgarr['36001'][SCREEN_MSG], $backup_path);
         $log_msg = sprintf($msgarr['36001'][LOG_MSG], $backup_path);
         result_log(OPERATION_RESTART . ":NG:" . $log_msg);
    } else {
        /* pidを取得する */
        $pid = getmypid();
        $tmpfile_path = $web_conf["dhcpadmin"]["dhcpd6confpath"] . ".tmp." .
                        $pid;
        /* セッションの情報から一時ファイルを作成する */
        $ret = create_tmp_file($tmpfile_path);
        /* 返値を判断する */
        if ($ret === FUNC_FALSE) {
             $err_msg = sprintf($msgarr['36002'][SCREEN_MSG], $tmpfile_path);
             $log_msg = sprintf($msgarr['36002'][LOG_MSG], $tmpfile_path);
             result_log(OPERATION_RESTART . ":NG:" . $log_msg);
        } else {
            /* 設定ファイルを上書きします */
            $ret = overwrite_setting_file($tmpfile_path,
                                      $web_conf["dhcpadmin"]["dhcpd6confpath"]);
            /* 返値を判断する */
            if ($ret === FUNC_FALSE) {
                $err_msg = sprintf($msgarr['36003'][SCREEN_MSG], $backup_path);
                $log_msg = sprintf($msgarr['36003'][LOG_MSG], $backup_path);
                result_log(OPERATION_RESTART . ":NG:" . $log_msg);
            } else {
                /* configtest */
                $ret = run_command($web_conf["dhcpadmin"]["dhcpd6conftestcom"]);
                /* 返値を判断する */
                if ($ret === FUNC_FALSE) {
                    /* バックアップファイルからdhcpd.confを戻す */
                    rename($backup_path, $web_conf["dhcpadmin"]["dhcpd6confpath"]);
                    $err_msg = $msgarr['36007'][SCREEN_MSG];
                    $log_msg = $msgarr['36007'][LOG_MSG];
                    result_log(OPERATION_RESTART . ":NG:" . $log_msg);
                    /* dhcpdを再起動する */
                    run_command($web_conf["dhcpadmin"]["dhcpd6restartcom"]);
                } else {
                    /* 再起動する */
                    $ret = run_command($web_conf["dhcpadmin"]["dhcpd6restartcom"]);
                    /* 返値を判断する */
                    if ($ret === FUNC_FALSE) {
                        /* バックアップファイルからdhcpd.confを戻す */
                        rename($backup_path,
                             $web_conf["dhcpadmin"]["dhcpd6confpath"]);
                        $err_msg = sprintf($msgarr['36008'][SCREEN_MSG],
                                           $backup_path);
                        $log_msg = sprintf($msgarr['36008'][LOG_MSG],
                                           $backup_path);
                        result_log(OPERATION_RESTART . ":NG:" . $log_msg);
                    } else {
                        $err_msg = sprintf($msgarr['36009'][SCREEN_MSG],
                                           $backup_path);
                        $log_msg = sprintf($msgarr['36009'][LOG_MSG],
                                           $backup_path);
                        result_log(OPERATION_RESTART . ":OK:" . $log_msg);
                    }
                }
            }
        }
    }
}

/********** 再起動ボタンここまで ***********/

/*****************************
 * dhcpdの状態確認
 * (#service dhcpd6 status)
 *****************************/
$ret = check_status($retval, $web_conf["dhcpadmin"]["dhcpd6checkcom"]);
switch ($retval) {
    case 3:
        $tag["<<STATUS>>"] = STOPPED;
        break;
    case 0:
        $tag["<<STATUS>>"] = RUNNING;
        break;
    /* コマンドのエラー */
    default:
        /* 設定適用画面を再表示し、エラーメッセージを出す*/
        $err_msg = sprintf($msgarr['36006'][SCREEN_MSG],
                           $web_conf["dhcpadmin"]["dhcpd6checkcom"]);
        $log_msg = sprintf($msgarr['36006'][LOG_MSG],
                           $web_conf["dhcpadmin"]["dhcpd6checkcom"]);
        result_log($log_msg);
        break;
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
