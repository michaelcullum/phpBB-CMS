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

// Due to mod_rewrite, PHP_SELF can be incorrect, force REQUEST_URI instead
$php_self = $_SERVER['PHP_SELF'];
$_SERVER['PHP_SELF'] = $_SERVER[( (isset($config['mod_rewrite']) && $config['mod_rewrite']) ? 'REQUEST_URI' : 'PHP_SELF' )];

// Start session management
$user->session_begin();

// Restore PHP_SELF
$_SERVER['PHP_SELF'] = $php_self;

$auth->acl($user->data);

// The CMS has not been installed, link the user to the installer
if(!isset($config['cms_version']))
{
	$user->setup('cms_common');
	trigger_error(sprintf($user->lang['CMS_NOT_INSTALLED'], append_sid($phpbb_root_path . 'install_cms/index.' . $phpEx)));
}

$strip_slash = false;

if (defined('PAGE_URL'))
{
	 $page_url = PAGE_URL;
}
else
{
	// We used to use the name of the request parameter with no value, probably better if we don't...
	/// $page_url = array_search('', $_GET);
	$page_url = request_var('p', '');
	
	// Remember that the Page URI was/was not passed as a request param
	$request = ($page_url != '') ? true : false;
	
	// Page URL wasn't passed as a request param, attempt to extract from the URI
	if (!$request)
	{
		// Strip out the CMS path
		$page_url = substr(rawurldecode($_SERVER['REQUEST_URI']), strlen($config['cms_path']) + 1);
		
		// Strip out the query string
		$page_url = (strpos($page_url, '?') !== false) ? substr($page_url, 0, strpos($page_url, '?')) : $page_url;
	}
	
	// Strip a trailing slash
	if (substr($page_url, -1) == '/')
	{
		// Remember that we stripped a slash - if the page exists we'll then redirect the user to the URL without the slash
		$strip_slash = true;
		$page_url = substr($page_url, 0, -1);
	}
}

$user_page = $user->page['page'];

if(!$request && $config['mod_rewrite'])
{
	// If mod_rewrite is being used, we can't auto-detect these
	$user->page['root_script_path'] = $config['script_path'] . '/';
	$user->page['script_path'] = $config['cms_path'] . '/';
	// Relative to phpBB
	$user->page['page'] = '../' . $page_url;
}

$version = request_var('v', (int) ((defined('PAGE_VERSION')) ? PAGE_VERSION : 0));

// Build the query
$sql_where = array();

if (!$auth->acl_get('a_'))
{
	// If the user is not an administrator we do not allow them to view disabled pages or drafts
	$sql_where = array_merge($sql_where, array(
		'page_enabled'		=> 1,
		'parent_enabled'	=> 1,
	));
}
elseif ($version)
{
	// Allow administrators to view different versions
	$sql_where['version_number'] = $version;
}

if ($page_url)
{
	$sql_where['page_url'] = $page_url;
}
else
{
	// No url specified, get the home page
	$sql_where['p.page_id'] = $config['home_page'];
}

$sql = 'SELECT p.*, v.*, p.version_id page_version_id
	FROM ' . PAGES_TABLE . ' p
	JOIN ' . PAGES_VERSIONS_TABLE . ' v
		ON ' . ( ($config['version_control'] && $version) ? 'v.page_id = p.page_id' : 'v.version_id = p.version_id') . '
	WHERE ' . $db->sql_build_array('SELECT', $sql_where);
$result = $db->sql_query($sql);

if ( !($page = $db->sql_fetchrow($result)) )
{
	// Okay, so we didn't find the page the user wanted
	// Can we still work out what they want? We can try...
	if ($page_url)
	{
		// Use the URLs table to see if a page used to use the URL the user specified
		$sql = 'SELECT a.page_id, page_url
			FROM ' . PAGES_URLS_TABLE . ' a
			JOIN ' . PAGES_TABLE . " p
				ON p.page_id = a.page_id
			WHERE url = '" . $db->sql_escape($page_url) . "'";
		$result = $db->sql_query($sql);
		
		if ($row = $db->sql_fetchrow($result))
		{
			// Send them on their way...
			header('HTTP/1.1 301 Moved Permanently');
			redirect(append_sid(generate_url($row['page_id'], $row['page_url'], false)));
		}
		
		if (preg_match('/^index\./', $page_url))
		{
			// The user probably wanted the home page
			header('HTTP/1.1 301 Moved Permanently');
			redirect(append_sid(generate_url(0, '', false)));
		}
		
		if (preg_match('/^(.+)\/index\..*$/', $page_url, $m))
		{
			// Silly user added an /index. onto the URL - see if a page exists without it and redirect
			$sql = 'SELECT page_id
				FROM ' . PAGES_TABLE . "
				WHERE page_url = '" . $db->sql_escape($m[1]) . "'";
			$result = $db->sql_query($sql);
			
			if ($row = $db->sql_fetchrow($result))
			{
				header('HTTP/1.1 301 Moved Permanently');
				redirect(append_sid(generate_url($row['page_id'], $m[1], false)));
			}
		}
	}
	
	// We don't log if there is no page_url - it means a home page has not been created
	page_not_found(($page_url) ? true : false);
}

$u_page = generate_cms_url(true, true) . generate_url($page['page_id'], $page['page_url'], false);

// Process trackbacks
if(isset($_POST['url']))
{
	header('Content-Type: application/xml; charset=UTF-8');
	echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
	echo '<response>' . "\n";
	
	$data = array(
		'page_id'		=> $page['page_id'],
		'version_id'	=> $page['version_id'],
		'page_title'	=> $page['page_title'],
		'page_url'		=> $u_page,
		'url'			=> request_var('url', ''),
		'title'			=> utf8_normalize_nfc(request_var('title', '', true)),
		'sitename'		=> utf8_normalize_nfc(request_var('blog_name', '', true)),
	);
	
	$result = add_linkback($data);
	
	echo '<error>' . ( ($result === true || $result == 48) ? 0 : 1 ) . '</error>' . "\n";
	
	if($result !== true && $result != 48)
	{
		echo '<message>';
		
		// We use the same error messages as the pingback protocol
		switch($result)
		{
			case 16:
				echo 'The source URI does not exist.';
			break;
			
			case 17:
				echo 'The source URI does not contain a link to the target URI, and so cannot be used as a source.';
			break;
		}
		echo '</message>' . "\n";
	}
	
	echo '</response>' . "\n";
	exit;
}

if ($page_url && $page['page_id'] == $config['home_page'])
{
	// The user specified the url for the home page
	// Instead, redirect them to the site root
	header('HTTP/1.1 301 Moved Permanently');
	redirect(append_sid(generate_url(0, '', false)));
}
elseif (($request && $config['mod_rewrite']) || (!$request && $page_url && !$config['mod_rewrite']) || $strip_slash)
{
	// User used wrong method to access page or added a slash - redirect
	// We do this check after the query to prevent the possibility of performing a redirect, then sending a 404
	header('HTTP/1.1 301 Moved Permanently');
	redirect(append_sid(generate_url($page['page_id'], $page['page_url'], false)));
}

// Set some page vars so we can refer to them in the hook when generating navigation
$user->page = array_merge($user->page, array(
	'page_id'	=> $page['page_id'],
	'parent_id'	=> $page['parent_id'],
	'left_id'	=> $page['left_id'],
	'right_id'	=> $page['right_id'],
));

$user->setup(false, $page['page_style']);

// Parse the HTML and breadcrumbs for HTML/module pages (some modules may use the HTML)
if ($page['version_type'] == VERSION_TYPE_MODULE || $page['version_type'] == VERSION_TYPE_HTML || $page['version_type'] == VERSION_TYPE_CATEGORY)
{
	// We check for the parameter, incase modules are doing something with start
	$break = request_var('break', 0);
	$start = ($break) ? request_var('start', 0) : 0;
	$page['version_html'] = explode(PAGEBREAK_SEPARATOR, $page['version_html']);
	$num_breaks = sizeof($page['version_html']);
	
	if (!isset($page['version_html'][$start]))
	{
		page_not_found();
	}
	
	// Generate table of contents
	if ($page['page_contents_table'])
	{
		$headings = array();
		$depth = 0;
		$i = 0;
		foreach($page['version_html'] as $page_start => $content)
		{
			$num_headings = preg_match_all('/<h([1-6]) id="(.+)">([^<]+)<\/h[1-6]>/i', $content, $matches);
		
			for($j = 0; $j < $num_headings; $j++)
			{
				$headings[$i] = array(
					'start'			=> $page_start,
					'id'			=> $matches[2][$j],
					'title'			=> $matches[3][$j],
					'next_change'	=> 0,
					'depth_change'	=> ($depth) ? ($matches[1][$j] - $depth) : 0,
				);
		
				if($j)
				{
					$headings[($i - 1)]['next_change'] = $headings[$i]['depth_change'];
				}
			
				$depth = $matches[1][$j];
				$i++;
			}
		}
		
		$base_url = generate_url($page['page_id'], $page['page_url']);
		$contents = '<ol>';
		foreach($headings as $heading)
		{
			$url = '';
			if($start != $heading['start'])
			{
				// We don't want to pass parameters with default values - only pass if we need them
				$params = array();
				if ($version)
				{
					$params['v'] = $version;
				}
			
				if ($heading['start'])
				{
					$params['break'] = 1;
					$params['start'] = $heading['start'];
				}
			
				// append_sid() will add a '?' unless $params is false. Grr.
				$url = append_sid($base_url, (sizeof($params)) ? $params : false );
			}
			
			$contents .= ($heading['depth_change'] < 0) ? str_repeat('</ol></li>', -$heading['depth_change']) : '';
			$contents .= '<li><a href="' . $url . '#' . $heading['id'] . '">' . strip_tags($heading['title']) . '</a>';
			$contents .= ($heading['next_change'] > 0) ? str_repeat('<ol>', $heading['next_change']) : '</li>';
		}
		$contents .= '</ol>';
		
		$template->assign_var('CONTENTS_TABLE', $contents);
	}
	$page['version_html'] = $page['version_html'][$start];
	
	$template->assign_vars(array(
		'BREAK_PAGINATION'	=> generate_pagination(append_sid(generate_url($page['page_id'], $page['page_url']), ( ($version) ? "v={$version}&amp;" : '' ) . 'break=1'), $num_breaks, 1, $start, true),
		'BREAK_PAGE_NUMBER'	=> on_page($num_breaks, 1, $start),
		'U_START'			=> ($start) ? append_sid(generate_url($page['page_id'], $page['page_url']), ($version) ? "v={$version}&amp;" : false) : '',
	));
	
	// Set the home page for the breadcrumb
	$template->assign_block_vars('navlinks', array(
		'U_PAGE'		=> append_sid(generate_url()),
		'PAGE_TITLE'	=> $user->lang['HOME'],
	));
	
	// If there is no parent ID, don't bother getting parents
	$parents = ($page['parent_id']) ? get_page_branch($page['page_id'], 'parents', 'descending', false) : array();
	
	// Don't set the home page again
	if ($page['page_id'] != $config['home_page'])
	{
		$parents[] = $page;
	}
	
	foreach($parents as $row)
	{
		$template->assign_block_vars('navlinks', array(
			'U_PAGE'		=> append_sid(generate_url($row['page_id'], $row['page_url'])),
			'PAGE_TITLE'	=> $row['page_title'],
		));
	}
	
	if ($config['cms_parse_template'])
	{
		require($phpbb_root_path . 'includes/cms_template.' . $phpEx);
		
		$cms_template = new cms_template;
		$page['version_html'] = $cms_template->assign_display($page['version_id'], $page['version_html'], $page['version_time']);
	}
	
	$page['version_html'] = format_links_for_display($page['version_html']);
	
	$template->assign_vars(array(
		'U_EDIT'	=> ($auth->acl_get('a_') && !empty($user->data['is_registered'])) ? append_sid("{$phpbb_root_path}adm/index.$phpEx", 'i=cms_page_editor&amp;p=' . $page['page_id'] . '&amp;action=info', true, $user->session_id) : '',
		'U_DELETE'	=> ($auth->acl_get('a_') && !empty($user->data['is_registered'])) ? append_sid("{$phpbb_root_path}adm/index.$phpEx", 'i=cms_page_editor&amp;p=' . $page['page_id'] . '&amp;action=delete', true, $user->session_id) : '',
	));
	
	if (!$page['page_enabled'] || !$page['parent_enabled'])
	{
		$template->assign_var('S_PAGE_ADM_ONLY', true);
	}
	elseif ($page['version_id'] != $page['page_version_id'])
	{
		$template->assign_var('S_VERSION_ADM_ONLY', true);
	}
}

// Update page view ... but only for humans and if this is their first 'page view'
if (isset($user->data['session_page']) && !$user->data['is_bot'] && ($user->data['session_page'] != $user_page || isset($user->data['session_created'])))
{
	// We don't count views for administrators
	if(!$auth->acl_get('a_'))
	{
		$sql = 'UPDATE ' . PAGES_TABLE . '
			SET page_views = page_views + 1
			WHERE page_id = ' . $page['page_id'];
		$db->sql_query($sql);
	
		$sql = 'UPDATE ' . PAGES_VERSIONS_TABLE . '
			SET version_views = version_views + 1
			WHERE version_id = ' . $page['version_id'];
		$db->sql_query($sql);
	
		$page['page_views']++;
	}
	
	// Process refbacks
	if (!$user->validate_referer())
	{
		$data = array(
			'link_page_id'	=> $page['page_id'],
			'url'			=> $user->referer,
		);
		
		add_linkback($data, true);
	}
}

// Autodiscovery of the pingback URI
$u_pingback = generate_cms_url(true) . '/xmlrpc.php';
header('X-Pingback: '. $u_pingback);

// We do this after processing the views - if the views have incremented, this will have changed
$template->assign_vars(array(
	'U_PAGE'		=> $u_page,
	'U_PINGBACK'	=> $u_pingback,
	'U_TRACKBACK'	=> $u_page, // Our trackback server has the same URL as the page. Isn't that nice?
	
	'PAGE_VERSION'	=> sprintf($user->lang['VERSION_NUMBER'], $page['version_number']),
	'PAGE_TIME'		=> $user->format_date($page['page_time']),
	'PAGE_LAST_MOD'	=> ($page['page_last_mod'] > $page['page_time']) ? $user->format_date($page['page_last_mod']) : false,
	'PAGE_VIEWS'	=> $page['page_views'],
));

// I’m feeling nice and tingly
switch($page['version_type'])
{
	case VERSION_TYPE_LINK:
		switch($page['version_link_type'])
		{
			case LINK_TYPE_URL:
				$url = $page['version_link_url'];
			break;
			
			case LINK_TYPE_PAGE:
				$url = append_sid(generate_url($page['version_link_id'], $page['version_link_url'], false));
			break;
			
			case LINK_TYPE_PHPBB:
				$url = append_sid($url_phpbb_root_path . 'index.' . $phpEx);
			break;
		}
		
		redirect($url, false, true);
	break;
	
	case VERSION_TYPE_FILE:
		$mode = request_var('mode', '');
		$size = request_var('s', IMAGE_SIZE_ORIGINAL);
	
		$user->add_lang('viewtopic');
	
		// Obtain all extensions...
		$extensions = obtain_upload_extensions();
		
		$download_mode = (int) $extensions[$page['version_extension']]['download_mode'];
	
		$page['version_physical_filename'] = basename($page['version_physical_filename']);
		$display_cat = $extensions[$page['version_extension']]['display_cat'];
	
		if (($display_cat == ATTACHMENT_CATEGORY_IMAGE || $display_cat == ATTACHMENT_CATEGORY_THUMB) && !$user->optionget('viewimg'))
		{
			$display_cat = ATTACHMENT_CATEGORY_NONE;
		}
	
		if ($display_cat == ATTACHMENT_CATEGORY_FLASH && !$user->optionget('viewflash'))
		{
			$display_cat = ATTACHMENT_CATEGORY_NONE;
		}
	
		$original = $page['version_physical_filename'];
		switch ($size)
		{
			case IMAGE_SIZE_THUMBNAIL:
				// We might want to stop the views being update if we're just looking at the thumbnail. Hm.
				$page['version_physical_filename'] = 'thumb_' . $page['version_physical_filename'];
			break;
		
			case IMAGE_SIZE_SMALL:
				$page['version_physical_filename'] = 'small_' . $page['version_physical_filename'];
			break;
		
			case IMAGE_SIZE_MEDIUM:
				$page['version_physical_filename'] = 'medium_' . $page['version_physical_filename'];
			break;
		
			case IMAGE_SIZE_LARGE:
				$page['version_physical_filename'] = 'large_' . $page['version_physical_filename'];
			break;
		}
	
		$filename = $phpbb_root_path . $config['cms_upload_path'] . '/' . $page['version_physical_filename'];
	
		// Sometimes the image uploaded is smaller than the size we're trying to view it at, so it wasn't resized.
		// In this case, we want to show the original instead
		$page['version_physical_filename'] = (@file_exists($filename)) ? $page['version_physical_filename'] : $original;
	
		if ($display_cat == ATTACHMENT_CATEGORY_IMAGE && $mode === 'view' && (strpos($page['version_mimetype'], 'image') === 0) && ((strpos(strtolower($user->browser), 'msie') !== false) && (strpos(strtolower($user->browser), 'msie 8.0') === false)))
		{
			wrap_img_in_html(append_sid(generate_url($page['page_id'], $page['page_url'])), $page['version_real_filename']);
			file_gc();
		}
		else
		{
			// Determine the 'presenting'-method
			if ($download_mode == PHYSICAL_LINK)
			{
				// This presenting method should no longer be used
				if (!@is_dir($phpbb_root_path . $config['cms_upload_path']))
				{
					trigger_error($user->lang['PHYSICAL_DOWNLOAD_NOT_POSSIBLE']);
				}
	
				redirect($url_phpbb_root_path . $config['cms_upload_path'] . '/' . $page['physical_filename']);
				file_gc();
			}
			else
			{
				send_file_to_browser($page, $config['cms_upload_path'], $display_cat);
				file_gc();
			}
		}
	break;
	
	case VERSION_TYPE_MODULE:
		require($phpbb_root_path . 'includes/functions_module.' . $phpEx);
	
		$module = new p_master();
	
		$module->p_class = 'cms';
		$module->p_name = $page['version_module_basename'];
	
		// Set active module to true instead of using the id
		$module->active_module = true;
	
		// Load the relevant module
		// We don't set the execute parameter so we can set up the module with some extra data
		$module->load_active($page['version_module_mode'], false, false);
	
		// Overwrite and correctly set the u_action - load_active() adds extra parameters which aren't relevant here
		$module->module->u_action = append_sid(generate_url($page['page_id'], $page['page_url']));
	
		// Give the module some data about the current page
		$module->module->page = $page;
		
		// Add the language pack, if there is one
		// Normally in the U/M/ACP this would be handled by a call to list_modules() in the module class
		// Since we do not need to call it, we must add it manually
		if (file_exists($user->lang_path . $user->lang_name . '/mods/info_cms_' . $page['version_module_basename'] . '.' . $phpEx))
		{
			$user->add_lang('mods/info_cms_' . $page['version_module_basename']);
		}
	
		// Execute the module
		$module->module->main($page['version_module_basename'], $page['version_module_mode']);
	
		// Generate the page, do not display/query online list
		// If the module does not specify a title, use the version title
		$module->display( ( ($module->get_page_title()) ? $module->get_page_title() : $page['page_title'] ), false);
	break;
		
	case VERSION_TYPE_CATEGORY:
		$page['version_html'] = generate_page_list($page['left_id'], $page['right_id']);
		
		// No break
	case VERSION_TYPE_HTML:	
		$view = request_var('view', '');
		
		// Page header - don’t display the online list
		page_header($page['page_title'], false);

		$template->set_filenames(array(
			'body' => ($view == 'print') ? 'cms_default_print.html' : 'cms_default.html',
		));
		
		$u_canonical = $u_page;
		if($break && $start)
		{
			$u_canonical .= ( ($config['mod_rewrite']) ? '?' : '&amp;' ) . 'break=1&start=' . $start;
		}

		$template->assign_vars(array(
			'U_PRINT_PAGE'	=> append_sid(generate_url($page['page_id'], $page['page_url']), '&amp;view=print'),
			'U_CANONICAL'	=> $u_canonical,
			'PAGE_CONTENT'	=> $page['version_html'],
		));

		page_footer();
	break;
}

/**
* Display page not found error to user, logging broken links
*/
function page_not_found($log = true)
{
	global $user, $page_url, $phpbb_hook;
	
	if ($phpbb_hook->call_hook(__FUNCTION__, $log))
	{
		if ($phpbb_hook->hook_return(__FUNCTION__))
		{
			return $phpbb_hook->hook_return_result(__FUNCTION__);
		}
	}
	
	// Do we have a broken link?
	// We don't log the link more than once
	if ($log && $user->referer && (isset($user->data['session_page']) && !$user->data['is_bot'] && ($user->data['session_page'] != $user->page['page'] || isset($user->data['session_created']))))
	{
		// The link is internal, or the link is external and it is a correctly formatted URL
		if ($user->validate_referer() || (!$user->validate_referer() && preg_match('#^http[s]?://(.*?\.)*?[a-z0-9\-]+\.[a-z]{2,4}#i', $user->referer)))
		{
			add_log('critical', 'LOG_' . ( ($user->validate_referer()) ? 'INTERNAL' : 'EXTERNAL' ) . '_BROKEN_LINK', $user->referer, $page_url);
		}
	}
	
	// Don't display a “soft” error page
	// Send correct header to allow search engines to recognise the error
	header('HTTP/1.1 404 Not Found');
	trigger_error('NO_PAGE');
}

/**
* Wraps an url into a simple html page. Used to display attachments in IE.
* this is a workaround for now; might be moved to template system later
* direct any complaints to 1 Microsoft Way, Redmond
*/
function wrap_img_in_html($src, $title)
{
	echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-Strict.dtd">';
	echo '<html>';
	echo '<head>';
	echo '<meta http-equiv="content-type" content="text/html; charset=UTF-8" />';
	echo '<title>' . $title . '</title>';
	echo '</head>';
	echo '<body>';
	echo '<div>';
	echo '<img src="' . $src . '" alt="' . $title . '" />';
	echo '</div>';
	echo '</body>';
	echo '</html>';
}

/**
* Send file to browser
*/
function send_file_to_browser($page, $upload_dir, $category)
{
	global $user, $db, $config, $phpbb_root_path;

	$filename = $phpbb_root_path . $upload_dir . '/' . $page['version_physical_filename'];

	if (!@file_exists($filename))
	{
		trigger_error($user->lang['ERROR_NO_ATTACHMENT'] . '<br /><br />' . sprintf($user->lang['FILE_NOT_FOUND_404'], $filename));
	}

	// Correct the mime type - we force application/octetstream for all files, except images
	// Please do not change this, it is a security precaution
	if ($category != ATTACHMENT_CATEGORY_IMAGE || strpos($page['version_mimetype'], 'image') !== 0)
	{
		$page['version_mimetype'] = (strpos(strtolower($user->browser), 'msie') !== false || strpos(strtolower($user->browser), 'opera') !== false) ? 'application/octetstream' : 'application/octet-stream';
	}

	if (@ob_get_length())
	{
		@ob_end_clean();
	}

	// Now send the File Contents to the Browser
	$size = @filesize($filename);

	// To correctly display further errors we need to make sure we are using the correct headers for both (unsetting content-length may not work)

	// Check if headers already sent or not able to get the file contents.
	if (headers_sent() || !@file_exists($filename) || !@is_readable($filename))
	{
		// PHP track_errors setting On?
		if (!empty($php_errormsg))
		{
			trigger_error($user->lang['UNABLE_TO_DELIVER_FILE'] . '<br />' . sprintf($user->lang['TRACKED_PHP_ERROR'], $php_errormsg));

		}

		trigger_error('UNABLE_TO_DELIVER_FILE');
	}

	// Now the tricky part... let's dance
	header('Pragma: public');

	/**
	* Commented out X-Sendfile support. To not expose the physical filename within the header if xsendfile is absent we need to look into methods of checking it's status.
	*
	* Try X-Sendfile since it is much more server friendly - only works if the path is *not* outside of the root path...
	* lighttpd has core support for it. An apache2 module is available at http://celebnamer.celebworld.ws/stuff/mod_xsendfile/
	*
	* Not really ideal, but should work fine...
	* <code>
	*	if (strpos($upload_dir, '/') !== 0 && strpos($upload_dir, '../') === false)
	*	{
	*		header('X-Sendfile: ' . $filename);
	*	}
	* </code>
	*/

	// Send out the Headers. Do not set Content-Disposition to inline please, it is a security measure for users using the Internet Explorer.
	$is_ie8 = (strpos(strtolower($user->browser), 'msie 8.0') !== false);
	header('Content-Type: ' . $page['version_mimetype']);

	if ($is_ie8)
	{
		header('X-Content-Type-Options: nosniff');
	}

	if ($category == ATTACHMENT_CATEGORY_FLASH && request_var('view', 0) === 1)
	{
		// We use content-disposition: inline for flash files and view=1 to let it correctly play with flash player 10 - any other disposition will fail to play inline
		header('Content-Disposition: inline');
	}
	else
	{
		if (empty($user->browser) || (!$is_ie8 && (strpos(strtolower($user->browser), 'msie') !== false)))
		{
			header('Content-Disposition: attachment; ' . header_filename(htmlspecialchars_decode($page['version_real_filename'])));
			if (empty($user->browser) || (strpos(strtolower($user->browser), 'msie 6.0') !== false))
			{
				header('expires: -1');
			}
		}
		else
		{
			header('Content-Disposition: ' . ((strpos($page['version_mimetype'], 'image') === 0) ? 'inline' : 'attachment') . '; ' . header_filename(htmlspecialchars_decode($page['version_real_filename'])));
			if ($is_ie8 && (strpos($page['version_mimetype'], 'image') !== 0))
			{
				header('X-Download-Options: noopen');
			}
		}
	}

	if ($size)
	{
		header("Content-Length: $size");
	}

	// Close the db connection before sending the file
	$db->sql_close();

	if (!set_modified_headers($page['version_time'], $user->browser))
	{
		// Try to deliver in chunks
		@set_time_limit(0);

		$fp = @fopen($filename, 'rb');

		if ($fp !== false)
		{
			while (!feof($fp))
			{
				echo fread($fp, 8192);
			}
			fclose($fp);
		}
		else
		{
			@readfile($filename);
		}

		flush();
	}
	file_gc();
}

/**
* Get a browser friendly UTF-8 encoded filename
*/
function header_filename($file)
{
	$user_agent = (!empty($_SERVER['HTTP_USER_AGENT'])) ? htmlspecialchars((string) $_SERVER['HTTP_USER_AGENT']) : '';

	// There be dragons here.
	// Not many follows the RFC...
	if (strpos($user_agent, 'MSIE') !== false || strpos($user_agent, 'Safari') !== false || strpos($user_agent, 'Konqueror') !== false)
	{
		return "filename=" . rawurlencode($file);
	}

	// follow the RFC for extended filename for the rest
	return "filename*=UTF-8''" . rawurlencode($file);
}

/**
* Check if downloading item is allowed
*/
function download_allowed()
{
	global $config, $user, $db;

	if (!$config['secure_downloads'])
	{
		return true;
	}

	$url = (!empty($_SERVER['HTTP_REFERER'])) ? trim($_SERVER['HTTP_REFERER']) : trim(getenv('HTTP_REFERER'));

	if (!$url)
	{
		return ($config['secure_allow_empty_referer']) ? true : false;
	}

	// Split URL into domain and script part
	$url = @parse_url($url);

	if ($url === false)
	{
		return ($config['secure_allow_empty_referer']) ? true : false;
	}

	$hostname = $url['host'];
	unset($url);

	$allowed = ($config['secure_allow_deny']) ? false : true;
	$iplist = array();

	if (($ip_ary = @gethostbynamel($hostname)) !== false)
	{
		foreach ($ip_ary as $ip)
		{
			if ($ip)
			{
				$iplist[] = $ip;
			}
		}
	}

	// Check for own server...
	$server_name = $user->host;

	// Forcing server vars is the only way to specify/override the protocol
	if ($config['force_server_vars'] || !$server_name)
	{
		$server_name = $config['server_name'];
	}

	if (preg_match('#^.*?' . preg_quote($server_name, '#') . '.*?$#i', $hostname))
	{
		$allowed = true;
	}

	// Get IP's and Hostnames
	if (!$allowed)
	{
		$sql = 'SELECT site_ip, site_hostname, ip_exclude
			FROM ' . SITELIST_TABLE;
		$result = $db->sql_query($sql);

		while ($row = $db->sql_fetchrow($result))
		{
			$site_ip = trim($row['site_ip']);
			$site_hostname = trim($row['site_hostname']);

			if ($site_ip)
			{
				foreach ($iplist as $ip)
				{
					if (preg_match('#^' . str_replace('\*', '.*?', preg_quote($site_ip, '#')) . '$#i', $ip))
					{
						if ($row['ip_exclude'])
						{
							$allowed = ($config['secure_allow_deny']) ? false : true;
							break 2;
						}
						else
						{
							$allowed = ($config['secure_allow_deny']) ? true : false;
						}
					}
				}
			}

			if ($site_hostname)
			{
				if (preg_match('#^' . str_replace('\*', '.*?', preg_quote($site_hostname, '#')) . '$#i', $hostname))
				{
					if ($row['ip_exclude'])
					{
						$allowed = ($config['secure_allow_deny']) ? false : true;
						break;
					}
					else
					{
						$allowed = ($config['secure_allow_deny']) ? true : false;
					}
				}
			}
		}
		$db->sql_freeresult($result);
	}

	return $allowed;
}

/**
* Check if the browser has the file already and set the appropriate headers-
* @returns false if a resend is in order.
*/
function set_modified_headers($stamp, $browser)
{
	// let's see if we have to send the file at all
	$last_load 	=  isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? strtotime(trim($_SERVER['HTTP_IF_MODIFIED_SINCE'])) : false;
	if ((strpos(strtolower($browser), 'msie 6.0') === false) && (strpos(strtolower($browser), 'msie 8.0') === false))
	{
		if ($last_load !== false && $last_load <= $stamp)
		{
			if (substr(strtolower(@php_sapi_name()),0,3) === 'cgi')
			{
				// in theory, we shouldn't need that due to php doing it. Reality offers a differing opinion, though
				header('Status: 304 Not Modified', true, 304);
			}
			else
			{
				header('HTTP/1.0 304 Not Modified', true, 304);
			}
			// seems that we need those too ... browsers
			header('Pragma: public');
			header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + 31536000));
			return true;
		}
		else
		{
			header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $stamp) . ' GMT');
		}
	}
	return false;
}

function file_gc()
{
	global $cache, $db;
	if (!empty($cache))
	{
		$cache->unload();
	}
	$db->sql_close();
	exit;
}

?>
