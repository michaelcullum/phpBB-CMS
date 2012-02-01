<?php
/**
*
* @package phpBB3
* @copyright (c) 2012 Michael Cullum (Unknown Bliss of http://michaelcullum.com)
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
* @ignore
*/
define('IN_PHPBB', true);
define('IN_CMS', true);
$cms_root_path = (defined('CMS_ROOT_PATH')) ? CMS_ROOT_PATH : './';
$phpEx = substr(strrchr(__FILE__, '.'), 1);

if (!file_exists($cms_root_path . 'config.' . $phpEx))
{
	die("<p>The config.$phpEx file could not be found.</p>");
}

require($cms_root_path . 'config.' . $phpEx);

if (!file_exists($phpbb_root_path . 'common.' . $phpEx))
{
	die("<p>Could not find the phpBB directory. Check the path is set correctly in config.$phpEx</p>");
}

require($phpbb_root_path . 'common.' . $phpEx);

// Do not update users last page entry
$user->session_begin(false);
$auth->acl($user->data);

$use_shutdown_function = (@function_exists('register_shutdown_function')) ? true : false;

// Output transparent gif
//header('Cache-Control: no-cache');
//header('Content-type: image/gif');
//header('Content-length: 43');

//echo base64_decode('R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==');

// test without flush ;)
// flush();

//
if (!isset($config['cron_lock']))
{
	set_config('cron_lock', '0', true);
}

// make sure cron doesn't run multiple times in parallel
if ($config['cron_lock'])
{
	// if the other process is running more than an hour already we have to assume it
	// aborted without cleaning the lock
	$time = explode(' ', $config['cron_lock']);
	$time = $time[0];

	if ($time + 3600 >= time())
	{
		exit;
	}
}

define('CRON_ID', time() . ' ' . unique_id());

$sql = 'UPDATE ' . CONFIG_TABLE . "
	SET config_value = '" . $db->sql_escape(CRON_ID) . "'
	WHERE config_name = 'cron_lock' AND config_value = '" . $db->sql_escape($config['cron_lock']) . "'";
$db->sql_query($sql);

// another cron process altered the table between script start and UPDATE query so exit
if ($db->sql_affectedrows() != 1)
{
	exit;
}

if ($use_shutdown_function)
{
	register_shutdown_function('process_refbacks');
}
else
{
	process_refbacks();
}

// Unloading cache and closing db after having done the dirty work.
if ($use_shutdown_function)
{
	register_shutdown_function('unlock_cron');
	register_shutdown_function('garbage_collection');
}
else
{
	unlock_cron();
	garbage_collection();
}

exit;

/**
* Process and unchecked refbacks, to make sure they actually link to us
*/
function process_refbacks()
{
	global $db, $config;
	
	if(!$config['cms_run_cron'])
	{
		return;
	}
	
	$sql = 'SELECT l.*, page_title, page_url, version_id
		FROM ' . PAGES_LINKS_TABLE . ' l
		JOIN ' . PAGES_TABLE . ' p
			ON p.page_id = l.link_page_id
		WHERE link_processed = 0';
	$result = $db->sql_query($sql);
	
	$delete_ary = array();
	while ($row = $db->sql_fetchrow($result))
	{
		$data = array(
			'url'		=> $row['link_url'],
			'page_url'	=> generate_cms_url(true, true) . generate_url($row['link_page_id'], $row['page_url'], false),
			'title'		=> $row['link_title'],
		);
		
		if(process_linkback($data) === true)
		{
			$sql = 'UPDATE ' . PAGES_LINKS_TABLE . "
				SET link_processed = 1, link_title = '" . $db->sql_escape($data['title']) . "'
				WHERE link_id = {$row['link_id']}";
			$db->sql_query($sql);
			
			if($config['log_incomming_links'])
			{
				add_page_log(false, $row['page_id'], $row['version_id'], 'admin', 'LOG_INCOMMING_LINK', $row['link_url'], $data['page_title']);
			}
		}
		else
		{
			$delete_ary[] = $row['link_id'];
		}
	}
	
	if(sizeof($delete_ary))
	{
		// We use sql_in_set to make sure we don't delete links which have been added whilst loading the page
		$sql = 'DELETE FROM ' . PAGES_LINKS_TABLE . '
			WHERE link_processed = 0
				AND ' . $db->sql_in_set('link_id', $delete_ary);
		$db->sql_query($sql);
	}
	
	set_config('cms_run_cron', 0, true);
}


/**
* Unlock cron script
*/
function unlock_cron()
{
	global $db;

	$sql = 'UPDATE ' . CONFIG_TABLE . "
		SET config_value = '0'
		WHERE config_name = 'cron_lock' AND config_value = '" . $db->sql_escape(CRON_ID) . "'";
	$db->sql_query($sql);
}

?>
