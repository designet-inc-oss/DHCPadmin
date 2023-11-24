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
 * ����Ŭ�Ѳ���
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
include_once("lib/dglibpostldapadmin");

/********************************************************
 * �ƥڡ����������
 ********************************************************/

define("TMPLFILE",      "admin_reload.tmpl");
define("RUNNING",       "��ư��");
define("STOPPED",       "�����");

define("OPERATION_RESTART", "Restart");

/***********************************************************
 * �������
 **********************************************************/

$template = TMPLFILE;

/* ��������� */
$tag["<<TITLE>>"]      = "";
$tag["<<JAVASCRIPT>>"] = "";
$tag["<<SK>>"]         = "";
$tag["<<TOPIC>>"]      = "";
$tag["<<MESSAGE>>"]    = "";
$tag["<<TAB>>"]        = "";
$tag["<<STATUS>>"]       = "";


$pathbackup = "";
global $msgarr;

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
/*�Ƶ�ư�ܥ���򲡤��줿��*/
if (isset($_POST["restart"])) {
    /*�Хå����åץե�����Υѥ������*/
    $backup_path = $web_conf["dhcpadmin"]["dhcpdconfpath"] . ".backup";
    $ret = create_back_file($web_conf["dhcpadmin"]["dhcpdconfpath"],
                            $backup_path);
    /*�֤��ͤ�Ƚ�Ǥ���*/
    if ($ret === FUNC_FALSE) {
         $err_msg = sprintf($msgarr['36001'][SCREEN_MSG], $backup_path);
         $log_msg = sprintf($msgarr['36001'][LOG_MSG], $backup_path);
         result_log(OPERATION_RESTART . ":NG:" . $log_msg);
    } else {
        /*pid���������*/
        $pid = getmypid();
        $tmpfile_path = $web_conf["dhcpadmin"]["dhcpdconfpath"]. ".tmp." . $pid;
        /*���å����ξ��󤫤����ե�������������*/
        $ret = create_tmp_file($tmpfile_path);
        /*�֤��ͤ�Ƚ�Ǥ���*/
        if ($ret === FUNC_FALSE) {
             $err_msg = sprintf($msgarr['36002'][SCREEN_MSG], $tmpfile_path);
             $log_msg = sprintf($msgarr['36002'][LOG_MSG], $tmpfile_path);
             result_log(OPERATION_RESTART . ":NG:" . $log_msg);
        } else {
            /*����ե�������񤭤��ޤ�*/
            $ret = overwrite_setting_file($tmpfile_path,
                                      $web_conf["dhcpadmin"]["dhcpdconfpath"]);
            /*�֤��ͤ�Ƚ�Ǥ���*/
            if ($ret === FUNC_FALSE) {
                $err_msg = sprintf($msgarr['36003'][SCREEN_MSG], $backup_path);
                $log_msg = sprintf($msgarr['36003'][LOG_MSG], $backup_path);
                result_log(OPERATION_RESTART . ":NG:" . $log_msg);
            } else {
                /*configtest*/
                $ret = run_command($web_conf["dhcpadmin"]["dhcpdconftestcom"]);
                /*�֤��ͤ�Ƚ�Ǥ���*/
                if ($ret === FUNC_FALSE) {
                    /*�Хå����åץե����뤫��dhcpd.conf�����*/
                    rename($backup_path, $web_conf["dhcpadmin"]["dhcpdconfpath"]);
                    $err_msg = $msgarr['36004'][SCREEN_MSG];
                    $log_msg = $msgarr['36004'][LOG_MSG];
                    result_log(OPERATION_RESTART . ":NG:" . $log_msg);
                    /*dhcpd��Ƶ�ư����*/
                    run_command($web_conf["dhcpadmin"]["dhcpdrestartcom"]);
                } else {
                    /*�Ƶ�ư����*/
                    $ret = run_command($web_conf["dhcpadmin"]["dhcpdrestartcom"]);
                    /*�֤��ͤ�Ƚ�Ǥ���*/
                    if ($ret === FUNC_FALSE) {
                        /*�Хå����åץե����뤫��dhcpd.conf�����*/
                        rename($backup_path,
                             $web_conf["dhcpadmin"]["dhcpdconfpath"]);
                        $err_msg = sprintf($msgarr['36005'][SCREEN_MSG],
                                           $backup_path);
                        $log_msg = sprintf($msgarr['36005'][LOG_MSG],
                                           $backup_path);
                        result_log(OPERATION_RESTART . ":NG:" . $log_msg);
                    } else {
                        $err_msg = sprintf($msgarr['36000'][SCREEN_MSG],
                                           $backup_path);
                        $log_msg = sprintf($msgarr['36000'][LOG_MSG],
                                           $backup_path);
                        result_log(OPERATION_RESTART . ":OK:" . $log_msg);
                    }
                }
            }
        }
    }
}


$ret = check_status($retval, $web_conf["dhcpadmin"]["dhcpdcheckcom"]);
if ($ret === FALSE) {
    /* ����Ŭ�Ѳ��̤��ɽ���������顼��å�������Ф�*/
    $err_msg = sprintf($msgarr['36006'][SCREEN_MSG],
                       $web_conf["dhcpadmin"]["dhcpdcheckcom"]);
} else {
    switch ($retval) {
        case 3:
            $tag["<<STATUS>>"] = STOPPED;
            break;
        case 0:
            $tag["<<STATUS>>"] = RUNNING;
            break;
        /* ���ޥ�ɤΥ��顼 */
        default:
            /* ����Ŭ�Ѳ��̤��ɽ���������顼��å�������Ф�*/
            $err_msg = sprintf($msgarr['36006'][SCREEN_MSG],
                               $web_conf["dhcpadmin"]["dhcpdcheckcom"]);
            $log_msg = sprintf($msgarr['36006'][LOG_MSG],
                               $web_conf["dhcpadmin"]["dhcpdcheckcom"]);
            result_log($log_msg);
            break;
    }
}

/***********************************************************
 * ɽ������
 **********************************************************/

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
