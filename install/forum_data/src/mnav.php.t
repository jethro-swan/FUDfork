<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: mnav.php.t,v 1.3 2003/07/31 00:12:15 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

/*{PRE_HTML_PHP}*/

	if (!isset($_GET['start']) || !($start = (int)$_GET['start'])) {
		$start = 0;
	}
	$forum_limiter = isset($_GET['forum_limiter']) ? $_GET['forum_limiter'] : '';
	$rng = isset($_GET['rng']) ? (float) $_GET['rng'] : 1;
	$unit = isset($_GET['u']) ? (int) $_GET['u'] : 86400;
	$ppg = $usr->posts_ppg ? $usr->posts_ppg : $POSTS_PER_PAGE;

/*{POST_HTML_PHP}*/

	$TITLE_EXTRA = ': {TEMPLATE: mnav_title}';

	ses_update_status($usr->sid, '{TEMPLATE: mnav_update}');

	if ($forum_limiter) {
		if ($forum_limiter[0] != 'c') {
			$qry_lmt = ' AND f.id=' . (int)$forum_limiter . ' ';
		} else {
			$qry_lmt = ' AND c.id=' . (int)substr($forum_limiter, 1) . ' ';
		}
	} else {
		$qry_lmt = '';
	}

	$mnav_time_unit = tmpl_draw_select_opt("60\n3600\n86400\n604800\n2635200", "{TEMPLATE: mnav_minute}\n{TEMPLATE: mnav_hour}\n{TEMPLATE: mnav_day}\n{TEMPLATE: mnav_week}\n{TEMPLATE: mnav_month}", $unit, '', '');

	if (!$rng) {
		$mnav_pager = $rng = ''; $unit = 86400;
		$mnav_data = '{TEMPLATE: mnav_no_range}';
	} else if ($unit <= 0) {
		$mnav_pager = $rng = ''; $unit = 86400;
		$mnav_data = '{TEMPLATE: mnav_invalid_unit}';
	} else if (($mage = round($rng * $unit)) > ($MNAV_MAX_DATE * 86400) && $MNAV_MAX_DATE > 0) {
		$mnav_data = '{TEMPLATE: mnav_invalid_date}';
		$mnav_pager = '';
	} else if (isset($_GET['u'])) {
		$tm = __request_timestamp__ - $mage;
	
		$total = q_singleval('SELECT count(*) FROM {SQL_TABLE_PREFIX}msg m
					INNER JOIN {SQL_TABLE_PREFIX}thread t ON m.thread_id=t.id
					INNER JOIN {SQL_TABLE_PREFIX}forum f ON t.forum_id=f.id
					INNER JOIN {SQL_TABLE_PREFIX}cat c ON f.cat_id=c.id
					INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id='.(_uid ? '2147483647' : '0').' AND g1.resource_id=f.id
					LEFT JOIN {SQL_TABLE_PREFIX}mod mm ON mm.forum_id=f.id AND mm.user_id='._uid.'
					LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='._uid.' AND g2.resource_id=f.id
				WHERE 
					m.post_stamp > '.$tm.' AND m.approved=\'Y\' '.$qry_lmt.'
					'.($GLOBALS['usr']->is_mod != 'A' ? ' AND (mm.id IS NOT NULL OR (CASE WHEN g2.id IS NOT NULL THEN g2.p_READ ELSE g1.p_READ END)=\'Y\')' : ''));
		if (!$total) {
			$mnav_pager = '';
			$mnav_data = '{TEMPLATE: mnav_no_results}';
		} else {
			$c = uq('SELECT
					u.alias, 
					f.name AS forum_name, f.id AS forum_id,
					m.poster_id, m.id, m.thread_id, m.subject, m.poster_id, m.foff, m.length, m.post_stamp, m.file_id, m.icon
					FROM {SQL_TABLE_PREFIX}msg m
					INNER JOIN {SQL_TABLE_PREFIX}thread t ON m.thread_id=t.id
					INNER JOIN {SQL_TABLE_PREFIX}forum f ON t.forum_id=f.id
					INNER JOIN {SQL_TABLE_PREFIX}cat c ON f.cat_id=c.id
					INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id='.(_uid ? '2147483647' : '0').' AND g1.resource_id=f.id
					LEFT JOIN {SQL_TABLE_PREFIX}users u ON m.poster_id=u.id
					LEFT JOIN {SQL_TABLE_PREFIX}mod mm ON mm.forum_id=f.id AND mm.user_id='._uid.'
					LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='._uid.' AND g2.resource_id=f.id
				WHERE 
					m.post_stamp > '.$tm.' AND m.approved=\'Y\' '.$qry_lmt.'
					'.($GLOBALS['usr']->is_mod != 'A' ? ' AND (mm.id IS NOT NULL OR (CASE WHEN g2.id IS NOT NULL THEN g2.p_READ ELSE g1.p_READ END)=\'Y\')' : '').'
					ORDER BY m.thread_id, t.forum_id, m.post_stamp DESC LIMIT '.qry_limit($ppg, $start));

			$oldf = $oldt = 0;
			$mnav_data = '{TEMPLATE: mnav_begin_results}';
			while ($r = db_rowobj($c)) {
				if ($oldf != $r->forum_id) {
					$mnav_data .= '{TEMPLATE: mnav_forum}';
					$oldf = $r->forum_id;
				}
				if ($oldt != $r->thread_id) {
					$mnav_data .= '{TEMPLATE: mnav_thread}';
					$oldt = $r->thread_id;
				}
				$body = read_msg_body($r->foff, $r->length, $r->file_id);
				/* remove stuff in quotes */
				while (($p = strpos($body, '<table border="0" align="center" width="90%" cellpadding="3" cellspacing="1"><tr><td class="SmallText"><b>')) !== FALSE) {
					$e = strpos($body, '<br></td></tr></table>', $p) + strlen('<br></td></tr></table>');
					$body = substr($body, 0, $p) . substr($body, $e);
				}

				$body = strip_tags($body);
				if (strlen($body) > $MNAV_MAX_LEN) {
					$body = substr($body, 0, $MNAV_MAX_LEN) . '...';
				}
				$poster_info = !empty($r->poster_id) ? '{TEMPLATE: mnav_user}' : '{TEMPLATE: mnav_anon}';
				
				$mnav_data .= '{TEMPLATE: mnav_msg}';
			}
			un_register_fps();
			$mnav_data .= '{TEMPLATE: mnav_end_results}';

			/* handle pager if needed */
			if ($total > $ppg) {
				if ($GLOBALS['USE_PATH_INFO'] == 'N') {
					$mnav_pager = tmpl_create_pager($start, $ppg, $total, '{ROOT}?t=mnav&amp;rng='.$rng.'&amp;u='.$unit.'&amp;'._rsid.'&amp;forum_limiter='.$forum_limiter);
				} else {
					$mnav_pager = tmpl_create_pager($start, $ppg, $total, '{ROOT}/ma/'.$rng.'/'.$u.'/', '/'._rsid);
				}
			} else {
				$mnav_pager = '';
			}
			qf($c);
		}
	} else {
		$mnav_pager = $mnav_data = '';
	}
/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: MNAV_PAGE}