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
 * v6Shared-network��������
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

define("TMPLFILE_LIST", "admin_v6network_sn_list.tmpl");
define("OPERATION", "Adding shared-network_v6");

/***********************************************************
 * �������
 **********************************************************/
$template = TMPLFILE_LIST;

/* ��������� */
$looptag               = array();
$tag["<<TITLE>>"]      = "";
$tag["<<JAVASCRIPT>>"] = "";
$tag["<<SK>>"]         = "";
$tag["<<TOPIC>>"]      = "";
$tag["<<MESSAGE>>"]    = "";
$tag["<<TAB>>"]        = "";
$tag["<<MENU>>"]       = "";
$tag["<<SNLIST>>"]     = "";
$tag["<<SN>>"]         = "";

$duplication = FALSE;

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

/* ��Ͽ�ܥ��󤬲����줿�� */
if (isset($_POST["add"])) {
    $input = $_POST["addname"];
    /* �����ͥ����å� */
    $ret = check_add_shnet($input);
    switch ($ret) {
        case 1:
            /* �����ͥ��顼 */
            $err_msg = sprintf($msgarr['27002'][SCREEN_MSG]);
            $log_msg = $msgarr['27002'][LOG_MSG];
            result_log(OPERATION . ":NG:" . $log_msg, LOG_ERR);
            $tag["<<SN>>"] = escape_html($input);
            break;
        case 2:
            /* ���Ϥʤ����顼 */
            $err_msg = sprintf($msgarr['27001'][SCREEN_MSG]);
            $log_msg = $msgarr['27001'][LOG_MSG];
            result_log(OPERATION . ":NG:" . $log_msg, LOG_ERR);
            break;
        case 0:
            /* ���� */
            /* Shared-network����ʣ�����å� */
            foreach ($_SESSION[STR_IP] as $key => $value){
                if ($key != "_other" && $key != "_common"){
                    /* ��ʣ�����Ĥ��ä���� */
                    if ($key == $input) {
                        $err_msg = sprintf($msgarr['27003'][SCREEN_MSG]);
                        $log_msg = $msgarr['27003'][LOG_MSG];
                        result_log(OPERATION . ":NG:" . $log_msg, LOG_ERR);
                        $duplication = TRUE;
                        $tag["<<SN>>"] = escape_html($input);
                        break;
                    }
                }
            }
            /* �롼�פ�ȴ����Ʊ̾���ʤ����$_SESSION�˥��å� */
            if ($duplication != TRUE) {
                $_SESSION[STR_IP]["$input"] = array();//DEBUG
                //$_SESSION[STR_IP]["$input"] = "";
                $err_msg = sprintf($msgarr['27000'][SCREEN_MSG], $input);
                $log_msg = sprintf($msgarr['27000'][LOG_MSG], $input);
                result_log(OPERATION . ":OK:" . $log_msg, LOG_ERR);
            }
            break;
    }
}

    

/***********************************************************
 * ɽ������
 **********************************************************/

$javascript = "function snSubmit(url, sn) {\n" . 
              "document.data_form.action = url;\n" .
              "document.data_form.sn.value = sn;\n" .
              "document.data_form.submit();\n" .
              "}";

/* $_SESSION����_other,_common�ʳ���ź������� */
$i = 0;
foreach ($_SESSION[STR_IP] as $key => $value){
    if ($key != "_other" && $key != "_common"){
        /* Shared-network������ɽ�������� */
        $looptag[$i]["<<SNLIST>>"] = "<a href=\"#\" onClick=\"snSubmit('mod.php', '$key')\">$key</a>";
        $i ++;
    }
}

/* ���� ���� */
set_tag_common($tag, $javascript);

/* �ڡ����ν��� */
$ret = display($template, $tag, $looptag, "<<LOOPSTART>>", "<<LOOPEND>>");
if ($ret === FALSE) {
    result_log($log_msg, LOG_ERR);
    syserr_display();
    exit(1);
}
?>
