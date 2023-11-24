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
include_once("lib/dglibpostldapadmin");
include_once("lib/dglibcommon");
include_once("lib/dglibpage");
include_once("lib/dglibsess");
include_once("lib/dglibdhcpadmin");

/********************************************************
 * �ƥڡ����������
 ********************************************************/

define("TMPLFILE_MOD", "admin_network_sn_mod.tmpl");
define("OPERATION_UP", "Updating shared-network");
define("OPERATION_DEL", "Deleting shared-network");

/***********************************************************
 * �������
 **********************************************************/
$template = TMPLFILE_MOD;

/* ��������� */
$tag["<<TITLE>>"]       = "";
$tag["<<JAVASCRIPT>>"]  = "";
$tag["<<SK>>"]          = "";
$tag["<<TOPIC>>"]       = "";
$tag["<<MESSAGE>>"]     = "";
$tag["<<TAB>>"]         = "";
$tag["<<MENU>>"]        = "";
$tag["<<SNLIST>>"]      = "";
$tag["<<SN>>"]          = "";
$tag["<<OTHERSUBNET>>"] = "";
$tag["<<OLDNAME>>"]     = "";
$tag["<<SUBNET>>"]     = "";

$duplication = FALSE;
$before_sh = FALSE;
$delete_sh = FALSE;
$in_subnet = FALSE;
$ok        = FALSE;

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

/* �����ܥ��󤬲����줿�� */
if (isset($_POST["mod"])) {
    $input   = isset($_POST["networkname"]) ? $_POST["networkname"] : "";
    $left    = isset($_POST["selectleft"])  ? $_POST["selectleft"]  : "";
    $right   = isset($_POST["selectright"]) ? $_POST["selectright"] : "";
    $oldname = isset($_POST["oldname"])     ? $_POST["oldname"]     : "";
    /* �����ͥ����å� */
    $ret = check_add_shnet($input);
    switch ($ret) {
        case 1:
            /* �����ͥ��顼 */
            $err_msg = sprintf($msgarr['28002'][SCREEN_MSG]);
            $log_msg = $msgarr['28002'][LOG_MSG];
            result_log(OPERATION_UP . ":NG:" . $log_msg, LOG_ERR);
            /* ���֥ͥåȤ��ɽ�� */
            re_display($left, $right);
            $tag["<<OLDNAME>>"] = $oldname;
            break;
        case 2:
            /* ���Ϥʤ����顼 */
            $err_msg = sprintf($msgarr['28001'][SCREEN_MSG]);
            $log_msg = $msgarr['28001'][LOG_MSG];
            result_log(OPERATION_UP . ":NG:" . $log_msg, LOG_ERR);
            /* ���֥ͥåȤ��ɽ�� */
            re_display($left, $right);
            $tag["<<OLDNAME>>"] = $oldname;
            break;
        case 0:
            /* ���� */
            /* shared-network̾���ѹ�����Ƥ����� */
            if ($oldname != $input) {
                /* Ʊ̾�����å� */
                $ret = check_same_name($_SESSION[STR_IP], $input);
                if ($ret == TRUE) {
                    /* ��ʣ�����Ĥ��ä���� */
                    $err_msg = sprintf($msgarr['28003'][SCREEN_MSG]);
                    $log_msg = $msgarr['28003'][LOG_MSG];
                    result_log(OPERATION_UP . ":NG:" . $log_msg, LOG_ERR);
                    /* ���֥ͥåȤ��ɽ�� */
                    re_display($left, $right);
                    $tag["<<OLDNAME>>"] = $oldname;
                } else {
                    /* Ʊ̾���ʤ���� */
                    /* $_SESSION��shared-network̾��¸�ߤ��뤫�����å� */
                    $ret = check_same_name($_SESSION[STR_IP], $oldname);
                    if ($ret == FALSE) {
                        /* �ѹ�����shared-network̾���ʤ� */
                        $err_msg = sprintf($msgarr['28006'][SCREEN_MSG], $oldname);
                        $log_msg = sprintf($msgarr['28006'][LOG_MSG], $oldname);
                        result_log(OPERATION_UP . ":NG:" . $log_msg, LOG_ERR);
                        /* ���֥ͥåȤ��ɽ�� */
                        re_display($left, $right);
                        $tag["<<OLDNAME>>"] = $oldname;
                    } else {
                        /* ��SESSION��Ʊ̾������� */
                        /* shared-network̾���ѹ� */
                        $_SESSION[STR_IP]["$input"] = $_SESSION[STR_IP]["$oldname"];
                        unset($_SESSION[STR_IP]["$oldname"]);
                        /* ������å������򥻥åȤ��ư������̤����� */
                        $err_msg = sprintf($msgarr['28000'][SCREEN_MSG], "$oldname->$input");
                        $log_msg = sprintf($msgarr['28000'][LOG_MSG], "$oldname->$input");
                        $ok = TRUE;
                    }
                }
            } else {
                /* Shared-network̾���ѹ�����Ƥ��ʤ���� */
                /* $_SESSION��shared-network̾��¸�ߤ��뤫�����å� */
                $ret = check_same_name($_SESSION[STR_IP], $oldname);
                if ($ret == FALSE) {
                    /* �롼�פ�ȴ���Ƹ�SESSION��Ʊ̾���ʤ���� */
                    /* �ѹ�����shared-network̾���ʤ� */
                    $err_msg = sprintf($msgarr['28006'][SCREEN_MSG], $oldname);
                    $log_msg = sprintf($msgarr['28006'][LOG_MSG], $oldname);
                    result_log(OPERATION_UP . ":NG:" . $log_msg, LOG_ERR);
                    /* ���֥ͥåȤ��ɽ�� */
                    re_display($left, $right);
                    $tag["<<OLDNAME>>"] = $oldname;
                } else {
                    /* ������å������򥻥åȤ��ư������̤����� */
                    $err_msg = sprintf($msgarr['28000'][SCREEN_MSG], "$oldname");
                    $log_msg = sprintf($msgarr['28000'][LOG_MSG], "$oldname");
                    $input = $oldname;
                    $ok = TRUE;
                }
            }
            if ($ok == TRUE) {
                /* ���֥ͥåȤ���������å� */
                if (is_array($left)) {
                    Move_Subnets($left, "_other", $input);
                }
                if (is_array($right)) {
                    Move_Subnets($right, $input, "_other");
                }
                result_log(OPERATION_UP . ":OK:" . $log_msg, LOG_ERR);
                dgp_location("index.php", $err_msg);
                exit(0);
            }
    }
/* ����ܥ��󤬲����줿�� */
} else if (isset($_POST["delete"])) {
    $input   = isset($_POST["networkname"]) ? $_POST["networkname"] : "";
    $left    = isset($_POST["selectleft"])  ? $_POST["selectleft"]  : "";
    $right   = isset($_POST["selectright"]) ? $_POST["selectright"] : "";
    $oldname = isset($_POST["oldname"])     ? $_POST["oldname"]     : "";
    /* $_SESSION��shared-network̾��¸�ߤ��뤫�����å� */
    $ret = check_same_name($_SESSION[STR_IP], $oldname);
    if ($ret == FALSE) {
        /* SESSION��Ʊ̾���ʤ���� */
        /* Shared-network̾���ʤ��ΤǺ�ɽ�� */
        $err_msg = sprintf($msgarr['28006'][SCREEN_MSG], $oldname);
        $log_msg = sprintf($msgarr['28006'][LOG_MSG], $oldname);
        result_log(OPERATION_DEL . ":NG:" . $log_msg, LOG_ERR);
        /* ���֥ͥåȤ��ɽ�� */
        re_display($left, $right);
        $tag["<<OLDNAME>>"] = $oldname;
    } else {
        /* �롼�פ�ȴ����SESSION��Ʊ̾����� */
        /* ���֥ͥåȤ���°���Ƥ��ʤ�����ǧ */
        foreach ($_SESSION[STR_IP]["$oldname"] as $key => $value) {
            /* ��°���Ƥ������ */
            if (isset($key)) {
                $err_msg = sprintf($msgarr['28007'][SCREEN_MSG]);
                $log_msg = sprintf($msgarr['28007'][LOG_MSG]);
                result_log(OPERATION_DEL . ":NG:" . $log_msg, LOG_ERR);
                /* ���֥ͥåȤ��ɽ�� */
                re_display($left, $right);
                $tag["<<OLDNAME>>"] = $oldname;
                $in_subnet = TRUE;
                break;
            }
        }
        /* �롼�פ�ȴ���ƥ��֥ͥåȤ���°���Ƥ��ʤ���к�� */
        if ($in_subnet != TRUE) {
            unset($_SESSION[STR_IP]["$oldname"]);
            /* ������å������򥻥åȤ��ư������̤����� */
            $err_msg = sprintf($msgarr['28004'][SCREEN_MSG], $oldname);
            $log_msg = sprintf($msgarr['28004'][LOG_MSG], $oldname);
            result_log(OPERATION_DEL . ":OK:" . $log_msg, LOG_ERR);
            dgp_location("index.php", $err_msg);
            exit(0);
        }
    }
/* ���ܥ��󤬲����줿��������̤����� */
} else if (isset($_POST["back"])) {
    dgp_location("index.php");
    exit(0);
} else {
    /* ���ɽ�� */
    /* �������̤ǲ����줿Shared-network̾��ɽ�� */
    $input = $_POST["sn"];
    /* hidden��OLDNAME�����˰��������褿Shared-network̾�򤤤�Ƥ��� */
    $tag["<<OLDNAME>>"] = $input;
    /* $_SESSION����������̤ǲ����줿Shared-network�Υ��֥ͥåȤ���� */
    if (is_array($_SESSION[STR_IP]["$input"])) {
        foreach ($_SESSION[STR_IP]["$input"] as $key => $value) {
            /* ��°�Υ��֥ͥåȤ�ɽ�������� */
            $tag["<<SUBNET>>"] .= "<option value=\"$key\">$key</option>\n";
        }
    }
    /* $_SESSION����_other�Υ��֥ͥåȤ���� */
    if (isset($_SESSION[STR_IP]["_other"]) &&
        $_SESSION[STR_IP]["_other"] != "") {
        foreach ($_SESSION[STR_IP]["_other"] as $other_key => $value) {
            /* ̤��°�Υ��֥ͥåȤ�ɽ�������� */
            $tag["<<OTHERSUBNET>>"] .= "<option value=\"$other_key\">$other_key</option>\n";
        }
    }
}

/***********************************************************
 * ɽ������
 **********************************************************/

$tag["<<SN>>"] = escape_html($input);
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
