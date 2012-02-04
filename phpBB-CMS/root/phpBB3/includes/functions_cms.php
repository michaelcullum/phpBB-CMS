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
* Generate page URLS in the CMS
*/
function generate_url($page_id = -1, $page_url = '', $relative = true)
{
	global $config, $cms_root_path, $url_cms_root_path;
	
	$url = ($relative) ? ( (defined('IN_ADMIN')) ? $cms_root_path : $url_cms_root_path) : $config['cms_path'] . '/';
	
	// If the page is the home page, there is no need to specify the page_url
	// If we want to force the page_url, even if the page is the home page, we can pass -1 as the page_id
	if(!$page_id || $page_id == $config['home_page'])
	{
		return $url;
	}
	
	if (!$config['mod_rewrite'] && $page_url)
	{
		// Mod rewrite is not in use, append the script name
		// We only need to do this if any variables are actually being passed
		return $url . '?p=' . $page_url;
	}
	
	return $url . $page_url;
}

/**
* Generate CMS url (example: http://www.example.com/cms)
* @param bool $without_script_path if set to true the script path gets not appended (example: http://www.example.com)
* @param bool $force force the server vars
*/
function generate_cms_url($without_script_path = false, $force = false)
{
	global $config, $user;

	$server_name = $user->host;
	$server_port = (!empty($_SERVER['SERVER_PORT'])) ? (int) $_SERVER['SERVER_PORT'] : (int) getenv('SERVER_PORT');

	// Forcing server vars is the only way to specify/override the protocol
	if ($force || $config['force_server_vars'] || !$server_name)
	{
		$server_protocol = ($config['server_protocol']) ? $config['server_protocol'] : (($config['cookie_secure']) ? 'https://' : 'http://');
		$server_name = $config['server_name'];
		$server_port = (int) $config['server_port'];
		$script_path = $config['cms_path'];

		$url = $server_protocol . $server_name;
		$cookie_secure = $config['cookie_secure'];
	}
	else
	{
		// Do not rely on cookie_secure, users seem to think that it means a secured cookie instead of an encrypted connection
		$cookie_secure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 1 : 0;
		$url = (($cookie_secure) ? 'https://' : 'http://') . $server_name;

		// root_script_path does not seem to like the CMS.. needs investigation
		// For now just use the config value
		// $script_path = $user->page['root_script_path'];
		$script_path = $config['cms_path'];
	}

	if ($server_port && (($cookie_secure && $server_port <> 443) || (!$cookie_secure && $server_port <> 80)))
	{
		// HTTP HOST can carry a port number (we fetch $user->host, but for old versions this may be true)
		if (strpos($server_name, ':') === false)
		{
			$url .= ':' . $server_port;
		}
	}

	if (!$without_script_path)
	{
		$url .= $script_path;
	}

	// Strip / from the end
	if (substr($url, -1, 1) == '/')
	{
		$url = substr($url, 0, -1);
	}

	return $url;
}

/**
* Get page branch
*/
function get_page_branch($page_id, $type = 'all', $order = 'descending', $include_page = true)
{
	global $db;

	switch ($type)
	{
		case 'parents':
			$condition = 'p1.left_id BETWEEN p2.left_id AND p2.right_id';
		break;

		case 'children':
			$condition = 'p2.left_id BETWEEN p1.left_id AND p1.right_id';
		break;

		default:
			$condition = 'p2.left_id BETWEEN p1.left_id AND p1.right_id OR p1.left_id BETWEEN p2.left_id AND p2.right_id';
		break;
	}

	$rows = array();

	$sql = 'SELECT p2.*, v.*
		FROM ' . PAGES_TABLE . ' p1
		LEFT JOIN ' . PAGES_TABLE . " p2 ON ($condition)
		JOIN " . PAGES_VERSIONS_TABLE . " v
			ON v.version_id = p2.version_id
		WHERE p1.page_id = $page_id
		ORDER BY p2.left_id " . (($order == 'descending') ? 'ASC' : 'DESC');
	$result = $db->sql_query($sql);

	while ($row = $db->sql_fetchrow($result))
	{
		if (!$include_page && $row['page_id'] == $page_id)
		{
			continue;
		}

		$rows[] = $row;
	}
	$db->sql_freeresult($result);

	return $rows;
}

/**
* Returns the page url for a given page_id
*
* @param string $page_id The page_id of the page to get the url
* @param bool $rebuild Return the recalulated url, based on parent pages (slower) if true
*/
function get_page_url($page_id, $rebuild = false)
{
	static $page_urls;
	
	if($rebuild)
	{
		$pages = get_page_branch($page_id, 'parents');
	
		$slugs = array();
		foreach ($pages as $row)
		{
			$slugs[] = $row['page_slug'];
		}
	
		$page_url = implode('/', $slugs);
	}
	else
	{
		global $db, $config;
	
		if ($page_id == $config['home_page'])
		{
			return '';
		}
	
		// We use a run-time cache to reduce queries
		if (isset($pages[$page_id]))
		{
			return $pages[$page_id];
		}
	
		$sql = 'SELECT page_url
			FROM ' . PAGES_TABLE . '
			WHERE page_id = ' . (int) $page_id;
		$result = $db->sql_query($sql);
		$page_url = $db->sql_fetchfield('page_url');
		$db->sql_freeresult($result);
	}
	
	$page_urls[$page_id] = $page_url;
	
	return $page_url;
}

/**
* Return the page url for a page with the specified module_basename and module_mode
* Useful for MODs with several modes
*/
function get_module_url($module_basename, $module_mode)
{
	global $db, $config;
	static $pages;
	
	// We use a run-time cache to reduce queries
	if (isset($pages[$module_basename][$module_mode]))
	{
		return $pages[$module_basename][$module_mode];
	}
	
	$sql = 'SELECT p.page_id, page_url
		FROM ' . PAGES_TABLE . ' p
		JOIN ' . PAGES_VERSIONS_TABLE . " v
			ON v.version_id = p.version_id
		WHERE version_module_basename = '" . $module_basename . "'
			AND version_module_mode = '" . $module_mode . "'";
	$result = $db->sql_query_limit($sql, 1);
	$row = $db->sql_fetchrow($result);
	$db->sql_freeresult($result);
	
	$pages[$module_basename][$module_mode] = $row['page_url'];
	
	return $pages[$module_basename][$module_mode];
}

/**
* Generate Jumpbox for the CMS
* Very similar to make_jumpbox()
* $page_id can be -1 if we want a page linking to the forums to be selected by default
*/
function make_cms_jumpbox($action, $page_id = false, $select_all = false, $force_display = false)
{
	global $config, $template, $user, $db;

	// We only return if the jumpbox is not forced to be displayed (in case it is needed for functionality)
	if (!$config['load_jumpbox'] && $force_display === false)
	{
		return;
	}

	$sql = 'SELECT p.*' . ( ($page_id == '-1') ? ', version_type, version_link_type' : '' ) . '
		FROM ' . PAGES_TABLE . ' p
		' . ( ($page_id == '-1') ? 'JOIN ' . PAGES_VERSIONS_TABLE . ' v
			ON v.version_id = p.version_id' : '' ) . '
		ORDER BY left_id ASC';
	$result = $db->sql_query($sql, 600);

	$right = $padding = $iteration = 0;
	$padding_store = array('0' => 0);
	$display_jumpbox = $page_phpbb = false;

	while ($row = $db->sql_fetchrow($result))
	{
		if ($row['left_id'] < $right)
		{
			$padding++;
			$padding_store[$row['parent_id']] = $padding;
		}
		else if ($row['left_id'] > $right + 1)
		{
			// Ok, if the $padding_store for this parent is empty there is something wrong. For now we will skip over it.
			// @todo digging deep to find out "how" this can happen.
			$padding = (isset($padding_store[$row['parent_id']])) ? $padding_store[$row['parent_id']] : $padding;
		}

		$right = $row['right_id'];

		if (!$row['page_enabled'] || !$row['page_display'] || !$row['parent_enabled'] || !$row['parent_display'])
		{
			continue;
		}

		if (!$display_jumpbox)
		{
			$template->assign_block_vars('jumpbox_pages', array(
				'PAGE_URL'		=> '',
				'PAGE_TITLE'	=> $user->lang['SELECT_PAGE'],
				'S_PAGE_COUNT'	=> $iteration)
			);

			$iteration++;
			$display_jumpbox = true;
		}
		
		$is_phpbb_link = ($page_id == '-1' && $row['version_type'] == VERSION_TYPE_LINK && $row['version_link_type'] == LINK_TYPE_PHPBB) ? true : false;

		$template->assign_block_vars('jumpbox_pages', array(
			'U_PAGE'		=> append_sid(generate_url($row['page_id'], $row['page_url'])),
			'PAGE_URL'		=> $row['page_url'],
			'PAGE_TITLE'	=> $row['page_title'],
			'SELECTED'		=> ($row['page_id'] == $page_id || ($is_phpbb_link && !$page_phpbb) ) ? ' selected="selected"' : '',
			'S_PAGE_COUNT'	=> $iteration,
		));
		
		$page_phpbb = ($is_phpbb_link) ? true : $page_phpbb;

		for ($i = 0; $i < $padding; $i++)
		{
			$template->assign_block_vars('jumpbox_pages.level', array());
		}
		$iteration++;
	}
	$db->sql_freeresult($result);
	unset($padding_store);

	$template->assign_vars(array(
		'S_DISPLAY_JUMPBOX'	=> $display_jumpbox,
		'S_JUMPBOX_ACTION'	=> $action,
		'S_MOD_REWRITE'		=> $config['mod_rewrite'],
	));

	return;
}

/**
* Rewrite the image result from session:img() to use $url_phpbb_root_path, rather than $phpbb_root_path
*/
function rewrite_user_img($img)
{
	global $url_phpbb_root_path, $phpbb_root_path;
	
	if (substr($img, 0, strlen($phpbb_root_path)) == $phpbb_root_path)
	{
		return $url_phpbb_root_path . substr($img, strlen($phpbb_root_path));
	}
	else
	{
		return preg_replace('#src="([^"]*)"#e', "'src=\"' . \$url_phpbb_root_path . substr('\\1', strlen(\$phpbb_root_path) ) . '\"'", $img);
	}
}

/**
* Format all internal links and images in HTML
*/
function format_links_for_display($html, $append_sid = true, $relative = true)
{
	// Allow format_links_callback() to determine whether to append_sid
	global $format_links;
	
	$format_links = array(
		'append_sid'	=> $append_sid,
		'relative'		=> $relative,
	);
	
	$match = array(
		"/(<a[\s]+[^>]*?href[\s]?=[\s\"\']+)(.*?)([\"\']+.*?>([^<]+|.*?)?<\/a>)/i",
		"/(<img[\s]+[^>]*?src[\s]?=[\s\"\']+)(.*?)([\"\']+.*?>)/i",
	);
	
	return preg_replace_callback($match, 'format_links_for_display_callback', $html);
}

/**
* Callback function for format_links()
* Checks if the link is internal and applies append_sid()
*/
function format_links_for_display_callback($link)
{
	global $format_links;
	
	$url_parts = parse_url($link[2]);
	
	// We store links to pages in the format cms://page:{PAGE_ID}/{PAGE_URL}
	if($url_parts['scheme'] == 'cms' && $url_parts['host'] == 'page')
	{
		// This is a page within the CMS
		$url = generate_url($url_parts['port'], substr($url_parts['path'], 1), $format_links['relative']);
		$url .= (isset($url_parts['query'])) ? '?' . $url_parts['query'] : '';
		$url .= (isset($url_parts['fragment'])) ? '#' . $url_parts['fragment'] : '';
		
		return $link[1] . ( ($format_links['append_sid']) ? append_sid($url) : $url ) . $link[3];
	}
	elseif (empty($url_parts['scheme']) && empty($url_parts['host']) && isset($url_parts['path']))
	{
		// This is an internal url
		return $link[1] . ( ($format_links['append_sid']) ? append_sid($link[2]) : $link[2] ) . $link[3];
	}
	else
	{
		// Not an internal link, leave unchanged
		return $link[0];
	}
}

/**
* Add page log event
*/
function add_page_log()
{
	global $db, $user;
	
	if (!empty($GLOBALS['skip_add_log']))
	{
		return false;
	}

	$args = func_get_args();
	
	$run_inline	= (bool) array_shift($args);
	$page_id	= (int) array_shift($args);
	$version_id	= (int) array_shift($args);
	
	// Since this should only be called for admin logs, we know the operation is argument number
	$log_operation = $args[1];
	
	// If we're running inline, don't add log it in the phpBB logs but still add it to the page logs
	$log_id = ($run_inline) ? 0 : call_user_func_array('add_log', $args);
	
	$sql_ary = array(
		'log_id'		=> $log_id,
		'user_id'		=> (empty($user->data)) ? ANONYMOUS : $user->data['user_id'],
		'page_id'		=> $page_id,
		'version_id'	=> $version_id,
		'log_time'		=> time(),
		'log_operation'	=> $log_operation,
	);
	
	$db->sql_query('INSERT INTO ' . PAGES_LOG_TABLE . ' ' . $db->sql_build_array('INSERT', $sql_ary));
}

/**
* Generate a HTML list of pages
*/
function generate_page_list($left_id = 0, $right_id = 0)
{
	global $db;
	
	$sql = 'SELECT page_id, left_id, right_id, parent_id, page_title, page_url
		FROM ' . PAGES_TABLE . '
		WHERE page_enabled = 1
			AND page_display = 1
			AND parent_enabled = 1
			AND parent_display = 1
			' . ( ($left_id && $right_id) ? 'AND left_id BETWEEN ' . $left_id . ' AND ' . $right_id . ' AND left_id <> ' . $left_id : '' ) . '
		ORDER BY left_id ASC';
	$result = $db->sql_query($sql);
	
	$pages = array();
	while($row = $db->sql_fetchrow($result))
	{
		$pages[$row['page_id']] = $row;
		$pages[$row['page_id']]['children'] = false;
		
		if($row['parent_id'] && isset($pages[$row['parent_id']]))
		{
			$pages[$row['parent_id']]['children'] = true;
		}
	}
	
	$page_list = '<ul>';
	
	$right = $depth = $depth_change = 0;
	$depth_store = array('0' => 0);
	foreach($pages as $row)
	{
		if ($row['left_id'] < $right)
		{
			$depth++;
			$depth_store[$row['parent_id']] = $depth;
			// We need a way to stop pages being ignored from putting empty tags here. This could be tricky
			$page_list .= '<ul>';
		}
		else if ($row['left_id'] > $right + 1)
		{
			$old_depth = $depth;
			$depth = (isset($depth_store[$row['parent_id']])) ? $depth_store[$row['parent_id']] : 0;
			$page_list .= str_repeat('</ul></li>', abs($depth - $old_depth));
		}

		$right = $row['right_id'];
		
		$page_list .= '<li><a href="' . append_sid(generate_url($row['page_id'], $row['page_url'])) . '">' . $row['page_title'] . '</a>' . ( (!$row['children']) ? '</li>' : '' ) . "\n";
	}
	$page_list .= '</ul>';
	
	return $page_list;
}

/**
* Get total number of site views
*
* @param bool $recount Recount the views from the database, ignoring the run-time cache
*/
function get_num_views($recount = false)
{
	global $db, $config;
	
	if(!$config['num_pages'])
	{
		// No pages, therefore no views
		return 0;
	}
	
	// We use a run-time cache
	static $num_views;
	
	if(!isset($num_views) || $recount)
	{
		$sql = 'SELECT SUM(page_views) page_views
			FROM ' . PAGES_TABLE;
		$result = $db->sql_query($sql);
		$num_views = (int) $db->sql_fetchfield('page_views');
		$db->sql_freeresult($result);
	}
	
	return $num_views;
}

/**
* Removes HTML and whitespace
* Not yet used, might come in handy sometime (particularly with search)
*/
function strip_html($html)
{
	return preg_replace('/\s\s+/', ' ', trim(ereg_replace('<[^<]*>', ' ', html_entity_decode($html))));
}

/**
* Returns an array of alternative URLs that could be used to access the same page
*/
function denormalize_url($url)
{
	preg_match('#^http[s]?://(www\.)?(.+)$#i', $url, $m);
	
	// Strip a trailing slash
	if (substr($url, -1) == '/')
	{
		$url = substr($url, 0, -1);
	}
	
	// We consider these the same URL
	$urls = array(
		'http://' . $m[2],
		'https://' . $m[2],
		'http://www.' . $m[2],
		'https://www.' . $m[2],
		
		'http://' . $m[2] . '/',
		'https://' . $m[2] . '/',
		'http://www.' . $m[2] . '/',
		'https://www.' . $m[2] . '/',
	);
	
	return $urls;
}

/**
* Validates and adds linkbacks (from refbacks, pingbacks and trackbacks)
*/
function add_linkback($data, $refback = false)
{
	global $db, $user, $config;
	
	if (!preg_match('#^http[s]?://(.*?\.)*?[a-z0-9\-]+\.[a-z]{2,4}#i', $data['url']))
	{
		return 16;
	}
	
	$url_matches = denormalize_url($data['url']);
	
	if($refback)
	{
		$sql = 'UPDATE ' . PAGES_LINKS_TABLE . '
			SET link_refers = link_refers + 1
			WHERE link_page_id = ' . $data['page_id'] . '
				AND link_external = 1
				AND ' . $db->sql_in_set('link_url', $url_matches);
		$db->sql_query($sql);
		
		if($db->sql_affectedrows() != 0)
		{
			return true;
		}
	}
	else
	{
		$sql = 'SELECT 1
			FROM ' . PAGES_LINKS_TABLE . '
			WHERE link_page_id = ' . $data['page_id'] . '
				AND link_external = 1
				AND ' . $db->sql_in_set('link_url', $url_matches);
		$result = $db->sql_query($sql);
		
		if($row = $db->sql_fetchrow($result))
		{
			// Already registered
			return 48;
		}
	}
	
	// If the linkback is not a refback, we'll check the page is actually linking to us now
	// It takes to long to do this when a user visits the page; we'll do it later in the cron
	if(!$refback)
	{
		$result = process_linkback($data);
		if($result !== true)
		{
			return $result;
		}
	}
	else
	{
		// We need to run the cron to validate the link
		set_config('cms_run_cron', 1, true);
	}
	
	// The record does not exist, add it
	$link_data = array(
		'page_id'			=> $data['page_id'],
		'version_id'		=> $data['version_id'],
		'link_page_id'		=> $data['link_page_id'],
		'link_external'		=> 1,
		'link_url'			=> $data['url'],
		'link_refers'		=> ($refback) ? 1 : 0,
		'link_time'			=> time(),
		'link_title'		=> (isset($data['title'])) ? $data['title'] : '',
		'link_sitename'		=> (isset($data['sitename'])) ? $data['sitename'] : '',
		'link_processed'	=> ($refback) ? 0 : 1,
	);
	
	$sql = 'INSERT INTO ' . PAGES_LINKS_TABLE . ' ' . $db->sql_build_array('INSERT', $link_data);
	$db->sql_query($sql);
	
	if($config['log_incomming_links'] && !$refback)
	{
		add_page_log(false, $data['page_id'], $data['version_id'], 'admin', 'LOG_INCOMMING_LINK', $data['url'], $data['page_title']);
	}
	
	return true;
}

/**
* Check the url 
*/
function process_linkback(&$data)
{
	$errstr = '';
	$errno = 0;
	$responce = remote_request($data['url'], false, &$errstr, &$errno);
	
	if(!$responce)
	{
		return 16;
	}
	
	$found = false;
	$page_url_matches = denormalize_url($data['page_url']);
	foreach($page_url_matches as $url)
	{
		if(strpos($responce['file_info'], $url) !== false)
		{
			$found = true;
			break;
		}
	}
	
	if(!$found)
	{
		return 17;
	}
	
	// The linkback is a pingback, or a trackback and we weren't given a title - get one
	if(!isset($data['title']) || !$data['title'])
	{
		$result = preg_match('/<title>([^<]*?)<\/title>/is', $responce['file_info'], $match);
		$data['title'] = ($result) ? strip_tags($match[1]) : '';
	}
	
	return true;
}

/**
* Obtain allowed extensions
*
* Unlike $cache->obtain_attachment_extentions(), this returns an array of all known extensions, regardless of whether or not they are allowed in posts/private messages.
* Since only administrators can upload files, we want them to be able to upload as many types of files as we can
*
* @return array allowed extensions array.
*/
function obtain_upload_extensions()
{
	global $cache;

	if (($extensions = $cache->get('_upload_extensions')) === false)
	{
		global $db;

		$extensions = array();

		// The rule is to only allow those extensions defined. ;)
		$sql = 'SELECT extension, cat_id, download_mode
			FROM ' . EXTENSIONS_TABLE . ' e, ' . EXTENSION_GROUPS_TABLE . ' g
			WHERE e.group_id = g.group_id';
		$result = $db->sql_query($sql);

		while ($row = $db->sql_fetchrow($result))
		{
			$extension = strtolower(trim($row['extension']));
			
			$extensions[$extension] = array(
				'display_cat'	=> (int) $row['cat_id'],
				'download_mode'	=> (int) $row['download_mode'],
			);
		}
		$db->sql_freeresult($result);

		$cache->put('_upload_extensions', $extensions);
	}

	return $extensions;
}

/**
* Retrieve contents from remotely stored file - modified get_remote_file()
* Modified to automatically parse the host and filename, add support for https and POST requests, return headers, allow the bytes read to be limited and follow redirects
*/
function remote_request($url, $post, &$errstr, &$errno, $timeout = 10, $limit = 0, $resolve_limit = 2, $resolve_num = 1)
{
	global $user;
	
	$url_parts = parse_url($url);
	
	if($url_parts['scheme'] == 'https')
	{
		$scheme = 'ssl://' . $url_parts['host'];
		$port = (isset($url_parts['port'])) ? $url_parts['port'] : 443;
	}
	else
	{
		$scheme = $url_parts['host'];
		$port = (isset($url_parts['port'])) ? $url_parts['port'] : 80;   
	}
	
	// We need the filename to be at least /
	$filename = (isset($url_parts['path'])) ? $url_parts['path'] : '/';
	$filename .= (isset($url_parts['query'])) ? '?' . $url_parts['query'] : '';
	$filename .= (isset($url_parts['fragment'])) ? '#' . $url_parts['fragment'] : '';
	
	if ($fsock = @fsockopen($scheme, $port, $errno, $errstr, $timeout))
	{
		@fputs($fsock, ( ($post) ? 'POST' : 'GET' ) . " $filename HTTP/1.1\r\n");
		@fputs($fsock, "HOST: {$url_parts['host']}\r\n");
		
		if($post)
		{
			@fputs($fsock, "Content-Type: application/x-www-form-urlencoded\n");
			@fputs($fsock, 'Content-Length: ' . strlen($post) . "\n");
		}
		
		@fputs($fsock, "Connection: close\r\n\r\n");
		
		if($post)
		{
			@fputs($fsock, $post);
		}

		$file_info = $header_info = '';
		$get_info = false;
		$file_read = 0;

		while (!@feof($fsock))
		{
			if ($get_info)
			{
				if($file_read < $limit || !$limit)
				{
					$file_info .= @fread($fsock, 1024);
					$file_read += 1024;
				}
			}
			else
			{
				$line = @fgets($fsock, 1024);
				if ($line == "\r\n")
				{
					$get_info = true;
				}
				else if (stripos($line, '404 not found') !== false)
				{
					$errstr = $user->lang['FILE_NOT_FOUND'] . ': ' . $filename;
					return false;
				}
				else if(preg_match('/Location\:(.*)/i', $line, $match))
				{
					// We're being redirected, resolve
					$url = trim($match[1]);
					
					$resolve_num++;
					if($resolve_count == $resolve_limit)
					{
						// Looks like the page may never resolve - we keep hitting redirects
						// Stop here
						$errstr = $user->lang['EXCEED_REDIRECTS'];
						return false;
					}
					
					return remote_request($url, $post, &$errstr, &$errno, $timeout, $limit, $resolve_limit, $resolve_num);
				}
				else
				{
					$header_info .= $line;
				}
			}
		}
		@fclose($fsock);
	}
	else
	{
		if ($errstr)
		{
			$errstr = utf8_convert_message($errstr);
			return false;
		}
		else
		{
			$errstr = $user->lang['FSOCK_DISABLED'];
			return false;
		}
	}

	return array(
		'url'			=> $url,
		'header_info'	=> $header_info,
		'file_info' 	=> $file_info,
	);
}

?>
