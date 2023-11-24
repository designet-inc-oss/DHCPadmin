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
 * ���饤������Խ�����
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

define("TMPLFILE_ADD",   "admin_client_add.tmpl");
define("ADD_SEARCH",    "Add client");

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
$tag["<<OLDMACADDR>>"]   = "";
$tag["<<MAC>>"]          = "";
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
$ret = analyze_dhcpd_conf($web_conf["dhcpadmin"]["dhcpdconfpath"], "IPv4");
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
    $ret = check_add_in($_POST);
    switch ($ret) {
    case 1:
        /* �ۥ���̾�����Ϥ��ʤ� */
        $err_msg = sprintf($msgarr['33002'][SCREEN_MSG]);
        $log_msg = $msgarr['33002'][LOG_MSG];
        result_log(ADD_SEARCH . ":NG:" . $log_msg, LOG_ERR);
        break;
    case 2:
    /* MAC���ɥ쥹�����Ϥ��ʤ� */
        $err_msg = sprintf($msgarr['33004'][SCREEN_MSG]);
        $log_msg = $msgarr['33004'][LOG_MSG];
        result_log(ADD_SEARCH . ":NG:" . $log_msg, LOG_ERR);
        break;
    case 3:
    /* IP�߽����꤬���򤵤�Ƥ��뤫 */
        $err_msg = sprintf($msgarr['33009'][SCREEN_MSG]);
        $log_msg = $msgarr['33009'][LOG_MSG];
        result_log(ADD_SEARCH . ":NG:" . $log_msg, LOG_ERR);
        break;
    /* �ۥ���̾�����ϥ����å����顼 */
    case 4:
        $err_msg = sprintf($msgarr['33003'][SCREEN_MSG]);
        $log_msg = $msgarr['33003'][LOG_MSG];
        result_log(ADD_SEARCH . ":NG:" . $log_msg, LOG_ERR);
        break;
    /* MAC���ɥ쥹�����ϥ����å����顼 */
    case 5:
        $err_msg = sprintf($msgarr['33005'][SCREEN_MSG]);
        $log_msg = $msgarr['33005'][LOG_MSG];
        result_log(ADD_SEARCH . ":NG:" . $log_msg, LOG_ERR);
        break;
    /* IP���ɥ쥹�����ϥ����å����顼 */
    case 6:
        $err_msg = sprintf($msgarr['33006'][SCREEN_MSG]);
        $log_msg = $msgarr['33006'][LOG_MSG];
        result_log(ADD_SEARCH . ":NG:" . $log_msg, LOG_ERR);
        break;
    /* ����ξ�� */
    case 0:
        /* IPv6���ɥ쥹�����֥ͥåȤ��ϰ��⤫�����å����� */
        if (isset($_POST["ipaddr"]) && $_POST["ipaddr"] != "") {
            $range_ret = in_range_ipv4($in_sub, $_POST["ipaddr"]);
            if ($range_ret == FALSE) {
                $err_msg = sprintf($msgarr['33020'][SCREEN_MSG]);
                $log_msg = $msgarr['33020'][LOG_MSG];
                result_log(ADD_SEARCH . ":NG:" . $log_msg, LOG_ERR);
                break;
            }
        }
        /* ���֥ͥåȤ�¸�ߤ�����å���shared-network���֤� */
        $sn = search_sn($in_sub);
        if ($sn == "") {
            $err_msg = sprintf($msgarr['33008'][SCREEN_MSG]);
            $log_msg = $msgarr['33008'][LOG_MSG];
            result_log(ADD_SEARCH . ":NG:" . $log_msg, LOG_ERR);
            break;
        }
        /* ��ʣ�����å� */
        /*������Ͽ�ξ�� */
        if ($_POST["oldhost"] == "") {
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
                result_log(ADD_SEARCH . ":NG:" . $log_msg, LOG_ERR);
                break;
            /* MAC���ɥ쥹��ʣ */
            case 2:
                $err_msg = sprintf($msgarr['33011'][SCREEN_MSG]);
                $log_msg = $msgarr['33011'][LOG_MSG];
                result_log(ADD_SEARCH . ":NG:" . $log_msg, LOG_ERR);
                break;
            /* IP���ɥ쥹��ʣ */
            case 3:
                $err_msg = sprintf($msgarr['33012'][SCREEN_MSG]);
                $log_msg = $msgarr['33012'][LOG_MSG];
                result_log(ADD_SEARCH . ":NG:" . $log_msg, LOG_ERR);
                break;
            /* ����ξ�� */
            case 0:
                /* ������Ͽ */
                $newhostline = new_add_host($_POST, $hostline);
                $_SESSION[STR_IP]["$sn"]["$in_sub"]["host"] = $newhostline;
                $err_msg = sprintf($msgarr['33007'][SCREEN_MSG]);
                $log_msg = $msgarr['33007'][LOG_MSG];
                result_log(ADD_SEARCH . ":OK:" . $log_msg, LOG_ERR);
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
                result_log(ADD_SEARCH . ":NG:" . $log_msg, LOG_ERR);
                break;
            /* MAC���ɥ쥹��ʣ */
            case 2:
                $err_msg = sprintf($msgarr['33011'][SCREEN_MSG]);
                $log_msg = $msgarr['33011'][LOG_MSG];
                result_log(ADD_SEARCH . ":NG:" . $log_msg, LOG_ERR);
                break;
            /* IP���ɥ쥹��ʣ */
            case 3:
                $err_msg = sprintf($msgarr['33012'][SCREEN_MSG]);
                $log_msg = $msgarr['33012'][LOG_MSG];
                result_log(ADD_SEARCH . ":NG:" . $log_msg, LOG_ERR);
                break;
            /* ����ξ�� */
            case 0:
                /* �Խ� */
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
$mac = "";
$ip = "";
$oldsn = "";
$oldhost = "";
$oldmac = "";
$oldip = "";
$oldipselect = "";

/* �ϤäƤ������֥ͥåȤ�ɽ�� */
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
    /* 2���ܰʹߤΥ��顼�Τ���hidden�������ͤ�����Ƥ��� */
    if (isset($_POST["oldhost"]) && $_POST["oldhost"] != ""){
        $oldsn = $_POST["oldsn"];
        $oldhost = escape_html($_POST["oldhost"]);
        $oldmac = ($_POST["oldmacaddr"]);
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
