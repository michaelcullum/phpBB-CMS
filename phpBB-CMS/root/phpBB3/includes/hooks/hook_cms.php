<?php
/**
*
* @package hooks
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

// Don't hook anything if the CMS is yet to be installed or we're installing
// If the user has yet to install, none of the tables will exist so errors will occur
if (isset($config['cms_version']) && !defined('UMIL_AUTO'))
{
	$phpbb_hook->register('phpbb_user_session_handler', 'cms_common');
	$phpbb_hook->register('append_sid', 'cms_append_sid');
	$phpbb_hook->register(array('template', 'display'), 'cms_template_display');
	
	// We can't do this in a hook - the earliest hook to be called is phpbb_user_session_handler, which is too late
	if($config['force_www'])
	{
		$correct_host = (substr($_SERVER['HTTP_HOST'], 0, 4) != 'www.') ? 'www.' . $_SERVER['HTTP_HOST'] : $_SERVER['HTTP_HOST'];
		//$correct_host = (substr($correct_host, -3) == ':80') ? substr($correct_host, 0, -3) : $correct_host;
		
		if($correct_host != $_SERVER['HTTP_HOST'])
		{
			// Forcing server vars is the only way to specify/override the protocol
			if ($config['force_server_vars'])
			{
				$server_protocol = ($config['server_protocol']) ? $config['server_protocol'] : (($config['cookie_secure']) ? 'https://' : 'http://');
			}
			else
			{
				$server_protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 'https://' : 'http://';
			}
			
			header('HTTP/1.1 301 Moved Permanently');
			header("Location: {$server_protocol}{$correct_host}{$_SERVER['REQUEST_URI']}");
		}
	}
	
	if(!$cms_root_path)
	{
		require($phpbb_root_path . 'cms_config.' . $phpEx);
	}
	
	require($phpbb_root_path . 'includes/constants_cms.' . $phpEx);
	require($phpbb_root_path . 'includes/functions_cms.' . $phpEx);
	
	// Due to url rewriting, the physical path to phpBB may not be the same as the virtual path
	// Work out how many "virtual" directories the current page is in... and the relative path to the phpBB folder
	// The point of this is to set a variable, $url_phpbb_root_path, to be used in place of $phpbb_root_path for all hyperlinks, image urls, etc
	$subfolders = substr_count(substr($_SERVER['REQUEST_URI'], strlen($config['cms_path'] . '/'), (strlen($_SERVER['REQUEST_URI']) - strlen($config['cms_path'].'/')) ), '/') - substr_count($_SERVER['QUERY_STRING'], '/');
	$url_cms_root_path = './' . str_repeat('../', $subfolders);
	$url_phpbb_root_path = $url_cms_root_path . substr($config['script_path'], strlen($config['cms_path'] . '/'), (strlen($config['script_path']) - strlen($config['cms_path'] . '/'))) . '/';
}

/**
* Hook for phpbb_user_session_handler
* Add any CMS language packs and handle disabling of the CMS
*/
function cms_common()
{
	global $user, $config;
	
	$user->add_lang('cms_common');
	
	// If the user is in the ACP, add the admin language pack
	// We use a seperate language pack rather than add definitions to the bottom of phpBB's common language file to keep the CMS code as seperate as possible from phpBB, reducing file modifications
	// Check using ADMIN_START, not IN_ADMIN (it has not yet been defined)
	if (defined('ADMIN_START'))
	{
		$user->add_lang('acp/cms_common');
	}
	
	// If the current page is part of the CMS, not the board, then update whether the board is disabled or not
	// The values of board_disable and board_disable_msg are processed later by the setup method in the session class - we change them at runtime
	if (defined('IN_CMS'))
	{
		$config['board_disable'] = $config['cms_disable'];
		$config['board_disable_msg'] = (!empty($config['cms_disable_msg'])) ? $config['cms_disable_msg'] : 'CMS_DISABLE';
		$user->lang['BOARD_DISABLED'] = $user->lang['CMS_DISABLED'];
	}
}

/**
* Hook for append_sid()
* Rewrite URLs to use the correct path to the phpBB root if in the CMS
*/
function cms_append_sid(&$hook, $url, $params = false, $is_amp = true, $session_id = false)
{
	global $phpbb_root_path, $phpEx, $url_phpbb_root_path;
	static $internal_use;
	
	// Only rewrite URLS if we're in the CMS
	if(defined('IN_CMS') && !$internal_use)
	{
		$file = substr($url, strlen($phpbb_root_path), (strlen($url) - strlen($phpbb_root_path) - strlen($phpEx) - 1) );
		
		// If the url to be rewritten is calling one of the following phpBB files, call append_sid() again, using the $url_phpbb_root path instead
		$phpbb_pages = array(
				'cron',
				'faq',
				'index',
				'mcp',
				'memberlist',
				'posting',
				'report',
				'search',
				'style',
				'ucp',
				'viewforum',
				'viewonline',
				'viewtopic',
				'adm/index',
		 );
		
		if(in_array($file, $phpbb_pages))
		{
			$internal_use = true;
			$append_sid =  append_sid($url_phpbb_root_path . $file . '.' . $phpEx, $params, $is_amp, $session_id);
			$internal_use = false;
			return $append_sid;
		}
	}
}

/**
* Hook for template::display()
* Builds the navigation, set and redefine up some template variables
*/
function cms_template_display()
{
	global $db, $config, $template, $SID, $_SID, $user, $auth, $phpEx, $phpbb_root_path, $url_phpbb_root_path, $url_cms_root_path;
	static $called;
	
	$called = (isset($called)) ? $called : false;
	
	// Ensure the variables only get set once - multiple calls to $template->assign_display() produce multiple calls here
	if($called)
	{
		return;
	}
	
	if(isset($user->page['page_id']))
	{
		$page_id = $user->page['page_id'];
	}
	else
	{
		$page_id = (defined('IN_CMS')) ? false : -1;
	}
	
	// Generate the jumpbox
	make_cms_jumpbox(append_sid(generate_url()), $page_id);
	
	if(isset($user->page['page_id']))
	{
		if($user->page['parent_id'] == 0)
		{
			$parent_left = $user->page['left_id'];
			$parent_right = $user->page['right_id'];
		}
		else
		{
			$sql = 'SELECT left_id, right_id
				FROM ' . PAGES_TABLE . '
				WHERE ' . $user->page['left_id'] . '
				BETWEEN left_id
					AND right_id
				AND parent_id = 0';
			$result = $db->sql_query($sql);
			$row = $db->sql_fetchrow($result);
			$parent_left = (int) $row['left_id'];
			$parent_right = (int) $row['right_id'];
			$db->sql_freeresult($result);
		}
	}
	else
	{
		$parent_left = 0;
		$parent_right = 0;
	}
	
	$sql = 'SELECT p.*
		FROM ' . PAGES_TABLE . ' p
		JOIN ' . PAGES_VERSIONS_TABLE . ' v
			ON v.version_id = p.version_id
		WHERE parent_id = 0
			AND page_enabled = 1
			AND page_display = 1
			AND parent_enabled = 1
			AND parent_display = 1
		ORDER BY left_id ASC';
	$result = $db->sql_query($sql);
	
	while ($row = $db->sql_fetchrow($result))
	{
		$template->assign_block_vars('nav', array(
			'U_PAGE'		=> append_sid(generate_url($row['page_id'], $row['page_url'])),
			'PAGE_TITLE'	=> $row['page_title'],
			'PAGE_CURRENT'	=> (isset($user->page['page_id']) && $user->page['page_id'] == $row['page_id']) ? true : false,
		));
	}
	$db->sql_freeresult($result);
	
	$template->assign_vars(array(
		'U_SITE_INDEX'			=> append_sid(generate_url()),
		
		// Setting a variable to let the style designer know where he/she is...
		'S_IN_CMS'				=> (defined('IN_CMS')) ? true : false,
	));
	
	if(!defined('IN_CRON') && $config['cms_run_cron'])
	{
		$call_cron = true;
		$time_now = (!empty($user->time_now) && is_int($user->time_now)) ? $user->time_now : time();
		
		// Any old lock present?
		if (!empty($config['cron_lock']))
		{
			$cron_time = explode(' ', $config['cron_lock']);

			// If 1 hour lock is present we do not call cron.php
			if ($cron_time[0] + 3600 >= $time_now)
			{
				$call_cron = false;
			}
		}
		
		if($call_cron)
		{
			$template->assign_var('RUN_CMS_CRON_TASK', '<img src="' . append_sid($url_cms_root_path . 'cron.' . $phpEx) . '" width="1" height="1" alt="cron" />');
		}
	}
	
	if(defined('IN_CMS'))
	{
		// We could use PHPBB_USE_BOARD_URL_PATH, but we can just re-assign the variables with the $url_phpbb_root_path version
		// Note - we only need to do this when we're in the CMS
		$template->assign_vars(array(
			'ROOT_PATH'	=> $url_phpbb_root_path,
		
			'T_THEME_PATH'			=> "{$url_phpbb_root_path}styles/" . $user->theme['theme_path'] . '/theme',
			'T_TEMPLATE_PATH'		=> "{$url_phpbb_root_path}styles/" . $user->theme['template_path'] . '/template',
			'T_SUPER_TEMPLATE_PATH'	=> (isset($user->theme['template_inherit_path']) && $user->theme['template_inherit_path']) ? "{$url_phpbb_root_path}styles/" . $user->theme['template_inherit_path'] . '/template' : "{$url_phpbb_root_path}styles/" . $user->theme['template_path'] . '/template',
			'T_IMAGESET_PATH'		=> "{$url_phpbb_root_path}styles/" . $user->theme['imageset_path'] . '/imageset',
			'T_IMAGESET_LANG_PATH'	=> "{$url_phpbb_root_path}styles/" . $user->theme['imageset_path'] . '/imageset/' . $user->data['user_lang'],
			'T_IMAGES_PATH'			=> "{$url_phpbb_root_path}images/",
			'T_SMILIES_PATH'		=> "{$url_phpbb_root_path}{$config['smilies_path']}/",
			'T_AVATAR_PATH'			=> "{$url_phpbb_root_path}{$config['avatar_path']}/",
			'T_AVATAR_GALLERY_PATH'	=> "{$url_phpbb_root_path}{$config['avatar_gallery_path']}/",
			'T_ICONS_PATH'			=> "{$url_phpbb_root_path}{$config['icons_path']}/",
			'T_RANKS_PATH'			=> "{$url_phpbb_root_path}{$config['ranks_path']}/",
			'T_UPLOAD_PATH'			=> "{$url_phpbb_root_path}{$config['upload_path']}/",
			'T_STYLESHEET_LINK'		=> (!$user->theme['theme_storedb']) ? "{$url_phpbb_root_path}styles/" . $user->theme['theme_path'] . '/theme/stylesheet.css' : "{$url_phpbb_root_path}style.$phpEx?sid=$user->session_id&amp;id=" . $user->theme['style_id'] . '&amp;lang=' . $user->data['user_lang'],
		
			'SITE_LOGO_IMG'			=> rewrite_user_img($user->img('site_logo')),
		));
	}
	
	// Remember that we were called
	$called = true;
}
?>
