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
class acp_cms_page_editor
{
	var $u_action;

	function main($id, $mode)
	{
		global $config, $db, $user, $auth, $template, $cache;
		global $cms_root_path, $phpbb_root_path, $phpbb_admin_path, $phpEx, $table_prefix, $page_id, $module;
		
		require($phpbb_root_path . 'includes/functions_cms_page.' . $phpEx);
		require($phpbb_root_path . 'includes/page.' . $phpEx);
		
		$user->add_lang('acp/cms_page_editor');
		$this->tpl_name = 'acp_cms_page_editor';
		
		$version_type_options = array(
			VERSION_TYPE_HTML		=> $user->lang['VERSION_TYPE_HTML'],
			VERSION_TYPE_CATEGORY	=> $user->lang['VERSION_TYPE_CATEGORY'],
			VERSION_TYPE_FILE		=> $user->lang['VERSION_TYPE_FILE'],
			VERSION_TYPE_LINK		=> $user->lang['VERSION_TYPE_LINK'],
			VERSION_TYPE_MODULE		=> $user->lang['VERSION_TYPE_MODULE'],
		);
		
		$sort_dir_text = array('a' => $user->lang['ASCENDING'], 'd' => $user->lang['DESCENDING']);

		$this->parent_id = request_var('parent_id', 0);
		$page_id = request_var('p', 0);
		
		$version_id = request_var('v', 0);
		$action = request_var('action', '');
		$errors = array();
		
		// Called by TinyMCE - running as popup
		if ($mode == 'upload')
		{
			$this->tpl_name = 'acp_cms_upload';
			$this->upload($action);
			
			return;
		}
		
		switch ($action)
		{
			case 'delete':
			
				if (!$page_id && !$version_id)
				{
					trigger_error($user->lang['NO_PAGE_ID'] . adm_back_link($this->u_action . '&amp;parent_id=' . $this->parent_id), E_USER_WARNING);
				}
				
				$sql = 'SELECT p.*, username, session_time
					FROM ' . PAGES_TABLE . ' p
					LEFT JOIN ' . USERS_TABLE . ' u
						ON u.user_id = page_lock_id
					LEFT JOIN ' . SESSIONS_TABLE . '
						ON session_user_id = u.user_id
							AND session_admin = 1
					' . ( ($version_id) ? ' JOIN ' . PAGES_VERSIONS_TABLE . ' v ON v.page_id = p.page_id' : '' ) . '
					WHERE ' . ( ($page_id) ? "page_id = '" . $db->sql_escape($page_id) . "'" : "v.version_id = '" . $db->sql_escape($version_id) . "'" ) . '
					GROUP BY p.page_id';
				$result = $db->sql_query($sql);
				
				if (!($row = $db->sql_fetchrow($result)))
				{
					trigger_error($user->lang['NO_PAGE'] . adm_back_link($this->u_action . '&amp;parent_id=' . $this->parent_id), E_USER_WARNING);
				}

				if (confirm_box(true))
				{
					$errors = ($version_id) ? delete_version($version_id) : delete_page($page_id);
					
					if ($errors === false)
					{
						trigger_error($user->lang['NO_PAGE'] . adm_back_link($this->u_action . '&amp;parent_id=' . $this->parent_id), E_USER_WARNING);
					}
					
					if (!sizeof($errors))
					{
						if ($version_id)
						{
							trigger_error($user->lang['VERSION_DELETED'] . adm_back_link($this->u_action . '&amp;action=info&amp;p=' . $row['page_id']));
						}
						else
						{
							trigger_error($user->lang['PAGE_DELETED'] . adm_back_link($this->u_action . '&amp;parent_id=' . $row['parent_id']));
						}
					}
				}
				else
				{
					$confirm_lang = 'DELETE_' . ( ($version_id) ? 'VERSION' : 'PAGE' );
					
					if (is_locked($row['page_lock_id'], $row['page_lock_time'], $row['session_time']))
					{
						$confirm_lang = sprintf($user->lang[$confirm_lang . '_LOCK'], $user->format_date($row['page_lock_time']), $row['username']);
					}
					
					if(!$version_id)
					{
						$this->link_breakage($page_id);
					}
					
					confirm_box(false, $confirm_lang, build_hidden_fields(array(
						'i'			=> $id,
						'parent_id'	=> $this->parent_id,
						'p'			=> $page_id,
						'v'			=> $version_id,
						'action'	=> $action,
					)), 'confirm_link_breakage.html');
					
					// The user canceled
					// Take them to the page info if deleting version
					// If not, continue executing to take them to the main page
					if($version_id)
					{
						redirect($this->u_action . '&amp;action=info&amp;p=' . $row['page_id']);
					}
				}	
			break;
			
			case 'disable':
			case 'enable':
			
				if (!$page_id)
				{
					trigger_error($user->lang['NO_PAGE_ID'] . adm_back_link($this->u_action . '&amp;parent_id=' . $this->parent_id), E_USER_WARNING);
				}

				$sql = 'SELECT left_id, right_id, parent_id, page_title
					FROM ' . PAGES_TABLE . ' p
						JOIN ' . PAGES_VERSIONS_TABLE . ' v
							ON v.version_id = p.version_id
					WHERE p.page_id = ' . $page_id;
				$result = $db->sql_query($sql);
				
				if (!($row = $db->sql_fetchrow($result)))
				{
					trigger_error($user->lang['NO_PAGE'] . adm_back_link($this->u_action . '&amp;parent_id=' . $this->parent_id), E_USER_WARNING);
				}
				
				$db->sql_freeresult($result);
				
				if($action == 'disable')
				{
					$link_breakage = $this->link_breakage($page_id, $row['left_id'], $row['right_id']);
				}
				
				if ($action == 'enable' || confirm_box(true) || !$link_breakage)
				{
					$sql = 'UPDATE ' . PAGES_TABLE . '
						SET page_enabled = ' . (($action == 'enable') ? 1 : 0) . "
						WHERE page_id = $page_id";
					$db->sql_query($sql);
					$db->sql_freeresult($result);
				
					$sql = 'UPDATE ' . PAGES_TABLE . '
						SET parent_enabled = ' . (($action == 'enable') ? 1 : 0) . "
						WHERE page_id != $page_id AND left_id BETWEEN {$row['left_id']} AND {$row['right_id']}";
					$db->sql_query($sql);
					$db->sql_freeresult($result);

					add_page_log(false, $page_id, 0, 'admin', 'LOG_PAGE_' . strtoupper($action), $row['page_title']);
				
					if($row['parent_id'] == 0)
					{
						refresh_home_page();
					}
				}
				elseif($action == 'disable')
				{
					confirm_box(false, 'DISABLE_PAGE', build_hidden_fields(array(
						'i'			=> $id,
						'parent_id'	=> $this->parent_id,
						'p'			=> $page_id,
						'v'			=> $version_id,
						'action'	=> $action,
					)), 'confirm_link_breakage.html');
				}
				
			break;

			case 'move_up':
			case 'move_down':
				if (!$page_id)
				{
					trigger_error($user->lang['NO_PAGE_ID'] . adm_back_link($this->u_action . '&amp;parent_id=' . $this->parent_id), E_USER_WARNING);
				}

				$sql = 'SELECT *
					FROM ' . PAGES_TABLE . ' p
						JOIN ' . PAGES_VERSIONS_TABLE . ' v
							ON v.version_id = p.version_id
					WHERE p.page_id = ' . $page_id;
				$result = $db->sql_query($sql);
				$row = $db->sql_fetchrow($result);
				$db->sql_freeresult($result);

				if (!$row)
				{
					trigger_error($user->lang['NO_PAGE'] . adm_back_link($this->u_action . '&amp;parent_id=' . $this->parent_id), E_USER_WARNING);
				}
				
				$move_page_name = move_page_by($row, $action, 1);
				
			break;
			
			case 'diff':
				$this->page_title = 'COMPARE_VERSIONS';
				
				$version1 = request_var('version1', 0);
				$version2 = request_var('version2', 0);
				$diff_type = request_var('diff_type', 0);
				$diff_mode = request_var('diff_mode', 'side_by_side');
				
				if (!$version1 || !$version2)
				{
					trigger_error($user->lang['NO_VERSION_ID_DIFF'] . adm_back_link($this->u_action . '&amp;parent_id=' . $this->parent_id), E_USER_WARNING);
				}
				
				// Are the versions to be compared the same?
				if ($version1 == $version2)
				{
					trigger_error($user->lang['DIFF_SAME_VERSIONS'] . adm_back_link($this->u_action . '&amp;parent_id=' . $this->parent_id), E_USER_WARNING);
				}
				
				$sql = 'SELECT *
					FROM ' . PAGES_VERSIONS_TABLE . '
					WHERE ' .  $db->sql_in_set('version_id', array($version1, $version2)) . '
					ORDER BY version_id ASC';
				$result = $db->sql_query($sql);
				
				$show_diff_type = false;
				
				$i = 0;
				$page_id = 0;
				$diff_data = array();
				while ($row = $db->sql_fetchrow($result))
				{
					$i++;
					if ($page_id && $page_id != $row['page_id'])
					{
						trigger_error($user->lang['NO_PAGE'] . adm_back_link($this->u_action . '&amp;parent_id=' . $this->parent_id), E_USER_WARNING);
					}
					$page_id = $row['page_id'];
					
					switch ($row['version_type'])
					{
						case VERSION_TYPE_MODULE:
							// Get any language packs for the modules
							$module->add_mod_info('cms');
							
							$info = get_module_infos($row['version_module_basename']);
							$info = $info[$row['version_module_basename']];
							if (!(isset($info['modes'][$row['version_module_mode']]['html']) && $info['modes'][$row['version_module_mode']]['html']) && $row['version_html'])
							{
								$diff_data[$i] = $this->lang_name($info['title']) . ' » ' . $this->lang_name($info['modes'][$row['version_module_mode']]['title']);
								break;
							}
							
							// No break otherwise
						case VERSION_TYPE_HTML:
							$show_diff_type = true;
							$row['version_html'] = format_links_for_display($row['version_html'], false, false);
							$diff_data[$i] = ($diff_type) ? $row['version_html'] : $this->html_to_text($row['version_html']);
						break;
							
						case VERSION_TYPE_LINK:
							$diff_data[$i] = $row['version_link'];
						break;
							
						case VERSION_TYPE_CATEGORY:
						case VERSION_TYPE_FILE:
					}
				}
				
				if ($i != 2)
				{
					trigger_error($user->lang['NO_PAGE'] . adm_back_link($this->u_action . '&amp;parent_id=' . $this->parent_id), E_USER_WARNING);
				}
				
				$user->add_lang('install');
				
				// Include renderer and engine
				include_once($phpbb_root_path . 'includes/diff/diff.' . $phpEx);
				include_once($phpbb_root_path . 'includes/diff/engine.' . $phpEx);
				include_once($phpbb_root_path . 'includes/diff/renderer.' . $phpEx);
				
				$diff = new diff($diff_data[1], $diff_data[2], false);
				
				$diff_mode_options = '';
				foreach (array('side_by_side', 'inline', 'unified', 'raw') as $option)
				{
					$diff_mode_options .= '<option value="' . $option . '"' . (($diff_mode == $option) ? ' selected="selected"' : '') . '>' . $user->lang['DIFF_' . strtoupper($option)] . '</option>';
				}
		
				// Now the correct renderer
				$render_class = 'diff_renderer_' . $diff_mode;
		
				if (!class_exists($render_class))
				{
					trigger_error('DIFF_NOT_SUPPORTED', E_USER_ERROR);
				}
		
				$renderer = new $render_class();
		
				$template->assign_vars(array(
					'TITLE'					=> $user->lang['COMPARE_VERSIONS'],
					'ACTION'				=> $action,
					'VERSION1'				=> $version1,
					'VERSION2'				=> $version2,
					'DIFF_CONTENT'			=> $renderer->get_diff_content($diff),
					'DIFF_TYPE'				=> $diff_type,
					'DIFF_MODE'				=> $diff_mode,
					
					'S_SHOW_DIFF_TYPE'		=> $show_diff_type,
					'S_DIFF_TYPE'			=> $diff_type,
					'S_DIFF_MODE_OPTIONS'	=> $diff_mode_options,
					'S_DIFF'				=> true,
					'U_BACK'				=> $this->u_action . '&amp;p=' . $page_id . '&amp;action=info',
				));
		
				unset($diff, $renderer);
				
				return;
			break;
			
			case 'links':
				$this->page_title = 'LINKS';
				
				$sql = 'SELECT page_title
					FROM ' . PAGES_TABLE . '
					WHERE page_id = ' . $page_id;
				$result = $db->sql_query($sql);
				
				if (!($title = $db->sql_fetchfield('page_title')))
				{
					trigger_error($user->lang['NO_PAGE'] . adm_back_link($this->u_action), E_USER_WARNING);
				}
				
				$start = request_var('start', 0);
				
				// Sort keys
				$sort_type	= request_var('st', 0);
				$sort_key	= request_var('sk', 't');
				$sort_dir	= request_var('sd', 'd');
				
				// Sorting
				$limit_type = array(
					0	=> $user->lang['ALL'],
					1	=> $user->lang['INTERNAL_LINKS'],
					2	=> $user->lang['EXTERNAL_LINKS'],
				);
				$sort_by_text = array(
					'p'	=> $user->lang['PAGE'],
					't'	=> $user->lang['TIME'],
					'r'	=> $user->lang['LINK_REFERS'],
				);
				$sort_by_sql = array(
					'p'	=> 'p.page_title',
					't'	=> 'l.link_time',
					'r'	=> 'l.link_refers',
				);
				
				$s_limit_days = $s_sort_key = $s_sort_dir = $u_sort_param = '';
				$limit_days = array();
				gen_sort_selects($limit_days, $sort_by_text, $sort_days, $sort_key, $sort_dir, $s_limit_days, $s_sort_key, $s_sort_dir, $u_sort_param);
				
				$s_sort_type = '<select name="st" id="sort_type">';
				foreach ($limit_type as $value => $lang)
				{
					$selected = ($sort_type == $value) ? ' selected="selected"' : '';
					$s_sort_type .= '<option value="' . $value . '"' . $selected . '>' . $lang . '</option>';
				}
				$s_sort_type .= '</select>';
				
				// Define where and sort sql for use in displaying logs
				$sql_where = ($sort_type) ? ' AND link_external = ' . ( ($sort_type == 1) ? '0' : '1' ) : '';
				$sql_sort = $sort_by_sql[$sort_key] . ' ' . (($sort_dir == 'd') ? 'DESC' : 'ASC');
				
				$sql = 'SELECT l.*, p.page_id, page_title
					FROM ' . PAGES_LINKS_TABLE . ' l
					LEFT JOIN ' . PAGES_VERSIONS_TABLE . ' v
						ON v.version_id = l.version_id
					LEFT JOIN ' . PAGES_TABLE . " p
						ON p.page_id = v.page_id
					WHERE link_page_id = $page_id
						AND (link_external = 1
							OR l.version_id = p.version_id)
						AND link_processed = 1
						$sql_where
					ORDER BY $sql_sort";
				$result = $db->sql_query_limit($sql, $config['topics_per_page'], $start);
				
				$s_refers = false;
				while ($row = $db->sql_fetchrow($result))
				{
					$s_refers = ($row['link_external']) ? true : $s_refers;
					
					$link_days = max(1, round((time() - $row['link_time']) / 86400));
					$refers_per_day = $row['link_refers'] / $link_days;
					$template->assign_block_vars('links', array(
						'U_PAGE'			=> ($row['page_id']) ? $this->u_action . '&amp;action=info&amp;p=' . $row['page_id'] : $row['link_url'],
						'PAGE_TITLE'		=> ($row['page_id']) ? $row['page_title'] : $row['link_title'],
						'LINK_EXTERNAL'		=> $row['link_external'],
						'LINK_SITENAME'		=> $row['link_sitename'],
						'LINK_TIME'			=> $user->format_date($row['link_time']),
						'LINK_REFERS'		=> $row['link_refers'],
						'REFERS_DAY'		=> sprintf($user->lang['REFERS_DAY'], $refers_per_day),
					));
				}
				
				$sql = 'SELECT COUNT(link_id) total_links
					FROM ' . PAGES_LINKS_TABLE . '
					WHERE link_page_id = ' . $page_id;
				$result = $db->sql_query($sql);
				$total_links = (int) $db->sql_fetchfield('total_links');
				$db->sql_freeresult($result);
				
				$template->assign_vars(array(
					'U_BACK'	=> $this->u_action . '&amp;action=info&amp;p=' . $page_id,
					'U_ACTION'	=> $this->u_action . '&amp;action=links&amp;p=' . $page_id,
					
					'TITLE'		=> $title,
					'S_LINKS'	=> true,
					'S_REFERS'	=> $s_refers,
					
					'S_LIMIT_BY'	=> $s_sort_type,
					'S_SORT_KEY'	=> $s_sort_key,
					'S_SORT_DIR'	=> $s_sort_dir,
					
					'S_ON_PAGE'		=> on_page($total_links, $config['topics_per_page'], $start),
					'PAGINATION'	=> generate_pagination($this->u_action . '&amp;action=links&amp;p=' . $page_id, $total_links, $config['topics_per_page'], $start, true),
				));
				
				return;
			break;
			
			case 'edit':
			
				if (!$page_id && !$version_id)
				{
					trigger_error($user->lang['NO_PAGE_ID'] . adm_back_link($this->u_action . '&amp;parent_id=' . $this->parent_id), E_USER_WARNING);
				}
				
				$sql = 'SELECT l.*, p.*, v.*, username, session_time
					FROM ' . PAGES_TABLE . ' p
					JOIN ' . PAGES_VERSIONS_TABLE . ' v ON ' . ( ($version_id) ? 'v.page_id = p.page_id' : 'v.version_id = p.version_id') . '
					LEFT JOIN ' . PAGES_LINKS_TABLE . ' l
						ON l.version_id = p.version_id
					LEFT JOIN ' . USERS_TABLE . ' u
						ON u.user_id = page_lock_id
					LEFT JOIN ' . SESSIONS_TABLE . '
						ON session_user_id = u.user_id
							AND session_admin = 1
					WHERE ' . ( ($page_id) ?  "p.page_id = $page_id" : "v.version_id = $version_id" ) . '
					GROUP BY p.page_id';
				$result = $db->sql_query($sql);
				
				if (!($page_row = $db->sql_fetchrow($result)))
				{
					trigger_error($user->lang['NO_PAGE'] . adm_back_link($this->u_action . '&amp;parent_id=' . $this->parent_id), E_USER_WARNING);
				}
				
				$page_id = $page_row['page_id'];
				
				if (is_locked($page_row['page_lock_id'], $page_row['page_lock_time'], $page_row['session_time']))
				{
					// We have a lock on our hands here
					// Check the user hasn't previously confirmed
					if (!confirm_box(true) && !(isset($_POST['submit']) || isset($_POST['submit_module'])))
					{
						confirm_box(false, sprintf($user->lang['EDIT_LOCK'], $user->format_date($page_row['page_lock_time']), $page_row['username']), build_hidden_fields(array(
							'i'			=> $id,
							'p'			=> $page_id,
							'v'			=> $version_id,
							'action'	=> $action,
						)));
						
						if (isset($_POST['cancel']))
						{
							redirect($this->u_action . '&amp;action=info&amp;p=' . $page_id);
						}
					}
				}
				
				$page_row['version_desc'] = '';
				$page_row['version_html'] = format_links_for_display($page_row['version_html'], false);
				$page_row['version_html'] = htmlspecialchars($page_row['version_html']);
				
				$submit = (isset($_POST['submit']) || isset($_POST['submit_module']) || isset($_POST['save'])) ? true : false;
				
				// Get actions since current lock
				if($submit)
				{
					$time = request_var('time', (int) $page_row['page_lock_time']);
					
					$sql = 'SELECT l.*, pl.*, username
						FROM ' . PAGES_LOG_TABLE . ' pl
						LEFT JOIN ' . LOG_TABLE . ' l
							ON l.log_id = pl.log_id
						LEFT JOIN ' . USERS_TABLE . " u
							ON u.user_id = l.user_id
						WHERE pl.page_id = '{$page_id}'
							AND (l.log_id > 0 OR pl.log_operation = 'LOG_VERSION_ADD')
							AND pl.log_time > " . $time . '
							AND pl.user_id <> ' . $user->data['user_id'] . '
						ORDER BY pl.log_time DESC, pl.log_id DESC';
					$result = $db->sql_query($sql);
					
					while ($row = $db->sql_fetchrow($result))
					{
						$row['action'] = (isset($user->lang[$row['log_operation'] . '_INFO'])) ? $user->lang[$row['log_operation'] . '_INFO'] : '{' . ucfirst(str_replace('_', ' ', $row['log_operation'])) . '}';
						if (!empty($row['log_data']))
						{
							$log_data_ary = unserialize($row['log_data']);

							if (isset($user->lang[$row['log_operation']]))
							{
								// Check if there are more occurrences of % than arguments, if there are we fill out the arguments array
								// It doesn't matter if we add more arguments than placeholders
								if ((substr_count($row['action'], '%') - sizeof($log_data_ary)) > 0)
								{
									$log_data_ary = array_merge($log_data_ary, array_fill(0, substr_count($row['action'], '%') - sizeof($log_data_ary), ''));
								}

								$row['action'] = vsprintf($row['action'], $log_data_ary);
								$row['action'] = bbcode_nl2br($row['action']);
							}
							else
							{
								$row['action'] .= '<br />' . implode('', $log_data_ary);
							}

							/* Apply make_clickable... has to be seen if it is for good. :/
							// Seems to be not for the moment, reconsider later...
							$row['action'] = make_clickable($row['action']);
							*/
						}
						
						$errors[] = sprintf($user->lang['LOCK_LOG_ACTION'], $row['username'], $row['action'], $user->format_date($row['log_time']));
					}
					
					// The user wants to disable the page - we need to check if link breakage will occur
					if(request_var('page_enabled', (int) $page_row['page_enabled']) != $page_row['page_enabled'] && !request_var('disable_confirm', 0))
					{
						
						// Next time the user submits, we'll assume they're okay with this and not generate an error next time
						$template->assign_var('S_DISABLE_CONFIRM', true);
					}
				}
				
			// no break

			case 'add':
			
				// Depending on the memory_limit value in php.ini, sometimes we run out of memory
				// This is a tempory fix...
				// ini_set('memory_limit','32M');
				
				$this->page_title = ( ($action == 'add') ? 'ADD' : 'EDIT') . '_PAGE';
				
				$show_info = false;
				$submit = (isset($_POST['submit']) || isset($_POST['submit_module']) || isset($_POST['save'])) ? true : false;
				$cancel = (isset($_POST['cancel'])) ? true : false;
				$update_ids = request_var('update_ids', array(0));
				
				$page = new page;
				
				if ($action == 'add')
				{
					$page->data['page_slug'] = make_slug(utf8_normalize_nfc(request_var('page_title', '')));
				}
				else
				{
					$page->old_data = $page_row;
					$page->data = array_merge($page->data, $page_row);
					// Lock the page for editing
					$page->lock();
				}
				
				// No changes were made
				if ($cancel)
				{
					if ($page_id)
					{
						// Unlock the page
						$page->lock(false);
						
						redirect($this->u_action . '&amp;action=info&amp;p=' . $page_id);
					}
					else
					{
						redirect($this->u_action . '&amp;parent_id=' . $this->parent_id);
					}
				}
				
				$page->data = array_merge($page->data, array(
					'page_id'					=> $page_id,
					'page_enabled'				=> request_var('page_enabled', (int) $page->data['page_enabled']),
					'page_display'				=> request_var('page_display', (int) $page->data['page_display']),
					'page_style'				=> request_var('page_style', (int) $page->data['page_style']),
					'parent_id'					=> request_var('parent_id', (int) $page->data['parent_id']),
					'page_title'				=> utf8_normalize_nfc(request_var('page_title', (string) $page->data['page_title'], true)),
					'page_slug'					=> request_var('page_slug', (string) $page->data['page_slug']),
					'page_contents_table'		=> request_var('page_contents_table', (int) $page->data['page_contents_table']),
					
					'version_type'				=> request_var('version_type', (int) $page->data['version_type']),
					'version_draft'				=> (isset($_POST['save'])) ? true : false,
					'version_html'				=> htmlspecialchars_decode(utf8_normalize_nfc(request_var('version_html', (string) $page->data['version_html'], true))),
					'version_module_basename'	=> request_var('version_module_basename', (string) $page->data['version_module_basename']),
					'version_module_mode'		=> request_var('version_module_mode', (string) $page->data['version_module_mode']),
					'version_desc'				=> utf8_normalize_nfc(request_var('version_desc', (string) $page->data['version_desc'], true)),
					
					'upload_type'				=> request_var('upload_type', UPLOAD_TYPE_FILE),
					'upload_url'				=> request_var('upload_url', ''),
					
					'version_link_type'			=> request_var('version_link_type', (int) $page->data['version_link_type']),
					'version_link_url'			=> request_var('version_link_url', (string) $page->data['version_link_url']),
					'version_link_id'			=> request_var('version_link_id', (int) $page->data['version_link_id']),
				));
				
				$s_version_type_options = '';
				foreach ($version_type_options as $value => $lang)
				{
					$selected = ($page->data['version_type'] == $value) ? ' selected="selected"' : '';
					$s_version_type_options .= '<option value="' . $value . '"' . $selected . '>' . $lang . '</option>';
				}
				
				// There may be errors if a user has ignored a lock
				if ($submit && !sizeof($errors))
				{
					$page->validate();
					$errors = $page->errors;
					
					if (!sizeof($errors) && $page->save() == false)
					{
						// No changes were made
						$action = 'info';
						$show_info = true;
						$errors = array();
						
						// Unlock the page
						$page->lock(false);
					}
					elseif (!sizeof($errors))
					{
						if ($action == 'add' && sizeof($update_ids))
						{
							foreach ($update_ids as $page_id)
							{
								if ($page_id == $page_data['page_id'])
								{
									continue;
								}
								
								if (move_page($page_id, $page_data['page_id']))
								{
									$sql = 'UPDATE ' . PAGES_TABLE . '
										SET parent_id = ' . $page_data['page_id'] . '
										WHERE page_id = ' . $page_id;
									$db->sql_query($sql);
								}
							}
						}
						
						if (!isset($_POST['submit_module']))
						{
							trigger_error((($action == 'add') ? $user->lang['PAGE_ADDED'] : $user->lang['PAGE_EDITED']) . adm_back_link($this->u_action . '&amp;action=info&amp;p=' . $page->data['page_id']));
						}
					}
					
					if (isset($_POST['submit_module']) && $page->data['version_type'] == VERSION_TYPE_MODULE && ($errors === false || !sizeof($errors)))
					{
						// We're redirecting to the module admin
						$info = get_module_infos($page->data['version_module_basename']);
						if (isset($info[$page->data['version_module_basename']]['modes'][$page->data['version_module_mode']]['acp']))
						{
							$info = $info[$page->data['version_module_basename']]['modes'][$page->data['version_module_mode']]['acp'];
							redirect(append_sid("{$phpbb_admin_path}index.$phpEx", 'i=' . $info['basename'] . '&amp;mode=' . $info['mode']));
						}
					}
				}
				
				foreach ($update_ids as $page_id)
				{
					$template->assign_block_vars('hidden', array(
						'PAGE_ID'	=> $page_id,
					));
				}
				
				$this->parent_select_js($page_id, $page->data['parent_id']);
				
				// Get any language packs for the modules
				$module->add_mod_info('cms');
				
				// Get module information
				$module_infos = get_module_infos();

				// Build name options
				$s_name_options = $s_mode_options = '';
				foreach ($module_infos as $option => $values)
				{
					if (!$page->data['version_module_basename'])
					{
						$page->data['version_module_basename'] = $option;
					}

					// Name options
					$s_name_options .= '<option value="' . $option . '"' . (($option == $page->data['version_module_basename']) ? ' selected="selected"' : '') . '>' . $this->lang_name($values['title']) . ' [cms_' . $option . ']</option>';

					$template->assign_block_vars('m_names', array('NAME' => $option, 'A_NAME' => addslashes($option)));

					// Build module modes
					foreach ($values['modes'] as $m_mode => $m_values)
					{
						if ($option == $page->data['version_module_basename'])
						{
							$s_mode_options .= '<option value="' . $m_mode . '"' . (($m_mode == $page->data['version_module_mode']) ? ' selected="selected"' : '') . '>' . $this->lang_name($m_values['title']) . '</option>';
						}
						
						$template->assign_block_vars('m_names.modes', array(
							'OPTION'		=> $m_mode,
							'VALUE'			=> $this->lang_name($m_values['title']),
							'A_OPTION'		=> addslashes($m_mode),
							'A_VALUE'		=> addslashes($this->lang_name($m_values['title'])),
							'S_HTML'		=> (isset($m_values['html']) && $m_values['html'] == true) ? true : false,
							'S_ACP'			=> (isset($m_values['acp']) && is_array($m_values['acp'])) ? true : false,
						));
					}
				}
				
				$s_cat_option = '<option value="0"' . (($page->data['parent_id'] == 0) ? ' selected="selected"' : '') . '>' . $user->lang['NO_PARENT'] . '</option>';
				
				$template->assign_vars(array_merge(array_change_key_case($page->data, CASE_UPPER), array(
					'S_ERROR'					=> (sizeof($errors)) ? true : false,
					'S_EDIT_PAGE'				=> true,
					'S_PARENT_OPTIONS'			=> $s_cat_option . $this->make_page_select($page->data['parent_id'], ($action == 'edit') ? $page_row['page_id'] : false),
					'S_VERSION_TYPE_OPTIONS'	=> $s_version_type_options,
					'S_STYLES_OPTIONS'			=> style_select($page->data['page_style'], true),
					'S_MODULE_NAMES'			=> $s_name_options,
					'S_MODULE_MODES'			=> $s_mode_options,
					'S_PREVIEW_STYLE'			=> $config['preview_style'],
					'S_BR_NEWLINES'				=> $config['br_newlines'],
					'S_ENABLE_SPELLCHECKER'		=> $config['enable_spellchecker'],
					'S_VERSION_CONTROL'			=> $config['version_control'],
					'S_LINK_PAGE_OPTIONS'		=> $this->make_page_select($page->data['version_link_id'], $page->data['page_id']),
					'S_NO_PAGES'				=> ($config['num_pages'] == 0) ? true : false,
					
					'U_BACK'					=> "{$this->u_action}&amp;" . ( ($action == 'add') ? 'parent_id=' . $this->parent_id : 'action=info&amp;p=' . $page_id),
					'U_EDIT_ACTION'				=> $this->u_action,
					'UA_CMS_UPLOAD'				=> addslashes(str_replace('&amp;', '&', append_sid("{$user->page['root_script_path']}adm/index.$phpEx", "i=cms_page_editor&amp;mode=upload"))),
					
					'A_PAGEBREAK_SEPARATOR'	=> addslashes(PAGEBREAK_SEPARATOR),
					
					// We can't use PAGE_TITLE here - it is used by for displaying the title of the rendered page
					// Use title instead
					'TITLE'					=> $page->data['page_title'],
					'VERSION_HTML'			=> htmlspecialchars($page->data['version_html']),
					
					'UPLOAD_PREVIEW'		=> ($action == 'edit' && $page->old_data['version_type'] == VERSION_TYPE_FILE) ? embed_page($page->old_data, IMAGE_SIZE_MEDIUM) : false,
					
					'VERSION_TYPE_HTML'		=> VERSION_TYPE_HTML,
					'VERSION_TYPE_CATEGORY'	=> VERSION_TYPE_CATEGORY,
					'VERSION_TYPE_FILE'		=> VERSION_TYPE_FILE,
					'VERSION_TYPE_MODULE'	=> VERSION_TYPE_MODULE,
					'VERSION_TYPE_LINK'		=> VERSION_TYPE_LINK,
					
					'UPLOAD_TYPE_FILE'		=> UPLOAD_TYPE_FILE,
					'UPLOAD_TYPE_URL'		=> UPLOAD_TYPE_URL,
					
					'LINK_TYPE_URL'		=> LINK_TYPE_URL,
					'LINK_TYPE_PAGE'	=> LINK_TYPE_PAGE,
					'LINK_TYPE_PHPBB'	=> LINK_TYPE_PHPBB,
					
					'SITE_URL'			=> generate_cms_url(true),
					
					// Stuff for tinyMCE
					'A_BASE_URL'			=> addslashes(generate_cms_url() . '/'),
					// Because we specify the document base url for pages, we need to specify the absolute path rather than relative to the current page
					// Note, we use & instead of &amp; here
					'A_STYLE_URL'			=> addslashes((!$user->theme['theme_storedb']) ? "{$user->page['root_script_path']}styles/" . $user->theme['theme_path'] . '/theme/stylesheet.css' : "{$user->page['root_script_path']}style.$phpEx?sid=$user->session_id&id=" . $user->theme['style_id'] . '&lang=' . $user->data['user_lang']),
					'A_TINYMCE_LANG'		=> addslashes(tinymce_lang()),
					'A_SPACE_SEPARATOR'		=> addslashes(SPACE_SEPARATOR),
					
					'ERROR_MSG'			=> (sizeof($errors)) ? implode('<br />', $errors) : '',
					'ACTION'			=> $action,
					'PAGE_ID'			=> $page_id,
					'VERSION_ID'		=> $version_id,
					
					// Used instead of page_lock_time, incase the users lock has been overrided
					'TIME'				=> time(),

				)));
			
			// If the user has not made any changes when creating a new version, continue executing and show them the page info
			if (!$show_info)
			{
				return;
				break;
			}
			
			case 'undo':
				if($action == 'undo')
				{
					$log_id = request_var('log', 0);
					
					$sql = 'SELECT l.*, v.*, p.*
						FROM ' . PAGES_LOG_TABLE . ' pl
						JOIN ' . PAGES_TABLE . ' p
							ON p.page_id = pl.page_id
						JOIN ' . PAGES_VERSIONS_TABLE . ' v
							ON v.version_id = p.version_id
						JOIN ' . LOG_TABLE . ' l
							ON l.log_id = pl.log_id
						WHERE pl.log_id = ' . $log_id;
					$result = $db->sql_query($sql);
					
					if ($row = $db->sql_fetchrow($result))
					{
						$page_id = $row['page_id'];
						$log_data_ary = unserialize($row['log_data']);
						
						$page = new page;
						$page->data = $page->old_data = $row;
						
						switch($row['log_operation'])
						{
							case 'LOG_PAGE_TITLE':
								$page->data['page_title'] = $log_data_ary[0];
							break;
							
							case 'LOG_PAGE_SLUG':
								$page->data['page_slug'] = $log_data_ary[0];
							break;
							
							case 'LOG_PAGE_DISABLE':
								$page->data['page_enabled'] = 1;
							break;
							
							case 'LOG_PAGE_ENABLE':
								$page->data['page_enabled'] = 0;
							break;
							
							case 'LOG_PAGE_NAV_DISPLAY':
								$page->data['page_display'] = 0;
							break;
							
							case 'LOG_PAGE_NAV_HIDE':
								$page->data['page_display'] = 1;
							break;
							
							case 'LOG_PAGE_MOVE_DOWN':
								$move_page_name = move_page_by($row, 'move_up', 1);
							break;
							
							case 'LOG_PAGE_MOVE_UP':
								$move_page_name = move_page_by($row, 'move_down', 1);
							break;
							
							case 'LOG_PAGE_CONTENTS_ENABLE':
								$page->data['page_contents_table'] = 0;
							break;
							
							case 'LOG_PAGE_CONTENTS_DISABLE':
								$page->data['page_contents_table'] = 1;
							break;
							
							case 'LOG_PAGE_STYLE':
								$sql = 'SELECT style_id
									FROM ' . STYLES_TABLE . "
									WHERE style_name = '" . $db->sql_escape($log_data_ary[0]) . "'";
								$result = $db->sql_query($sql);
								
								// This is good - if the page was using the default template or a (since) deleted one, this will be 0
								$page->data['page_style'] = (int) $db->sql_fetchfield('style_id');
								$db->sql_freeresult($result);
							break;
							
							case 'LOG_PAGE_REVERT':
								$sql = 'SELECT version_id, version_number
								FROM ' . PAGES_VERSIONS_TABLE . '
								WHERE page_id = ' . $row['page_id'] . '
									AND version_number = ' . (int) $log_data_ary[1];
								$result = $db->sql_query($sql);
								$revert = $db->sql_fetchrow($result);
								
								if($revert['version_id'] && $revert['version_id'] != $row['version_id'])
								{
									$errors = array_merge($errors, revert_page($row['page_id'], $revert['version_id'], 0));
									
									add_page_log(false, $page_id, 0, 'admin', 'LOG_PAGE_REVERT', $row['page_title'], $row['version_number'], $revert['version_number']);
								}
							break;
						}
						
						if($row['log_operation'] != 'LOG_PAGE_MOVE_DOWN'
							&& $row['log_operation'] != 'LOG_PAGE_MOVE_UP'
							&& $row['log_operation'] != 'LOG_PAGE_REVERT')
						{
							if($page->validate())
							{
								$page->save();
							}
							else
							{
								$errors = $page->errors;
							}
						}
					}
				}
				// No break
			case 'revert':
				if ($action == 'revert')
				{
					$sql = 'SELECT v1.page_id, v1.version_number new_version_number, v1.version_draft, v2.version_number as old_version_number, v2.version_id, page_title, page_lock_id, page_lock_time, username, session_time
						FROM ' . PAGES_VERSIONS_TABLE . ' v1
						JOIN ' . PAGES_TABLE . ' p
							ON p.page_id = v1.page_id
						JOIN ' . PAGES_VERSIONS_TABLE .  ' v2
							ON v2.version_id = p.version_id
						LEFT JOIN ' . USERS_TABLE . ' u
							ON u.user_id = page_lock_id
						LEFT JOIN ' . SESSIONS_TABLE . "
							ON session_user_id = u.user_id
								AND session_admin = 1
						WHERE v1.version_id = $version_id
						GROUP BY p.page_id";
					$result = $db->sql_query($sql);
				
					if ($row = $db->sql_fetchrow($result))
					{
						$page_id = $row['page_id'];
						
						// Don't revert if the version number is already the current version
						if ($version_id != $row['version_id'])
						{
							// If we do not have a lock or the user has confirmed they wish to proceed
							if (!is_locked($row['page_lock_id'], $row['page_lock_time'], $row['session_time']) || confirm_box(true))
							{
								$errors = array_merge($errors, revert_page($page_id, $version_id, $row['version_draft']));
								
								add_page_log(false, $page_id, 0, 'admin', 'LOG_PAGE_REVERT', $row['page_title'], $row['old_version_number'], $row['new_version_number']);
							}
							elseif ($row['page_lock_id'])
							{
								confirm_box(false, sprintf($user->lang['REVERT_LOCK'], $user->format_date($row['page_lock_time']), $row['username']), build_hidden_fields(array(
									'i'			=> $id,
									'p'			=> $page_id,
									'v'			=> $version_id,
									'action'	=> $action,
								)));
							}
						}
					}
				}
				// No break
			
			case 'info':
			case 'delete_log';
				$this->page_title = 'PAGE_INFO';
			
				if (!$page_id)
				{
					trigger_error($user->lang['NO_PAGE_ID'] . adm_back_link($this->u_action . '&amp;parent_id=' . $this->parent_id), E_USER_WARNING);
				}
				
				$sql = 'SELECT p.*, u.user_id, u.username, u.user_colour, ul.username l_username, ul.user_colour l_user_colour, session_time
					FROM ' . PAGES_TABLE . " p
					JOIN " . USERS_TABLE . ' u
						ON u.user_id = p.user_id
					LEFT JOIN ' . USERS_TABLE . ' ul
						ON ul.user_id = page_lock_id
					LEFT JOIN ' . SESSIONS_TABLE . "
						ON session_user_id = ul.user_id
							AND session_admin = 1
					WHERE p.page_id = $page_id
					GROUP BY p.page_id";
				$result = $db->sql_query($sql);
				
				if (!($page_data = $db->sql_fetchrow($result)))
				{
					trigger_error($user->lang['NO_PAGE'] . adm_back_link($this->u_action . '&amp;parent_id=' . $this->parent_id), E_USER_WARNING);
				}
				
				$profile_url = append_sid("{$phpbb_admin_path}index.$phpEx", 'i=users&amp;mode=overview');
				
				$s_show_desc = false;
				
				if ($config['version_control'])
				{
					$start = request_var('start', 0);
					$page_log_id = request_var('log', 0);
					
					if ($page_log_id && $action == 'delete_log')
					{
						$sql = 'SELECT page_log_id, log_id
							FROM ' . PAGES_LOG_TABLE . "
							WHERE page_log_id = {$page_log_id}
								AND page_id = {$page_id}";
						$result = $db->sql_query($sql);
						
						if($log = $db->sql_fetchrow($result))
						{
							$sql = 'DELETE FROM ' . PAGES_LOG_TABLE . '
								WHERE page_log_id = ' . $page_log_id;
							$db->sql_query($sql);
							
							if($log['log_id'])
							{
								$sql = 'DELETE FROM ' . LOG_TABLE . '
									WHERE log_id = ' . $log['log_id'];
								$db->sql_query($sql);
							}
						}
					}
					
					// Sort keys
					$sort_type	= request_var('st', 0);
					$sort_key	= request_var('sk', 't');
					$sort_dir	= request_var('sd', 'd');
				
					// Sorting
					$limit_type = array(
						0	=> $user->lang['ALL'],
						1	=> $user->lang['VERSIONS'],
					);
					$sort_by_text = array(
						'u'	=> $user->lang['USERNAME'],
						't'	=> $user->lang['TIME'],
					);
					$sort_by_sql = array(
						'u'	=> 'username',
						't'	=> 'pl.log_time',
					);
				
					$s_limit_days = $s_sort_key = $s_sort_dir = $u_sort_param = '';
					$limit_days = array();
					gen_sort_selects($limit_days, $sort_by_text, $sort_days, $sort_key, $sort_dir, $s_limit_days, $s_sort_key, $s_sort_dir, $u_sort_param);
				
					$s_sort_type = '<select name="st" id="sort_type">';
					foreach ($limit_type as $value => $lang)
					{
						$selected = ($sort_type == $value) ? ' selected="selected"' : '';
						$s_sort_type .= '<option value="' . $value . '"' . $selected . '>' . $lang . '</option>';
					}
					$s_sort_type .= '</select>';
				
					// Define where and sort sql
					$sql_where = ($sort_type) ? " AND pl.log_operation = 'LOG_VERSION_ADD'" : '';
					$sql_sort_dir = ($sort_dir == 'd') ? 'DESC' : 'ASC';
					$sql_sort = $sort_by_sql[$sort_key] . ' ' . $sql_sort_dir . ', pl.log_operation ' . $sql_sort_dir;
					
					$sql = 'SELECT l.*, pl.*, v.*, u.user_id, username, user_colour, v.version_id
						FROM ' . PAGES_LOG_TABLE . ' pl
						LEFT JOIN ' . LOG_TABLE . ' l
							ON l.log_id = pl.log_id
						LEFT JOIN ' . PAGES_VERSIONS_TABLE . ' v
							ON v.version_id = pl.version_id
						LEFT JOIN ' . USERS_TABLE . " u
							ON u.user_id = l.user_id
								OR u.user_id = pl.user_id
						WHERE pl.page_id = '{$page_id}'
							AND (l.log_id > 0
								OR pl.log_operation = 'LOG_VERSION_ADD'
								OR pl.log_operation = 'LOG_PAGE_ADD')
							{$sql_where}
						ORDER BY {$sql_sort}";
					$result = $db->sql_query_limit($sql, 10, $start);
					
					while ($row = $db->sql_fetchrow($result))
					{
						$s_show_desc = ($row['version_desc']) ? true : $s_show_desc;
						
						if ($row['log_operation'] == 'LOG_VERSION_ADD')
						{
							$url = $this->u_action . '&amp;v=' . $row['version_id'];
							
							$template->assign_block_vars('history', array(
								'VERSION_ID'		=> $row['version_id'],
								'VERSION_NUMBER'	=> sprintf($user->lang['VERSION_NUMBER'], $row['version_number']),
								'VERSION_TYPE'		=> ($row['version_type'] == VERSION_TYPE_FILE) ? sprintf($user->lang['VERSION_TYPE_FILE_INFO'], $row['version_extension']) : $version_type_options[$row['version_type']],
								'VERSION_DRAFT'		=> $row['version_draft'],
								'VERSION_DESC'		=> truncate_string($row['version_desc'], 100, 255, true, '... <a href="#" onclick="alert(\'' . addslashes($row['version_desc']) . '\'); return false;">' . $user->lang['READ'] . '</a>'),
								'USERNAME'			=> get_username_string('full', $row['user_id'], $row['username'], $row['user_colour'], false, $profile_url),
								'VERSION_TIME'		=> $user->format_date($row['version_time']),
								'VERSION_VIEWS'		=> $row['version_views'],
					
								'S_DELETE'			=> ($page_data['page_versions'] > 1) ? true : false,
								
								'U_VERSION'			=> append_sid(generate_url($page_data['page_id'], $page_data['page_url']), ( ($row['version_id'] != $page_data['version_id']) ? '&amp;v=' . $row['version_number'] : '') ),
								'U_EDIT'			=> $url . '&amp;action=edit',
								'U_REVERT'			=> $url . '&amp;action=revert',
								'U_DELETE'			=> $url . '&amp;action=delete',
							));
						}
						else
						{
							$row['action'] = (isset($user->lang[$row['log_operation'] . '_INFO'])) ? $user->lang[$row['log_operation'] . '_INFO'] : '{' . ucfirst(str_replace('_', ' ', $row['log_operation'])) . '}';
							if (!empty($row['log_data']))
							{
								$log_data_ary = unserialize($row['log_data']);

								if (isset($user->lang[$row['log_operation']]))
								{
									// Check if there are more occurrences of % than arguments, if there are we fill out the arguments array
									// It doesn't matter if we add more arguments than placeholders
									if ((substr_count($row['action'], '%') - sizeof($log_data_ary)) > 0)
									{
										$log_data_ary = array_merge($log_data_ary, array_fill(0, substr_count($row['action'], '%') - sizeof($log_data_ary), ''));
									}

									$row['action'] = vsprintf($row['action'], $log_data_ary);
									$row['action'] = bbcode_nl2br($row['action']);
								}
								else
								{
									$row['action'] .= '<br />' . implode('', $log_data_ary);
								}

								/* Apply make_clickable... has to be seen if it is for good. :/
								// Seems to be not for the moment, reconsider later...
								$row['action'] = make_clickable($row['action']);
								*/
							}
							
							$template->assign_block_vars('history', array(
								'S_LOG'			=> true,
								
								'LOG_OPERATION'	=> $row['log_operation'],
								'USERNAME'		=> get_username_string('full', $row['user_id'], $row['username'], $row['user_colour'], false, $profile_url),
								'IP'			=> $row['log_ip'],
								'DATE'			=> $user->format_date($row['log_time']),
								'ACTION'		=> $row['action'],
								
								'U_REVERT'		=> ($row['log_operation'] != 'LOG_PAGE_ADD' && $row['log_operation'] != 'LOG_VERSION_REMOVED' && $row['log_operation'] != 'LOG_PAGE_PARENT' && $row['log_operation'] != 'LOG_INCOMMING_LINK') ? $this->u_action . '&amp;action=undo&amp;log=' . $row['log_id'] : '',
								'U_DELETE'		=> $this->u_action . "&amp;action=delete_log&amp;p={$page_id}&amp;log={$row['page_log_id']}",
							));
						}
					}
					
					$sql = 'SELECT COUNT(log_id) total_history
						FROM ' . PAGES_LOG_TABLE . " pl
						WHERE page_id = $page_id
						{$sql_where}";
					$result = $db->sql_query($sql);
					$total_history = (int) $db->sql_fetchfield('total_history');
					$db->sql_freeresult($result);
					
					$template->assign_vars(array(
						'S_ON_PAGE'		=> on_page($total_history, 10, $start),
						'PAGINATION'	=> generate_pagination($this->u_action . '&amp;action=info&amp;p=' . $page_id, $total_history, 10, $start, true),
						
						'S_SHOW_DESC'	=> $s_show_desc,
						'S_LIMIT_BY'	=> $s_sort_type,
						'S_SORT_KEY'	=> $s_sort_key,
						'S_SORT_DIR'	=> $s_sort_dir,
						
						'ICON_REVERT'			=> '<img src="' . $phpbb_admin_path . 'images/file_modified.gif" alt="' . $user->lang['REVERT'] . '" title="' . $user->lang['REVERT'] . '" />',
						'ICON_REVERT_DISABLED'	=> '<img src="' . $phpbb_admin_path . 'images/revert_disabled.gif" alt="' . $user->lang['REVERT'] . '" title="' . $user->lang['REVERT'] . '" />',
					));
				}
				
				$page_path = '<a href="' . $this->u_action . '">' . $user->lang['SITE_ROOT'] . '</a>';
				
				$branch = get_page_branch($page_id, 'parents', 'descending');
				
				foreach ($branch as $page)
				{
					$page_path .= ' -&gt; <a href="' . $this->u_action . '&amp;parent_id=' . $page['page_id'] . '">' . $page['page_title'] . '</a>';
				}
				
				$lock = '';
				if (is_locked($page_data['page_lock_id'], $page_data['page_lock_time'], $page_data['session_time']))
				{
					$lock = sprintf($user->lang['PAGE_LOCKED'], $user->format_date($page_data['page_lock_time']), get_username_string('full', $page_data['page_lock_id'], $page_data['l_username'], $page_data['l_user_colour'], false, $profile_url));
				}
				
				$views_pct = ( (get_num_views()) ? min(100, ($page_data['page_views'] / get_num_views()) * 100) : 0);
				
				$page_days = max(1, round((time() - $page_data['page_time']) / 86400));
				$views_per_day = $page_data['page_views'] / $page_days;
				
				$sql = 'SELECT *
					FROM (SELECT
						COUNT(link_id) internal
						FROM ' . PAGES_LINKS_TABLE . ' l
						JOIN ' . PAGES_TABLE . ' p
							ON l.version_id = p.version_id
						WHERE link_page_id = ' . $page_id . '
							AND link_external = 0
							AND link_processed = 1) i1,
						(SELECT COUNT(link_id) external
						FROM ' . PAGES_LINKS_TABLE . '
						WHERE link_page_id = ' . $page_id . '
							AND link_external = 1
							AND link_processed = 1) i2';
				$result = $db->sql_query($sql);
				$links = $db->sql_fetchrow($result);
				
				$template->assign_vars(array(
					'PAGE_ID'			=> $page_id,
					'PAGE_PATH'			=> $page_path,
					'PAGE_TIME'			=> $user->format_date($page_data['page_time']),
					'PAGE_URL'			=> $page_data['page_url'],
					'PAGE_VIEWS'		=> $page_data['page_views'],
					'PAGE_VIEWS_INFO'	=> sprintf($user->lang['PAGE_VIEWS_INFO'], $views_pct, $views_per_day),
					'PAGE_VERSIONS'		=> $page_data['page_versions'],
					'PAGE_EDITS'		=> $page_data['page_edits'],
					'PAGE_USERNAME'		=> get_username_string('full', $page_data['user_id'], $page_data['username'], $page_data['user_colour'], false, $profile_url),
					'PAGE_ENABLED'		=> $user->lang['PAGE_' . ( ($page_data['page_enabled']) ? 'ENABLED' : 'DISABLED')],
					'PAGE_DISPLAY'		=> $user->lang['VISABILITY_' . ( ($page_data['page_display']) ? 'DISPLAY' : 'HIDE')],
					'LINKS_INTERNAL'	=> ($links['internal']) ? sprintf($user->lang['LINKS_INTERNAL'], $links['internal']) : false,
					'LINKS_EXTERNAL'	=> ($links['external']) ? sprintf($user->lang['LINKS_EXTERNAL'], $links['external']) : false,
					
					'VERSION_ID'		=> $page_data['version_id'],
					'TITLE'				=> $page_data['page_title'],
					
					'LOCK'		=> $lock,
					'ICON_LOCK'	=> '<img src="' . $phpbb_admin_path . 'images/file_not_modified.gif" alt="' . $user->lang['LOCKED'] . '" title="' . $user->lang['LOCKED'] . '" />',
					
					'S_VERSIONS'		=> true,
					'S_VERSION_CONTROL'	=> $config['version_control'],
					'S_ERROR'			=> (sizeof($errors)) ? true : false,
					
					'ERROR_MSG'			=> (sizeof($errors)) ? implode('<br />', $errors) : '',
					
					'U_ACTION'			=> $this->u_action,
					'U_PAGE'			=> append_sid(generate_url($page_data['page_id'], $page_data['page_url'])),
					'U_BACK'			=> $this->u_action . '&amp;parent_id=' . $page_data['parent_id'],
					'U_EDIT_ACTION'		=> $this->u_action . '&amp;parent_id=' . $page_data['parent_id'],
					'U_DELETE'			=> $this->u_action . '&amp;action=delete&amp;p=' . $page_id,
					'U_VERSION_NEW'		=> $this->u_action . '&amp;action=edit&amp;p=' . $page_id,
					'U_LINKS'			=> $this->u_action . '&amp;action=links&amp;p=' . $page_id,
				));
				
				return;
				
			break;
		}
		
		$this->page_title = 'ACP_CMS_PAGE_EDITOR';
		
		if (!$this->parent_id)
		{
			$navigation = $user->lang['SITE_ROOT'];
		}
		else
		{
			$navigation = '<a href="' . $this->u_action . '">' . $user->lang['SITE_ROOT'] . '</a>';
			
			$branch = get_page_branch($this->parent_id, 'parents', 'descending');
			
			foreach ($branch as $page)
			{
				if ($page['page_id'] == $this->parent_id)
				{
					$navigation .= ' -&gt; ' . $page['page_title'];
				}
				else
				{
					$navigation .= ' -&gt; <a href="' . $this->u_action . '&amp;parent_id=' . $page['page_id'] . '">' . $page['page_title'] . '</a>';
				}
			}
		}
		
		if ($this->parent_id)
		{
			$row = get_page_row($this->parent_id);
			
			if (!$row)
			{
				trigger_error($user->lang['NO_PAGE'] . adm_back_link($this->u_action . '&amp;parent_id=' . $this->parent_id), E_USER_WARNING);
			}

			$url = $this->u_action . '&amp;parent_id=' . $this->parent_id . '&amp;p=' . $row['page_id'];

			$template->assign_vars(array(
				'S_PARENT'			=> true,
				'PAGE_ENABLED'		=> ($row['page_enabled']) ? true : false,

				'U_INFO'			=> $url . '&amp;action=info',
				'U_DELETE'			=> $url . '&amp;action=delete',
				'U_ENABLE'			=> $url . '&amp;action=enable',
				'U_DISABLE'			=> $url . '&amp;action=disable')
			);
		}
		
		$no_pages = false;
		
		$sql = 'SELECT p.*, version_type, version_draft, version_physical_filename, username, session_time
			FROM ' . PAGES_TABLE . ' p
			JOIN ' . PAGES_VERSIONS_TABLE . ' v
				ON v.version_id = p.version_id
			LEFT JOIN ' . USERS_TABLE . ' u
				ON u.user_id = p.page_lock_id
			LEFT JOIN ' . SESSIONS_TABLE . "
				ON session_user_id = u.user_id
					AND session_admin = 1
			WHERE parent_id = {$this->parent_id}
			GROUP BY p.page_id
			ORDER BY left_id ASC";
		$result = $db->sql_query($sql);
		
		if ($row = $db->sql_fetchrow($result))
		{
			$enabled_pages = false;
			
			do
			{
				$url = $this->u_action . '&amp;p=' . $row['page_id'];
				
				$lock = '';
				if (is_locked($row['page_lock_id'], $row['page_lock_time'], $row['session_time']))
				{
					$lock = sprintf($user->lang['PAGE_LOCKED'], $user->format_date($row['page_lock_time']), $row['username']);
				}
				
				$template->assign_block_vars('pages', array(
					'PAGE_IMAGE'		=> $this->page_icon($row),
					'HOME_PAGE'			=> ($row['page_id'] == $config['home_page']) ? true : false,
					'PAGE_TITLE'		=> $row['page_title'],
					'PAGE_ENABLED'		=> ($row['page_enabled']) ? true : false,
					'PAGE_DISPLAY'		=> ($row['page_display']) ? true : false,
					
					'S_LOCK'	=> ($lock) ? true : false,
					'A_LOCK'	=> addslashes($lock),
					'ICON_LOCK'	=> '<img src="' . $phpbb_admin_path . 'images/file_not_modified.gif" alt="' . $lock . '" title="' . $lock . '" />',
		
					'U_PAGE'			=> $this->u_action . '&amp;parent_id=' . $row['page_id'],
					'U_MOVE_UP'			=> $url . '&amp;action=move_up',
					'U_MOVE_DOWN'		=> $url . '&amp;action=move_down',
					'U_INFO'			=> $url . '&amp;action=info',
					'U_DELETE'			=> $url . '&amp;action=delete',
					'U_ENABLE'			=> $url . '&amp;action=enable',
					'U_DISABLE'			=> $url . '&amp;action=disable')
				);
				$enabled_pages = ($row['page_enabled']) ? true : $enabled_pages;
			}
			
			while ($row = $db->sql_fetchrow($result));
			
			if ($enabled_pages == false)
			{
				// Okay, we know there are no enabled pages for this view, but are there enabled pages in the rest of the site?
				// If not, show a notice
				$sql = 'SELECT 1
					FROM ' . PAGES_TABLE . '
					WHERE page_enabled = 1';
				$result = $db->sql_query($sql);
				if ( !($row = $db->sql_fetchrow($result)) )
				{
					$template->assign_var('S_NO_ENABLED_PAGES', true);
				}
				$db->sql_freeresult($result);
			}
		}
		elseif ($config['num_pages'] == 0)
		{
			$template->assign_var('S_NO_PAGES', true);
			$no_pages = true;
		}
		$db->sql_freeresult($result);
		
		// Default management page
		if (sizeof($errors))
		{
			$template->assign_vars(array(
				'S_ERROR'	=> true,
				'ERROR_MSG'	=> implode('<br />', $errors))
			);
		}
		
		$template->assign_vars(array(
			'U_SEL_ACTION'	=> $this->u_action,
			'U_ACTION'		=> $this->u_action . '&amp;parent_id=' . $this->parent_id,
			
			'ICON_HOME'		=> '<img src="images/icon_home.gif" alt="' . $user->lang['SITE_INDEX'] .'" title="' . $user->lang['SITE_INDEX'] . '" />',
			
			'NAVIGATION'	=> $navigation,
			'PAGE_BOX'		=> (!$no_pages) ? $this->make_page_select($this->parent_id) : false,
			'PARENT_ID'		=> $this->parent_id,
		));
	}
	
	/**
	* Simple version of jumpbox, just lists pages
	*/
	function make_page_select($select_id = false, $ignore_id = false, $enabled = false)
	{
		global $db, $user, $auth, $config;

		$sql = 'SELECT p.*
			FROM ' . PAGES_TABLE . ' p
			ORDER BY left_id ASC';
		$result = $db->sql_query($sql);

		$right = $iteration = 0;
		$padding_store = array('0' => '');
		$page_list = $padding = '';

		while ($row = $db->sql_fetchrow($result))
		{
			if ($row['left_id'] < $right)
			{
				$padding .= '&nbsp; &nbsp;';
				$padding_store[$row['parent_id']] = $padding;
			}
			elseif ($row['left_id'] > $right + 1)
			{
				$padding = (isset($padding_store[$row['parent_id']])) ? $padding_store[$row['parent_id']] : '';
			}

			$right = $row['right_id'];
		
			// ignore this page?
			if ((is_array($ignore_id) && in_array($row['page_id'], $ignore_id)) || $row['page_id'] == $ignore_id || ($enabled && !($row['page_enabled'] || $row['parent_enabled'])))
			{
				continue;
			}

			$selected = (is_array($select_id)) ? ((in_array($row['page_id'], $select_id)) ? ' selected="selected"' : '') : (($row['page_id'] == $select_id) ? ' selected="selected"' : '');
			$page_list .= '<option value="' . $row['page_id'] . '"' . $selected . ((!$row['page_enabled']) ? ' class="disabled"' : '') . '>' . $padding . $row['page_title'] . '</option>';

			$iteration++;
		}
		$db->sql_freeresult($result);

		unset($padding_store);

		return $page_list;
	}
	
	/**
	* Generate the icon to use for a page, based on its properties
	*/
	function page_icon($row)
	{
		global $phpbb_root_path, $config, $user;
		
		if (!$row['page_enabled'])
		{
			// Page is not enabled - regardless of type show deactivated icon
			return '<img src="images/icon_folder_lock.gif" alt="' . $user->lang['DEACTIVATED_PAGE'] .'" />';
		}
		elseif ($row['version_type'] == VERSION_TYPE_FILE && file_exists($phpbb_root_path . $config['cms_upload_path'] . '/thumb_' . $row['version_physical_filename']))
		{
			// Page is an image, show image as icon
			return '<img src="' . append_sid(generate_url($row['page_id'], $row['page_url']), 's=' . IMAGE_SIZE_THUMBNAIL) . '" alt="' . $user->lang['VERSION_TYPE_FILE'] . '" />';
		}
		elseif ($row['left_id'] + 1 != $row['right_id'])
		{
			// Page has children
			return '<img src="images/icon_subfolder.gif" alt="' . $user->lang['PARENT'] . '" />';
		}
		elseif ($row['version_type'] == VERSION_TYPE_LINK)
		{
			// Page type is a link or phpBB link, show a link icon
			return '<img src="images/icon_folder_link.gif" alt="' . $user->lang['VERSION_TYPE_LINK'] . '" />';
		}
		else
		{
			// Page has no children or we could not find an image icon
			return '<img src="images/icon_folder.gif" alt="' . $user->lang['PAGE'] . '" />';
		}
	}
	
	/**
	* Convert HTML to plain text, maintaining some formating
	* Used for generating text for the DIFF engine
	* Based on html2text by Jon Abernathy - http://www.chuggnutt.com/html2text.php
	*/ 
	function html_to_text($html)
	{
		$search = array(
		    "/\r/",                                  // Non-legal carriage return
		    "/[\n\t]+/",                             // Newlines and tabs
		    '/[ ]{2,}/',                             // Runs of spaces, pre-handling
		    '/<h[123][^>]*>(.*?)<\/h[123]>/ie',      // H1 - H3
		    '/<h[456][^>]*>(.*?)<\/h[456]>/ie',      // H4 - H6
		    '/<p[^>]*>/i',                           // <P>
		    '/<br[^>]*>/i',                          // <br>
		    '/<b[^>]*>(.*?)<\/b>/ie',                // <b>
		    '/<strong[^>]*>(.*?)<\/strong>/ie',      // <strong>
		    '/<i[^>]*>(.*?)<\/i>/i',                 // <i>
		    '/<em[^>]*>(.*?)<\/em>/i',               // <em>
		    '/(<ul[^>]*>|<\/ul>)/i',                 // <ul> and </ul>
		    '/(<ol[^>]*>|<\/ol>)/i',                 // <ol> and </ol>
		    '/<li[^>]*>(.*?)<\/li>/i',               // <li> and </li>
		    '/<li[^>]*>/i',                          // <li>
		    '/<hr[^>]*>/i',                          // <hr>
		    '/(<table[^>]*>|<\/table>)/i',           // <table> and </table>
		    '/(<tr[^>]*>|<\/tr>)/i',                 // <tr> and </tr>
		    '/<td[^>]*>(.*?)<\/td>/i',               // <td> and </td>
		    '/<th[^>]*>(.*?)<\/th>/ie',              // <th> and </th>
		    '/[ ]{2,}/'                              // Runs of spaces, post-handling
		);
		
		$replace = array(
		    '',                                     // Non-legal carriage return
		    ' ',                                    // Newlines and tabs
		    ' ',                                    // Runs of spaces, pre-handling
		    "strtoupper(\"\n\n\\1\n\n\")",          // H1 - H3
		    "ucwords(\"\n\n\\1\n\n\")",             // H4 - H6
		    "\n\n\t",                               // <P>
		    "\n",                                   // <br>
		    'strtoupper("\\1")',                    // <b>
		    'strtoupper("\\1")',                    // <strong>
		    '_\\1_',                                // <i>
		    '_\\1_',                                // <em>
		    "\n\n",                                 // <ul> and </ul>
		    "\n\n",                                 // <ol> and </ol>
		    "\t* \\1\n",                            // <li> and </li>
		    "\n\t* ",                               // <li>
		    "\n-------------------------\n",        // <hr>
		    "\n\n",                                 // <table> and </table>
		    "\n",                                   // <tr> and </tr>
		    "\t\t\\1\n",                            // <td> and </td>
		    "strtoupper(\"\t\t\\1\n\")",            // <th> and </th>
		    ' '                                     // Runs of spaces, post-handling
		);
		
		$text = preg_replace($search, $replace, html_entity_decode($html));
		
		// Strip out any remaining tags
		$text = ereg_replace('<[^<]*>', '', $text);
		
		// Limit max number of empty lines to 2
		$text = preg_replace("/\n\s+\n/", "\n\n", $text);
        $text = preg_replace("/[\n]{3,}/", "\n\n", $text);
		
		return $text;
	}
	
	function parent_select_js($page_id, $parent_id)
	{
		global $db, $template;
		
		$url = generate_url(-1, '', false);
		
		if ($parent_id == 0)
		{
			$template->assign_var('PARENT_URL', $url);
		}
		
		$template->assign_block_vars('pages', array(
			'PAGE_ID'	=> 0,
			'A_URL'		=> addslashes($url),
		));
		
		$sql = 'SELECT page_id, page_url
			FROM ' . PAGES_TABLE . '
			WHERE page_id != ' . $page_id;
		$result = $db->sql_query($sql);
		
		while ($row = $db->sql_fetchrow($result))
		{
			$url = generate_url(-1, $row['page_url'], false);
			
			if ($parent_id == $row['page_id'])
			{
				$template->assign_var('PARENT_URL', $url . '/');
			}
			
			$template->assign_block_vars('pages', array(
				'PAGE_ID'	=> $row['page_id'],
				'A_URL'		=> addslashes($url) . '/',
			));
		}
	}
	
	/**
	* Return correct language name
	*/
	function lang_name($module_langname)
	{
		global $user;

		return (!empty($user->lang[$module_langname])) ? $user->lang[$module_langname] : $module_langname;
	}
	
	/**
	* Generate the upload popup
	*/
	function upload($action)
	{
		global $template, $user, $db, $phpbb_root_path, $page_id, $config;
		
		$this->page_title = 'UPLOAD_' . ( ($action == 'image') ? 'IMAGE' : 'FILE');
		$upload = (isset($_REQUEST['upload'])) ? true : false;
		$template->assign_var('S_TINYMCE', true);
		$errors = array();
		
		if ($upload)
		{
			$submit = (isset($_POST['submit'])) ? true : false;
			
			$page = new page;
			$page->data = array_merge($page->data, array(
				'page_enabled'	=> 1,
				'page_display'	=> 0, // Since used as an embedded file, we almost certainly want it hidden
				'parent_id'		=> $this->parent_id,
				'version_type'	=> VERSION_TYPE_FILE,
				'page_title'	=> utf8_normalize_nfc(request_var('page_title', '')),
				'page_slug'		=> request_var('page_slug', ''),
				'upload_type'	=> request_var('upload_type', UPLOAD_TYPE_FILE),
				'upload_url'	=> request_var('upload_url', ''),
			));
		
			if ($submit)
			{
				if ($page->validate())
				{
					$page->save();
					$this->upload_insert($action, $page->data, true);
				}
				else
				{
					$errors = $page->errors;
				}
			}
		
			$this->parent_select_js(0, $this->parent_id);
			
			$s_cat_option = '<option value="0"' . (($page->data['parent_id'] == 0) ? ' selected="selected"' : '') . '>' . $user->lang['NO_PARENT'] . '</option>';
			$template->assign_vars(array_merge(array(
				'S_ERROR'					=> (sizeof($errors)) ? true : false,
				'S_UPLOAD'					=> true,
				'S_PARENT_OPTIONS'			=> $s_cat_option . $this->make_page_select($page->data['parent_id']),
				
				'U_BACK'	=> $this->u_action . '&amp;action=' . $action . '&amp;parent_id=' . $this->parent_id,
				'U_ACTION'	=> $this->u_action . '&amp;parent_id=' . $this->parent_id,
				
				// We can't use PAGE_TITLE here - it is used by for displaying the title of the rendered page
				// Use title instead
				'TITLE'				=> $page->data['page_title'],
			
				'UPLOAD_TYPE_FILE'	=> UPLOAD_TYPE_FILE,
				'UPLOAD_TYPE_URL'	=> UPLOAD_TYPE_URL,
				
				'A_SPACE_SEPARATOR'	=> addslashes(SPACE_SEPARATOR),
			
				'ERROR_MSG'			=> (sizeof($errors)) ? implode('<br />', $errors) : '',
				'ACTION'			=> $action,
				'PAGE_ID'			=> $page_id,
			),
				array_change_key_case($page->data, CASE_UPPER))
			);
		}
		elseif ($page_id)
		{
			$sql = 'SELECT *
				FROM ' . PAGES_TABLE . ' p
				JOIN ' . PAGES_VERSIONS_TABLE . " v
					ON p.version_id = v.version_id
				WHERE p.page_id = '" . $db->sql_escape($page_id) . "'";
			$result = $db->sql_query($sql);
			
			if (!($page_data = $db->sql_fetchrow($result)))
			{
				$errors[] = $user->lang['NO_PAGE'];
				
				// Go back to the tree
				$this->upload_navigate($action);
			}
			elseif ($page_data['page_enabled'] == 0 || $page_data['parent_enabled'] == 0)
			{
				// Don't let disabled pages be inserted
				$this->parent_id = $page_data['parent_id'];
				$errors[] = $user->lang['DISABLED_INSERT'];
				
				// Go back to the tree
				$this->upload_navigate($action);
			}
			else
			{
				$this->upload_insert($action, $page_data);
			}
			
			$db->sql_freeresult($result);
		}
		else
		{
			$this->upload_tree($action);
		}
		
		$template->assign_vars(array(
			'ERROR_MSG'		=> (sizeof($errors)) ? implode('<br />', $errors) : '',
			'S_ERROR'		=> (sizeof($errors)) ? true : false,
		));
	}
	
	/**
	* Generate the upload tree
	*/
	function upload_tree($action)
	{
		global $template, $user, $db, $page_id, $config;
		
		if (!$this->parent_id)
		{
			$navigation = $user->lang['SITE_ROOT'];
		}
		else
		{
			$navigation = '<a href="' . $this->u_action . '&amp;action=' . $action . '">' . $user->lang['SITE_ROOT'] . '</a>';
		
			$branch = get_page_branch($this->parent_id, 'parents', 'descending');
		
			foreach ($branch as $page)
			{
				if ($page['page_id'] == $this->parent_id)
				{
					$navigation .= ' -&gt; ' . $page['page_title'];
				}
				else
				{
					$navigation .= ' -&gt; <a href="' . $this->u_action . '&amp;action=' . $action . '&amp;parent_id=' . $page['page_id'] . '">' . $page['page_title'] . '</a>';
				}
			}
		}
	
		if ($this->parent_id)
		{
			$row = get_page_row($this->parent_id);
			
			if (!$row)
			{
				trigger_error($user->lang['NO_PAGE'] . adm_back_link($this->u_action . '&amp;parent_id=' . $this->parent_id), E_USER_WARNING);
			}
			
			$page_insert = ($action == 'file' || $row['version_image']) ? true : false;
			
			$template->assign_vars(array(
				'U_INSERT'			=> ($page_insert) ? $this->u_action . '&amp;action=' . $action . '&amp;p=' . $this->parent_id : false,
				
				'A_FILENAME'		=> ($page_insert && !$row['version_image'] && $row['page_enabled'] && $row['parent_enabled']) ? addslashes(generate_url(-1, $row['page_url'], false)) : false,
				// We need to encode double quotes here
				'A_EMBED_HTML'		=> ($page_insert && !$row['version_image'] && $row['page_enabled'] && $row['parent_enabled']) ? addslashes(str_replace('"', '&#34;', embed_page($row))) : false,
			));
		}
	
		$sql = "SELECT p.*, v.*
			FROM " . PAGES_TABLE . " p
			JOIN " . PAGES_VERSIONS_TABLE . " v
				ON v.version_id = p.version_id
			WHERE parent_id = {$this->parent_id}
			ORDER BY left_id ASC";
		$result = $db->sql_query($sql);
	
		if ($row = $db->sql_fetchrow($result))
		{
			$enabled_pages = false;
			
			do
			{
				$page_insert = ($action == 'file' || $row['version_image']) ? true : false;
				
				$template->assign_block_vars('pages', array(
					'PAGE_IMAGE'		=> $this->page_icon($row),
					'HOME_PAGE'			=> ($row['page_id'] == $config['home_page']) ? true : false,
					'PAGE_TITLE'		=> $row['page_title'],
					'PAGE_ENABLED'		=> ($row['page_enabled']) ? true : false,
					'PAGE_DISPLAY'		=> ($row['page_display']) ? true : false,
					
					'A_FILENAME'		=> ($page_insert && !$row['version_image'] && $row['page_enabled'] && $row['parent_enabled']) ? addslashes(generate_url(-1, $row['page_url'], false)) : false,
					// We need to encode double quotes here
					'A_EMBED_HTML'		=> ($page_insert && !$row['version_image'] && $row['page_enabled'] && $row['parent_enabled']) ? addslashes(str_replace('"', '&#34;', embed_page($row))) : false,
					
					'U_PAGE'			=> $this->u_action . '&amp;action=' . $action . '&amp;parent_id=' . $row['page_id'],
					'U_INSERT'			=> ($page_insert) ? $this->u_action . '&amp;action=' . $action . '&amp;p=' . $row['page_id'] : false,
				));
				
				$enabled_pages = ($row['page_enabled']) ? true : $enabled_pages;
			}
		
			while ($row = $db->sql_fetchrow($result));
		
		}
		$db->sql_freeresult($result);
		
		$template->assign_vars(array(
			'ICON_HOME'		=> '<img src="images/icon_home.gif" alt="' . $user->lang['SITE_INDEX'] .'" />',
		
			'NAVIGATION'	=> $navigation,
			'PAGE_BOX'		=> $this->make_page_select($this->parent_id),
			'PARENT_ID'		=> $this->parent_id,
			'ACTION'		=> $action,
		));
	}
	
	/**
	* Create the upload page to insert a page
	*/
	function upload_insert($action, $page_data, $uploaded = false)
	{
		global $phpbb_root_path, $config, $template, $user;
		
		$insert = (isset($_POST['insert'])) ? true : false;
		if ($page_data['version_image'] && !$insert)
		{
			$dimension = @getimagesize($phpbb_root_path . $config['cms_upload_path'] . '/' . $page_data['version_physical_filename']);
			list($width, $height, $type, ) = $dimension;

			list($small_x, $small_y) = get_cms_img_size_format($width, $height, IMAGE_SIZE_SMALL);
			list($medium_x, $medium_y) = get_cms_img_size_format($width, $height, IMAGE_SIZE_MEDIUM);
			list($large_x, $large_y) = get_cms_img_size_format($width, $height, IMAGE_SIZE_LARGE);


			$template->assign_vars(array(
				'U_ACTION'	=> $this->u_action,
				'U_BACK'	=> $this->u_action . '&amp;action=' . $action . '&amp;parent_id=' . $page_data['page_id'],
				
				'S_IMAGE_OPTIONS'	=> true,
				
				// We only show size options if the resized image will be smaller then the image itself
				'S_SIZE_SMALL'	=> ($small_x < $width) ? true : false,
				'S_SIZE_MEDIUM'	=> ($medium_x < $width) ? true : false,
				'S_SIZE_LARGE'	=> ($large_x < $width) ? true : false,
	
				'L_IMAGE_SIZE_SMALL'	=> sprintf($user->lang['IMAGE_SIZE_SMALL'], $small_x, $small_y),
				'L_IMAGE_SIZE_MEDIUM'	=> sprintf($user->lang['IMAGE_SIZE_MEDIUM'], $medium_x, $medium_y),
				'L_IMAGE_SIZE_LARGE'	=> sprintf($user->lang['IMAGE_SIZE_LARGE'], $large_x, $large_y),
				'L_IMAGE_SIZE_ORIGINAL'	=> sprintf($user->lang['IMAGE_SIZE_ORIGINAL'], $width, $height),
				
				'ACTION'		=> $action,
				'PAGE_ID'		=> $page_data['page_id'],
				'UPLOADED'		=> ($uploaded) ? 1 : 0,
				'IMAGE_SIZE'	=> IMAGE_SIZE_MEDIUM,
	
				'IMAGE_SIZE_SMALL'		=> IMAGE_SIZE_SMALL,
				'IMAGE_SIZE_MEDIUM'		=> IMAGE_SIZE_MEDIUM,
				'IMAGE_SIZE_LARGE'		=> IMAGE_SIZE_LARGE,
				'IMAGE_SIZE_ORIGINAL'	=> IMAGE_SIZE_ORIGINAL,
			));
		}
		else
		{
			$image_size = request_var('image_size', IMAGE_SIZE_ORIGINAL);
			
			// Force the page_url - don't hide it if it is the home page
			$u_page = generate_url(-1, $page_data['page_url'], false);
			$template->assign_vars(array(
				'S_INSERT'			=> true,
				
				'A_FILENAME'		=> addslashes($u_page. ( ($image_size) ? '?s=' . $image_size : '') ),
				'A_EMBED_HTML'		=> addslashes(embed_page($page_data, $image_size)),
				
				'ACTION'			=> $action,
				'PAGE_ID'			=> ($uploaded) ? $page_data['page_id'] : false,
			));
		}
	}
	
	/**
	* Determine if there are pages referencing the given page_id and assign template vars for confirm_link_breakage.html
	*/
	function link_breakage($page_id, $left_id = 0, $right_id = 0)
	{
		global $db, $template, $user;
		$s_internal_links = $link_breakage_explain = false;
		
		$sql = 'SELECT ' . ( ($left_id) ? 'p1.page_id to_id, p1.page_title to_title, ' : '' ) . 'p2.page_id from_id, p2.page_title from_title
			FROM ' . PAGES_LINKS_TABLE . ' l
			' . ( ($left_id) ? 'JOIN ' . PAGES_TABLE . ' p1' . // The page being linked to
				' ON p1.page_id = link_page_id' : '' ) . '
			JOIN ' . PAGES_VERSIONS_TABLE . ' v
				ON v.version_id = l.version_id
			JOIN ' . PAGES_TABLE . ' p2 ' . // The page thats linking to the page in question
				'ON p2.page_id = v.page_id
			WHERE ' . ( ($left_id) ? "p1.left_id BETWEEN $left_id AND $right_id" : 'link_page_id = ' . $page_id ) . '
				AND link_external = 0
				AND link_processed = 1
				AND l.version_id = p2.version_id
			ORDER BY link_time DESC';
		$result = $db->sql_query($sql);
		
		while ($row = $db->sql_fetchrow($result))
		{
			$s_internal_links = true;
			$template->assign_block_vars('internal_links', array(
				'U_FROM'		=> $this->u_action . '&amp;action=info&amp;p=' . $row['from_id'],
				'FROM_TITLE'	=> $row['from_title'],
				'TO'			=> ($left_id) ? sprintf($user->lang['LINKING_TO'], '<a href="' . $this->u_action . '&amp;action=info&amp;p=' . $row['to_id'] . '">' . $row['to_title'] . '</a>') : '',
			));
		}
		
		$sql = 'SELECT COUNT(link_id) external_links
			FROM ' . PAGES_LINKS_TABLE . '
			WHERE link_external = 1
				AND link_processed = 1
				AND link_page_id = ' . $page_id;
		$result = $db->sql_query($sql);
		$external_links = $db->sql_fetchfield('external_links');
		$s_external_links = ($external_links) ? true : false;
		
		if($s_internal_links && $s_external_links)
		{
			$link_breakage_explain = sprintf($user->lang['LINK_BREAKAGE_INTERNAL_EXTERNAL'], $external_links);
		}
		elseif($s_internal_links)
		{
			$link_breakage_explain = $user->lang['LINK_BREAKAGE_INTERNAL'];
		}
		elseif($s_external_links)
		{
			$link_breakage_explain = sprintf($user->lang['LINK_BREAKAGE_EXTERNAL'], $external_links);
		}
		
		$template->assign_vars(array(
			'U_LINKS'				=> $this->u_action . '&amp;action=links&amp;p=' . $page_id,
			'S_INTERNAL_LINKS'		=> $s_internal_links,
			'S_EXTERNAL_LINKS'		=> $s_external_links,
			'LINK_BREAKAGE_EXPLAIN'	=> $link_breakage_explain,
		));
		
		return ($s_internal_links || $s_external_links) ? true : false;
	}
}
?>
