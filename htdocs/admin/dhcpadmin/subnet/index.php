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

include_once("../initial");
include_once("lib/dglibldap");
include_once("lib/dglibcommon");
include_once("lib/dglibpage");
include_once("lib/dglibsess");

/********************************************************
 * �ƥڡ����������
 ********************************************************/

define("TMPLFILE_LIST", "admin_network_subnet_list.tmpl");
define("TMPLFILE_ADD",  "admin_network_subnet_add.tmpl");
define("TMPLFILE_RANGE", "admin_network_subnet_range.tmpl");
define("TMPLFILE_RANGE_ADD", "admin_network_subnet_range_add.tmpl");

/***********************************************************
 * �������
 **********************************************************/

$template = TMPLFILE_LIST;

/* ��������� */
$tag["<<TITLE>>"]      = "";
$tag["<<JAVASCRIPT>>"] = "";
$tag["<<SK>>"]         = "";
$tag["<<TOPIC>>"]      = "";
$tag["<<MESSAGE>>"]    = "";
$tag["<<TAB>>"]        = "";
$tag["<<MENU>>"]       = "";

/* ����ե�����䥿�ִ����ե������ɹ������å����Υ����å� */
$ret = init();
if ($ret === FALSE) {
    syserr_display();
    exit(1);
}

if (isset($_POST["add"])) {
    $template = TMPLFILE_ADD;
}

if (isset($_POST["range"])) {
    $template = TMPLFILE_RANGE;
}

if (isset($_POST["range_add"])) {
    $template = TMPLFILE_RANGE_ADD;
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
