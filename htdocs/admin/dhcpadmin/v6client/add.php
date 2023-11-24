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
 * ���饤�����v6�Խ�����
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
 * �ƥڡ����������
 ********************************************************/

define("TMPLFILE_ADD",   "admin_v6client_add.tmpl");
define("OPERATION_ADD",    "Add client_v6");

/*********************************************************
 * check_add_v6_in()
 *
 * ���饤������Խ�v6���������ͥ����å�
 *
 * [����]
 *      $post        ���̤����ϤäƤ����� 
 * [�֤���]
 *      0            ����
 *      1            �ۥ���̤̾����
 *      2            DUID���ɥ쥹̤����
 *      3            IP���ɥ쥹̤����
 *      4            �ۥ���̾���顼
 *      5            DUID���ɥ쥹���顼
 *      6            IP���ɥ쥹���顼
 **********************************************************/

function check_add_v6_in($post)
{
    $hostname = $post["host"];
    $duid = $post["duid"];
    $ip = $post["ipaddr"];

    $must = check_must($post);
    if ($must != 0) {
        return $must;
    }
    $ret = check_search_in($hostname, $duid, $ip);
    switch ($ret) {
    /* �ۥ���̾���顼 */
    case 1:
        return 4;
    /* DUID���顼 */
    case 2:
        return 5;
    /* IP�߽����ꥨ�顼 */
    case 3:
        return 6;
    }
    return 0;
}

/***********************************************************
 * �������
 **********************************************************/

$template = TMPLFILE_ADD;

/* ��������� */
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
$tag["<<OLDDUID>>"]   = "";
$tag["<<DUID>>"]          = "";
$tag["<<OLDIPADDR>>"]    = "";
$tag["<<OLDIPSELECT>>"]    = "";
$tag["<<IP>>"]           = "";
$tag["<<LEASE>>"]        = ""; 
$tag["<<LEASE_ALLOW>>"]  = "";
$tag["<<LEASE_DENY>>"]   = "";

/* ����ե�����䥿�ִ����ե������ɹ������å����Υ����å� */
$ret = init();
if ($ret === FALSE) {
    syserr_display();
    exit(1);
}

/* dhcpd.conf�β��� */
$ret = analyze_dhcpd_conf($web_conf["dhcpadmin"]["dhcpd6confpath"], "IPv6");
/* dhcpd.conf�ɤ߹��ߥ��顼 */
if ($ret == FALSE) {
    $err_msg = $msgarr['27004'][SCREEN_MSG];
    $log_msg = $msgarr['27004'][LOG_MSG];
    result_log($log_msg, LOG_ERR);
    syserr_display();
    exit(1);
}

/* ��ť�����Υ����å� */
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
 * main����
 **********************************************************/

/* ��Ͽ�ܥ��󤬲����줿�� */
if (isset($_POST["add"])) {
    $in_sub = escape_html($_POST["subnet"]);
    /* ���ϥ����å� */
    $ret = check_add_v6_in($_POST);
    switch ($ret) {
    /* �ۥ���̾�����Ϥ��ʤ� */
    case 1:
        $err_msg = sprintf($msgarr['33002'][SCREEN_MSG]);
        $log_msg = $msgarr['33002'][LOG_MSG];
        result_log(OPERATION_ADD . ":NG:" . $log_msg, LOG_ERR);
        break;
    /* DUID���ɥ쥹�����Ϥ��ʤ� */
    case 2:
        $err_msg = sprintf($msgarr['33013'][SCREEN_MSG]);
        $log_msg = $msgarr['33013'][LOG_MSG];
        result_log(OPERATION_ADD . ":NG:" . $log_msg, LOG_ERR);
        break;
    /* IP�߽����꤬���򤵤�Ƥ��뤫 */
    case 3:
        $err_msg = sprintf($msgarr['33009'][SCREEN_MSG]);
        $log_msg = $msgarr['33009'][LOG_MSG];
        result_log(OPERATION_ADD . ":NG:" . $log_msg, LOG_ERR);
        break;
    /* �ۥ���̾�����ϥ����å����顼 */
    case 4:
        $err_msg = sprintf($msgarr['33003'][SCREEN_MSG]);
        $log_msg = $msgarr['33003'][LOG_MSG];
        result_log(OPERATION_ADD . ":NG:" . $log_msg, LOG_ERR);
        break;
    /* DUID���ɥ쥹�����ϥ����å����顼 */
    case 5:
        $err_msg = sprintf($msgarr['33014'][SCREEN_MSG]);
        $log_msg = $msgarr['33014'][LOG_MSG];
        result_log(OPERATION_ADD . ":NG:" . $log_msg, LOG_ERR);
        break;
    /* IPv6���ɥ쥹�����ϥ����å����顼 */
    case 6:
        $err_msg = sprintf($msgarr['33016'][SCREEN_MSG]);
        $log_msg = $msgarr['33016'][LOG_MSG];
        result_log(OPERATION_ADD . ":NG:" . $log_msg, LOG_ERR);
        break;
    /* ����ξ�� */
    case 0:
        /* IPv6���ɥ쥹�����֥ͥåȤ��ϰ��⤫�����å����� */
        if (isset($_POST["ipaddr"]) && $_POST["ipaddr"] != "") {
            $range_ret = in_range_ipv6($in_sub, $_POST["ipaddr"]);
            if ($range_ret == FALSE) {
                $err_msg = sprintf($msgarr['33019'][SCREEN_MSG]);
                $log_msg = $msgarr['33019'][LOG_MSG];
                result_log(OPERATION_ADD . ":NG:" . $log_msg, LOG_ERR);
                break;
            }
        }
        /* ���֥ͥåȤ�¸�ߤ�����å���shared-network���֤� */
        $sn = search_sn($in_sub);
        if ($sn == "") {
            $err_msg = sprintf($msgarr['33008'][SCREEN_MSG]);
            $log_msg = $msgarr['33008'][LOG_MSG];
            result_log(OPERATION_ADD . ":NG:" . $log_msg, LOG_ERR);
            break;
        }
        /* ��ʣ�����å� */
        /*������Ͽ�ξ�� */
        if (isset($_POST["oldhost"]) && $_POST["oldhost"] == "") {
            /* �ۥ��Ȥ���Ȥ�����н�ʣ�����å� */
            if (isset($_SESSION[STR_IP]["$sn"]["$in_sub"]["host"])) {
                $hostline = $_SESSION[STR_IP]["$sn"]["$in_sub"]["host"];
                $ret = check_add_duplication($hostline, $_POST);
            } else {
                /* �ۥ��Ȥ���Ȥ��ʤ����0������ */
                $hostline = "";
                $ret = 0;
            }
            switch ($ret) {
            /* �ۥ���̾��ʣ */
            case 1:
                $err_msg = sprintf($msgarr['33010'][SCREEN_MSG]);
                $log_msg = $msgarr['33010'][LOG_MSG];
                result_log(OPERATION_ADD . ":NG:" . $log_msg, LOG_ERR);
                break;
            /* DUID���ɥ쥹��ʣ */
            case 2:
                $err_msg = sprintf($msgarr['33015'][SCREEN_MSG]);
                $log_msg = $msgarr['33015'][LOG_MSG];
                result_log(OPERATION_ADD . ":NG:" . $log_msg, LOG_ERR);
                break;
            /* IPv6���ɥ쥹��ʣ */
            case 3:
                $err_msg = sprintf($msgarr['33018'][SCREEN_MSG]);
                $log_msg = $msgarr['33018'][LOG_MSG];
                result_log(OPERATION_ADD . ":NG:" . $log_msg, LOG_ERR);
                break;
            /* ����ξ�� */
            case 0:
                /* ������Ͽ */
                $newhostline = new_add_host($_POST, $hostline);
                $_SESSION[STR_IP]["$sn"]["$in_sub"]["host"] = $newhostline;
                $err_msg = sprintf($msgarr['33007'][SCREEN_MSG]);
                $log_msg = $msgarr['33007'][LOG_MSG];
                result_log(OPERATION_ADD . ":OK:" . $log_msg, LOG_ERR);
                dgp_location("index.php", $err_msg);
                exit(0);
            }
        } else { 
            /* �Խ��ξ�� */
            $old_sn = $_POST["oldsn"];
            $hostline = $_SESSION[STR_IP]["$old_sn"]["$in_sub"]["host"];
            $ret = check_mod_duplication($hostline, $_POST);
            switch ($ret) {
            /* �ۥ���̾��ʣ */
            case 1:
                $err_msg = sprintf($msgarr['33010'][SCREEN_MSG]);
                $log_msg = $msgarr['33010'][LOG_MSG];
                result_log(OPERATION_ADD . ":NG:" . $log_msg, LOG_ERR);
                break;
            /* DUID���ɥ쥹��ʣ */
            case 2:
                $err_msg = sprintf($msgarr['33011'][SCREEN_MSG]);
                $log_msg = $msgarr['33011'][LOG_MSG];
                result_log(OPERATION_ADD . ":NG:" . $log_msg, LOG_ERR);
                break;
            /* IP���ɥ쥹��ʣ */
            case 3:
                $err_msg = sprintf($msgarr['33012'][SCREEN_MSG]);
                $log_msg = $msgarr['33012'][LOG_MSG];
                result_log(OPERATION_ADD . ":NG:" . $log_msg, LOG_ERR);
                break;
            /* ����ξ�� */
            case 0:
                /* �Խ� */
                $ret = mod_client($hostline, $_POST);
                $_SESSION[STR_IP]["$old_sn"]["$in_sub"]["host"] = $ret;
                $err_msg = sprintf($msgarr['33007'][SCREEN_MSG]);
                $log_msg = $msgarr['33007'][LOG_MSG];
                result_log(OPERATION_ADD . ":OK:" . $log_msg, LOG_ERR);
                dgp_location("index.php", $err_msg);
                exit(0);
            }
        }
    }
/* ���ܥ��󤬲����줿�� */
} else if (isset($_POST["back"])) {
    /* �������� */
    dgp_location("index.php");
    exit(0);
}

/***********************************************************
 * ɽ������
 **********************************************************/
$host = "";
$duid = "";
$ip = "";
$oldsn = "";
$oldhost = "";
$oldduid = "";
$oldip = "";
$oldipselect = "";

/* �ϤäƤ������֥ͥåȤ�ɽ�� */
if (!isset($_POST["modsubnet"])) {
    $subnet = $_POST["subnet"];
    $host = escape_html($_POST["host"]);
    $duid = escape_html($_POST["duid"]);
    $ip = escape_html($_POST["ipaddr"]);
    if (isset($_POST["ipselect"])) {
        $select = $_POST["ipselect"];
    } else {
        $select = "";
    }
    /* 2���ܰʹߤΥ��顼�Τ���hidden�������ͤ�����Ƥ��� */
    if (isset($_POST["oldhost"]) && $_POST["oldhost"] != "") {
        $oldsn = $_POST["oldsn"];
        $oldhost = escape_html($_POST["oldhost"]);
        $oldduid = ($_POST["oldduid"]);
        $oldip = ($_POST["oldipaddr"]);
        $select = ($_POST["oldipselect"]);
    }
} else {
    /* ���ɽ�� */
    $subnet = $_POST["modsubnet"];
    if (isset($_POST["modhost"])) {
        $oldsn = $_POST["mode"];
        $host = base64_decode($_POST["modhost"]);
        $host = escape_html($host);
        $oldhost = $host;
        $duid = escape_html($_POST["modduid"]);
        $oldduid = escape_html($_POST["modduid"]);
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
$tag["<<DUID>>"] = $duid;
$tag["<<IP>>"] = $ip;
$tag["<<OLDSN>>"] = $oldsn;
$tag["<<OLDHOST>>"] = $oldhost;
$tag["<<OLDDUID>>"] = $oldduid;
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

/* ���� ���� */
set_tag_common($tag);

/* �ڡ����ν��� */
$ret = display($template, $tag, array(), "", "");
if ($ret === FALSE) {
    result_log($log_msg, LOG_ERR);
    syserr_display();
    exit(1);
}
?>
