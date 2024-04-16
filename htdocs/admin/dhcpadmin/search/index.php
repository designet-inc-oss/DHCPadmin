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
 * �ߤ��Ф����󸡺�����
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
include_once("lib/dglibpostldapadmin");
include_once("lib/dglibdhcpadmin");

/********************************************************
 * �ƥڡ����������
 ********************************************************/

define("TMPLFILE_LIST",   "admin_search.tmpl");

/*********************************************************
 * check_input_searchdata_v4()
 *
 * �������������ͥ����å�
 *
 * [����]
 *      $data         ������
 *
 * [�֤���]
 *      TRUE          ����
 *      FALSE         �۾�
 **********************************************************/
function check_input_searchdata_v4($data)
{
    global $msgarr;
    global $err_msg;
    global $log_msg;

    // ������(����)�����å�
    $ret = check_input_searchdata($data);
    if ($ret === FALSE) {
        return FALSE;
    }

    // �����ͥ����å�
    if (strlen($data["searchword"]) > 17) {
        $err_msg = $msgarr['35006'][SCREEN_MSG];
        $log_msg = $msgarr['35006'][LOG_MSG];
        return FALSE;
    }

    return TRUE;
}

/*********************************************************
 * get_lease_data()
 *
 * �꡼���ǡ������ɤ߹��ߤ�����
 *
 * [����]
 *      $postdata     ������
 *      $looptag      HTML�����ѥǡ���(�����Ϥ�)
 *
 * [�֤���]
 *      TRUE          ����
 *      FALSE         �۾�
 **********************************************************/
function get_lease_data($postdata, &$looptag)
{
    global $web_conf;
    global $err_msg;
    global $log_msg;
    global $msgarr;

    $count = 0;
    $data = array();
    $tmpdisphtml = "";
    $leasehead = '^lease .* {$'; // �꡼�������ѥǡ����إå�
    $starthead = "starts [0-6]"; // �߽��������ѥإå�
    $endhead   = "ends [0-6]";   // �߽д��¸����ѥإå�
    $stats = "";
    $ends = "";
    $mac = "";
    $ip = "";

    // lease�ե�������ɤ߹��ߥ����å�
    if (is_readable_file($web_conf['dhcpadmin']['dhcpdleasespath']) === FALSE) {
        return FALSE;
    }

    // �����ѥե����ޥåȺ���
    make_search_format($starthead, $endhead, $format_data);

    // lease�ե�������ɤ߹���(������)
    $lease_data_tmp = file($web_conf['dhcpadmin']['dhcpdleasespath']); 
    $lease_data = array_reverse($lease_data_tmp);

    foreach ($lease_data as $line) { 

        // �Ԥ�Ƭ��#�Υ����ȹԤǤ����̵��
        if (substr($line, 0, 1) == "#") {
            continue;
        }

        // ���ԤǤ����̵��
        if (strlen($line) == 0) {
            continue;
        }

        // ɽ��������ȿ���ã�����齪λ
        if ($count == $web_conf['dhcpadmin']['leaseslistnum']) {
            break;
        }

        // ���Ԥ������
        $line = rtrim($line);

        // ��Ƭ�ζ��򡦥��֤���
        $line = trim($line);

        // �߽����θ���
        if (preg_match($format_data["start"], $line, $tmp)) {
            $starts = preg_replace("/$starthead /", "", $tmp[0]);
            $starts = preg_replace("/:[0-9]{1,2}$/", "", $starts);
            $starts = conv_localtime($starts);

        // �߽д��¤θ���
        } elseif (preg_match($format_data["end"], $line, $tmp)) {
            $ends = preg_replace("/$endhead /", "",$tmp[0]);
            $ends = preg_replace("/:[0-9]{1,2}$/", "",$ends);
            $ends = conv_localtime($ends);

        // MAC���ɥ쥹�θ���
        } elseif (preg_match($format_data["mac"], $line, $tmp)) {
            $mac = $tmp[0];

        // lease�����ä���硢ɽ��������¹�
        } elseif (preg_match("/$leasehead/", $line)) {

            // IP���ɥ쥹�θ���
            if (preg_match($format_data["ip"], $line, $tmp)) {
                $ip = $tmp[0];
            }

            // �������˥ޥå����뤫��ǧ
            $ret = check_lease_data($postdata, $starts, $ends, $mac, $ip);
            if ($ret === FALSE) {
                continue;
            }

            // �ͤ�����
            $looptag[$count]["<<LEASETIME>>"]   = $starts;
            $looptag[$count]["<<MACADDR>>"]     = $mac;
            $looptag[$count]["<<IPADDR>>"]      = $ip;
            $looptag[$count]["<<LEASEPERIOD>>"] = $ends;

            // ɽ�������������
            $count++;
        }
    }

    // 1���ɽ�����ܤ��ʤ����
    if ($count == 0) {
        $err_msg = $msgarr['35005'][SCREEN_MSG];
        $log_msg = $msgarr['35005'][LOG_MSG];
    }

    return TRUE;
}

/*********************************************************
 * make_search_format()
 *
 * �����ѥե����ޥåȺ���
 *
 * [����]
 *      $starthead     �߽����إå�
 *      $endhead       �߽д��¥إå�
 *      $format_data   �ե����ޥåȥǡ���(�����Ϥ�)
 *
 * [�֤���]
 *      �ʤ�
 **********************************************************/
function make_search_format($starthead, $endhead, &$format_data)
{
    $preg_date = '[0-9]{4}\/[0-9]{1,2}\/[0-9]{1,2}';
    $preg_time = '[0-9]{1,2}:[0-9]{1,2}:[0-9]{1,2}';

    // �߽����ե����ޥåȺ���
    $format_data["start"] = "/$starthead $preg_date $preg_time/";

    // �߽д��¥ե����ޥåȺ���
    $format_data["end"] = "/$endhead $preg_date $preg_time/";

    // IP���ɥ쥹�Υե����ޥåȺ���
    $format_data["ip"] = '/[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+/';

    // MAC���ɥ쥹�Υե����ޥåȺ���
    $mip = "[0-9a-fA-F][0-9a-fA-F]";
    $format_data["mac"] = sprintf("/%s:%s:%s:%s:%s:%s/", $mip, $mip, $mip,
                                                        $mip, $mip, $mip);
    return TRUE;
}

/*********************************************************
 * check_lease_data()
 *
 * �������Ƥ˥ޥå����뤫������å�
 *
 * [����]
 *      $postdata      ������
 *      $starts        �߽���
 *      $ends          �߽д���
 *      $mac           MAC���ɥ쥹
 *      $ip            IP���ɥ쥹
 *
 * [�֤���]
 *      �ʤ�
 **********************************************************/
function check_lease_data($postdata, $starts, $ends, $mac, $ip)
{
    // �߽��������å�
    $ret = check_lease_time($postdata["ssyear"], $postdata["ssmon"], $postdata["ssday"],
                            $postdata["seyear"], $postdata["semon"], $postdata["seday"],
                            $starts);
    if ($ret === FALSE) {
        return FALSE;
    }

    // �߽д��¥����å�
    $ret = check_lease_time($postdata["esyear"], $postdata["esmon"], $postdata["esday"],
                            $postdata["eeyear"], $postdata["eemon"], $postdata["eeday"],
                            $ends);
    if ($ret === FALSE) {
        return FALSE;
    }

    // MAC���ɥ쥹�ڤ�IP���ɥ쥹�Υ����å�
    $ret = check_lease_ip_mac($postdata["searchword"], $mac, $ip);
    if ($ret === FALSE) {
        return FALSE;
    }

    return TRUE;
}

/*********************************************************
 * check_lease_ip_mac()
 *
 * MAC���ɥ쥹 or IP���ɥ쥹�����å�
 *
 * [����]
 *      $word         ������
 *      $mac          MAC���ɥ쥹
 *      $ip           IP���ɥ쥹
 *
 * [�֤���]
 *      TRUE          ����
 *      FALSE         �۾�
 **********************************************************/
function check_lease_ip_mac($word, $mac, $ip)
{
    // ���դ����ꤵ��Ƥ��ʤ��Ȥ���̵��
    if ($word == "") {
        return TRUE;
    }

    // MAC���ɥ쥹�����å�
    if (preg_match("/". preg_quote($word) . "/", $mac)) {
        return TRUE;
    }

    // IP���ɥ쥹�����å�
    if (preg_match("/". preg_quote($word) . "/", $ip)) {
        return TRUE;
    }

    return FALSE;
}

/***********************************************************
 * �������
 **********************************************************/

$template = TMPLFILE_LIST;

/* ��������� */
$tag["<<TITLE>>"]       = "";
$tag["<<JAVASCRIPT>>"]  = "";
$tag["<<SK>>"]          = "";
$tag["<<TOPIC>>"]       = "";
$tag["<<MESSAGE>>"]     = "";
$tag["<<TAB>>"]         = "";
$tag["<<SSYEAR>>"]      = "";
$tag["<<SSMON>>"]       = "";
$tag["<<SSDAY>>"]       = "";
$tag["<<SEYEAR>>"]      = "";
$tag["<<SEMON>>"]       = "";
$tag["<<SEDAY>>"]       = "";
$tag["<<ESYEAR>>"]      = "";
$tag["<<ESMON>>"]       = "";
$tag["<<ESDAY>>"]       = "";
$tag["<<EEYEAR>>"]      = "";
$tag["<<EEMON>>"]       = "";
$tag["<<EEDAY>>"]       = "";
$tag["<<SEARCHWORD>>"] = "";
$looptag = array();

/* ����ե�����䥿�ִ����ե������ɹ������å����Υ����å� */
$ret = init();
if ($ret === FALSE) {
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

/***********************************************************
 * main����
 **********************************************************/

if (isset($_POST["search"])) {

    /* �����ͥ����å� */
    $ret = check_input_searchdata_v4($_POST);
    if ($ret === FALSE) {
        result_log($log_msg, LOG_ERR);
    } else {
        // ��������
        $ret = get_lease_data($_POST, $looptag);
        if ($ret === FALSE) {
            result_log($log_msg, LOG_ERR);
            syserr_display();
            exit(1);
        }
    }

    /* �ͤ��ݻ� */
    $tag["<<SSYEAR>>"]     = escape_html($_POST["ssyear"]);
    $tag["<<SSMON>>"]      = escape_html($_POST["ssmon"]);
    $tag["<<SSDAY>>"]      = escape_html($_POST["ssday"]);
    $tag["<<SEYEAR>>"]     = escape_html($_POST["seyear"]);
    $tag["<<SEMON>>"]      = escape_html($_POST["semon"]);
    $tag["<<SEDAY>>"]      = escape_html($_POST["seday"]);
    $tag["<<ESYEAR>>"]     = escape_html($_POST["esyear"]);
    $tag["<<ESMON>>"]      = escape_html($_POST["esmon"]);
    $tag["<<ESDAY>>"]      = escape_html($_POST["esday"]);
    $tag["<<EEYEAR>>"]     = escape_html($_POST["eeyear"]);
    $tag["<<EEMON>>"]      = escape_html($_POST["eemon"]);
    $tag["<<EEDAY>>"]      = escape_html($_POST["eeday"]);
    $tag["<<SEARCHWORD>>"] = escape_html($_POST["searchword"]);
}

/***********************************************************
 * ɽ������
 **********************************************************/

/* ���� ���� */
set_tag_common($tag);

/* �ڡ����ν��� */
$ret = display($template, $tag, $looptag, "<<STARTLOOP>>", "<<ENDLOOP>>");
if ($ret === FALSE) {
    result_log($log_msg, LOG_ERR);
    syserr_display();
    exit(1);
}
?>
