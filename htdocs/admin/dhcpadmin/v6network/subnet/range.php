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
 * IPv6 �ϰ��������
 *
 * $RCSfile: range.php $
 * $Revision: 1.0 $
 * $Date: 2014/07/16 $
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

define("TMPLFILE_RANGE", "admin_v6network_subnet_range.tmpl");
define("OPERATION_DEL", "Deleting range_v6");
define("OPERATION_UP", "Updating range_v6");

/***********************************************************
 * �������
 **********************************************************/

$template = TMPLFILE_RANGE;

/* ��������� */
$tag["<<TITLE>>"]      = "";
$tag["<<JAVASCRIPT>>"] = "";
$tag["<<SK>>"]         = "";
$tag["<<TOPIC>>"]      = "";
$tag["<<MESSAGE>>"]    = "";
$tag["<<TAB>>"]        = "";
$tag["<<MENU>>"]       = "";

$tag["<<SUBNET>>"]     = "";
$tag["<<RANGELIST>>"]    = "";

/* �ѿ��ν���� */
$subnet_data = array();
$range_data = array();
$range_update = "";
$hiden_data = array();
$count_subnet = 0;
$sn = "";
$subnet = "";

/* ����ե�����䥿�ִ����ե������ɹ������å����Υ����å� */
$ret = init();
if ($ret === FALSE) {
    syserr_display();
    exit(1);
}

/* dhcp.conf��ʬ�Ϥ�Ԥ��ؿ���ƤӽФ� */
$ret = analyze_dhcpd_conf($web_conf["dhcpadmin"]["dhcpd6confpath"], "IPv6");
if ($ret === FALSE) {
    $err_msg = $msgarr['27007'][SCREEN_MSG];
    $log_msg = $msgarr['27007'][LOG_MSG];
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

/* ������Ͽ����Ƥ������ƤΥ��֥ͥåȤ�������� */
$subnet_data = get_all_subnets();

/* ���֥ͥåȤ�¸�ߤ��ʤ���� */
$count_subnet = count($subnet_data);
if ($count_subnet == 0) {
    $err_msg = sprintf($msgarr['29006'][SCREEN_MSG], $_POST["subnetlist"]);
    $log_msg = sprintf($msgarr['29006'][LOG_MSG], $_POST["subnetlist"]);
    result_log(OPERATION_UP . ":NG:" . $log_msg);
    /* �������̤˰�ư���� */
    dgp_location("index.php", $err_msg);
    exit (1);
}

/***********************************************************
 * main����
 **********************************************************/

/* ���֥ͥåȤ�������� */
if (isset($_POST["subnet_netmask"])) {
    /* ���򤷤����֥ͥåȤ�������� */
    $subnet = $_POST["subnet_netmask"];
} else {
    /* ���֥ͥåȰ������̤˰�ư���� */
    dgp_location("index.php");
    exit (1);
}


/* �ϰ������Խ����̤����å��������ĥ����Ϥ� */
if (isset($_POST["msg_add_range"])) {
    $err_msg = $_POST["msg_add_range"];
}

/* �ϰϤ��������ؿ���ƤӽФ� */
$ret = get_list_range($range_data, $sn, $subnet);
/* �֤��ͤ�Ƚ�Ǥ��� */
if ($ret === FUNC_FALSE) {
    $err_msg = sprintf($msgarr['29006'][SCREEN_MSG], $_POST["subnetlist"]);
    $log_msg = sprintf($msgarr['29006'][LOG_MSG], $_POST["subnetlist"]);
    result_log(OPERATION_UP . ":NG:" . $log_msg);
    /* �������̤˰�ư���� */
    dgp_location("index.php", $err_msg);
    exit (1);
}

/* ���ܥ���򲡤��줿�� */
if (isset($_POST["back"])) {
    /* ���֥ͥåȴ������̤˰�ư���� */
    dgp_location("index.php");
    exit (1); 
/* �����ܥ���򲡤��줿���ν��� */
} else if (isset($_POST["modify"])) {
    /* �����оݤ����򤵤줿���ɤ�����Ƚ�Ǥ��� */
    if (!isset($_POST["rangelist"])) {
        /* ��å����������ꤹ�� */
        $err_msg = $msgarr['30001'][SCREEN_MSG];
        /* ���˥�å����������ꤹ�� */
        $log_msg = $msgarr['30001'][LOG_MSG];
        result_log(OPERATION_UP . ":NG:" . $log_msg);
    /* �����оݤ����򤵤�Ƥ������ */
    } else {
        $ret =  check_range_in_session($range_data, $_POST["rangelist"]);
        if ($ret === FUNC_FALSE) {
            $err_msg = sprintf($msgarr['30002'][SCREEN_MSG], $_POST["rangelist"]);
            $log_msg = sprintf($msgarr['30002'][LOG_MSG], $_POST["rangelist"]);
            result_log(OPERATION_UP . ":NG:" . $log_msg);
        } else {
            /* �Ϥ��ͤ����ꤹ�� */
            $hidden_data["type"] = "2"; 
            $hidden_data["sn"] = $sn; 
            $hidden_data["subnet_netmask"] = $subnet;
            $hidden_data["range"] = $_POST["rangelist"];
            /* �Խ����̤˰�ư���� */
            dgp_location_hidden("range_mod.php", $hidden_data);
            exit (0);
        }
    }
/* �ɲåܥ���򲡤��줿�� */
} else if (isset($_POST["range_add"])) {
    /* �Ϥ��ͤ����ꤹ�� */
    $hidden_data["sn"] = $sn; 
    $hidden_data["type"] = "1"; 
    $hidden_data["subnet_netmask"] = $subnet;
    /* �Խ����̤˰�ư���� */
    dgp_location_hidden("range_mod.php", $hidden_data);
    exit (1);
/* ����ܥ���򲡤��줿�� */
} else if (isset($_POST["delete"])) {
    /* ����оݤ����򤫤ɤ�����Ƚ�Ǥ��� */
    if (!isset($_POST["rangelist"])) {
        /* ��å����������ꤹ�� */
        $err_msg = $msgarr['30001'][SCREEN_MSG];
        /* ���˥�å����������ꤹ�� */
        $log_msg = $msgarr['30001'][LOG_MSG];
        result_log(OPERATION_DEL . ":NG:" . $log_msg);
    } else {
        $ret = TRUE;
        if (isset($_SESSION[STR_IP][$sn][$subnet]["host"])) {
           $ret = check_delete_range($_SESSION[STR_IP][$sn][$subnet]["host"], $_POST["rangelist"]);
        }
        if ($ret === FALSE) {
            /*��å����������ꤹ��*/
            $err_msg = sprintf($msgarr['33021'][SCREEN_MSG], $subnet);
            /*���˥�å����������ꤹ��*/
            $log_msg = sprintf($msgarr['33021'][LOG_MSG], $subnet);
            result_log(OPERATION_DEL . ":NG:" . $log_msg);
        } else {
            $ret =  check_range_in_session($range_data, $_POST["rangelist"]);
            if ($ret === FUNC_FALSE) {
                $err_msg = sprintf($msgarr['30002'][SCREEN_MSG],
                               $_POST["rangelist"]);
                $log_msg = sprintf($msgarr['30002'][LOG_MSG], $_POST["rangelist"]);
                result_log(OPERATION_DEL . ":NG:" . $log_msg);
            } else {
                /* �����unset���� */
                $key = array_search($_POST["rangelist"] ,$range_data);
                if ($key !== FALSE) {
                    unset($range_data[$key]);
                    /* ���å������ϰϤ��ѹ�����ؿ���ƤӽФ� */
                    update_range_session($range_data, $sn, $subnet);
                    /* ��å����������ꤹ�� */
                    $err_msg = sprintf($msgarr['30000'][SCREEN_MSG],
                                   $_POST["rangelist"]);
                    /* ���˥�å����������ꤹ�� */
                    $log_msg = sprintf($msgarr['30000'][LOG_MSG],
                                   $_POST["rangelist"]);
                    result_log(OPERATION_DEL . ":OK:" . $log_msg);
                }
            }
        }
    }
}

/***********************************************************
 * ɽ������
 **********************************************************/

/* ���ƤΥ��֥ͥåȤ�ɽ������ */
$tag["<<RANGELIST>>"] = set_range_list($range_data);
$tag["<<SUBNET>>"] =  escape_html($subnet);

/* �����򥻥å� */
set_tag_common($tag);

/* �ڡ����ν��� */
$ret = display($template, $tag, array(), "", "");
if ($ret === FALSE) {
    result_log($log_msg, LOG_ERR);
    syserr_display();
    exit(1);
}
?>
