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

// Start session management
$user->session_begin();
$auth->acl($user->data);
$user->setup();

// The CMS has not been installed, link the user to the installer
if(!isset($config['cms_version']))
{
	$user->add_lang('cms_common');
	trigger_error(sprintf($user->lang['CMS_NOT_INSTALLED'], append_sid($phpbb_root_path . 'install/index.' . $phpEx)));
}

// gzip_compression
if ($config['gzip_compress'])
{
	if (@extension_loaded('zlib') && !headers_sent())
	{
		ob_start('ob_gzhandler');
	}
}

// IF debug extra is enabled and admin want to "explain" the page we need to set other headers...
if (!defined('DEBUG_EXTRA') || !request_var('explain', 0) || !$auth->acl_get('a_'))
{
	header('Content-Type: application/xml; charset=UTF-8');
	header('Last-Modified: ' . gmdate('D, d M Y H:i:s', time()) . ' GMT');
}
else
{
	header('Content-type: text/html; charset=UTF-8');
	header('Cache-Control: private, no-cache="set-cookie"');
	header('Expires: 0');
	header('Pragma: no-cache');

	$mtime = explode(' ', microtime());
	$totaltime = $mtime[0] + $mtime[1] - $starttime;

	if (method_exists($db, 'sql_report'))
	{
		$db->sql_report('display');
	}

	garbage_collection();
	exit_handler();
}

// Date format for PHP > 5 and PHP4
$date_format = (PHP_VERSION >= 5) ? 'c' : "Y-m-d\TH:i:sO";
$mode = request_var('mode', 'cms');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
	xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
	xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9
	http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">' . "\n";

// Make sure comparisons are consistant
$time = time();

if ($mode == 'forum')
{
	// Not really much point in calculating the priority if we have less than 10 topics
	if ($config['num_topics'] > 10)
	{
		$sql = 'SELECT MAX(topic_views * 100000 / (' . $time . ' - topic_time)) max_val, MIN(topic_views / (' . $time . ' - topic_time)) min_val, AVG(topic_views) avg_views
			FROM ' . TOPICS_TABLE . ' t
			WHERE t.topic_status <> ' . ITEM_MOVED . '
				AND topic_approved = 1';
			//	AND ' . $db->sql_in_set();
		$result = $db->sql_query($sql);
		$stats = $db->sql_fetchrow($result);
	}
	
	// We only consider the priority accurate enough if pages have had 100 views on average
	$cal_priority = ($config['num_topics'] > 10 && $stats['avg_views'] >= 100) ? true : false;
	
	$sql = 'SELECT topic_id, f.forum_id, topic_time, topic_replies, topic_last_post_time, forum_type, forum_link' . ( ($cal_priority) ? ', (topic_views * 100000 / (' . $time . ' - topic_time)) topic_val' : '' ) . '
		FROM ' . TOPICS_TABLE . ' t
		JOIN ' . FORUMS_TABLE . ' f
			ON f.forum_id = t.forum_id
		WHERE t.topic_status <> ' . ITEM_MOVED . '
			AND topic_approved = 1
		ORDER BY ' . ( ($cal_priority) ? 'topic_val' : 'topic_views') .' DESC';
	// Limit the number of pages returned to 50,000 as according to the sitemap spec
	$result = $db->sql_query_limit($sql, 50000);
	
	// $user->page['root_script_path'], used by generate_board_url() is incorrect
	// Force server vars to stop make it use $config['script_path'] instead
	$config['force_server_vars'] = 1;
	
	$server_url = generate_board_url();
	$forum_auth = array();
	while ($row = $db->sql_fetchrow($result))
	{
		// Permissions check
		if (!isset($forum_auth[$row['forum_id']]))
		{
			$forum_auth[$row['forum_id']] = (!$auth->acl_gets('f_list', 'f_read', $row['forum_id']) || ($row['forum_type'] == FORUM_LINK && $row['forum_link'] && !$auth->acl_get('f_read', $forum_id))) ? false : true;
		}
		
		if ($forum_auth[$row['forum_id']])
		{	
			// The average time the topic changes, in seconds
			$avg_change = ( ($time - $row['topic_time']) / ($row['topic_replies'] + 1));
			
			if ($cal_priority)
			{
				$priority = ($row['topic_val'] - $stats['min_val']) / ($stats['max_val'] - $stats['min_val']);
				
				// Sitemap priority is to 1 decimal place
				// Make sure the value is between 0 and 1, to prevent rounding errors
				$priority =  min(max(round($priority, 1), 0), 1);
			}
			
			echo '<url>' . "\n";
			echo '<loc>' . $server_url . '/viewtopic.' . $phpEx . '?f=' . $row['forum_id'] . '&amp;t=' . $row['topic_id'] . '</loc>' . "\n";
			echo '<lastmod>' . $user->format_date($row['topic_last_post_time'], $date_format, true) . '</lastmod>' . "\n";
			echo '<changefreq>' . change_freq($avg_change) . '</changefreq>' . "\n";
			echo ($cal_priority) ? '<priority>' . $priority . '</priority>' . "\n" : '';
			echo '</url>' . "\n";
		}
	}
}
else
{
	// Calculate the time the forums last changed
	$sql = 'SELECT MAX(forum_last_post_time) last_post
		FROM ' . FORUMS_TABLE;
	$result = $db->sql_query($sql);
	$phpbb_last_mod = (int) $db->sql_fetchfield('last_post');
	$phpbb_avg_change = ( ($time - $config['board_startdate']) / $config['num_posts']);
	
	// Not really much point in calculating the priority if we have less than 10 pages
	if ($config['num_pages'] > 10)
	{
		$sql = 'SELECT MAX(page_views * 100000 / (' . $time . ' - page_time)) max_val, MIN(page_views / (' . $time . ' - page_time)) min_val, AVG(page_views) avg_views
			FROM ' . PAGES_TABLE . ' p
			JOIN ' . PAGES_VERSIONS_TABLE . ' v
				ON v.version_id = p.version_id
			WHERE page_enabled = 1
				AND parent_enabled = 1
				AND ' . $db->sql_in_set('version_type', array(VERSION_TYPE_HTML, VERSION_TYPE_FILE, VERSION_TYPE_MODULE));
		$result = $db->sql_query($sql);
		$stats = $db->sql_fetchrow($result);
	}
	
	// We only consider the priority accurate enough if pages have had 100 views on average
	$cal_priority = ($config['num_pages'] > 10 && $stats['avg_views'] >= 100) ? true : false;
	
	$sql = 'SELECT p.*, version_type, version_link_type' . ( ($cal_priority) ? ', (page_views * 100000 / (' . $time . ' - page_time)) page_val' : '' ) . '
		FROM ' . PAGES_TABLE . ' p
		JOIN ' . PAGES_VERSIONS_TABLE . ' v
			ON p.version_id = v.version_id
		WHERE page_enabled = 1
			AND parent_enabled = 1
		ORDER BY ' . ( ($cal_priority) ? 'page_val' : 'page_views') .' DESC';
	// Limit the number of pages returned to 50,000 as according to the sitemap spec
	// Sort by the number of views to attempt to list the most relevant pages, incase the limit is exceded
	$result = $db->sql_query_limit($sql, 49999);
	
	$cms_url = generate_cms_url(true);
	$phpbb_link = false;
	while ($row = $db->sql_fetchrow($result))
	{
		$priority = false;
		$avg_change = '';
		
		$phpbb_link = ($row['version_type'] == VERSION_TYPE_LINK && $row['version_link_type'] == LINK_TYPE_PHPBB) ? true : $phpbb_link;
		
		if ($row['version_type'] == VERSION_TYPE_HTML || $row['version_type'] == VERSION_TYPE_FILE)
		{
			// The average time the page changes, in seconds
			$avg_change = ($time - $row['page_time']) / $row['page_edits'];
			
			if ($cal_priority)
			{
				if ($config['home_page'] == $row['page_id'])
				{
					// The home page always has a priority of 1
					$priority = 1;
				}
				else
				{
					$priority = ($row['page_val'] - $stats['min_val']) / ($stats['max_val'] - $stats['min_val']);
					
					// Sitemap priority is to 1 decimal place
					// Make sure the value is between 0 and 1, to prevent rounding errors
					$priority =  min(max(round($priority, 1), 0), 1);
				}
			}
		}
		elseif ($row['version_type'] == VERSION_TYPE_LINK && $row['version_link_type'] == LINK_TYPE_PHPBB)
		{
			$avg_change = $phpbb_avg_change;
			$row['page_last_mod'] = $phpbb_last_mod;
		}
		
		echo '<url>' . "\n";
		echo '<loc>' . $cms_url . generate_url($row['page_id'], $row['page_url'], false) . '</loc>' . "\n";
		echo '<lastmod>' . $user->format_date($row['page_last_mod'], $date_format, true) . '</lastmod>' . "\n";
		echo ($avg_change) ? '<changefreq>' . change_freq($avg_change) . '</changefreq>' . "\n" : '';
		echo ($priority !== false) ? '<priority>' . $priority . '</priority>' . "\n" : '';
		echo '</url>' . "\n";
	}
	
	// If we haven't yet linked to the forums
	if (!$phpbb_link)
	{
		echo '<url>' . "\n";
		echo '<loc>' . generate_board_url() . '</loc>' . "\n";
		echo '<lastmod>' . $user->format_date($phpbb_last_mod, $date_format, true) . '</lastmod>' . "\n";
		echo ($phpbb_avg_change) ? '<changefreq>' . change_freq($phpbb_avg_change) . '</changefreq>' . "\n" : '';
		echo '</url>' . "\n";
	}
}
echo '</urlset>';

garbage_collection();
exit_handler();

/**
* Calculate the textual representation of the change frequency, as per the sitemap spec
*/
function change_freq($avg_change)
{
	if ($avg_change < 86400)
	{
		// Changes more than in a day (60 x 60 x 24)
		return 'hourly';
	}
	elseif ($avg_change < 604800)
	{
		// Changes more than in a week (60 x 60 x 24 x 7)
		return 'daily';
	}
	elseif ($avg_change < 2678400)
	{
		// Changes more than in a month (60 x 60 x 24 x 31)
		return 'weekly';
	}
	elseif ($avg_change < 31536000)
	{
		// Changes more than in a year (60 x 60 x 24 x 365)
		return 'monthly';
	}
	else
	{
		return 'yearly';
	}
}

?>
