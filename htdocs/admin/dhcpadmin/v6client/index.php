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
 * v6���饤������������
 *
 * $RCSfile: index.php,v $
 * $Revision: 1.0 $
 * $Date: 2014// $
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

define("TMPLFILE_LIST",   "admin_v6client_search.tmpl");
define("OPERATION_SEARCH", "Search client_v6");
define("DELETE_SEARCH",    "Delete client_v6");
define("ADD_SEARCH",    "Add client_v6");

/*********************************************************
 * set_tag_search_result()
 *
 * �������ɽ�� 
 *
 * [����]
 *      $hosts        �����̤ä���̤����� 
 *
 * [�֤���]
 *      �ʤ� 
 **********************************************************/

function set_tag_search_result($hosts)
{ 

    global $looptag;
    $i = 0;

    foreach ($hosts as $host) {
        $pieces = explode(",", $host);
        $hostname = ltrim($pieces[3], "\"");
        $sub = rtrim($hostname, "\"");

        $looptag[$i]["<<SUBNET>>"] = $pieces[5];
        $encodehost = base64_encode($sub);
        $escapehost = escape_html($sub);
        $looptag[$i]["<<ESCAPEHOST>>"] = $escapehost;
        $looptag[$i]["<<HOST>>"] = "<a href=\"#\" onClick=\"snSubmit('add.php', '$encodehost', '$pieces[5]', '$pieces[1]', '$pieces[2]', '$pieces[4]', '$pieces[6]')\">$escapehost</a>";
        $looptag[$i]["<<DUID>>"] = $pieces[1];
        $looptag[$i]["<<IPV6>>"] = $pieces[2];
        $looptag[$i]["<<CHECK>>"] = $pieces[4];
        if ($pieces[4] == "allow") {
            $looptag[$i]["<<LEASE>>"] = '����';
        } else {
            $looptag[$i]["<<LEASE>>"] = '����';
        }
        $looptag[$i]["<<SN>>"] = $pieces[6];
        $i ++;
    }
}

/*********************************************************
 * print_csv()
 *
 * �������ɽ�� 
 *
 * [����]
 *      $hosts        �����̤ä���̤����� 
 *
 * [�֤���]
 *      �ʤ� 
 **********************************************************/

function print_csv($hosts)
{

    $down = "";

    foreach ($hosts as $host) {
        $pieces = explode(",", $host);
        $hostname = ltrim($pieces[3], "\"");
        $hostname = rtrim($hostname, "\"");

        if ($pieces[4] == "allow") {
            $select = '����';
        } else {
            $select = '����';
        }
        $select = mb_convert_encoding($select, "SJIS", "EUC-JP");
        $down = $pieces[5] . "," . $hostname . "," . $pieces[1] . "," . $pieces[2] . "," . $select . "\n";
        print($down);
    }
}

/*********************************************************
 * search_client()
 *
 * ���饤����Ȥξ�︡�� 
 *
 * [����]
 *      $sn           $_SESSION�ˤ���shared-network 
 *      $subnet       $_SESSION�ˤ��륵�֥ͥå� 
 *      $hoststr      $_SESSION����� 
 *      $post         ���̤����ϤäƤ����� 
 *
 * [�֤���]
 *      $retval       �����̤ä���̤����� 
 **********************************************************/
function search_client($sn, $subnet, $hoststr, $post)
{ 

    $retval = array();
    $hosts = explode("\n", $hoststr);
    foreach ($hosts as $host) {
        /* [host]����Ȥ����ʤ�continue */
        if ($host == "") {
            continue;
        }
        /* ���֥ͥåȤ����뤫 */
        if (isset($post["subnet"])) {
            /* ���֥ͥåȤ�Ʊ���� */
            if ($subnet != $post["subnet"]) {
                continue;
            }
        }

        /* ����ޤǶ��ڤ� */
        $pieces = explode(",", $host);
        $hostname = ltrim($pieces[3], "\"");
        $hostname = rtrim($hostname, "\"");
        $duid = $pieces[1];
        $ip = $pieces[2];
        $select = $pieces[4];

        /* �ۥ���̾�����뤫 */
        if ($post["host"] != "") {
            /* ���פʤ� */
            if ($post["hostsearch"] == "same"){
                if ($post["host"] != $hostname) {
                    continue;
                }
            /* �ޤ�ʤ� */
            } else {
                if (strpos($hostname, $post["host"]) === FALSE) {
                    continue;
                }
            }
        }
        /* DUID���ɥ쥹�����뤫 */
        if ($post["duid"] != "") {
            /* ���פʤ� */
            if ($post["duidsearch"] == "same"){
                $formed_duid = check_macaddr($post["duid"]);
                if ($formed_duid != $duid) {
                    continue;
                }
            /* �ޤ�ʤ� */
            } else {
                if (strpos($duid, strtolower($post["duid"])) === FALSE) {
                    continue;
                }
            }
        }
        /* IP���ɥ쥹�����뤫 */
        if ($post["ipaddr"] != "") {
            if (strpos($ip, $post["ipaddr"]) === FALSE) {
                continue;
            }
        }
        /* IP�߽Ф����뤫 */
        if (isset($post["ipselect"])) {
            if ($post["ipselect"] != "noselect") {
                if ($post["ipselect"] != $select) {
                    continue;
                }
            }
        }
        /* ����̤����������� */
        $host = $host . "," . $subnet . "," . $sn;
        $retval[] = $host;
    }
    return $retval;
}

/*********************************************************
 * delete_client()
 *
 * ���饤����Ȥξ�︡�� 
 *
 * [����]
 *      $sn           $_SESSION�ˤ���shared-network 
 *      $subnet       $_SESSION�ˤ��륵�֥ͥå� 
 *      $hoststr      ���򤵤줿$_SESSION["host"]����� 
 *      $del         ���̤����ϤäƤ����� 
 *
 * [�֤���]
 *      $retval       �����̤ä���̤����� 
 **********************************************************/
function delete_client($sn, $subnet, &$hoststr, $del)
{

    /* [host]�������Ԥ�ʬ�� */
    $hosts = explode("\n", $hoststr);

    /* ����ޤǶ��ڤ� */
    $delpieces = explode(",", $del);

    /* sn��Ʊ���� */
    $new_host = "";
    foreach ($hosts as $host) {
        $pieces = explode(",", $host);
        $not_match_flag = 0;

        /* [host]����Ȥ����ʤ�continue */
        if ($host == "") {
            continue;
        }
        $hostname = ltrim($pieces[3], "\"");
        $hostname = rtrim($hostname, "\"");

        /* �ۥ���̾�����פ��뤫 */
        if ($delpieces[2] != $hostname) {
            $not_match_flag = 1;
        }
        if ($not_match_flag == 0) {
            /* DUID�����פ��뤫 */
            if ($delpieces[3] != $pieces[1]) {
                $not_match_flag = 1;
            }
        }
        if ($not_match_flag == 0) {
            /* IP���ɥ쥹�����뤫 */
            if ($delpieces[4] != $pieces[2]) {
                $not_match_flag = 1;
            }
        }
        if ($not_match_flag == 0) {
            /* IP�߽Ф����뤫 */
            if ($delpieces[5] != $pieces[4]) {
                $not_match_flag = 1;
            }
        }
        /* ���פ��ʤ��ä����ѿ������� */
        if ($not_match_flag == 1){
            $new_host .= $host . "\n";
        }
    }
    /* new_host�ȥ��å����Υۥ��Ȥ�������Ʊ���ʤ饨�顼 */
    if ($_SESSION[STR_IP]["$sn"]["$subnet"]["host"] == $new_host) {
        return FALSE;
    }
    /* �ѿ������������������ */
    if ($new_host != ""){
        $_SESSION[STR_IP]["$sn"]["$subnet"]["host"] = $new_host; 
    } else {
        /* �ѿ����������ʤ���к�� */
        unset($_SESSION[STR_IP]["$sn"]["$subnet"]["host"]);
    }
    $hoststr = $new_host; 
    return TRUE;
}



/***********************************************************
 * �������
 **********************************************************/

$template = TMPLFILE_LIST;
$i = 0;

/* ��������� */
$looptag                 = array();
$tag["<<TITLE>>"]        = "";
$tag["<<JAVASCRIPT>>"]   = "";
$tag["<<SK>>"]           = "";
$tag["<<SN>>"]           = "";
$tag["<<TOPIC>>"]        = "";
$tag["<<MESSAGE>>"]      = "";
$tag["<<TAB>>"]          = "";
$tag["<<MENU>>"]         = "";
$tag["<<SEARCHSUBNET>>"] = "";
$tag["<<SUBNET>>"]       = "";
$tag["<<SEARCHHOST>>"]   = "";
$tag["<<ESCAPEHOST>>"]   = "";
$tag["<<HOST>>"]         = "";
$tag["<<HOST_MATCH>>"]   = "";
$tag["<<SEARCHDUID>>"]    = "";
$tag["<<DUID>>"]          = "";
$tag["<<DUID_MATCH>>"]    = "";
$tag["<<SEARCHIPV6>>"]     = "";
$tag["<<IPV6>>"]           = "";
$tag["<<SEARCHLEASE>>"]  = ""; 
$tag["<<LEASE>>"]        = ""; 
$tag["<<CHECK>>"]        = ""; 

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
    $log_msg = sprintf($msgarr['27005'][LOG_MSG], $_SERVER["REMOTE_ADDR"]);
    result_log($log_msg, LOG_ERR);
    syserr_display();
    exit(1);
}



/***********************************************************
 * main����
 **********************************************************/

/* �����ܥ��󤬲����줿�� */
if (isset($_POST["search"]) || isset($_POST["download"])) {
    $in_host = escape_html($_POST["host"]);
    $select_host = $_POST["hostsearch"];
    $in_duid = escape_html($_POST["duid"]);
    $select_duid = $_POST["duidsearch"];
    $in_ip = escape_html($_POST["ipaddr"]);
    $in_case = $_POST["ipselect"];
    /* �����ͤ����ϥ����å� */
    $ret = check_search_in($in_host, $in_duid, $in_ip);
    switch ($ret) {
    /* �����ͥ��顼�ξ�� */
    /* �ۥ���̾�������ͥ����å� */
    case 1:
        $err_msg = sprintf($msgarr['32001'][SCREEN_MSG]);
        $log_msg = $msgarr['32001'][LOG_MSG];
        result_log(OPERATION_SEARCH . ":NG:" . $log_msg, LOG_ERR);
        break;
    /* DUID�������ͥ����å� */
    case 2:
        $err_msg = sprintf($msgarr['32007'][SCREEN_MSG]);
        $log_msg = $msgarr['32007'][LOG_MSG];
        result_log(OPERATION_SEARCH . ":NG:" . $log_msg, LOG_ERR);
        break;
    /* IP���ɥ쥹�������ͥ����å� */
    case 3:
        $err_msg = sprintf($msgarr['32008'][SCREEN_MSG]);
        $log_msg = $msgarr['32008'][LOG_MSG];
        result_log(OPERATION_SEARCH . ":NG:" . $log_msg, LOG_ERR);
        break;
    /* ����ξ�� */
    case 0:
        /* ���� */
        $search_result_array = array();
        foreach ($_SESSION[STR_IP] as $sn => $value) {
            /* ���֥ͥåȤ򸫤Ĥ��� */
            if (is_array($_SESSION[STR_IP]["$sn"])) {
                foreach ($_SESSION[STR_IP]["$sn"] as $sub => $value) {
                    if (isset($_SESSION[STR_IP]["$sn"]["$sub"]["host"])) {
                        $hostline = $_SESSION[STR_IP]["$sn"]["$sub"]["host"];
                        /* ��︡���δؿ���Ƥ� */
                        $search_result = search_client($sn, $sub, $hostline,
                                                       $_POST);
                        $search_result_array = array_merge($search_result_array,
                                                           $search_result);
                    }
                }
            }
        }
        /* �����ܥ���λ� */
        if (isset($_POST["search"])) {
            /* ��̤���̤�ɽ�� */
            set_tag_search_result($search_result_array);
            client_re_display($in_case, $select_host, $select_duid);
        /* ������̥�������ɥܥ���λ� */
        } else if (isset($_POST["download"])) {
            /* ��̤�ʸ����˥��å� */
            header("Content-Disposition: attachment; filename=\"search.csv\"");
            header("Content-Type: application/octet-stream");
            print_csv($search_result_array);
            exit(0);
        }
    }
/* ���饤�������Ͽ�ܥ��󤬲����줿�� */
} else if (isset($_POST["add_client"])) {
    /* ���֥ͥåȤ����򤵤�Ƥ��뤫 */
    if (empty($_POST["subnet"])) {
        $err_msg = sprintf($msgarr['32006'][SCREEN_MSG]);
        $log_msg = $msgarr['32006'][LOG_MSG];
        result_log(ADD_SEARCH . ":NG:" . $log_msg, LOG_ERR);
    } else {
        /* �������� */
        $array["modsubnet"] = $_POST["subnet"];
        dgp_location_hidden("add.php", $array);
        exit(0);
    }
/* ���饤����Ȱ����Ͽ�ܥ��󤬲����줿�� */
} else if (isset($_POST["upload"])) {
    /* �������� */
    dgp_location("upload.php");
    exit(0);
/* ����ܥ��󤬲����줿�� */
} else if (isset($_POST["delete"])) {
    $in_case = $_POST["ipselect"];
    /* �����å��ܥå����˥����å�������� */
    if (empty($_POST["alldel"]) || $_POST["alldel"] == "on") {
        $err_msg = sprintf($msgarr['32005'][SCREEN_MSG]);
        $log_msg = $msgarr['32005'][LOG_MSG];
        result_log(DELETE_SEARCH . ":NG:" . $log_msg, LOG_ERR);
    } else {
        $alldel = $_POST["alldel"];
        $delete_flag = 0;
        foreach ($alldel as $del) {
            /* �����å����줿�ͤ�,�Ƕ��ڤ� */
            $deletepiece = explode(",", $del);
            if (isset($_SESSION[STR_IP]["$deletepiece[0]"]["$deletepiece[1]"]["host"])) {
                $hostline = $_SESSION[STR_IP]["$deletepiece[0]"]["$deletepiece[1]"]["host"];
                /* ���򤵤줿1�Ԥ��ĺ������ */
                $ret = delete_client($deletepiece[0], $deletepiece[1], $hostline, $del);
                if ($ret == FALSE) {
                    $delete_flag = 1;
                }
            } else {
                $delete_flag = 1;
            }
        }
        if ($delete_flag == 1) {
            $err_msg = sprintf($msgarr['32004'][SCREEN_MSG]);
            $log_msg = $msgarr['32004'][LOG_MSG];
            result_log(DELETE_SEARCH . ":NG:" . $log_msg, LOG_ERR);
        } else {
            $err_msg = sprintf($msgarr['32000'][SCREEN_MSG]);
            $log_msg = $msgarr['32000'][LOG_MSG];
            result_log(DELETE_SEARCH . ":OK:" . $log_msg, LOG_ERR);
        }
    }
}

/***********************************************************
 * ɽ������
 **********************************************************/
/* ���ɽ�� */
/* ��Ͽ����Ƥ��륵�֥ͥåȤΰ�����ɽ�� */
foreach ($_SESSION[STR_IP] as $sn => $value){
    if ($sn != "_common" && is_array($_SESSION[STR_IP]["$sn"])) {
        foreach ($_SESSION[STR_IP]["$sn"] as $key => $value){
            /* ��°�Υ��֥ͥåȤ�ɽ�������� */
            if ((isset($_POST["subnet"]) && $_POST["subnet"] == $key)) {
                $tag["<<SEARCHSUBNET>>"] .= "<option value=\"$key\" selected>$key</option>\n";
            } else {
                $tag["<<SEARCHSUBNET>>"] .= "<option value=\"$key\">$key</option>\n";
            }
        }
    }
}

/* ���ɽ�� */
if (empty($_POST["hostsearch"])) {
    $in_host = "";
    $select_host = "same";
    $in_duid = "";
    $select_duid = "same";
    $in_ip = "";
    $in_case = "noselect";
} else {
/* ��ɽ�� */
    $in_host = escape_html($_POST["host"]);
    $select_host = $_POST["hostsearch"];
    $in_duid = escape_html($_POST["duid"]);
    $select_duid = $_POST["duidsearch"];
    $in_ip = escape_html($_POST["ipaddr"]);
    $in_case = $_POST["ipselect"];
}
$tag["<<SEARCHHOST>>"] = "$in_host";
$tag["<<SEARCHDUID>>"] = "$in_duid";
$tag["<<SEARCHIPV6>>"] = "$in_ip";
/* IP�߽�����κ�ɽ�� */
client_re_display($in_case, $select_host, $select_duid);
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
