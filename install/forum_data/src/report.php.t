<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: report.php.t,v 1.1.1.1 2002/06/17 23:00:09 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

/*#? Post Editor Page */
	include_once "GLOBALS.php";
	{PRE_HTML_PHP}

	$flt = new fud_ip_filter;
	$returnto_d = $returnto;
		
	if ( !is_numeric($msg_id) ) {
		error_dialog('{TEMPLATE: report_err_nosuchmsg_title}', '{TEMPLATE: report_err_nosuchmsg_msg}', $returnto_d, 'FATAL');
		exit();
	}
	
	if ( isset($GLOBALS['HTTP_SERVER_VARS']['REMOTE_ADDR']) && $flt->is_blocked($GLOBALS['HTTP_SERVER_VARS']['REMOTE_ADDR']) ) {
		error_dialog('{TEMPLATE: report_err_cantreport_title}', '{TEMPLATE: report_err_cantreport_msg}', $returnto_d);
		exit();
	}
	unset($flt);
	
	$r = Q("SELECT {SQL_TABLE_PREFIX}thread.forum_id, {SQL_TABLE_PREFIX}msg.*, {SQL_TABLE_PREFIX}users.login FROM {SQL_TABLE_PREFIX}msg LEFT JOIN {SQL_TABLE_PREFIX}users ON {SQL_TABLE_PREFIX}msg.poster_id={SQL_TABLE_PREFIX}users.id LEFT JOIN {SQL_TABLE_PREFIX}thread ON {SQL_TABLE_PREFIX}msg.thread_id={SQL_TABLE_PREFIX}thread.id WHERE {SQL_TABLE_PREFIX}msg.id=".$msg_id);
	if( !DB_COUNT($r) ) { QF($r); invl_inp_err(); }

	$msg = DB_SINGLEOBJ($r);

	if( !is_perms(_uid, $msg->forum_id, 'p_READ') ) {
		std_error('access');		
		exit;
	}

	if( BQ("SELECT id FROM {SQL_TABLE_PREFIX}msg_report WHERE msg_id=".$msg->id." AND user_id="._uid) ) {
		error_dialog('{TEMPLATE: report_already_reported_title}', '{TEMPLATE: report_already_reported_msg}', $returnto);		
		exit();
	}

	if ( !empty($btn_report) ) {
		if( trim($HTTP_POST_VARS['reason']) ) {
			submit_msg_report(_uid, $msg->id, $reason);
			check_return();
			exit();
		}
		else
			$reason_error = '{TEMPLATE: report_empty_report}';	
	}

	{POST_HTML_PHP}
	$user_login = ( !empty($msg->login) ) ? htmlspecialchars($msg->login) : htmlspecialchars($GLOBALS['ANON_NICK']);
	$return_field = create_return();
	{POST_PAGE_PHP_CODE}
?>
{TEMPLATE: REPORT_PAGE}