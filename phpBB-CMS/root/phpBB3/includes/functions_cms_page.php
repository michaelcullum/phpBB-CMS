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
if (!defined('IN_PHPBB'))
{
	exit;
}

/**
* Get row for specified page
*/
function get_page_row($page_id)
{
	global $db, $user;

	$sql = 'SELECT *
		FROM ' . PAGES_TABLE . " p
		JOIN " . PAGES_VERSIONS_TABLE . " v ON v.version_id = p.version_id
		JOIN " . USERS_TABLE . " u ON u.user_id = p.user_id
		WHERE p.page_id = $page_id";
	$result = $db->sql_query($sql);
	
	if (!($row = $db->sql_fetchrow($result)))
	{
		return false;
	}
	$db->sql_freeresult($result);
	
	return $row;
}

/**
* Strips invalid characters from a string to make it a valid slug
*/
function make_slug($slug)
{
	$slug = htmlspecialchars_decode($slug);
	
	$match = array('/\s+/', '/[^a-z0-9-[\]_+]/',);
	$replace = array(preg_quote(SPACE_SEPARATOR), '');
	
	return preg_replace($match, $replace, strtolower($slug));
}

/**
* Recalculates the home page, setting the home_page config value
*/
function refresh_home_page()
{
	global $db, $config;
	
	$sql = 'SELECT page_id
		FROM ' . PAGES_TABLE . '
		WHERE page_enabled = 1
			AND parent_enabled = 1
			AND parent_id = 0
		ORDER BY left_id ASC';
	$result = $db->sql_query_limit($sql, 1);
	$home_page = (int) $db->sql_fetchfield('page_id');
	$db->sql_freeresult($result);
	
	// Only set the config if the home page has actually changed
	if($home_page != $config['home_page'])
	{
		set_config('home_page', $home_page);
	}
}

/**
* Remove a version
*/
function delete_version($version_id)
{
	global $config, $db, $phpbb_root_path, $phpEx, $phpbb_hook;
	
	if ($phpbb_hook->call_hook(__FUNCTION__, $version_id))
	{
		if ($phpbb_hook->hook_return(__FUNCTION__))
		{
			return $phpbb_hook->hook_return_result(__FUNCTION__);
		}
	}
	
	$sql = 'SELECT p.page_id, p.version_id, page_title, version_number, version_physical_filename
		FROM ' . PAGES_VERSIONS_TABLE . ' v
		JOIN ' . PAGES_TABLE . ' p
			ON p.page_id = v.page_id
		WHERE v.version_id = ' . $version_id;
	$result = $db->sql_query($sql);
	$page_data = $db->sql_fetchrow($result);
	$db->sql_freeresult($result);
	
	$sql = 'DELETE FROM ' . PAGES_VERSIONS_TABLE . '
		WHERE version_id = ' . $version_id;
	$db->sql_query($sql);
	
	// Keep the links data, so long as the page exists, so we can tell which URLs we've already resent linkbacks to
	//$sql = 'DELETE FROM ' . PAGES_LINKS_TABLE . '
	//	WHERE version_id = ' . $version_id;
	//$db->sql_query($sql);
	
	$sql = 'DELETE FROM ' . PAGES_LOG_TABLE . "
		WHERE version_id = $version_id
			AND log_operation = 'LOG_VERSION_ADD'";
	$db->sql_query($sql);
	
	// Delete any cached content, if it exists
	@unlink("{$phpbb_root_path}cache/cms_version_{$version_id}.{$phpEx}");
	
	if($page_data['version_physical_filename'])
	{
		phpbb_unlink_upload($page_data['version_physical_filename']);
	}
	
	// The version we want to delete is the current version for the page
	if ($page_data['version_id'] == $version_id)
	{
		// Get the next latest version for this page
		$sql = 'SELECT version_id
			FROM ' . PAGES_VERSIONS_TABLE . '
			WHERE page_id = ' . $page_data['page_id'] . '
				AND version_id != ' . $version_id . '
			ORDER BY version_number DESC';
		$result = $db->sql_query_limit($sql, 1);
		$new_version_id = (int) $db->sql_fetchfield('version_id');
		$db->sql_freeresult($result);
	}
	
	// Update the current number of versions for the page and the new version_id, if necessary
	$sql = 'UPDATE ' . PAGES_TABLE . '
		SET page_versions = page_versions - 1' . ( (isset($new_version_id)) ? ', version_id = ' . $new_version_id : '' ) .  '
		WHERE page_id = ' . $page_data['page_id'];
	$db->sql_query($sql);
	
	set_config_count('num_versions', -1, true);
	
	add_page_log(false, $page_data['page_id'], $version_id, 'admin', 'LOG_VERSION_REMOVED', $page_data['version_number'], $page_data['page_title']);
	
	return array();
}

/**
* Remove a page
*/
function delete_page($page_id)
{
	global $db, $user, $config, $phpbb_root_path, $phpEx, $phpbb_hook;
	
	if ($phpbb_hook->call_hook(__FUNCTION__, $page_id))
	{
		if ($phpbb_hook->hook_return(__FUNCTION__))
		{
			return $phpbb_hook->hook_return_result(__FUNCTION__);
		}
	}

	$row = get_page_row($page_id);
	
	if(!$row)
	{
		return false;
	}

	$branch = get_page_branch($page_id, 'children', 'descending', false);

	if (sizeof($branch))
	{
		return array($user->lang['CANNOT_REMOVE_PAGE']);
	}

	// If not move
	$diff = 2;
	
	$sql = 'DELETE FROM ' . PAGES_TABLE . "
		WHERE page_id = $page_id";
	$db->sql_query($sql);
	
	$sql = 'DELETE FROM ' . PAGES_LOG_TABLE . "
		WHERE page_id = $page_id";
	$db->sql_query($sql);
	
	$sql = 'DELETE FROM ' . PAGES_URLS_TABLE . "
		WHERE page_id = $page_id";
	$db->sql_query($sql);
	
	if($page_id == $config['home_page'])
	{
		refresh_home_page();
	}
	
	set_config_count('num_pages', -1, true);
	set_config_count('num_versions', -$row['page_versions'], true);
	
	$sql = 'SELECT version_id, version_physical_filename
		FROM ' . PAGES_VERSIONS_TABLE . '
		WHERE page_id = ' . $page_id;
	$result = $db->sql_query($sql);
	
	$version_ids = array();
	while ($version = $db->sql_fetchrow($result))
	{
		$version_ids[] = $version['version_id'];
		
		// Delete any cached content, if it exists
		@unlink("{$phpbb_root_path}cache/cms_version_{$version['version_id']}.{$phpEx}");
		
		if($version['version_physical_filename'])
		{
			phpbb_unlink_upload($version['version_physical_filename']);
		}
	}
	
	$sql = 'DELETE FROM ' . PAGES_VERSIONS_TABLE . "
		WHERE page_id = $page_id";
	$db->sql_query($sql);
	
	$sql = 'DELETE FROM ' . PAGES_LINKS_TABLE . '
		WHERE ' . $db->sql_in_set('version_id', $version_ids);
	$db->sql_query($sql);

	$row['right_id'] = (int) $row['right_id'];
	$row['left_id'] = (int) $row['left_id'];

	// Resync tree
	$sql = 'UPDATE ' . PAGES_TABLE . "
		SET right_id = right_id - $diff
		WHERE left_id < {$row['right_id']} AND right_id > {$row['right_id']}";
	$db->sql_query($sql);

	$sql = 'UPDATE ' . PAGES_TABLE . "
		SET left_id = left_id - $diff, right_id = right_id - $diff
		WHERE left_id > {$row['right_id']}";
	$db->sql_query($sql);

	add_page_log(false, $page_id, 0, 'admin', 'LOG_PAGE_REMOVED', $row['page_title']);

	return array();

}

/**
* Revert a page to a previous version
*/
function revert_page($page_id, $version_id, $draft_check = 1, $link_check = true)
{
	global $db, $user, $phpbb_hook;
	
	if ($phpbb_hook->call_hook(__FUNCTION__, $page_id, $version_id, $draft_check, $link_check))
	{
		if ($phpbb_hook->hook_return(__FUNCTION__))
		{
			return $phpbb_hook->hook_return_result(__FUNCTION__);
		}
	}
	
	$errors = array();
	
	$sql = 'UPDATE ' . PAGES_TABLE . '
		SET version_id = ' . $version_id . ',
		page_last_mod = ' . time() . '
		WHERE page_id = ' . $page_id;
	$db->sql_query($sql);
	
	// Make sure the version isn't a draft
	if ($draft_check)
	{
		$sql = 'UPDATE ' . PAGES_VERSIONS_TABLE . '
			SET version_draft = 0
			WHERE version_id = ' . $version_id;
		$db->sql_query($sql);
	}
	
	if(!$link_check)
	{
		return $errors;
	}
	
	// Check if the new version has any broken links
	$sql = 'SELECT COUNT(link_id) broken_links
		FROM ' . PAGES_LINKS_TABLE . ' l
		LEFT JOIN ' . PAGES_TABLE . ' p
			ON p.page_id = l.link_page_id
		WHERE p.page_id IS NULL
			AND l.version_id = ' . $version_id;
	$result = $db->sql_query($sql);
	$broken_links = $db->sql_fetchfield('broken_links');
	$db->sql_freeresult($result);
					
	if($broken_links)
	{
		$errors[] = $user->lang['VERSION_BROKEN_LINK' . ( ($broken_links != 1) ? 'S' : '' )];
	}
	
	return $errors;
}

/**
* Sends a linkback notification (either a pingback or trackback) to a URL
*/
function send_linkback($from_url, $to_url, $title = false, $responce = false)
{
	$pingback_server = '';
	
	if(!$responce)
	{
		$errstr = '';
		$errno = 0;
	
		// Get the file
		// We limit the size to 100 KB incase the user linked to a large file, so we don't have to download it all (;
		$responce = remote_request($to_url, false, $errstr, $errno, 3, 102400);
	}
	
	// Pingback auto-discovery
	// Check for a pingback server in the header
	if (preg_match('/x-pingback:(.*)/i', $responce['header_info'], $match))
	{
		$pingback_server = trim($match[1]);
	}
	else
	{
		// No header found, see if the site uses a link tag instead
		if (preg_match('/<link rel="pingback" href="([^"]+)"/i', $responce['file_info']))
		{
			$pingback_server = $match[1];
		}
	}
	
	if ($pingback_server)
	{
		// We found a pingback server, send a pingback
		// If the error code is 50, the server could not communicate with an upstream server, or received an error from an upstream server, and therefore could not complete the request.
		// So the problem could be temporary - we'll try again in a second
		for($i = 0; $i <= 1; $i++)
		{
			$result = send_pingback($pingback_server, $from_url, $to_url);
			
			if ($result === true)
			{
				return true;
				break;
			}
			elseif($result != 50)
			{
				// We got another error code, don't bother trying again
				break;
			}
			
			sleep(1);
		}
	}
	
	// Either we didn't find a pingback server or were unable to send a pingback
	// Lets try sending a trackback instead (pingbacks are preferable)
	preg_match_all('/<rdf:RDF(.*)<\/rdf:RDF>/smi', $responce['file_info'], $matches);
	
	// Don't need this
	unset($matches[0]);
	
	// Loop through each RDF block
	foreach ($matches as $match)
	{
		// Is the RDF for the page we want to link to?
		if (preg_match('/dc:identifier="' . preg_quote($to_url, '/') . '"/smi', $match[0]))
		{
			// Can we find a trackback server for the page?
			if (preg_match('/trackback:ping="([^"]+)"/i', $match[0], $m))
			{
				// We got it, stop looking
				$trackback_server = $m[1];
				break;
			}
		}
	}
	
	if(!$trackback_server)
	{
		// We could not find a trackback server
		return false;
	}
	
	if(send_trackback($trackback_server, $from_url, $config['sitename'], $title) === true)
	{
		return true;
	}
	
	// We were unable to send a pingback or trackback, bad times ):
	return false;
}

/**
* Send a pingback notification
*/
function send_pingback($pingback_server, $from_url, $to_url)
{
	global $phpbb_root_path, $phpEx;
	
	include_once($phpbb_root_path . 'includes/ixr.' . $phpEx);
	
	$client = new IXR_Client($pingback_server);
	$client->useragent .= ' -- phpBB CMS';
	$client->timeout = 3;
	// $client->debug = true;
	
	// We count the error code 48 (already submitted) as being a success too
	if ($client->query('pingback.ping', $from_url, $to_url) || (isset($client->error->code) && $client->error->code = 48))
	{
		return true;
	}
	
	if (isset($client->error->code) && in_array($client->error->code, array(0, 16, 17, 32, 33, 48, 49, 50)))
	{
		return $client->error->code;
	}
	
	// Unable to get an error code
	return false;
}

/**
* Send a trackback notification
*/
function send_trackback($trackback_server, $from_url, $blog_name = false, $title = false, $excerpt = false)
{
	$post = 'url=' . urlencode($from_url);
	
	// These are all optional parameters
	$post .= ($blog_name) ? '&blog_name=' . urlencode($blog_name) : '';
	$post .= ($title) ? '&title=' . urlencode($title) : '';
	$post .= ($excerpt) ? '&excerpt=' . urlencode($excerpt) : '';
	
	$errstr = '';
	$errno = 0;
	$responce = remote_request($trackback_server, $post, $errstr, $errno);
	
	if($responce['file_info'] === false)
	{
		return false;
	}
	
	preg_match('/<error>([^<]*?)<\/error>/', $responce['file_info'], $match);
	
	if($match[1] == 0)
	{
		return true;
	}
	
	if(preg_match('/<message>([^<]*?)<\/message>/', $responce['file_info'], $match))
	{
		// Return the error message
		return $match[1];
	}
	
	// No error message returned
	return false;
}

/**
* Move page around the tree
*/
function move_page($moved_pages, $from_page_id, $to_parent_id)
{
	global $db;
	
	if (!sizeof($moved_pages))
	{
		return false;
	}
	
	$from_data = $moved_pages[0];
	$diff = sizeof($moved_pages) * 2;

	$moved_ids = array();
	for ($i = 0; $i < sizeof($moved_pages); ++$i)
	{
		$moved_ids[] = $moved_pages[$i]['page_id'];
	}

	// Resync parents
	$sql = 'UPDATE ' . PAGES_TABLE . "
		SET right_id = right_id - $diff
		WHERE left_id < " . (int) $from_data['right_id'] . '
			AND right_id > ' . (int) $from_data['right_id'];
	$db->sql_query($sql);

	// Resync righthand side of tree
	$sql = 'UPDATE ' . PAGES_TABLE . "
		SET left_id = left_id - $diff, right_id = right_id - $diff
		WHERE left_id > " . (int) $from_data['right_id'];
	$db->sql_query($sql);

	if ($to_parent_id > 0)
	{
		$to_data = get_page_row($to_parent_id);

		// Resync new parents
		$sql = 'UPDATE ' . PAGES_TABLE . "
			SET right_id = right_id + $diff
			WHERE " . (int) $to_data['right_id'] . ' BETWEEN left_id AND right_id
				AND ' . $db->sql_in_set('page_id', $moved_ids, true);
		$db->sql_query($sql);

		// Resync the righthand side of the tree
		$sql = 'UPDATE ' . PAGES_TABLE . "
			SET left_id = left_id + $diff, right_id = right_id + $diff
			WHERE left_id > " . (int) $to_data['right_id'] . '
				AND ' . $db->sql_in_set('page_id', $moved_ids, true);
		$db->sql_query($sql);

		// Resync moved branch
		$to_data['right_id'] += $diff;
		if ($to_data['right_id'] > $from_data['right_id'])
		{
			$diff = '+ ' . ($to_data['right_id'] - $from_data['right_id'] - 1);
		}
		else
		{
			$diff = '- ' . abs($to_data['right_id'] - $from_data['right_id'] - 1);
		}
	}
	else
	{
		$sql = 'SELECT MAX(right_id) AS right_id
			FROM ' . PAGES_TABLE . "
			WHERE " . $db->sql_in_set('page_id', $moved_ids, true);
		$result = $db->sql_query($sql);
		$row = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);

		$diff = '+ ' . (int) ($row['right_id'] - $from_data['left_id'] + 1);
	}

	$sql = 'UPDATE ' . PAGES_TABLE . "
		SET left_id = left_id $diff, right_id = right_id $diff
		WHERE " . $db->sql_in_set('page_id', $moved_ids);
	$db->sql_query($sql);
	
	return true;
}

/**
* Move page position by $steps up/down
*/
function move_page_by($page_row, $action = 'move_up', $steps = 1)
{
	global $db;

	/**
	* Fetch all the siblings between the pages's current spot
	* and where we want to move it to. If there are less than $steps
	* siblings between the current spot and the target then the
	* page will move as far as possible
	*/
	$sql = 'SELECT page_id, left_id, right_id, page_title
		FROM ' . PAGES_TABLE . '
		WHERE parent_id = ' . (int) $page_row['parent_id'] . '
			AND ' . (($action == 'move_up') ? 'right_id < ' . (int) $page_row['right_id'] . ' ORDER BY right_id DESC' : 'left_id > ' . (int) $page_row['left_id'] . ' ORDER BY left_id ASC');
	$result = $db->sql_query_limit($sql, $steps);

	$target = array();
	while ($row = $db->sql_fetchrow($result))
	{
		$target = $row;
	}
	$db->sql_freeresult($result);

	if (!sizeof($target))
	{
		// The page is already on top or bottom
		return false;
	}

	/**
	* $left_id and $right_id define the scope of the nodes that are affected by the move.
	* $diff_up and $diff_down are the values to substract or add to each node's left_id
	* and right_id in order to move them up or down.
	* $move_up_left and $move_up_right define the scope of the nodes that are moving
	* up. Other nodes in the scope of ($left_id, $right_id) are considered to move down.
	*/
	if ($action == 'move_up')
	{
		$left_id = (int) $target['left_id'];
		$right_id = (int) $page_row['right_id'];

		$diff_up = (int) ($page_row['left_id'] - $target['left_id']);
		$diff_down = (int) ($page_row['right_id'] + 1 - $page_row['left_id']);

		$move_up_left = (int) $page_row['left_id'];
		$move_up_right = (int) $page_row['right_id'];
	}
	else
	{
		$left_id = (int) $page_row['left_id'];
		$right_id = (int) $target['right_id'];

		$diff_up = (int) ($page_row['right_id'] + 1 - $page_row['left_id']);
		$diff_down = (int) ($target['right_id'] - $page_row['right_id']);

		$move_up_left = (int) ($page_row['right_id'] + 1);
		$move_up_right = (int) $target['right_id'];
	}

	// Now do the dirty job
	$sql = 'UPDATE ' . PAGES_TABLE . "
		SET left_id = left_id + CASE
			WHEN left_id BETWEEN {$move_up_left} AND {$move_up_right} THEN -{$diff_up}
			ELSE {$diff_down}
		END,
		right_id = right_id + CASE
			WHEN right_id BETWEEN {$move_up_left} AND {$move_up_right} THEN -{$diff_up}
			ELSE {$diff_down}
		END
		WHERE left_id BETWEEN {$left_id} AND {$right_id}
			AND right_id BETWEEN {$left_id} AND {$right_id}";
	$db->sql_query($sql);
	
	add_page_log(false, $page_row['page_id'], 0, 'admin', 'LOG_PAGE_' . strtoupper($action), $page_row['page_title'], $target['page_title']);
	
	if($page_row['parent_id'] == 0)
	{
		refresh_home_page();
	}

	return $target['page_title'];
}

/**
* Calculate the needed size for Thumbnail
*/
function get_cms_img_size_format($width, $height, $max_width)
{
	global $config;

	// Maximum Width the Image can take
	$max_width = ($max_width == IMAGE_SIZE_LARGE) ? 640 : ( ($max_width == IMAGE_SIZE_MEDIUM) ? 300 : ( ($max_width == IMAGE_SIZE_SMALL) ? 150 : 50 ) );

	if ($width > $height)
	{
		return array(
			round($width * ($max_width / $width)),
			round($height * ($max_width / $width))
		);
	}
	else
	{
		return array(
			round($width * ($max_width / $height)),
			round($height * ($max_width / $height))
		);
	}
}

/**
* Delete upload file
*/
function phpbb_unlink_upload($filename)
{
	global $db, $phpbb_root_path, $config;

	// Because of duplicate versions or modifications a physical filename could be assigned more than once. If so, do not remove the file itself.
	$sql = 'SELECT COUNT(version_id) AS num_entries
		FROM ' . PAGES_VERSIONS_TABLE . "
		WHERE version_physical_filename = '" . $db->sql_escape(basename($filename)) . "'";
	$result = $db->sql_query($sql);
	$num_entries = (int) $db->sql_fetchfield('num_entries');
	$db->sql_freeresult($result);

	// Do not remove file if at least one additional entry with the same name exist.
	if ($num_entries > 1)
	{
		return false;
	}
	
	$path = $phpbb_root_path . $config['cms_upload_path'] . '/';
	$filename = basename($filename);
	
	@unlink($path . 'thumb_' . $filename);
	@unlink($path . 'small_' . $filename);
	@unlink($path . 'medium_' . $filename);
	@unlink($path . 'large_' . $filename);
	
	return @unlink($path . $filename);
}

/**
* User Notification
*/
function user_page_notification($mode, $page_id, $page_title, $page_url)
{
	global $db, $user, $config, $phpbb_root_path, $phpEx, $auth;
	
	$notify_rows = array();
	
	switch($config['email_notifications'])
	{
		case EMAIL_NOTIFICATIONS_NONE:
			return;
		break;
		
		case EMAIL_NOTIFICATIONS_ALL:
			// Grab an array of user_id's with a_user permissions ... these users can activate a user
			$admin_ary = $auth->acl_get_list(false, 'a_', false);
			$admin_ary = (!empty($admin_ary[0]['a_'])) ? $admin_ary[0]['a_'] : array();

			// Also include founders
			$where_sql = ' WHERE user_type = ' . USER_FOUNDER;

			if (sizeof($admin_ary))
			{
				$where_sql .= ' OR ' . $db->sql_in_set('user_id', $admin_ary);
			}
			
			$sql = 'SELECT u.user_id, u.username, u.user_email, u.user_lang, u.user_notify_type, u.user_jabber
			FROM ' . USERS_TABLE . ' u
			' . $where_sql;
		break;
		
		case EMAIL_NOTIFICATIONS_CONTRIBUTORS:
			$sql = 'SELECT u.user_id, u.username, u.user_email, u.user_lang, u.user_notify_type, u.user_jabber
				FROM ' . USERS_TABLE . ' u
				JOIN ' . PAGES_TABLE . ' p
					ON p.user_id = u.user_id
				JOIN ' . PAGES_VERSIONS_TABLE . ' v
					ON v.user_id = u.user_id
				WHERE (p.page_id = ' . $page_id . ' OR v.page_id = ' . $page_id . ')
					AND u.user_type IN (' . USER_NORMAL . ', ' . USER_FOUNDER . ')
				GROUP BY u.user_id';
		break;
	}
	
	$result = $db->sql_query($sql);
	while ($row = $db->sql_fetchrow($result))
	{
		$notify_rows[$row['user_id']] = array(
			'user_id'		=> $row['user_id'],
			'username'		=> $row['username'],
			'user_email'	=> $row['user_email'],
			'user_jabber'	=> $row['user_jabber'],
			'user_lang'		=> $row['user_lang'],
			'method'		=> $row['user_notify_type'],
		);
	}
	$db->sql_freeresult($result);

	// Now, we are able to really send out notifications
	if (sizeof($notify_rows))
	{
		include_once($phpbb_root_path . 'includes/functions_messenger.' . $phpEx);
		$messenger = new messenger();
		foreach ($notify_rows as $addr)
		{
			$messenger->template('cms_notify', $addr['user_lang']);

			$messenger->to($addr['user_email'], $addr['username']);
			$messenger->im($addr['user_jabber'], $addr['username']);

			$messenger->assign_vars(array(
				'USERNAME'		=> htmlspecialchars_decode($addr['username']),
				'PAGE_TITLE'	=> htmlspecialchars_decode($page_title),
				'EDIT_USERNAME'	=> htmlspecialchars_decode($user->data['username']),

				'U_PAGE'		=> generate_cms_url() . '/' . generate_url($page_id, $page_url),
			));

			$messenger->send($addr['method']);
		}
		unset($notify_rows);

		$messenger->save_queue();
	}
}

/**
* Get available module information from module files
*/
function get_module_infos($module = '')
{
	global $phpbb_root_path, $phpEx;

	$directory = $phpbb_root_path . 'includes/cms/info/';
	$fileinfo = array();

	if (!$module)
	{
		$dh = @opendir($directory);

		if (!$dh)
		{
			return $fileinfo;
		}

		while (($file = readdir($dh)) !== false)
		{
			// Is module?
			if (preg_match('/^cms_.+\.' . $phpEx . '$/', $file))
			{
				$class = str_replace(".$phpEx", '', $file) . '_info';

				if (!class_exists($class))
				{
					include($directory . $file);
				}

				// Get module title tag
				if (class_exists($class))
				{
					$c_class = new $class();
					$module_info = $c_class->module();
					$fileinfo[str_replace('cms_', '', $module_info['filename'])] = $module_info;
				}
			}
		}
		closedir($dh);

		ksort($fileinfo);
	}
	else
	{
		$filename = 'cms_' . basename($module);
		$class = 'cms_' . basename($module) . '_info';

		if (!class_exists($class))
		{
			include($directory . $filename . '.' . $phpEx);
		}

		// Get module title tag
		if (class_exists($class))
		{
			$c_class = new $class();
			$module_info = $c_class->module();
			$fileinfo[str_replace('cms_', '', $module_info['filename'])] = $module_info;
		}
	}
	
	return $fileinfo;
}

/*
* Is a page locked for the current user?
*/
function is_locked($lock_id, $lock_time, $session_time)
{
	if (!$lock_id)
	{
		// Page is not locked
		return false;
	}
	
	global $user, $config;
	
	if($lock_id == $user->data['user_id'])
	{
		// Don't count if the user that locked the page is the user!
		return false;
	}
	
	// The user does not have an active session
	if(!$session_time)
	{
		return false;
	}
	
	$timespan = ($config['form_token_lifetime'] == -1) ? -1 : max(30, $config['form_token_lifetime']);
	if ((time() - $lock_time) <= $timespan)
	{
		// Is the form still valid?
		return true;
	}
	
	return false;
}

/**
* Determine which language pack to use for tinyMCE
*/
function tinymce_lang()
{
	global $phpbb_root_path, $user, $config;
	
	// We just check for the presence of a js file in the langs folder
	// This should be enough, although checks could also be made for lang files in the themes/plugins folder
	$lang_path = $phpbb_root_path . 'adm/style/tiny_mce/langs/';
	
	// Ideally, use the user's language
	if (file_exists($lang_path . $user->data['user_lang'] . '.js'))
	{
		return $user->data['user_lang'];
	}
	
	// No? Can we use the board default?
	if ($user->data['user_lang'] != $config['default_lang'] && file_exists($lang_path . $config['default_lang'] . '.js'))
	{
		return $config['default_lang'];
	}
	
	// Try English - this comes with tinyMCE by default, so should exist
	if (file_exists($lang_path . 'en.js'))
	{
		return 'en';
	}
	
	// Could not find English, are there any other language packs?
	if ($handle = opendir($lang_path))
	{
		while (false !== ($file = readdir($handle)))
		{
			if (preg_match('/^(.+)\.js$/', $file, $match))
			{
				// No preference, just grab the first one we find
				closedir($handle);
				return $match[1];
			}
		}
		closedir($handle);
	}
	
	// No language packs seem to exist
	return false;
}

/**
* Generates the HTML to embed a page for TinyMCE
*/
function embed_page($page, $image_size = 0)
{
	global $phpbb_root_path, $config;
	
	$u_page = generate_url(-1, $page['page_url'], false);
	
	$display_cat = '';
	
	if($page['version_type'] == VERSION_TYPE_FILE)
	{
		$extensions = obtain_upload_extensions();
		$page['version_extension'] = strtolower(trim($page['version_extension']));
		$display_cat = $extensions[$page['version_extension']]['display_cat'];
		$filename = $phpbb_root_path . $config['cms_upload_path'] . '/' . $page['version_physical_filename'];
	}
	
	// Work out the HTML to display
	switch ($display_cat)
	{
		// Images
		case ATTACHMENT_CATEGORY_IMAGE:
			$embed = '<img src="' . $u_page . ( ($image_size) ? '?s=' . $image_size : '' ) . '" alt="' . $page['page_title'] . '" />';
			
			// If the image is not the original, link to the original
			$embed = ($image_size != IMAGE_SIZE_ORIGINAL) ? '<a href="' . $u_page . '">' . $embed . '</a>' : $embed;
		break;

		// Windows Media Streams
		case ATTACHMENT_CATEGORY_WM:
			global $user;
			
			$embed = '<object width="320" height="285" classid="CLSID:6BF52A52-394A-11d3-B153-00C04F79FAA6" id="wmstream_' . $page['page_id'] . '">';
			$embed .= '<param name="url" value="' . $u_page . '" />';
			$embed .= '<param name="showcontrols" value="1" />';
			$embed .= '<param name="showdisplay" value="0" />';
			$embed .= '<param name="showstatusbar" value="0" />';
			$embed .= '<param name="autosize" value="1" />';
			$embed .= '<param name="autostart" value="0" />';
			$embed .= '<param name="visible" value="1" />';
			$embed .= '<param name="animationstart" value="0" />';
			$embed .= '<param name="loop" value="0" />';
			$embed .= '<param name="src" value="' . $u_page . '" />';
			
			// Browser is IE
			/*if(strpos(strtolower($user->browser), 'msie') !== false)
			{
				$embed .= '<object width="320" height="285" type="video/x-ms-wmv" data="' . $u_page . '">';
				$embed .= '<param name="src" value="' . $u_page . '" />';
				$embed .= '<param name="controller" value="1" />';
				$embed .= '<param name="showcontrols" value="1" />';
				$embed .= '<param name="showdisplay" value="0" />';
				$embed .= '<param name="showstatusbar" value="0" />';
				$embed .= '<param name="autosize" value="1" />';
				$embed .= '<param name="autostart" value="0" />';
				$embed .= '<param name="visible" value="1" />';
				$embed .= '<param name="animationstart" value="0" />';
				$embed .= '<param name="loop" value="0" />';
				$embed .= '</object>';
			}*/
			
			$embed .= '</object>';
		break;

		// Real Media Streams
		case ATTACHMENT_CATEGORY_RM:
			$embed .= '<object id="rmstream_' . $page['page_id'] . '" classid="clsid:CFCDAA03-8BE4-11cf-B84B-0020AFBBCCFA" width="200" height="50">';
			$embed .= '<param name="src" value="' . $page['page_id'] . '">';
			$embed .= '<param name="autostart" value="false">';
			$embed .= '<param name="controls" value="ImageWindow">';
			$embed .= '<param name="console" value="ctrls_' . $page['page_id'] . '">';
			$embed .= '<param name="prefetch" value="false">';
			$embed .= '<embed name="rmstream_' . $page['page_id'] . '" type="audio/x-pn-realaudio-plugin" src="' . $u_page . '" width="0" height="0" autostart="false" controls="ImageWindow" console="ctrls_' . $page['page_id'] . '" prefetch="false"></embed>';
			$embed .= '</object>';
			$embed .= '<br />';
			$embed .= '<object id="ctrls_' . $page['page_id'] . '" classid="clsid:CFCDAA03-8BE4-11cf-B84B-0020AFBBCCFA" width="0" height="36">';
			$embed .= '<param name="controls" value="ControlPanel">';
			$embed .= '<param name="console" value="ctrls_' . $page['page_id'] . '">';
			$embed .= '<embed name="ctrls_{_file.ATTACH_ID}" type="audio/x-pn-realaudio-plugin" width="200" height="36" controls="ControlPanel" console="ctrls_' . $page['page_id'] . '"></embed>';
			$embed .= '</object>';
			
			/*$embed .= '<script type="text/javascript">';
			$embed .= '// <![CDATA[' . "\n";
			$embed .= '	if (document.rmstream_' . $page['page_id'] . '.GetClipWidth)';
			$embed .= '	{';
			$embed .= '		while (!document.rmstream_' . $page['page_id'] . '.GetClipWidth())';
			$embed .= '		{';
			$embed .= '		}';
			$embed .= '		var width = document.rmstream_' . $page['page_id'] . '.GetClipWidth();';
			$embed .= '		var height = document.rmstream_' . $page['page_id'] . '.GetClipHeight();';
			$embed .= '		document.rmstream_' . $page['page_id'] . '.width = width;';
			$embed .= '		document.rmstream_' . $page['page_id'] . '.height = height;';
			$embed .= '		document.ctrls_' . $page['page_id'] . '.width = width;';
			$embed .= '	}';
			$embed .= '// ]]>';
			$embed .= '</script>';*/
		break;
		
		case ATTACHMENT_CATEGORY_QUICKTIME:
			$embed = '<object id="qtstream_' . $page['page_id'] . '" classid="clsid:02BF25D5-8C17-4B23-BC80-D3488ABDDC6B" codebase="http://www.apple.com/qtactivex/qtplugin.cab#version=6,0,2,0" width="320" height="285">';
			$embed .= '<param name="src" value="' . $u_page . '" />';
			$embed .= '<param name="controller" value="true" />';
			$embed .= '<param name="autoplay" value="false" />';
			$embed .= '<param name="type" value="video/quicktime" />';
			$embed .= '<embed name="qtstream_' . $page['page_id'] . '" src="' . $u_page . '" pluginspage="http://www.apple.com/quicktime/download/" enablejavascript="true" controller="true" width="320" height="285" type="video/quicktime" autoplay="false"></embed>';
			$embed .= '</object>';
		break;

		// Macromedia Flash Files
		case ATTACHMENT_CATEGORY_FLASH:
			list($width, $height) = @getimagesize($filename);

			$embed = '<object classid="clsid:D27CDB6E-AE6D-11CF-96B8-444553540000" codebase="http://active.macromedia.com/flash2/cabs/swflash.cab#version=5,0,0,0" width="' . $width . '" height="' . $height . '">';
			$embed .= '<param name="movie" value="' . $u_page . '" />';
			$embed .= '<param name="play" value="true" />';
			$embed .= '<param name="loop" value="true" />';
			$embed .= '<param name="quality" value="high" />';
			$embed .= '<embed src="' . $u_page . '" type="application/x-shockwave-flash" pluginspage="http://www.macromedia.com/shockwave/download/index.cgi?P1_Prod_Version=ShockwaveFlash" width="' . $width . '" height="' . $height . '" play="true" loop="true" quality="high" allowscriptaccess="never" allownetworking="internal"></embed>';
			$embed .= '</object>';
		break;
		
		// It would be nice, in the future, to include a flash audio player for nice embedding of mp3s
		// There's a nice audio player plugin for Wordpress; http://wpaudioplayer.com/ Maybe we could use this?
		// We could also do the same for flash video

		default:
			// Either the page is not a file, or it is a file we do not know how to embed - display a link instead
			$embed = '<a href="' . $u_page . '">' . $page['page_title'] . '</a>';
		break;
	}
	
	return $embed;
}

?>
