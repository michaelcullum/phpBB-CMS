<?php
/**
*
* @package acp
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
* @package acp
*/
class acp_cms_main
{
	var $u_action;

	function main($id, $mode)
	{
		global $config, $db, $user, $auth, $template, $cms_root_path, $phpbb_root_path, $phpbb_admin_path, $phpEx;

		$action = request_var('action', '');
		
		$version_type_options = array(
			VERSION_TYPE_HTML		=> $user->lang['VERSION_TYPE_HTML'],
			VERSION_TYPE_CATEGORY	=> $user->lang['VERSION_TYPE_CATEGORY'],
			VERSION_TYPE_FILE		=> $user->lang['VERSION_TYPE_FILE'],
			VERSION_TYPE_LINK		=> $user->lang['VERSION_TYPE_LINK'],
			VERSION_TYPE_MODULE		=> $user->lang['VERSION_TYPE_MODULE'],
		);

		if ($action)
		{
			if (!confirm_box(true))
			{
				switch ($action)
				{
					case 'views':
						$confirm = true;
						$confirm_lang = 'RESET_VIEWS_CONFIRM';
					break;
					case 'date':
						$confirm = true;
						$confirm_lang = 'RESET_CMS_DATE_CONFIRM';
					break;
					case 'stats':
						$confirm = true;
						$confirm_lang = 'RESYNC_CMS_STATS_CONFIRM';
					break;
					case 'page_data':
						$confirm = true;
						$confirm_lang = 'RESYNC_PAGE_DATA_CONFIRM';
					break;

					default:
						$confirm = true;
						$confirm_lang = 'CONFIRM_OPERATION';
					break;
				}

				if ($confirm)
				{
					confirm_box(false, $user->lang[$confirm_lang], build_hidden_fields(array(
						'i'			=> $id,
						'mode'		=> $mode,
						'action'	=> $action,
					)));
				}
			}
			else
			{
				switch ($action)
				{

					case 'views':
						$sql = 'UPDATE ' . PAGES_TABLE . ' SET page_views = 0';
						$db->sql_query($sql);
						
						$sql = 'UPDATE ' . PAGES_VERSIONS_TABLE . ' SET version_views = 0';
						$db->sql_query($sql);
						
						add_log('admin', 'LOG_RESET_PAGE_VIEWS');
					break;
					
					case 'date':
						set_config('cms_startdate', time() - 1);
						add_log('admin', 'LOG_RESET_CMS_DATE');
					break;
					
					case 'stats':
						$sql = 'SELECT p.page_id, version_type, page_versions, page_views, COUNT(v.version_id) n_page_versions, SUM(version_views) n_page_views
							FROM ' . PAGES_TABLE . ' p
							JOIN ' . PAGES_VERSIONS_TABLE . ' v
								ON p.page_id = v.page_id
							GROUP BY p.page_id';
						$result = $db->sql_query($sql);
						
						$num_pages = $num_versions = 0;
						while ($row = $db->sql_fetchrow($result))
						{
							$num_pages++;
							
							if ($row['page_versions'] != $row['n_page_versions'] || $row['page_views'] != $row['n_page_views'])
							{
								// Only update if the values are different
								$sql = 'UPDATE ' . PAGES_TABLE . '
									SET page_views = ' . $row['n_page_views'] . ', page_versions = ' . $row['n_page_versions'] . '
									WHERE page_id = ' . $row['page_id'];
								$db->sql_query($sql);
							}
							
							$num_versions += $row['n_page_versions'];
						}
						set_config('num_pages', $num_pages, true);
						set_config('num_versions', $num_versions, true);
						
						add_log('admin', 'LOG_RESYNC_CMS_STATS');
					break;
					
					case 'page_data':
						
						require($phpbb_root_path . 'includes/functions_cms_page.' . $phpEx);
						refresh_home_page();
						
						$sql = 'SELECT page_id, page_url
							FROM ' . PAGES_TABLE;
						$result = $db->sql_query($sql);
						
						while ($row = $db->sql_fetchrow($result))
						{
							$page_url = get_page_url($row['page_id'], true);
							
							if ($page_url != $row['page_url'])
							{
								// Only update if the values are different
								$sql = 'UPDATE ' . PAGES_TABLE . "
									SET page_url = '" . $page_url . "'
									WHERE page_id = " . $row['page_id'];
								$db->sql_query($sql);
							}
						}
						
						$sql = 'SELECT v.version_id, v.page_id, v.user_id v_user_id, version_time, p.user_id p_user_id, page_time,
								(SELECT COUNT(log_id)
									FROM ' . PAGES_LOG_TABLE . " l
									WHERE l.page_id = v.page_id
										AND log_operation = 'LOG_PAGE_ADD') num_page_add,
								(SELECT COUNT(log_id)
									FROM " . PAGES_LOG_TABLE . " l
									WHERE l.version_id = v.version_id
										AND log_operation = 'LOG_VERSION_ADD') num_version_add
							FROM " . PAGES_VERSIONS_TABLE . ' v
							JOIN ' . PAGES_TABLE . ' p
								ON p.page_id = v.page_id
							HAVING num_page_add = 0
								OR num_version_add = 0';
						$result = $db->sql_query($sql);
						
						$page_add = $sql_ary = array();
						while ($row = $db->sql_fetchrow($result))
						{
							if(!$row['num_page_add'] && !isset($page_add[$row['page_id']]))
							{
								$sql_ary[] = array(
									'log_id'		=> 0,
									'user_id'		=> $row['p_user_id'],
									'page_id'		=> $row['page_id'],
									'version_id'	=> 0,
									'log_time'		=> $row['page_time'],
									'log_operation'	=> 'LOG_PAGE_ADD',
								);
								
								// Make sure we only add a record once
								$page_add[$row['page_id']] = true;
							}
							
							if(!$row['num_version_add'])
							{
								$sql_ary[] = array(
									'log_id'		=> 0,
									'user_id'		=> $row['v_user_id'],
									'page_id'		=> $row['page_id'],
									'version_id'	=> $row['version_id'],
									'log_time'		=> $row['version_time'],
									'log_operation'	=> 'LOG_VERSION_ADD',
								);
							}
						}
						
						if (sizeof($sql_ary))
						{
							$db->sql_multi_insert(PAGES_LOG_TABLE, $sql_ary);
						}
						
						add_log('admin', 'LOG_RESYNC_PAGE_DATA');
					break;
				}
			}
		}

		$cmsdays = (time() - $config['cms_startdate']) / 86400;

		$views_per_day = sprintf('%.2f', get_num_views() / $cmsdays);

		if ($views_per_day > get_num_views())
		{
			$views_per_day = get_num_views();
		}
		
		// Try and work out the TinyMCE version
		$tiny_mce_version = false;
		if (file_exists($phpbb_root_path . 'adm/style/tiny_mce/tiny_mce.js'))
		{
			$tiny_mce = file_get_contents($phpbb_root_path . 'adm/style/tiny_mce/tiny_mce.js');
			
			preg_match('/majorVersion:[\'" ]?([0-9.]+)[\'"]?/', $tiny_mce, $match1);
			preg_match('/minorVersion:[\'" ]?([0-9.]+)[\'"]?/', $tiny_mce, $match2);
			
			if (isset($match1[1]) && isset($match2[1]))
			{
				$tiny_mce_version = $match1[1] . '.' . $match2[1];
			}
		}
		
		$template->assign_vars(array(
			'TOTAL_PAGES'			=> $config['num_pages'],
			'TOTAL_VERSIONS'		=> $config['num_versions'],
			'TOTAL_VIEWS'			=> get_num_views(),
			'VIEWS_PER_DAY'			=> $views_per_day,
			'START_DATE'			=> $user->format_date($config['cms_startdate']),
			'CMS_VERSION'			=> $config['cms_version'],
			'TINY_MCE_VERSION'		=> $tiny_mce_version,

			'U_ACTION'			=> $this->u_action,
			'U_ADMIN_LOG'		=> append_sid("{$phpbb_admin_path}index.$phpEx", 'i=logs&amp;mode=admin'),

			'S_ACTION_OPTIONS'	=> ($auth->acl_get('a_board')) ? true : false,
			)
		);
		
		$timespan = ($config['form_token_lifetime'] == -1) ? -1 : max(30, $config['form_token_lifetime']);
		
		$sql = 'SELECT p.page_id, page_lock_id, page_lock_time, page_title, username, user_colour
		FROM ' . PAGES_TABLE . ' p
		JOIN ' . PAGES_VERSIONS_TABLE . ' v
			ON v.version_id = p.version_id
		JOIN ' . USERS_TABLE . ' u
			ON u.user_id = p.page_lock_id
		JOIN ' . SESSIONS_TABLE . '
			ON session_user_id = u.user_id
				AND session_admin = 1
		WHERE page_lock_id <> 0
			AND page_lock_id <> ' . $user->data['user_id'] . '
			' . ( ($timespan != -1) ? 'AND page_lock_time <= ' . ($timespan + time()) : '' ) . '
		ORDER BY page_lock_time DESC';
		$result = $db->sql_query($sql);
		
		$profile_url = append_sid("{$phpbb_admin_path}index.$phpEx", 'i=users&amp;mode=overview');
		while ($row = $db->sql_fetchrow($result))
		{
			$template->assign_block_vars('locks', array(
				'U_PAGE'		=> append_sid("{$phpbb_admin_path}index.$phpEx", 'i=cms_page_editor&amp;action=info&amp;p=' . $row['page_id']),
				'PAGE_TITLE'	=> $row['page_title'],
				'USERNAME'		=> get_username_string('full', $row['page_lock_id'], $row['username'], $row['user_colour'], false, $profile_url),
				'DATE'			=> $user->format_date($row['page_lock_time']),
			));
		}

		$log_data = array();
		$log_count = 0;

		if ($auth->acl_get('a_viewlogs'))
		{
			view_cms_log('admin', $log_data, $log_count, 5);

			foreach ($log_data as $row)
			{
				$template->assign_block_vars('log', array(
					'USERNAME'	=> $row['username_full'],
					'IP'		=> $row['ip'],
					'DATE'		=> $user->format_date($row['time']),
					'ACTION'	=> $row['action'])
				);
			}
		}
		
		if(get_num_views())
		{
			$sql = 'SELECT p.page_id, page_views, page_title, page_time, version_type, version_extension
				FROM ' . PAGES_TABLE . ' p
				JOIN ' . PAGES_VERSIONS_TABLE . ' v
					ON v.version_id = p.version_id
				WHERE page_views > 0
				ORDER BY page_views DESC';
			$result = $db->sql_query_limit($sql, 10);
		
			if ($row = $db->sql_fetchrow($result))
			{
				do
				{
					$views_pct = ( (get_num_views()) ? min(100, ($row['page_views'] / get_num_views()) * 100) : 0);
				
					$page_days = max(1, round((time() - $row['page_time']) / 86400));
					$views_per_day = $row['page_views'] / $page_days;
				
					$template->assign_block_vars('popular', array(
						'U_PAGE'		=> append_sid("{$phpbb_admin_path}index.$phpEx", 'i=cms_page_editor&amp;action=info&amp;p=' . $row['page_id']),
						'PAGE_TITLE'	=> $row['page_title'],
					
						'PAGE_TYPE'			=> ($row['version_type'] == VERSION_TYPE_FILE) ? sprintf($user->lang['VERSION_TYPE_FILE_INFO'], $row['version_extension']) : $version_type_options[$row['version_type']],
						'PAGE_VIEWS'		=> $row['page_views'],
						'PAGE_VIEWS_INFO'	=> sprintf($user->lang['PAGE_VIEWS_INFO'], $views_pct, $views_per_day),
					));
				}
			
				while ($row = $db->sql_fetchrow($result));
			}
			$db->sql_freeresult($result);
		}
		
		$sql = 'SELECT l.*, page_title
			FROM ' . PAGES_LINKS_TABLE . ' l
			JOIN ' . PAGES_TABLE . ' p
				ON p.page_id = link_page_id
			WHERE link_external = 1
			ORDER BY link_refers DESC';
		$result = $db->sql_query_limit($sql, 10);
		
		if ($row = $db->sql_fetchrow($result))
		{
			do
			{
				$link_days = max(1, round((time() - $row['link_time']) / 86400));
				$refers_per_day = $row['link_refers'] / $link_days;
				$template->assign_block_vars('links', array(
					'U_EXTERNAL'	=> $row['link_url'],
					'LINK_URL'		=> $row['link_url'],
					'LINK_REFERS'	=> $row['link_refers'],
					'REFERS_DAY'	=> sprintf($user->lang['REFERS_DAY'], $refers_per_day),
					
					'U_PAGE'		=> append_sid("{$phpbb_admin_path}index.$phpEx", 'i=cms_page_editor&amp;action=info&amp;p=' . $row['link_page_id']),
					'PAGE_TITLE'	=> $row['page_title'],
				));
			}
			
			while ($row = $db->sql_fetchrow($result));
		}
		$db->sql_freeresult($result);
		
		if ($config['num_pages'] == 0)
		{
			$template->assign_var('S_NO_PAGES', true);
		}
		else
		{
			$sql = 'SELECT 1 FROM ' . PAGES_TABLE . '
				WHERE page_enabled = 1';
			$result = $db->sql_query($sql);
			if ( !($row = $db->sql_fetchrow($result)) )
			{
				$template->assign_var('S_NO_ENABLED_PAGES', true);
			}
			$db->sql_freeresult($result);
		}
		
		$this->tpl_name = 'acp_cms_main';
		$this->page_title = 'ACP_CMS_MAIN';
	}
}

/**
* View log
*/
function view_cms_log($mode, &$log, &$log_count, $limit = 0, $offset = 0, $page_id = 0, $user_id = 0, $limit_days = 0, $sort_by = 'l.log_time DESC')
{
	// This is really a bit crap... nicked almost entirely from functions_admin.php just for the sake of some minor changes
	global $db, $user, $auth, $phpEx, $phpbb_root_path, $phpbb_admin_path;

	$topic_id_list = $reportee_id_list = $is_auth = $is_mod = array();

	$profile_url = (defined('IN_ADMIN')) ? append_sid("{$phpbb_admin_path}index.$phpEx", 'i=users&amp;mode=overview') : append_sid("{$phpbb_root_path}memberlist.$phpEx", 'mode=viewprofile');
	
	// Only get logs for the CMS please
	$sql_where = $db->sql_in_set('l.log_operation', array(
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
		'LOG_PAGE_CONTENTS_ENABLE',
		'LOG_PAGE_CONTENTS_DISABLE',
		'LOG_PAGE_REMOVED',
		'LOG_PAGE_ADD',
		'LOG_PAGE_EDIT',
		'LOG_VERSION_ADD',
		'LOG_VERSION_REMOVED',
		'LOG_PAGE_REVERT',
		'LOG_CMS_INSTALL',
		'LOG_CMS_UPDATE',
	));
	
	if ($user_id)
	{
		$sql_where .= 'AND l.user = ' . (int) $user_id;
	}
	else if (is_array($page_id))
	{
		$sql_where .= 'AND ' . $db->sql_in_set('l.page_id', array_map('intval', $page_id));
	}
	else if ($page_id)
	{
		$sql_where .= 'AND l.page_id = ' . (int) $page_id;
	}

	$sql = "SELECT l.*, u.username, u.username_clean, u.user_colour
		FROM " . LOG_TABLE . " l, " . USERS_TABLE . ' u
		WHERE u.user_id = l.user_id
			' . (($limit_days) ? "AND l.log_time >= $limit_days" : '') . "
			AND $sql_where
		ORDER BY $sort_by";
	$result = $db->sql_query_limit($sql, $limit, $offset);

	$i = 0;
	$log = array();
	$info = ($mode == 'admin_info') ? '_INFO' : '';
	while ($row = $db->sql_fetchrow($result))
	{
		if ($row['topic_id'])
		{
			$topic_id_list[] = $row['topic_id'];
		}

		if ($row['reportee_id'])
		{
			$reportee_id_list[] = $row['reportee_id'];
		}

		$log[$i] = array(
			'id'				=> $row['log_id'],

			'user_id'			=> $row['user_id'],
			'username'			=> $row['username'],
			'username_full'		=> get_username_string('full', $row['user_id'], $row['username'], $row['user_colour'], false, $profile_url),

			'ip'				=> $row['log_ip'],
			'time'				=> $row['log_time'],
			'log_operation'		=> $row['log_operation'],

			//'viewpage'		=> append_sid(generate_url($row['page_id'], $row['page_url'])),
			'action'			=> (isset($user->lang[$row['log_operation'] . $info])) ? $user->lang[$row['log_operation'] . $info] : '{' . ucfirst(str_replace('_', ' ', $row['log_operation'])) . '}',
		);

		if (!empty($row['log_data']))
		{
			$log_data_ary = unserialize($row['log_data']);

			if (isset($user->lang[$row['log_operation']]))
			{
				// Check if there are more occurrences of % than arguments, if there are we fill out the arguments array
				// It doesn't matter if we add more arguments than placeholders
				if ((substr_count($log[$i]['action'], '%') - sizeof($log_data_ary)) > 0)
				{
					$log_data_ary = array_merge($log_data_ary, array_fill(0, substr_count($log[$i]['action'], '%') - sizeof($log_data_ary), ''));
				}

				$log[$i]['action'] = vsprintf($log[$i]['action'], $log_data_ary);

				// If within the admin panel we do not censor text out
				if (defined('IN_ADMIN'))
				{
					$log[$i]['action'] = bbcode_nl2br($log[$i]['action']);
				}
				else
				{
					$log[$i]['action'] = bbcode_nl2br(censor_text($log[$i]['action']));
				}
			}
			else
			{
				$log[$i]['action'] .= '<br />' . implode('', $log_data_ary);
			}

			/* Apply make_clickable... has to be seen if it is for good. :/
			// Seems to be not for the moment, reconsider later...
			$log[$i]['action'] = make_clickable($log[$i]['action']);
			*/
		}

		$i++;
	}
	$db->sql_freeresult($result);

	$sql = 'SELECT COUNT(l.log_id) AS total_entries
		FROM ' . LOG_TABLE . ' l
		WHERE l.log_type = ' . LOG_ADMIN . "
			AND l.log_time >= $limit_days
			AND $sql_where";
	$result = $db->sql_query($sql);
	$log_count = (int) $db->sql_fetchfield('total_entries');
	$db->sql_freeresult($result);

	return;
}
?>
