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
 * Shared-network�Խ�����
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
 * �ƥڡ����������
 ********************************************************/

define("TMPLFILE_MOD", "admin_v6network_subnet_add.tmpl");
define("OPERATION", "Updating subnet_v6");

/***********************************************************
 * �������
 **********************************************************/

$template = TMPLFILE_MOD;

/* ��������� */
$tag["<<TITLE>>"]        = "";
$tag["<<JAVASCRIPT>>"]   = "";
$tag["<<SK>>"]           = "";
$tag["<<TOPIC>>"]        = "";
$tag["<<MESSAGE>>"]      = "";
$tag["<<TAB>>"]          = "";
$tag["<<MENU>>"]         = "";
$tag["<<ROUTER>>"]       = "";
$tag["<<DOMAINNAME>>"]   = "";
$tag["<<LEASETIME>>"]    = "";
$tag["<<MAXLEASETIME>>"] = "";
$tag["<<DNS>>"]          = "";
$tag["<<OPTION>>"]       = "";

/*�ѿ��ν����*/
$subnet_data = array();
$domainname = "";
$leasetime = "";
$maxlease = "";
$dns = "";
$option = "";

/* ����ե�����䥿�ִ����ե������ɹ������å����Υ����å� */
$ret = init();
if ($ret === FALSE) {
    syserr_display();
    exit(1);
}

/*dhcp.conf��ʬ�Ϥ�Ԥ��ؿ���ƤӽФ�*/
$ret = analyze_dhcpd_conf($web_conf["dhcpadmin"]["dhcpd6confpath"], "IPv6");
if ($ret === FALSE) {
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
    $log_msg = sprintf($msgarr['27005'][LOG_MSG], $_SERVER["REMOTE_ADDR"]);
    result_log($log_msg, LOG_ERR);
    syserr_display();
    exit(1);
}

/*������Ͽ����Ƥ������ƤΥ��֥ͥåȤ�������*/
$subnet_data = get_all_subnets();

/***********************************************************
 * main����
 **********************************************************/
/*�ɲåܥ���򲡤��줿��(���֥ͥåȴ������̤���)*/
if (isset($_POST["subnet"])) {
    /*���֥ͥåȤ��ͤ����ꤹ��*/
    $subnet_up = $_POST["subnet"]. "/". $_POST["netmask"];
    /*�ɥᥤ����ͤ����ꤹ�� */
    $domainname = $web_conf["dhcpadmin"]["defdomain"];
    $leasetime = $web_conf["dhcpadmin"]["defleasetime"];
    $maxlease = $web_conf["dhcpadmin"]["defmaxleasetime"];

}

/*�����ܥ���򲡤��줿��(���֥ͥåȴ������̤���)*/
if (isset($_POST["subnet_netmask"])) {
    /*����������˥��֥ͥåȤ�¸�ߤ��뤫�����å�*/
    $ret = check_subnet_in_session($subnet_data, $_POST["subnet_netmask"]);
    if ($ret == FUNC_FALSE) {
        /*��å����������ꤹ��*/
        $err_msg = sprintf($msgarr['29006'][SCREEN_MSG], $_POST["subnet_netmask"]);
        $log_msg = sprintf($msgarr['29006'][LOG_MSG], $_POST["subnet_netmask"]);
        result_log(OPERATION . ":NG:" . $log_msg);
        /*���֥ͥåȴ������̤˰�ư���� */
        dgp_location("index.php", $err_msg);
        exit (1);
    }

    $subnet_up = $_POST["subnet_netmask"];
    $sn = judge_sn($subnet_up);
    /*���å���󤫤�ɥᥤ��̾���������*/
    if (isset($_SESSION[STR_IP][$sn][$subnet_up]["domain"])) {
        $domainname =  $_SESSION[STR_IP][$sn][$subnet_up]["domain"];
    }
    /*���å���󤫤�ɸ��꡼�����֤��������*/
    if (isset($_SESSION[STR_IP][$sn][$subnet_up]["leasetime"])) {
        $leasetime =  $_SESSION[STR_IP][$sn][$subnet_up]["leasetime"];
    }
    /*���å���󤫤����꡼�����֤��������*/
    if (isset($_SESSION[STR_IP][$sn][$subnet_up]["maxleasetime"])) {
        $maxlease = $_SESSION[STR_IP][$sn][$subnet_up]["maxleasetime"];
    }
    /*���å���󤫤�DNS�����Х��ɥ쥹���������*/
    if (isset($_SESSION[STR_IP][$sn][$subnet_up]["dns"])) {
        $dns = $_SESSION[STR_IP][$sn][$subnet_up]["dns"];
    }
    /*���å���󤫤�Extra���ץ������������*/
    if (isset($_SESSION[STR_IP][$sn][$subnet_up]["option"])) {
        $option = $_SESSION[STR_IP][$sn][$subnet_up]["option"];
    }
}

/*���ܥ���򲡤��줿��*/
if (isset($_POST["back"])) {
    /*���֥ͥåȴ������̤˰�ư����*/
    dgp_location("index.php");
}

/* ��Ͽ�ܥ���򲡤������ */
if (isset($_POST["addition"])) {
    /*���Ϥ�����å�����*/
    $ret = check_update_subnet_data_v6($_POST);
    if ($ret === FUNC_FALSE) {
        /*���Ϥ��ݻ�����*/
        $subnet_up = $_POST["subnet_update"];
        $domainname = $_POST["domainname"];
        $leasetime = $_POST["leasetime"];
        $maxlease = $_POST["maxleasetime"];
        $dns = $_POST["dnsserver"];
        $option = $_POST["exoption"];
        /*���ե�����˽񤭹���*/
        result_log(OPERATION . ":NG:" . $log_msg);
    } else {
        $subnet_up = $_POST["subnet_update"];
        $sn = judge_sn($subnet_up);
        /* Shared-net̾���ߤĤ���ʤ��ä��顢_other�����֤��� */
        if ($sn == "") {
            $sn = "_other";
        }
        /*���å��������ꤹ��*/
        $_SESSION[STR_IP][$sn][$subnet_up]["domain"] = $_POST["domainname"];
        $_SESSION[STR_IP][$sn][$subnet_up]["leasetime"] = $_POST["leasetime"];
        $_SESSION[STR_IP][$sn][$subnet_up]["maxleasetime"] = $_POST["maxleasetime"];
        $_SESSION[STR_IP][$sn][$subnet_up]["dns"] = $_POST["dnsserver"];
        $_SESSION[STR_IP][$sn][$subnet_up]["option"] = $_POST["exoption"];

        /*��å����������ꤹ��*/
        $err_msg = sprintf($msgarr['29007'][SCREEN_MSG], $_POST["subnet_update"]);
        $log_msg = sprintf($msgarr['29007'][LOG_MSG], $_POST["subnet_update"]);
        result_log(OPERATION . ":OK:" . $log_msg);
        /*���֥ͥåȴ������̤˰�ư���� */
        dgp_location("index.php", $err_msg);
        exit (0);
    }
}

/***********************************************************
 * ɽ������
 **********************************************************/

$tag["<<SUBNET>>"] =  escape_html($subnet_up);
$tag["<<DOMAINNAME>>"] =  escape_html($domainname);
$tag["<<LEASETIME>>"] =  escape_html($leasetime);
$tag["<<MAXLEASETIME>>"] = escape_html($maxlease);
$tag["<<DNS>>"] = escape_html($dns);
$tag["<<OPTION>>"] = escape_html($option);

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
