<?php
/* ���ظ ���� )�)����  	
 * First 20 bytes of linux 2.4.18, so various windows utils think
 * this is a binary file and don't apply CR/LF logic
 */

/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: oldfrm_upgrade.php,v 1.1.1.1 2002/06/17 23:00:09 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	if( !isset($HTTP_SERVER_VARS['PATH_TRANSLATED']) && isset($HTTP_SERVER_VARS['SCRIPT_FILENAME']) ) 
		$HTTP_SERVER_VARS['PATH_TRANSLATED'] = $GLOBALS['HTTP_SERVER_VARS']['PATH_TRANSLATED'] = $HTTP_SERVER_VARS['SCRIPT_FILENAME'];

	$st = stat(basename($HTTP_SERVER_VARS['PATH_TRANSLATED']));
	$myuid = isset($st['uid']) ? $st['uid'] : $st[4];

	if( ini_get("safe_mode") && getmyuid() != $myuid && $GLOBALS['HTTP_SERVER_VARS']['PATH_TRANSLATED'][0] == '/' && basename($HTTP_SERVER_VARS['PATH_TRANSLATED']) != 'oldfrm_upgrade_safe.php' ) {
		copy("oldfrm_upgrade.php", "oldfrm_upgrade_safe.php");
		header("Location: oldfrm_upgrade_safe.php");
		exit;
	}

	if( !ini_get("track_errors") ) ini_set("track_errors", 1);
	if( !ini_get("display_errors") ) ini_set("display_errors", 1);
	
	error_reporting(E_ALL & ~E_NOTICE);
	ini_set("memory_limit", "20M");
	ignore_user_abort(true);
	set_time_limit(6000);

echo '<html><body bgcolor="#FFFFFF">';
	
function make_insrt_qry($obj, $tbl, $field_data)
{
	$vl = $kv = '';

	while( list($k,$v) = each($obj) ) {
		switch ( strtolower($field_data[$k]->type) )
		{
			case 'string':
			case 'blob':
			case 'text':
			case 'date':
				if( empty($v) && !$field_data[$k]->not_null ) 
					$vl .= 'NULL,';
				else
					$vl .= '"'.str_replace("\n", '\n', str_replace("\t", '\t', str_replace("\r", '\r', addslashes($v)))).'",';
				break;
			default:
				if( empty($v) && !$field_data[$k]->not_null ) 
					$vl .= 'NULL,';
				else
					$vl .= $v.',';
		}
		
		$kv .= $k.',';
	}	
	$vl = substr($vl, 0, -1);
	$kv = substr($kv, 0, -1);
	return "INSERT INTO ".$tbl." (".$kv.") VALUES(".$vl.")";
}

function filetomem($fn)
{
	$fp = fopen($fn, 'rb');
	$st = fstat($fp);
	$size = isset($st['size']) ? $st['size'] : $st[7];
	$str = fread($fp, $size);
	fclose($fp);
	
	return $str;
}

function write_body($data, &$len, &$offset)
{
	$MAX_FILE_SIZE = 2147483647;
	$curdir = getcwd();
	chdir($GLOBALS["MSG_STORE_DIR"]);

	$len = strlen($data);
	$i=1;
	while( $i<100 ) {
		$fp = fopen('msg_'.$i, 'ab');
		if( !($off = ftell($fp)) ) $off = __ffilesize($fp);
		if( !$off || sprintf("%u", $off+$len)<$MAX_FILE_SIZE ) break;
		fclose($fp);
		$i++;
	}
	
	$len = fwrite($fp, $data);
	fclose($fp);
	
	if( !$off ) @chmod('msg_'.$i, 0600);
	
	chdir($curdir);
	
	if( $len == -1 ) exit("FATAL ERROR: system has ran out of disk space<br>\n");
	$offset = $off;
	
	return $i;
}

function register_fp($id)
{
	if( empty($GLOBALS['__MSG_FP__'][$id]) ) 
		$GLOBALS['__MSG_FP__'][$id] = fopen($GLOBALS["MSG_STORE_DIR"].'msg_'.$id, 'rb');
	return $GLOBALS['__MSG_FP__'][$id];
}

function un_register_fps()
{
	if( !@is_array($GLOBALS['__MSG_FP__']) ) return;

	reset($GLOBALS['__MSG_FP__']);
	while( list($k,$v) = each($GLOBALS['__MSG_FP__']) ) {
		fclose($v);
		$GLOBALS['__MSG_FP__'][$k] = NULL;
	}	
}

function read_msg_body($off, $len, $file_id)
{
	$fp = register_fp($file_id);
	fseek($fp, $off);
	return fread($fp, $len);
}

function read_ext_set($file)
{
	$fp = fopen($file, 'rb');
	
	$ln = fread($fp, 8)+0;
	$RET[] = fread($fp, $ln);
	
	$ln = fread($fp, 8)+0;
	$RET[] = fread($fp, $ln);
	
	fclose($fp);

	return $RET;	
}

function cleandir($dir)
{
	$od = getcwd();
	chdir($dir);
	
	$dp = opendir('.');
	readdir($dp); readdir($dp);
	 
	while( $file = readdir($dp) ) {
		if( 
			$file == 'GLOBALS.php' || 
			$file == basename($GLOBALS['HTTP_SERVER_VARS']['PATH_TRANSLATED']) || 
			@is_link($file) || 
			$file == 'messages' ||
			$file == 'images' || 
			$file == 'avatars' ||
			$file == 'custom_avatars' ||
			$file == 'forum_icons' ||
			$file == 'message_icons' ||
			$file == 'smiley_icons' ||
			$file == '.backup' ||
			$file == 'errors'
			
		) continue;
	
		if( @is_dir($file) ) 
			cleandir($file);
		else
			unlink($file);		
	}
	
	closedir($dp);
	chdir($od);
}

function __mkdir($dir)
{
	if( @is_dir($dir) ) return 1;
	
	$m = umask(0);
	if( !($ret = mkdir($dir, 0700)) ) $ret = mkdir(dirname($dir),0700);
	umask($m);
	
	return $ret;
}

function upgrade_decompress_archive($data_root, $web_root, $data)
{
	$pos = strpos($data, "2105111608_\\ARCH_START_HERE");

	if( $pos === false ) exit("Couldn't locate start of archive<br>\n");
	
	$data = substr($data, $pos+strlen("2105111608_\\ARCH_START_HERE"));
	$data = base64_decode($data);
	
	$pos=0;
	
	$oldmask = umask(0177);
	
	while( ($pos = strpos($data, "\n//", $pos)) !== false ) {
		$end = strpos($data, "\n", $pos+1);
		$meta_data = explode('//',  substr($data, $pos, ($end-$pos)));
		$pos = $end;
		
		if( $meta_data[3] == '/install' || !isset($meta_data[3]) ) continue;
		
		$path = preg_replace('!^/install/forum_data!', $data_root, $meta_data[3]);
		$path = preg_replace('!^/install/www_root!', $web_root, $path);
		$path .= "/".$meta_data[1];
		
		$path = str_replace("//", "/", $path);
		
		if( isset($meta_data[5]) ) {
			$file = substr($data, ($pos+1), $meta_data[5]);
			if( md5($file) != $meta_data[4] ) exit("ERROR: file ".$meta_data[1]." not read properly from archive\n");
			
			if( $meta_data[1] == 'GLOBALS.php' ) {
				$fp = @fopen($path.'.new', 'wb');
				if( !$fp ) exit("Couldn't open $path for write<br>\n");
				fwrite($fp, $file);
				fclose($fp);
				continue;
			}
						
			$fp = @fopen($path, 'wb');
			if( !$fp ) exit("Couldn't open $path for write<br>\n");
				fwrite($fp, $file);
			fclose($fp);
		}
		else {
			if( substr($path, -1) == '/' ) $path = preg_replace('!/+$!', '', $path);
			if( !@is_dir($path) && !__mkdir($path) ) 
				exit("ERROR: failed creating $path directory<br>\n");
		}
	}
	umask($oldmask);
}

function l_get_mime_by_ext($ext)
{
	return Q_SINGLEVAL("SELECT id FROM fud_mime WHERE fl_ext='".$ext."'");
}

	if( !include_once("GLOBALS.php") ) exit("Cannot open the GLOBALS.php file, this file should be placed inside your forum's main web directory.<br>\n");
	
if( !function_exists("fud_use") ) {

	function fud_use($file)
	{
		include_once $GLOBALS["INCLUDE"].$file;
	}
}
	fud_use('db.inc');

if( !function_exists("__ffilesize") ) {
	
	function __ffilesize($fp)
	{
		$st = fstat($fp);
		return (isset($st['size']) ? $st['size'] : $st[7]);
	}
}

if( !function_exists("Q") ) {
	
	if ( !defined('_db_connection_ok_') ) {
		$connect_func = ( $GLOBALS['MYSQL_PERSIST'] == 'Y' ) ? 'mysql_pconnect' : 'mysql_connect';
		
		if ( !($GLOBALS['__DB_INC__']['SQL_LINK']=$connect_func($GLOBALS['MYSQL_SERVER'], $GLOBALS['MYSQL_LOGIN'], $GLOBALS['MYSQL_PASSWORD'])) ) {
			error_handler("db.inc", "unable to establish mysql connection on ".$GLOBALS['MYSQL_SERVER'], 0);
		}
		
		if ( !@mysql_select_db($GLOBALS['MYSQL_DB'],$GLOBALS['__DB_INC__']['SQL_LINK']) ) {
			error_handler("db.inc", "unable to connect to database", 0);
		}
		
		define('_db_connection_ok_', 1); 
	}
	
	function Q($query)
	{
		if ( !($result=mysql_query($query,$GLOBALS['__DB_INC__']['SQL_LINK'])) ) {
			echo "<b>Query Failed:</b> ".htmlspecialchars($query)."<br>\n<b>Reason:</b> ".mysql_error()."<br>\n<b>From:</b> ".$GLOBALS['SCRIPT_FILENAME']."<br>\n<b>Server Version:</b> ".Q_SINGLEVAL("SELECT VERSION()")."<br>\n";
			exit;
		}
		return $result; 
	}

	function QF($result)
	{
		mysql_free_result($result);
	}
	
	function DB_ROWOBJ($result)
	{
		return mysql_fetch_object($result);
	}

	function DB_ROWARR($result)
	{
		return mysql_fetch_row($result);
	}
	
	function DB_COUNT($result)
	{
		if ( $n=@mysql_num_rows($result) ) 
			return $n;
		else
			return 0;
	}

	function DB_SINGLEOBJ($res)
	{
		$obj = DB_ROWOBJ($res);
		QF($res);
		return $obj;
	}

	function DB_SINGLEARR($res)
	{
		$arr = DB_ROWARR($res);
		QF($res);
		return $arr;
	}

	function IS_RESULT($res)
	{
		if ( DB_COUNT($res) ) 
			return $res;
	
		QF($res);

		return;
	}

	function Q_SINGLEVAL($query)
	{
		$r = Q($query);
		if( !IS_RESULT($r) ) return;
	
		list($val) = DB_SINGLEARR($r);
	
		return $val;
	}

	function YN($val) 
	{
		return ( strlen($val) && strtolower($val) != 'n' ) ? 'Y' : 'N';
	} 

	function INTNULL($val)
	{	
		return ( strlen($val) ) ? $val : 'NULL';
	}

	function INTZERO($val)
	{
		return ( !empty($val) ) ? $val : '0';
	}

	function IFNULL($val, $alt)
	{
		return ( strlen($val) ) ? "'".$val."'" : $alt;
	}

	function STRNULL($val)
	{
		return ( strlen($val) ) ? "'".$val."'" : 'NULL';
	}
}

	/* Here we determine if the forum has necessary MySQL permissions needed for the upgrade script */
	mysql_query("DROP TABLE upgrade_test_table");
	if( !mysql_query("CREATE TABLE upgrade_test_table (test_val INT)") ) 
		exit("FATAL ERROR: your forum's MySQL account does not have permissions to create new MySQL tables<br>\nEnable this functionality and restart the script.<br>\n");
	if( !mysql_query("ALTER TABLE upgrade_test_table ADD test_val2 INT") ) 
		exit("FATAL ERROR: your forum's MYSQL account does not have permissions to run ALTER queries on existing MySQL tables<br>\nEnable this functionality and restart the script.<br>\n");
	mysql_query("DROP TABLE upgrade_test_table");

	umask(0);
	
	$ROOT_DIR = getcwd();
	chdir($ERROR_PATH);
	
	
	echo "Deleting old backup files<br>\n";
	flush();
	
	$dir = opendir('.');
	readdir($dir); readdir($dir);
	while( $file = readdir($dir) ) {
		if( @is_file($file) && strpos($file, '.bk') ) unlink($file);
	}
	closedir($dir);
	
	echo "Making a backup of current files<br>\n";
	flush();
	
	if( !@is_dir('.backup') && !mkdir('.backup', 0755) ) exit("Cannot make .backup directory inside ".getcwd()."<br>\n");
	chdir($MSG_STORE_DIR);

	$dir = opendir('.');
	readdir($dir); readdir($dir);
	while( $file = readdir($dir) ) {
		if( @is_file($file) && strpos($file, 'th_')!==FALSE ) copy($file, $ERROR_PATH.'.backup/'.$file);
	}
	closedir($dir);
	
	chdir($USER_SETTINGS_PATH);
	
	$dir = opendir('.');
	readdir($dir); readdir($dir);
	while( $file = readdir($dir) ) {
		if( @is_file($file) ) copy($file, $ERROR_PATH.'.backup/'.$file);
	}
	closedir($dir);
	
	chdir($FORUM_SETTINGS_PATH);
	
	$dir = opendir('.');
	readdir($dir); readdir($dir);
	while( $file = readdir($dir) ) {
		if( @is_file($file) ) copy($file, $ERROR_PATH.'.backup/'.$file);
	}
	closedir($dir);
	
	copy($INCLUDE.'GLOBALS.php', $ERROR_PATH.'.backup/GLOBALS.php');
	
	echo "Making a backup of MySQL data<br>\n";
	flush();
	
	fud_use('db.inc');
	$MYSQL_TBL_PREFIX = 'fud_';
	
	$r = Q("show tables");
	
	$prefix_len = strlen($MYSQL_TBL_PREFIX);
	
	$fp = fopen($ERROR_PATH.'.backup/mysql_dump.sql', 'wb');
	
	while( list($tbl_name) = DB_ROWARR($r) ) {
		if( substr($tbl_name, 0, $prefix_len) != $MYSQL_TBL_PREFIX ) continue;
		
		echo "Processing table: $tbl_name .... ";
		flush();
			
		$r2 = Q("SELECT * FROM ".$tbl_name);
		if( DB_COUNT($r2) ) {
			$field_data = array();
			for( $i=0; $i<mysql_num_fields($r2); $i++ ) {
				$field_info = mysql_fetch_field($r2);
				$field_data[$field_info->name] = $field_info;
			}
			while( $obj = mysql_fetch_object($r2, MYSQL_ASSOC) ) fwrite($fp, make_insrt_qry($obj, $tbl_name, $field_data)."\n");
		}	
		QF($r2);
		echo "DONE<br>\n";
		flush();
	}
	QF($r);
	fclose($fp);
	
	mysql_query("ALTER TABLE fud_thread 		DROP 	approved");
	mysql_query("ALTER TABLE fud_thread 		DROP 	fud_replyallowed");
	mysql_query("ALTER TABLE fud_thread 		CHANGE 	rating rating TINYINT UNSIGNED NOT NULL DEFAULT 0");
	mysql_query("ALTER TABLE fud_msg 		ADD 	offset_preview 		INT UNSIGNED NOT NULL DEFAULT 0");
	mysql_query("ALTER TABLE fud_msg 		ADD 	length_preview  	INT UNSIGNED NOT NULL DEFAULT 0");
	mysql_query("ALTER TABLE fud_msg 		ADD 	file_id_preview 	INT UNSIGNED NOT NULL DEFAULT 0");

	if( mysql_query("ALTER TABLE fud_msg ADD file_id INT UNSIGNED NOT NULL DEFAULT 1") ) {
		echo "Convert Message Storage to new format<br>\n";
		flush();	
	
		$cur_th=0;
		
		chdir($MSG_STORE_DIR);

		$r = Q("SELECT id,thread_id,offset,length FROM fud_msg ORDER BY thread_id,id");
		$i=0;
		while( $obj = DB_ROWOBJ($r) ) {
			if( $cur_th != $obj->thread_id ) {
				if( !empty($cur_th) && $fp ) fclose($fp);
				$fp = @fopen('th_'.$obj->thread_id, 'rb');
				if( !$fp ) continue;
			
				$cur_th = $obj->thread_id;
			}
		
			fseek($fp, $obj->offset);
		
			$body = fread($fp, $obj->length);
			$body_length = strlen($body);
		
			$file_id = write_body($body, $len, $off);
			if( strlen(read_msg_body($off, $len, $file_id)) != $body_length ) {
				echo "\nERROR: data mismatch on msg $obj->id\n";
				echo "$body_length != ".strlen(read_msg_body($off, $len, $file_id))."\n";
			}	
			Q("UPDATE fud_msg SET offset=".$off.", length=".$len.",file_id=".$file_id." WHERE id=".$obj->id);
		}
		QF($r);

		un_register_fps();
		
		echo "Removing old message files<br>\n";
		flush();
		
		$dir = opendir('.');
		readdir($dir); readdir($dir);
		while( $file = readdir($dir) ) {
			if( @is_file($file) && strpos($file, 'th_')!==FALSE ) unlink($file);
		}
		closedir($dir);	
	}
	
	echo "Moving user's homepage urls & biographies in to the database<br>\n";
	
	mysql_query("ALTER TABLE fud_users 	DROP	threads_age");
	mysql_query("ALTER TABLE fud_users 	ADD	home_page 		CHAR(255)");
	mysql_query("ALTER TABLE fud_users 	ADD 	u_last_post_id 		INT UNSIGNED NOT NULL DEFAULT 0");
	mysql_query("ALTER TABLE fud_users 	ADD 	show_avatars 		ENUM('Y', 'N') NOT NULL DEFAULT 'Y'");
	mysql_query("ALTER TABLE fud_users 	CHANGE 	show_sigs show_sigs 	ENUM('Y', 'N') NOT NULL DEFAULT 'Y'");
	mysql_query("ALTER TABLE fud_users 	DROP 	show_tool_tips");
	mysql_query("ALTER TABLE fud_users 	ADD 	jabber                  CHAR(255)");
	mysql_query("ALTER TABLE fud_users 	CHANGE 	private_messages 	email_messages ENUM('Y', 'N') NOT NULL DEFAULT 'Y'");

	if( mysql_query("ALTER TABLE fud_users 	ADD 	bio 			TEXT") ) {
		chdir($USER_SETTINGS_PATH);
		$dir = opendir('.');
		readdir($dir); readdir($dir);
		while( $file = readdir($dir) ) {
			if( substr($file, -4) != '.fud' ) continue;
			list($www, $bio) = read_ext_set($file);
			$id = substr($file, 0, strpos($file, '.'));
			Q("UPDATE fud_users SET home_page='".addslashes($www)."', bio='".addslashes($bio)."' WHERE id=".$id);
		
			unlink($file);
		}
		closedir($dir);
		chdir('..');
		rmdir($USER_SETTINGS_PATH);
	}
	
	echo "Decompressing New Forum Files<br>\n";
	flush();
	
	cleandir($INCLUDE);	
	cleandir($ROOT_DIR);
	
	upgrade_decompress_archive(realpath($INCLUDE.'../'), $ROOT_DIR, filetomem($HTTP_SERVER_VARS['PATH_TRANSLATED']));
	
	echo "Importing New MySQL tables<br>\n";
	flush();
	
	$new_tables = array(
		'fud_mime.tbl'=>'def_mime.sql',
		'fud_groups.tbl'=>'def_groups.sql',
		'fud_group_cache.tbl'=>NULL,
		'fud_group_members.tbl'=>NULL,
		'fud_group_resources.tbl'=>NULL,
		'fud_thr_exchange.tbl'=>NULL,
		'fud_action_log.tbl'=>NULL
	);	
	
	while( list($k,$v) = each($new_tables) ) {
		$data = filetomem(realpath($INCLUDE.'../').'/sql/'.$k);
		$data = trim(preg_replace("/#.*?\n/", "\n", $data));
		$data = explode(";", $data, 2);
		$data[0] = str_replace('{SQL_TABLE_PREFIX}', 'fud_', $data[0]);
		$data[1] = str_replace('{SQL_TABLE_PREFIX}', 'fud_', $data[1]);
		Q($data[0]);
		Q($data[1]);
		if( $v ) {
			$queries = explode("\n", str_replace('{SQL_TABLE_PREFIX}', 'fud_', filetomem(realpath($INCLUDE.'../').'/sql/'.$v)));
			while( list(,$v2) = each($queries) ) {
				if ( trim($v2) ) 
					Q($v2);
			}
		}
	}
	
	echo "Create groups to control permissions in every forum<br>\n";
	flush();
	
	Q("DELETE FROM fud_groups WHERE id>2");
	Q("DELETE FROM fud_group_members");
	Q("DELETE FROM fud_group_resources");
	
	$r = Q("SELECT * FROM fud_forum");
	while( $obj = DB_ROWOBJ($r) ) {
	
		Q("INSERT INTO fud_groups (name, res, res_id, p_VISIBLE, p_READ, p_POST, p_REPLY, p_EDIT, p_DEL, p_STICKY, p_POLL, p_FILE, p_VOTE, p_RATE, p_SPLIT, p_LOCK, p_MOVE, p_SML, p_IMG) 
				VALUES ('".addslashes($obj->name)."', 'forum', $obj->id,  'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y')");
		$id = mysql_insert_id();

		Q("INSERT INTO fud_group_resources(group_id, resource_type, resource_id) VALUES($id, 'forum', $obj->id)");
		$up_VISIBLE = $obj->hidden=='Y' ? 'N' : 'Y';
		$up_READ = YN($obj->anon_viewing);
		$up_POST = YN($obj->anon_topic_creation);
		$up_REPLY = YN($obj->anon_replying);
		$up_POLL = $up_VOTE = ( $up_REPLY == 'Y' && $obj->allow_polls == 'Y' ) ? 'Y' : 'N';
		$up_FILE = ( $up_REPLY == 'Y' && $obj->enable_attachments == 'Y' ) ? 'Y' : 'N';
		$up_RATE = ( $up_REPLY == 'Y' && $obj->allow_user_vote == 'Y' ) ? 'Y' : 'N';
		$up_SML = ( $up_REPLY == 'Y' && $obj->enable_smileys == 'Y' ) ? 'Y' : 'N';
		$up_IMG = ( $up_REPLY == 'Y' && $obj->enable_images == 'Y' ) ? 'Y' : 'N';
		
		
		Q("INSERT INTO fud_group_members (user_id, group_id,  up_VISIBLE, up_READ, up_POST, up_REPLY, up_POLL, up_FILE, up_RATE, up_SML, up_IMG)
				VALUES (0, $id, '$up_VISIBLE', '$up_READ', '$up_POST', '$up_REPLY', '$up_POLL', '$up_FILE', '$up_RATE', '$up_SML', up_IMG)");
		
		$up_POLL = $up_VOTE = ( $obj->allow_polls == 'Y' ) ? 'Y' : 'N';
		$up_FILE = ( $obj->enable_attachments == 'Y' ) ? 'Y' : 'N';
		$up_RATE = ( $obj->allow_user_vote == 'Y' ) ? 'Y' : 'N';
		$up_SML = ( $obj->enable_smileys == 'Y' ) ? 'Y' : 'N';
		$up_IMG = ( $obj->enable_images == 'Y' ) ? 'Y' : 'N';
		$up_VOTE = ( $up_REPLY == 'Y' && $obj->allow_user_vote == 'Y' ) ? 'Y' : 'N';
		
		Q("INSERT INTO fud_group_members (user_id, group_id, up_VISIBLE, up_READ, up_POST, up_REPLY,    up_POLL,    up_FILE,    up_VOTE,    up_RATE,    up_SML,   up_IMG)
				VALUES (4294967295, $id, 		     '$up_VISIBLE',     'Y',     'Y',      'Y', '$up_POLL', '$up_FILE', '$up_VOTE', '$up_RATE', '$up_SML', '$up_IMG')");

	}
	QF($r);
	
	mysql_query("ALTER TABLE fud_forum DROP allow_polls");
	mysql_query("ALTER TABLE fud_forum DROP enable_attachments");
	mysql_query("ALTER TABLE fud_forum DROP enable_smileys");
	mysql_query("ALTER TABLE fud_forum DROP enable_images");
	mysql_query("ALTER TABLE fud_forum DROP anon_topic_creation");
	mysql_query("ALTER TABLE fud_forum DROP anon_replying");
	mysql_query("ALTER TABLE fud_forum DROP anon_viewing");
	mysql_query("ALTER TABLE fud_forum DROP allow_user_vote");
	mysql_query("ALTER TABLE fud_forum ADD  index(last_post_id)");
	mysql_query("ALTER TABLE fud_forum ADD  message_threshold	INT UNSIGNED NOT NULL DEFAULT 0");
	mysql_query("ALTER TABLE fud_pmsg  ADD  ref_msg_id CHAR(11)");
	mysql_query("ALTER TABLE fud_pmsg  ADD  INDEX(duser_id, folder_id, id)");
	mysql_query("ALTER TABLE fud_forum DROP hidden");
	mysql_query("ALTER TABLE fud_cat DROP hidden");
	
	echo "Add mime types to exisiting uploaded files<br>\n";
	
	mysql_query("ALTER TABLE fud_attach ADD mime_type	INT UNSIGNED NOT NULL DEFAULT 0");
	$r = Q("SELECT * FROM fud_attach");
	while( $obj = DB_ROWOBJ($r) ) {
		$ext = substr(strrchr($obj->original_name, '.'), 1);
		$mime_id = l_get_mime_by_ext($ext);
		if( !$mime_id ) $mime_id = 40;
		Q("UPDATE fud_attach SET mime_type=$mime_id WHERE id=".$obj->id);
	}
	QF($r);	
	
	mysql_query("ALTER TABLE fud_smiley	ADD	vieworder       	INT UNSIGNED NOT NULL");
	mysql_query("ALTER TABLE fud_replace CHANGE type type ENUM('REPLACE', 'PERL') NOT NULL DEFAULT 'REPLACE'");
	
	echo "Adding new GLOBAL variables<br>\n";
	flush();
	
	fud_use('static/glob.inc');
	
	$GLOBALS['__GLOBALS.INC__'] = $INCLUDE.'GLOBALS.php.new';
	$global_data = read_global_config();
	$global_array = global_config_ar($global_data);
	
	while( list($k,$v) = each($global_array) ) {
		if( isset($GLOBALS[$k]) ) change_global_val($k, $GLOBALS[$k], $global_data);
	}
	
	change_global_val('TEMPLATE_DIR', realpath($INCLUDE.'../').'/template/', $global_data);
	change_global_val('WWW_ROOT_DISK', $ROOT_DIR.'/', $global_data);
	change_global_val('MYSQL_TBL_PREFIX', 'fud_', $global_data);
	
	write_global_config($global_data);
	
	rename($INCLUDE.'GLOBALS.php.new', $INCLUDE.'GLOBALS.php');

	echo "Compiling New Forum<br>\n";
	flush();
	
	/* convert the replace table into the new format */
	$r = Q("SELECT * FROM ".$MYSQL_TBL_PREFIX."replace WHERE type='REPLACE'");
	while ( $obj = DB_ROWOBJ($r) ) {
		$obj->replace_str = addslashes(preg_quote($obj->replace_str));
		$obj->replace_str = '/'.str_replace('/', '\\\\/',  $obj->replace_str).'/i';
		$obj->with_str = str_replace('\\', "\\\\", $obj->with_str);
		Q("UPDATE ".$MYSQL_TBL_PREFIX."replace SET replace_str='$obj->replace_str', with_str='$obj->with_str' WHERE id=$obj->id");
	}
	QF($r);
	
	require $INCLUDE.'GLOBALS.php';
	fud_use('static/compiler.inc');
	fud_use('static/lang.inc');
	
	switch_lang();
	compile_all();

	if( @file_exists('oldfrm_upgrade_safe.php') ) unlink('oldfrm_upgrade_safe.php');
?>
<br>Executing Consistency Checker (if the popup with the consistency checker failed to appear you <a href="javascript://" onClick="javascript: window.open('adm/consist.php?enable_forum=1');">MUST click here</a><br>
<script>
	window.open('adm/consist.php?enable_forum=1');
</script>
<font color="red" size="4">PLEASE REMOVE THIS FILE(oldfrm_upgrade.php) UPON COMPLETION OF THE UPGRADE PROCESS.<br>THIS IS IMPERATIVE, OTHERWISE ANYONE COULD RUN THIS SCRIPT!</font>
</body>
</html>
<?php exit; ?>
2105111608_\ARCH_START_HERE
