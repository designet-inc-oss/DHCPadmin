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
 * Shared-network��������
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

define("TMPLFILE_MOD", "admin_network_subnet_range_add.tmpl");
define("OPERATION", "Add range");
define("FLG_ADD", "1");
define("FLG_UPDATE", "2");

/***********************************************************
 * �������
 **********************************************************/

$template = TMPLFILE_MOD;

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

/*�ѿ��ν����*/
$subnet_data = array();
$range_data = array();
$range_update = "";
$sn = "";
$subnet = "";
$oldrange = "";

$newrangestart = "";
$newrangeend = "";
$type = "";

/* ����ե�����䥿�ִ����ե������ɹ������å����Υ����å� */
$ret = init();
if ($ret === FALSE) {
    syserr_display();
    exit(1);
}

/*dhcp.conf��ʬ�Ϥ�Ԥ��ؿ���ƤӽФ�*/
$ret = analyze_dhcpd_conf($web_conf["dhcpadmin"]["dhcpdconfpath"], "IPv4");
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
    $log_msg = $msgarr['27005'][LOG_MSG];
    result_log($log_msg, LOG_ERR);
    syserr_display();
    exit(1);
}

/***********************************************************
 * main����
 **********************************************************/

/*�ܥ���μ���򲡤���*/
if (isset($_POST["type"])) {
    if ($_POST["type"] == FLG_UPDATE) {
        /*���򤷤����֥ͥåȤ�������*/
        if (isset($_POST["subnet_netmask"]) && isset($_POST["sn"]) && isset($_POST["range"])) {
            $subnet = $_POST["subnet_netmask"];
            $sn = $_POST["sn"];
            $oldrange = $_POST["range"];
            /*�ϰϤ�ʬ�Ϥ���*/
            $exp_range = explode(",", $oldrange);
            /*�ϰϤ��ͤ����ꤹ��*/
            $newrangestart = $exp_range[0];
            $newrangeend = $exp_range[1];
            /*�ե饰�����ꤹ��*/
            $type = FLG_UPDATE;
        }
    } else if ($_POST["type"] == FLG_ADD) {
        if (isset($_POST["sn"]) && isset($_POST["subnet_netmask"])) {
            $sn = $_POST["sn"];
            $subnet = $_POST["subnet_netmask"];
            $type = FLG_ADD;
            /*�ե饰�����ꤹ��*/
        }
    } 
}

/*�۾�ξ��*/ 
if ($type == "") {
    /*�ϰ�������̤˰�ư����*/
    dgp_location("index.php");
    exit (1);
}


/*���ܥ���򲡤��줿��*/
if (isset($_POST["back"])) {
    /*�ϰ�������̤˰�ư����*/
    $hidden_data["subnet_netmask"] = $subnet;
    /*�Խ����̤˰�ư����*/
    dgp_location_hidden("range.php", $hidden_data);
    exit (0); 
/*��Ͽ�ܥ���򲡤��줿��*/
} else if (isset($_POST["rangemod"])) {
    /*�ϰ�������̤��ɲåܥ���򲡤�*/
    if ($type == FLG_UPDATE) { 
        /*�ϰ�(��)�����Ϥ�����*/
        if (isset($_POST["startrange"])) {
            $newrangestart = $_POST["startrange"];
         }
        /*�ϰ�(��)�����Ϥ�����*/
        if (isset($_POST["endrange"])) {
            $newrangeend = $_POST["endrange"];
        }

        /*���Ϥ�����å�����ؿ���ƤӽФ�*/
        $ret = check_add_range($newrangestart, $newrangeend);
        if ($ret === FUNC_TRUE) {
            /*��ʣ������å�����*/
            $ret = check_duplicate_range($sn, $subnet, $newrangestart, $newrangeend, $oldrange);
            if ($ret === FUNC_TRUE) {
                $ret =  mod_range_session($sn, $subnet, $newrangestart, $newrangeend, $oldrange);
                if ($ret === FUNC_TRUE) {
                    $msg = sprintf($msgarr['31000'][SCREEN_MSG], $oldrange,
                                   $newrangestart . "," . $newrangeend);
                    $log = sprintf($msgarr['31000'][LOG_MSG], $oldrange,
                                   $newrangestart . "," . $newrangeend);
                    result_log(OPERATION . ":OK:" . $log);
                    /*�ϰ�������̤˰�ư����*/
                    $hidden_data["subnet_netmask"] = $subnet;
                    $hidden_data["msg_add_range"] = $msg;
                    /*�Խ����̤˰�ư����*/
                    dgp_location_hidden("range.php", $hidden_data);
                    exit (0);
                } else {
                    result_log(OPERATION . ":NG:" . $log_msg);
                    $hidden_data["subnet_netmask"] = $subnet;
                    $hidden_data["msg_add_range"] = $err_msg;
                    /*�ϰ�������̤˰�ư����*/
                    dgp_location_hidden("range.php", $hidden_data);
 
                }
            } else {
                result_log(OPERATION . ":NG:" . $log_msg);
            }
        } else {
            result_log(OPERATION . ":NG:" . $log_msg);
        }
    } else if ($type == FLG_ADD){
        /*�ϰ�(��)�����Ϥ�����*/
        if (isset($_POST["startrange"])) {
            $newrangestart = $_POST["startrange"];
        }
        /*�ϰ�(��)�����Ϥ�����*/
        if (isset($_POST["endrange"])) {
            $newrangeend = $_POST["endrange"];
        }

        /*���Ϥ�����å�����ؿ���ƤӽФ�*/
        $ret = check_add_range($newrangestart, $newrangeend);
        if ($ret === FUNC_TRUE) {
            /*��ʣ������å�����ؿ���ƤӽФ�*/
            $ret = check_duplicate_range($sn, $subnet, $newrangestart, $newrangeend);
            if ($ret === FUNC_TRUE) {
                $ret =  add_range_session($sn, $subnet, $newrangestart, $newrangeend);
                if ($ret === FUNC_TRUE) {
                    $msg = sprintf($msgarr['31009'][SCREEN_MSG], $newrangestart . "," . $newrangeend);
                    $log = sprintf($msgarr['31009'][LOG_MSG], $newrangestart . "," . $newrangeend);
                    result_log(OPERATION . ":OK:" . $log);
                    /*�ϰ�������̤˰�ư����*/
                    $hidden_data["subnet_netmask"] = $subnet;
                    $hidden_data["msg_add_range"] = $msg;
                    /*�Խ����̤˰�ư����*/
                    dgp_location_hidden("range.php", $hidden_data);
                    exit (0);
                } else {
                    result_log(OPERATION . ":NG:" . $log_msg);
                }
            } else {
                result_log(OPERATION . ":NG:" . $log_msg);
            }
        } else {
            result_log(OPERATION . ":NG:" . $log_msg);
        }
    }   
}
 
/***********************************************************
 * ɽ������
 **********************************************************/

/*���ƥ��֥ͥåȤ�ɽ������*/
$tag["<<RANGESTART>>"] =  escape_html($newrangestart);
$tag["<<RANGEEND>>"] =  escape_html($newrangeend);

$tag["<<SN>>"] =  escape_html($sn);
$tag["<<SUBNET>>"] =  escape_html($subnet);
$tag["<<TYPE>>"] =  $type;
$tag["<<RANGE>>"] =  $oldrange;

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
