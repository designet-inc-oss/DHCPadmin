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
 * ���饤����Ȱ����Ͽ����
 *
 * $RCSfile: index.php,v $
 * $Revision: 1.0 $
 * $Date: 2014 $
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

define("TMPLFILE_UPLOAD",   "admin_client_upload.tmpl");
define("UPLOAD_CLIENT",     "Upload client");
define("MAX_SUBNET_LENGTH", "31");


/*********************************************************
 * check_file_duplication()
 *
 * �ե��������Ȥν�ʣ�����å�
 *
 * [����]
 *      $file_data   �ե��������Ȥ����ä�����
 * [�֤���]
 *      0            ����
 *      1            �ۥ���̾���顼
 *      2            MAC���ɥ쥹���顼
 *      3            IP���ɥ쥹���顼
 **********************************************************/
function check_file_duplication($file_data, &$error_line)
{
    $i = 0;
    /* 1�Ԥ��Ĥ��줾��$check��������ֹ��Ĥ�������ľ�� */
    foreach ($file_data as $data) {
        $data = rtrim($data);
        list($subnet, $hostname, $mac, $ip, $select) = explode(",", $data);

        $check[$i]["subnet"] = $subnet;
        $check[$i]["mac"] = $mac;
        $check[$i]["ip"] = $ip;
        $check[$i]["hostname"] = $hostname;
        $i++;
    }
    $count = $i;
    /* �ե�������ǽ�ʣ��̵���������å����� */
    for ($i = 0; $i < $count; $i++) {
        /* �����Ƥ���Ԥμ��ιԤȽ��֤���Ӥ��Ƥ��� */
        for ($j = $i + 1; $j < $count; $j++) {
            /* ���֥ͥåȤ�Ʊ���ξ��Τ߽�ʣ�����å� */
            if ($check[$i]["subnet"] == $check[$j]["subnet"]) {
                if ($check[$i]["hostname"] == $check[$j]["hostname"]) {
                    $error_line = $j;
                    return 1;
                }
                if ($check[$i]["mac"] == $check[$j]["mac"]) {
                    $error_line = $j;
                    return 2;
                }
                if ($check[$i]["ip"] == $check[$j]["ip"]) {
                    $error_line = $j;
                    return 3;
                }
            }
        }
    }
    return 0;
}


/*********************************************************
 * check_update_in()
 *
 * ���饤����Ȱ����Ͽ���������ͥ����å�
 *
 * [����]
 *      $file_data   �ե��������Ȥ����ä�����
 * [�֤���]
 *      0            ����
 *      1            ���֥ͥå�̤����
 *      2            �ۥ���̤̾����
 *      3            MAC���ɥ쥹̤����
 *      4            IP�߽�����̤����
 *      5            ���֥ͥåȥ��顼
 *      6            �ۥ���̾���顼
 *      7            MAC���ɥ쥹���顼
 *      8            IP���ɥ쥹���顼
 *      9            IP�߽����ꥨ�顼
 *     10            ���֥ͥåȤ�¸�ߤ��ʤ�
 *     11            IP�����֥ͥå��ϰϳ�
 **********************************************************/

function check_update_in($file_data, &$line)
{
    foreach ($file_data as $data) {
        $data = rtrim($data);
        /* ����ޤǶ��ڤ� */
        list($subnet, $hostname, $mac, $ip, $select) = explode(",", $data);

        $line++;
        /* ɬ�ܹ��ܥ����å� */
        $must = check_in_must($subnet, $hostname, $mac, $select);
        if ($must != 0) {
            /* ���ϥ��顼 */
            return $must;
        }
        /* ���֥ͥåȤ����Ϥ����顼 */
        $ret = check_subnet($subnet);
        if ($ret == FALSE) {
            return 5;
        }
        /* �ۥ���̾�����Ϥ����顼 */
        $ret = check_hostname($hostname);
        if ($ret == FALSE) {
            return 6;
        }
        /* MAC���ɥ쥹�����Ϥ����顼 */
        $ret = check_add_mac($mac);
        if ($ret == FALSE) {
            return 7;
        }
        /* IP���ɥ쥹�����Ϥ����顼 */
        $ret = check_ip($ip);
        if ($ret == FALSE) {
            return 8;
        }
        /* IP�߽���������Ϥ����顼 */
        $ret = check_select($select);
        if ($ret == FALSE) {
            return 9;
        }
        /* ���å�������Ʊ�����֥ͥåȤ����뤫 */
        $judge = judge_sn($subnet);
        if ($judge == "") {
            return 10;
        }
        /* IP���ϰϤ����顼 */
        if (isset($ip) && $ip != "") {
            $ret = in_range_ipv4($subnet, $ip);
            if ($ret == FALSE) {
                return 11;
            }
        }
    }
    return 0;
}

/*********************************************************
 * check_in_must()
 *
 * ɬ�ܹ��ܤ����Ϥ���Ƥ��뤫�����å�
 *
 * [����]
 *      $subnet      1�Ԥ��ȤΥ��֥ͥå�
 *      $hostname    1�Ԥ��ȤΥۥ���̾
 *      $mac         1�Ԥ��Ȥ�mac���ɥ쥹
 *      $select      1�Ԥ��Ȥ�IP�߽�����
 * [�֤���]
 *      0            ����
 *      1            ���֥ͥåȥ��顼
 *      2            �ۥ���̾���顼
 *      3            MAC���ɥ쥹���顼
 *      4            IP���ɥ쥹���顼
 **********************************************************/
function check_in_must($subnet, $hostname, $mac, $select)
{
    /* ���֥ͥåȤ����Ϥ����뤫 */
    if ($subnet == "") {
        return 1;
    } 
    /* �ۥ���̾�����Ϥ����뤫 */
    if ($hostname == "") {
        return 2;
    } 
    /* MAC���ɥ쥹�����Ϥ����뤫 */
    if ($mac == "") {
        return 3;
    }
    /* IP�߽����꤬���򤵤�Ƥ��뤫 */
    if ($select == "") {
        return 4;
    }
    return 0;
}

/*********************************************************
 * check_subnet()
 *
 * ���֥ͥåȤ������ͥ����å�
 *
 * [����]
 *      $subnet      ���֥ͥåȤ���
 * [�֤���]
 *      TRUE         ����
 *      FALSE        �۾�
 **********************************************************/
function check_subnet($subnet)
{
    /* ʸ���������å� */
    /* subnet���ͤ����뤫Ĵ�٤� */
    $length = strlen($subnet);
    if ($length > MAX_SUBNET_LENGTH) {
        return FALSE;
    }
    /* /�������ʬ���� */
    $piece = explode("/", $subnet);
    if (count($piece) != 2) {
        return FALSE;
    }
    /* /������򤽤줾������å� */
    $ret = check_ip($piece[0]);
    if ($ret == FALSE) {
        return FALSE;
    }
    $ret = check_ip($piece[1]);
    if ($ret == FALSE) {
        return FALSE;
    }
    return TRUE;
}

/*********************************************************
 * check_select()
 *
 * IP�߽���������å� 
 *
 * [����]
 *      $select      IP�߽�����
 * [�֤���]
 *      TRUE         ����
 *      FALSE        �۾�
 **********************************************************/
function check_select($select)
{
    /*���Ϥ��줿�ͤ�allow�⤷����deny��*/
    $select = mb_convert_encoding($select, "EUC-JP", "SJIS");
    if ($select == "����" || $select == "����") {
        return TRUE;
    }
    return FALSE;
}


/*********************************************************
 * add_host_session()
 *
 * ������Ͽ�ؿ� 
 *
 * [����]
 *      $file_data    �ե��������� 
 * [�֤���]
 *      TRUE         ����
 *      FALSE        �۾�
 **********************************************************/
function add_host_session($file_data)
{
    foreach ($file_data as $data) {
        $data = rtrim($data);
        /* data����Ȥ����ʤ�continue */
        if ($data == "") {
            continue;
        }
        /* ����ޤǶ��ڤ� */
        list($subnet, $hostname, $mac, $ip, $select) = explode(",", $data);
        /* MAC���ɥ쥹��2���·���� */
        $mac = check_macaddr($mac);
        /* IP�߽�������Ѵ����� */
        $select = mb_convert_encoding($select, "EUC-JP", "SJIS");
        if ($select == "����") {
            $select = "allow";
        } else {
            $select = "deny";
        }

        /* SESSION�������ʸ������¤٤� */
        $line = $hostname . "," . $mac . "," . $ip . "," . "\"$hostname\"" . "," . $select;
        /* Shared-network��Ĵ�٤� */
        $sn = judge_sn($subnet);
        if ($sn == "") {
            return FALSE;
        }
        if (isset($_SESSION[STR_IP]["$sn"]["$subnet"]["host"])) {
            /* host����Ȥ���ФĤʤ��� */
            $hostline = $_SESSION[STR_IP]["$sn"]["$subnet"]["host"];
            $hostline = $hostline . $line . "\n";
        } else {
            /* host����Ȥʤ�������� */
            $hostline = $line . "\n";
        }
        /* SESSION����Ͽ */
        $_SESSION[STR_IP]["$sn"]["$subnet"]["host"] = $hostline;
    }
    return TRUE;
}
/*********************************************************
 * check_column()
 *
 * �����������å��ؿ� 
 *
 * [����]
 *      $file_data    �ե��������Ȥ����ä�����
 * [�֤���]
 *      $line         ���顼�ι��� 
 **********************************************************/
/*�����������å�*/
function check_column($file_data) 
{
    $line = 0;
    foreach ($file_data as $data) {
        $line++;
        $data = rtrim($data);
        /* ����ޤǶ��ڤ� */
        $column = explode(",", $data);
        /*�����������å�*/
        if (count($column) != 5) {
            /* ���顼�ιԿ����֤� */
            return $line;
        }
    }
    /* ���������0���֤� */
    return 0;
}

/***********************************************************
 * �������
 **********************************************************/

$template = TMPLFILE_UPLOAD;

/* ��������� */
$tag["<<TITLE>>"]        = "";
$tag["<<JAVASCRIPT>>"]   = "";
$tag["<<SK>>"]           = "";
$tag["<<SN>>"]           = "";
$tag["<<TOPIC>>"]        = "";
$tag["<<MESSAGE>>"]      = "";
$tag["<<TAB>>"]          = "";
$tag["<<MENU>>"]         = "";
$newhostline             = "";
$addhostline             = "";

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
    $log_msg = sprintf($msgarr['27005'][LOG_MSG], $_SERVER["REMOTE_ADDR"]);
    result_log($log_msg, LOG_ERR);
    syserr_display();
    exit(1);
}

/***********************************************************
 * main����
 **********************************************************/

if (isset($_POST["csvupload"])) {
    /* $_FILES�ѿ��� tmp_name �˥����о�Υե�����̾������ */
    $csv_file = $_FILES["csv_upload"]["tmp_name"];

    /* UPLOAD���줿�ե����뤫�����å��򤹤� */
    if (is_uploaded_file($csv_file) === FALSE) {
        $err_msg = sprintf($msgarr['34008'][SCREEN_MSG]);
        $log_msg = $msgarr['34008'][LOG_MSG];
        result_log(UPLOAD_CLIENT . ":NG:" . $log_msg, LOG_ERR);
    } else {
        $file_data = file($csv_file);
        /* �ե��������Ȥ����ʤ饨�顼 */
        if (empty($file_data)) {
            $err_msg = sprintf($msgarr['34013'][SCREEN_MSG]);
            $log_msg = $msgarr['34013'][LOG_MSG];
            result_log(UPLOAD_CLIENT . ":NG:" . $log_msg, LOG_ERR);
        } else {
            /*�����������å�*/
            $line = check_column($file_data);
            if ($line != 0) {
                /* ������������ */
                $err_msg = sprintf($msgarr['34001'][SCREEN_MSG], $line);
                $log_msg = sprintf($msgarr['34001'][LOG_MSG], $line);
                result_log(UPLOAD_CLIENT . ":NG:" . $log_msg, LOG_ERR);
            } else {
                /*���ϥ����å�*/
                $line = 0;
                $ret = check_update_in($file_data, $line);
                switch ($ret) {
                case 1:
                    /* ���֥ͥåȤ����Ϥ��ʤ� */
                    $err_msg = sprintf($msgarr['34002'][SCREEN_MSG], $line);
                    $log_msg = sprintf($msgarr['34002'][LOG_MSG], $line);
                    result_log(UPLOAD_CLIENT . ":NG:" . $log_msg, LOG_ERR);
                    break;
                case 2:
                    /* �ۥ���̾�����Ϥ��ʤ� */
                    $err_msg = sprintf($msgarr['34003'][SCREEN_MSG], $line);
                    $log_msg = sprintf($msgarr['34003'][LOG_MSG], $line);
                    result_log(UPLOAD_CLIENT . ":NG:" . $log_msg, LOG_ERR);
                    break;
                case 3:
                /* MAC���ɥ쥹�����Ϥ��ʤ� */
                    $err_msg = sprintf($msgarr['34005'][SCREEN_MSG], $line);
                    $log_msg = sprintf($msgarr['34005'][LOG_MSG], $line);
                    result_log(UPLOAD_CLIENT . ":NG:" . $log_msg, LOG_ERR);
                    break;
                case 4:
                /* IP�߽����꤬���򤵤�Ƥ��뤫 */
                    $err_msg = sprintf($msgarr['34010'][SCREEN_MSG], $line);
                    $log_msg = sprintf($msgarr['34010'][LOG_MSG], $line);
                    result_log(UPLOAD_CLIENT . ":NG:" . $log_msg, LOG_ERR);
                    break;
                /* ���֥ͥåȤ����ϥ����å����顼 */
                case 5:
                    $err_msg = sprintf($msgarr['34009'][SCREEN_MSG], $line);
                    $log_msg = sprintf($msgarr['34009'][LOG_MSG], $line);
                    result_log(UPLOAD_CLIENT . ":NG:" . $log_msg, LOG_ERR);
                    break;
                /* �ۥ���̾�����ϥ����å����顼 */
                case 6:
                    $err_msg = sprintf($msgarr['34004'][SCREEN_MSG], $line);
                    $log_msg = sprintf($msgarr['34004'][LOG_MSG], $line);
                    result_log(UPLOAD_CLIENT . ":NG:" . $log_msg, LOG_ERR);
                    break;
                /* MAC���ɥ쥹�����ϥ����å����顼 */
                case 7:
                    $err_msg = sprintf($msgarr['34006'][SCREEN_MSG], $line);
                    $log_msg = sprintf($msgarr['34006'][LOG_MSG], $line);
                    result_log(UPLOAD_CLIENT . ":NG:" . $log_msg, LOG_ERR);
                    break;
                /* IP���ɥ쥹�����ϥ����å����顼 */
                case 8:
                    $err_msg = sprintf($msgarr['34007'][SCREEN_MSG], $line);
                    $log_msg = sprintf($msgarr['34007'][LOG_MSG], $line);
                    result_log(UPLOAD_CLIENT . ":NG:" . $log_msg, LOG_ERR);
                    break;
                /* IP�߽���������ϥ����å����顼 */
                case 9:
                    $err_msg = sprintf($msgarr['34012'][SCREEN_MSG], $line);
                    $log_msg = sprintf($msgarr['34012'][LOG_MSG], $line);
                    result_log(UPLOAD_CLIENT . ":NG:" . $log_msg, LOG_ERR);
                    break;
                /* ���֥ͥå�¸�ߥ��顼 */
                case 10:
                    $err_msg = sprintf($msgarr['34011'][SCREEN_MSG], $line);
                    $log_msg = sprintf($msgarr['34011'][LOG_MSG], $line);
                    result_log(UPLOAD_CLIENT . ":NG:" . $log_msg, LOG_ERR);
                    break;
                /* IP���ɥ쥹���ϰϥ��顼 */
                case 11:
                    $err_msg = sprintf($msgarr['33020'][SCREEN_MSG], $line);
                    $log_msg = sprintf($msgarr['33020'][LOG_MSG], $line);
                    result_log(UPLOAD_CLIENT . ":NG:" . $log_msg, LOG_ERR);
                    break;
                /* ����ξ�� */
                case 0:
                    /* �ե��������Ƚ�ʣ�����å� */
                    $ret = check_file_duplication($file_data, $line);
                    switch ($ret) {
                    /* �ۥ���̾��ʣ */
                    case 1:
                        $err_msg = sprintf($msgarr['34018'][SCREEN_MSG], $line + 1);
                        $log_msg = sprintf($msgarr['34018'][LOG_MSG], $line + 1);
                        result_log(UPLOAD_CLIENT . ":NG:" . $log_msg, LOG_ERR);
                        break;
                    /* MAC���ɥ쥹��ʣ */
                    case 2:
                        $err_msg = sprintf($msgarr['34019'][SCREEN_MSG], $line + 1);
                        $log_msg = sprintf($msgarr['34019'][LOG_MSG], $line + 1);
                        result_log(UPLOAD_CLIENT . ":NG:" . $log_msg, LOG_ERR);
                        break;
                    /* IP���ɥ쥹��ʣ */
                    case 3:
                        $err_msg = sprintf($msgarr['34020'][SCREEN_MSG], $line + 1);
                        $log_msg = sprintf($msgarr['34020'][LOG_MSG], $line + 1);
                        result_log(UPLOAD_CLIENT . ":NG:" . $log_msg, LOG_ERR);
                        break;
                    /* ����ξ�� */
                    case 0:
                        /* ��ʣ�����å� */
                        $line = 0;
                        $dup_flag = 0;
                        foreach ($file_data as $data) {
                            $line++;
                            $ret = check_duplication_data($data);
                            switch ($ret) {
                            /* �ۥ���̾��ʣ */
                            case 1:
                                $err_msg = sprintf($msgarr['34015'][SCREEN_MSG], $line);
                                $log_msg = sprintf($msgarr['34015'][LOG_MSG], $line);
                                result_log(UPLOAD_CLIENT . ":NG:" . $log_msg, LOG_ERR);
                                $dup_flag = 1;
                                break 2;
                            /* MAC���ɥ쥹��ʣ */
                            case 2:
                                $err_msg = sprintf($msgarr['34016'][SCREEN_MSG], $line);
                                $log_msg = sprintf($msgarr['34016'][LOG_MSG], $line);
                                result_log(UPLOAD_CLIENT . ":NG:" . $log_msg, LOG_ERR);
                                $dup_flag = 1;
                                break 2;
                            /* IP���ɥ쥹��ʣ */
                            case 3:
                                $err_msg = sprintf($msgarr['34017'][SCREEN_MSG], $line);
                                $log_msg = sprintf($msgarr['34017'][LOG_MSG], $line);
                                result_log(UPLOAD_CLIENT . ":NG:" . $log_msg, LOG_ERR);
                                $dup_flag = 1;
                                break 2;
                            /* Shared-network��¸�ߤ��ʤ� */
                            case 4:
                                $err_msg = sprintf($msgarr['34022'][SCREEN_MSG], $line);
                                $log_msg = sprintf($msgarr['34022'][LOG_MSG], $line);
                                result_log(UPLOAD_CLIENT . ":NG:" . $log_msg, LOG_ERR);
                                $dup_flag = 1;
                                break 2;
                            /* ����ξ�� */
                            case 0:
                                break;
                            }
                        }
                        /* ��������ξ�� */
                        if ($dup_flag == 0) {
                            /* ��Ͽ���� */
                            $ret = add_host_session($file_data);
                            if ($ret == FALSE) {
                                /* Shared-network��¸�ߤ��ʤ� */
                                $err_msg = sprintf($msgarr['34022'][SCREEN_MSG], $line);
                                $log_msg = sprintf($msgarr['34022'][LOG_MSG], $line);
                                result_log(UPLOAD_CLIENT . ":NG:" . $log_msg, LOG_ERR);
                            } else {
                                /* ��Ͽ���� */
                                $err_msg = sprintf($msgarr['34021'][SCREEN_MSG], $line);
                                $log_msg = sprintf($msgarr['34021'][LOG_MSG], $line);
                                result_log(UPLOAD_CLIENT . ":OK:" . $log_msg, LOG_ERR);
                            }
                        }
                    }
                }
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
