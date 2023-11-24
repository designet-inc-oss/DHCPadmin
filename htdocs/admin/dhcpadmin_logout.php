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
 * logout.php
 * ログアウト
 *
 * $RCSfile: logout.php,v $
 * $Revision: 1.3 $
 * $Date: 2011/09/21 07:25:55 $
 *
 **********************************************************/

include_once("lib/dglibcommon");
include_once("lib/dglibsess");
include_once("lib/dglibpage");
include_once("lib/dglibldap");
include_once("lib/dglibdhcpadmin");
include_once("lib/dglibpostldapadmin");

define("OPERATION_LOGOUT", "Log out");

/***********************************************************
 * main処理
 **********************************************************/
/* $basedirと$topdirのセット */
url_search();

/* セッションの破棄 */
$_SESSION = array();
session_destroy();

/* セッションキー */
if (isset($_POST["sk"])) {
    $sesskey = $_POST["sk"];
}

/* ドメイン取得 */
$domain = $_SERVER['DOMAIN'];

/* 引数チェック */
if (isset($sesskey) === FALSE) {
    err_location("index.php?e=2");
    exit (1);
}

/* 設定ファイル読込 */
if (read_web_conf(NULL) === TRUE) {
    /* メッセージファイルの読込 */
    if (make_msgarr(MESSAGEFILE) === FALSE) {
        return FALSE;
    }
    sess_logout($sesskey);
    result_log(OPERATION_LOGOUT . ":OK:" . $msgarr['23001'][LOG_MSG]);
}

/* セッションチェック */
if (is_sysadm($sesskey) !== TRUE) {
   err_location("index.php?e=1");
   exit (1);
}

/* ロックファイルの削除 */
dhcpadmin_del_lockfile();

dgp_location("index.php");

?>
