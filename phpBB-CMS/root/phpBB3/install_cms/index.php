<?php
/**
*
* @package umil
* @copyright (c) 2012 Michael Cullum (Unknown Bliss of http://michaelcullum.com)
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
* @ignore
*/

define('UMIL_AUTO', true);
define('IN_PHPBB', true);
$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : './../';
$phpEx = substr(strrchr(__FILE__, '.'), 1);

include($phpbb_root_path . 'common.' . $phpEx);
include_once($phpbb_root_path . 'includes/constants_cms.' . $phpEx);

$user->session_begin();
$auth->acl($user->data);
// Add the ACP common pack so module names are displayed correctly
$user->setup('acp/cms_common');
$user->add_lang('mods/permissions_cms');
$user->add_lang('mods/info_acp_news');

if (!file_exists($phpbb_root_path . 'umil/umil_auto.' . $phpEx))
{
	trigger_error('Please download the latest UMIL (Unified MOD Install Library) from: <a href="http://www.phpbb.com/mods/umil/">phpBB.com/mods/umil</a>', E_USER_ERROR);
}

$mod_name = 'PHPBB_CMS';
$version_config_name = 'cms_version';
$language_file = 'cms_install';

// If we're installing, give the user some basic settings
if(!isset($config['cms_version']))
{
	$user->add_lang('acp/cms_settings');
	
	// Calculate the CMS path from the phpBB path
	$cms_path = dirname($config['script_path']);
	$cms_path = (substr($cms_path, -1) == '/') ? substr($cms_path, 0, -1) : $cms_path;
	
	$options = array(
		'legend2'		=> 'PHPBB_CMS',
		'cms_path'		=> array('lang' => 'CMS_PATH',		'validate' => 'script_path',	'default' => $cms_path,							'type' => 'text::255',		'explain' => true),
		'mod_rewrite'	=> array('lang' => 'MOD_REWRITE',	'validate' => 'bool',			'default' => (int) mod_rewrite_enabled(),	'type' => 'radio:yes_no',	'explain' => true),
	);
}

$versions = array(
	'0.0.1'	=> array(
		'config_add' => array(
			array('br_newlines',			'0',							0),
			array('cms_disable',			'0',							0),
			array('cms_disable_msg',		'',								0),
			array('cms_parse_template',		'',								0),
			array('cms_path',				request_var('cms_path', ''),	0),
			array('cms_run_cron',			0,								1),
			array('cms_startdate',			time(),							0),
			array('cms_upload_path',		'uploads',						0),
			array('email_notifications',	'0',							0),
			array('enable_spellchecker',	'1',							0),
			array('force_www',				'0',							0),
			array('home_page',				'0',							0),
			array('log_incomming_links',	'1',							0),
			array('mod_rewrite',			request_var('mod_rewrite', 1),	0),
			array('num_pages',				'0',							1),
			array('num_versions',			'0',							1),
			array('preview_style',			'1',							0),
			array('send_linkbacks',			'1',							0),
			array('version_control',		'1',							0),
			
			// News module
			array('news_forum',				'0',							0),
			array('news_display_children',	'1',							0),
		),
		
		'table_add' => array(
			array(PAGES_TABLE, array(
				'COLUMNS'		=> array(
					'page_id'				=> array('UINT',		NULL,	'auto_increment'),
					'page_enabled'			=> array('BOOL',		1),
					'page_display'			=> array('BOOL',		1),
					'parent_id'				=> array('UINT',		0),
					'parent_enabled'		=> array('BOOL',		1),
					'parent_display'		=> array('BOOL',		1),
					'left_id'				=> array('UINT',		0),
					'right_id'				=> array('UINT',		0),
					'version_id'			=> array('UINT',		0),
					'cur_version_number'	=> array('UINT',		1), // Not necessarily the current version's number
					'page_title'			=> array('VCHAR',		''),
					'page_slug'				=> array('VCHAR',		''),
					'page_url'				=> array('VCHAR',		''),
					'page_versions'			=> array('UINT',		1),
					'page_edits'			=> array('UINT',		1),
					'user_id'				=> array('UINT',		0),
					'page_time'				=> array('TIMESTAMP',	0),
					'page_last_mod'			=> array('TIMESTAMP',	0),
					'page_views'			=> array('UINT',		0),
					'page_style'			=> array('UINT',		0),
					'page_contents_table'	=> array('BOOL',		0),
					'page_lock_id'			=> array('UINT',		0),
					'page_lock_time'		=> array('TIMESTAMP',	0),
				),

				'PRIMARY_KEY'	=> 'page_id',

				'KEYS'			=> array(
					'left_right_id'	=> array('INDEX',	array('left_id', 'right_id')),
					'parent_id'		=> array('INDEX',	'parent_id'),
					'user_id'		=> array('INDEX',	'user_id'),
					'page_lock_id'	=> array('INDEX',	'page_lock_id'),
					'version_id'	=> array('INDEX',	'version_id'),
					'page_url'		=> array('INDEX',	'page_url'),
				),
			)),
			
			// Stores internal and external links between pages
			array(PAGES_LINKS_TABLE, array(
				'COLUMNS'		=> array(
					'link_id'			=> array('UINT',		NULL,	'auto_increment'),
					'page_id'			=> array('UINT',		0),
					'version_id'		=> array('UINT',		0),
					'link_external'		=> array('BOOL',		0),
					'link_page_id'		=> array('UINT',		0),
					'link_url'			=> array('VCHAR',		''),
					'link_refers'		=> array('UINT',		0),
					'link_time'			=> array('TIMESTAMP',	0),
					'link_title'		=> array('VCHAR',		''),
					'link_sitename'		=> array('VCHAR',		''),
					'link_processed'	=> array('BOOL',		1),
				),

				'PRIMARY_KEY'	=> 'link_id',

				'KEYS'			=> array(
					'page_id'		=> array('INDEX',	'page_id'),
					'version_id'	=> array('INDEX',	'version_id'),
					'link_page_id'	=> array('INDEX',	'link_page_id'),
					'link_url'		=> array('INDEX',	'link_url'),
				),
			)),
			
			// Stores history of actions applied to pages
			// Additional to LOG_TABLE so clearing the log doesn't clear the history
			array(PAGES_LOG_TABLE, array(
				'COLUMNS'		=> array(
					'page_log_id'	=> array('UINT',		NULL,	'auto_increment'),
					'log_id'		=> array('UINT',		0),
					'user_id'		=> array('UINT',		0),
					'page_id'		=> array('UINT',		0),
					'version_id'	=> array('UINT',		0),
					'log_time'		=> array('TIMESTAMP',	0),
					'log_operation'	=> array('TEXT',		''),
				),
				
				'PRIMARY_KEY'	=> 'page_log_id',

				'KEYS'			=> array(
					'log_id'		=> array('INDEX',	'log_id'),
					'user_id'		=> array('INDEX',	'user_id'),
					'page_id'		=> array('INDEX',	'page_id'),
					'version_id'	=> array('INDEX',	'version_id'),
				),
			)),
			
			// Stores old URLs and the page that used them so we can redirect users
			array(PAGES_URLS_TABLE, array(
				'COLUMNS'		=> array(
					'url'		=> array('VCHAR',	''),
					'page_id'	=> array('UINT',	0),
				),

				'PRIMARY_KEY'	=> 'url',

				'KEYS'			=> array(
					'page_id'	=> array('INDEX',	'page_id'),
				),
			)),

			array(PAGES_VERSIONS_TABLE, array(
				'COLUMNS'		=> array(
					'version_id'				=> array('UINT',		NULL,	'auto_increment'),
					'page_id'					=> array('UINT',		0),
					'version_number'			=> array('UINT',		1),
					'version_draft'				=> array('BOOL',		0),
					'version_type'				=> array('BOOL',		0), // Its not a bool, but the only way to get an unsigned 1 digit int :/
					'version_html'				=> array('MTEXT',		''),
					'version_physical_filename'	=> array('VCHAR',		''),
					'version_real_filename'		=> array('VCHAR',		''),
					'version_extension'			=> array('XSTEXT_UNI',	''),
					'version_mimetype'			=> array('XSTEXT_UNI',	''),
					'version_image'				=> array('BOOL',		0),
					'version_filesize'			=> array('INT:20',		0),
					'version_module_basename'	=> array('VCHAR',		''),
					'version_module_mode'		=> array('VCHAR',		''),
					'version_link_type'			=> array('BOOL',		0),
					'version_link_url'			=> array('VCHAR',		''),
					'version_link_id'			=> array('UINT',		0),
					'version_checksum'			=> array('VCHAR:32',	''),
					'user_id'					=> array('UINT',		0),
					'version_time'				=> array('TIMESTAMP',	0),
					'version_views'				=> array('UINT',		0),
					'version_desc'				=> array('VCHAR',		''),
				),

				'PRIMARY_KEY'	=> 'version_id',

				'KEYS'			=> array(
					'page_id'			=> array('INDEX',	'page_id'),
					'version_number'	=> array('INDEX',	'version_number'),
					'user_id'			=> array('INDEX',	'user_id'),
				),
			)),
		),
		
		'module_add' => array(
			array('acp',	0, array(
					'module_langname'	=> 'ACP_CAT_CMS',
					'after'				=> 'ACP_CAT_GENERAL',
				),
			),
			array('acp',	'ACP_CAT_CMS',					'ACP_CAT_CMS_GENERAL'),
			array('acp',	'ACP_CAT_CMS',					'ACP_CAT_CMS_CONFIGURATION'),
			array('acp',	'ACP_CAT_CMS',					'ACP_CAT_CMS_PAGES'),
			array('acp',	'ACP_CAT_CMS',					'ACP_CAT_CMS_MODULES'),

			array('acp',	'ACP_CAT_CMS_GENERAL',			array('module_basename'	=> 'cms_main')),
			array('acp',	'ACP_CAT_CMS_CONFIGURATION',	array('module_basename'	=> 'cms_settings')),
			array('acp',	'ACP_CAT_CMS_PAGES',			array('module_basename'	=> 'cms_page_editor')),
			array('acp',	'ACP_CAT_CMS_MODULES',			array('module_basename'	=> 'cms_news')),
		),
		
		'permission_add'	=> array(
			array('a_cms', true),
		),
		
		// Give admins permission by default
		'permission_set'	=> array(
			array('ROLE_ADMIN_FULL', 'a_cms'),
		),
		
		'custom'	=> array(
			'add_demo_pages',
			'write_cms_config',
			'log_action',
		),
	),
);

include($phpbb_root_path . 'umil/umil_auto.' . $phpEx);

/*
* Check if mod_rewrite is available on the server
*/
function mod_rewrite_enabled()
{
	if(!function_exists('apache_get_modules'))
	{
		return false;
	}
	
	return in_array('mod_rewrite', apache_get_modules());
}

/*
* Add some sample pages to the CMS for demonstration purposes
*/
function add_demo_pages($action, $version)
{
	global $phpbb_root_path, $phpEx, $user;
	
	// Only add on install
	if ($action != 'install')
	{
		return;
	}
	
	$user->add_lang('cms_common');
	
	require($phpbb_root_path . 'includes/functions_cms.' . $phpEx);
	require($phpbb_root_path . 'includes/functions_cms_page.' . $phpEx);
	require($phpbb_root_path . 'includes/page.' . $phpEx);
	
	$page = new page;
	$page->data = array_merge($page->data, array(
		'page_title'				=> $user->lang['SITE_INDEX'],
		'page_slug'					=> make_slug($user->lang['SITE_INDEX']),
		'version_type'				=> VERSION_TYPE_HTML,
		'version_html'				=> $user->lang['INDEX_CONTENT'],
	));
	
	$page->validate();
	$page->save(true);
	$errors = $page->errors;
	
	$page = new page;
	$page->data = array_merge($page->data, array(
		'page_title'				=> $user->lang['COMMUNITY'],
		'page_slug'					=> make_slug($user->lang['COMMUNITY']),
		'version_type'				=> VERSION_TYPE_LINK,
		'version_link_type'			=> LINK_TYPE_PHPBB,
	));
	
	$page->validate();
	$page->save(true);
	$errors = array_merge($errors, $page->errors);
	
	return array(
		'command'	=> 'ADD_DEMO_PAGES',
		'result'	=> (sizeof($errors)) ? implode('<br />', $errors) : 'SUCCESS',
	);
}

/*
* Write the CMS config file
*/
function write_cms_config($action, $version)
{
	// Déjà vu? Yeah this is a modified version of create_config_file(), used by the phpBB installer
	global $phpbb_root_path, $phpEx;
	
	// Only write on install
	if ($action != 'install')
	{
		return;
	}
	
	// We make the assumption that the CMS root path is in the directory above the phpBB one
	$cms_root_path = $phpbb_root_path . '../';
	
	// Calculate the path from the CMS root, to the phpBB root
	$path = dirname(dirname(__FILE__)); 
	$path = str_replace('\\', '/', $path); 
	$path = explode('/', $path);
	$pos = strrpos($path, '/');
	$path = substr($path, ($pos + 1)) . '/';

	// Time to convert the data provided into a config file
	$config_data = "<?php\n";
	$config_data .= "// phpBB CMS auto-generated configuration file\n\n";
	$config_data .= "// Set path to phpBB relative to the current folder, e.g. “phpBB3/”. Include a trailing slash.\n";
	$config_data .= "// If the UMIL installer was unable to write to this file, you'll need to set this manually.\n";
	$config_data .= "\$phpbb_root_path = \$cms_root_path . '" . str_replace("'", "\\'", str_replace('\\', '\\\\', $path)) . "';\n";
	$config_data .= '?' . '>'; // I've done this to prevent highlighting editors getting confused!

	// Attempt to write out the config file directly. If it works, this is the easiest way to do it ...
	if ((file_exists($cms_root_path . 'config.' . $phpEx) && is_writable($cms_root_path . 'config.' . $phpEx)) || is_writable($cms_root_path))
	{
		// Assume it will work ... if nothing goes wrong below
		$written = true;

		if (!($fp = @fopen($cms_root_path . 'config.' . $phpEx, 'w')))
		{
			$written = false;
		}

		if (!(@fwrite($fp, $config_data)))
		{
			$written = false;
		}

		@fclose($fp);

		if ($written)
		{
			// We may revert back to chmod() if we see problems with users not able to change their config.php file directly
			phpbb_chmod($cms_root_path . 'config.' . $phpEx, CHMOD_READ);
		}
	}

	return array(
		'command'	=> array(
			'WRITE_CMS_CONFIG',
			$cms_root_path . 'config.' . $phpEx,
		),
		'result'	=> ($written) ? 'SUCCESS' : 'WRITE_CMS_CONFIG_FAIL',
	);
}

/*
* Log the install/update action in the ACP or remove CMS logs if we're uninstalling
*/
function log_action($action, $version)
{
	if($action == 'uninstall')
	{
		global $db;
		
		$sql_where = $db->sql_in_set('log_operation', array(
			'LOG_INTERNAL_BROKEN_LINK',
			'LOG_EXTERNAL_BROKEN_LINK',
			
			'LOG_RESYNC_CMS_STATS',
			'LOG_RESYNC_PAGE_DATA',
			'LOG_RESET_PAGE_VIEWS',
			'LOG_RESET_CMS_DATE',
			'LOG_CONFIG_CMS_SETTINGS',
			'LOG_CONFIG_WRITING_SETTINGS',
			
			'LOG_INCOMMING_LINK',
			'LOG_PAGE_TITLE',
			'LOG_PAGE_URL',
			'LOG_PAGE_PARENT',
			'LOG_PAGE_DISABLE',
			'LOG_PAGE_ENABLE',
			'LOG_PAGE_NAV_DISPLAY',
			'LOG_PAGE_NAV_HIDE',
			'LOG_PAGE_STYLE',
			'LOG_PAGE_MOVE_DOWN',
			'LOG_PAGE_MOVE_UP',
			'LOG_PAGE_REMOVED',
			'LOG_PAGE_ADD',
			'LOG_PAGE_EDIT',
			'LOG_VERSION_ADD',
			'LOG_VERSION_REMOVED',
			'LOG_PAGE_REVERT',
			'LOG_CMS_INSTALL',
			'LOG_CMS_UPDATE',
		));
		
		$sql = 'DELETE FROM ' . LOG_TABLE . "
			WHERE $sql_where";
		$db->sql_query($sql);
	}
	else
	{
		add_log('admin', 'LOG_CMS_' . strtoupper($action), $version);
	}
	
	return array(
		'command'	=> array(
			'LOG_ACTION_CMS_' . strtoupper($action),
			$version,
		),
		'result'	=> 'SUCCESS',
	);
}

?>
