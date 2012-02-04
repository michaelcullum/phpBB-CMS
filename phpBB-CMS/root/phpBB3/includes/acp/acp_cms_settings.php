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
class acp_cms_settings
{
	var $u_action;
	var $new_config = array();

	function main($id, $mode)
	{
		global $db, $user, $auth, $template;
		global $config, $phpbb_root_path, $phpbb_admin_path, $phpEx;

		$user->add_lang('acp/cms_settings');
		
		$action	= request_var('action', '');
		$submit = (isset($_POST['submit'])) ? true : false;

		$form_key = 'acp_cms_settings';
		add_form_key($form_key);

		/**
		*	Validation types are:
		*		string, int, bool,
		*		script_path (absolute path in url - beginning with / and no trailing slash),
		*		rpath (relative), rwpath (realtive, writable), path (relative path, but able to escape the root), wpath (writable)
		*/
		switch ($mode)
		{
			case 'cms_settings':
				$display_vars = array(
					'title'	=> 'ACP_CMS_SETTINGS',
					'vars'	=> array(
						'legend1'				=> 'SERVER_URL_SETTINGS',
						'cms_path'				=> array('lang' => 'CMS_PATH',				'validate' => 'script_path',	'type' => 'text::255', 'explain' => true),
						'mod_rewrite'			=> array('lang' => 'MOD_REWRITE',			'validate' => 'bool',			'type' => 'radio:yes_no', 'explain' => true),
						'force_www'				=> array('lang' => 'FORCE_WWW',				'validate' => 'bool',			'type' => 'radio:yes_no', 'explain' => true),
						
						'legend2'				=> 'DISABLE_CMS',
						'cms_disable'			=> array('lang' => 'DISABLE_CMS',			'validate' => 'bool',			'type' => 'custom', 'method' => 'cms_disable', 'explain' => true),
						'cms_disable_msg'		=> false,
					)
				);
			break;
			
			case 'writing_settings':
				$display_vars = array(
					'title'	=> 'ACP_CMS_WRITING_SETTINGS',
					'vars'	=> array(
						'legend1'				=> 'PAGE_SETTINGS',
						'version_control'		=> array('lang' => 'ENABLE_VERSION_CONTROL',	'validate' => 'bool',	'type' => 'radio:yes_no', 'explain' => true),
						'cms_parse_template'	=> array('lang' => 'CMS_PARSE_TEMPLATE',		'validate' => 'bool',	'type' => 'radio:yes_no', 'explain' => true),
						
						'legend2'				=> 'EDITOR_SETTINGS',
						'preview_style'			=> array('lang' => 'PREVIEW_STYLE',			'validate' => 'bool',	'type' => 'radio:yes_no', 'explain' => true),
						'enable_spellchecker'	=> array('lang' => 'ENABLE_SPELLCHECKER',	'validate' => 'bool',	'type' => 'radio:yes_no', 'explain' => true),
						'br_newlines'			=> array('lang' => 'BR_NEWLINES',			'validate' => 'bool',	'type' => 'radio:yes_no', 'explain' => true),
						
						'legend3'				=> 'NOTIFICATION_SETTINGS',
						'email_notifications'	=> array('lang' => 'EMAIL_NOTIFICATIONS',	'validate' => 'int',	'type' => 'custom', 'method' => 'select_email_notifications', 'explain' => true),
						'send_linkbacks'		=> array('lang' => 'SEND_LINKBACKS',		'validate' => 'bool',	'type' => 'radio:yes_no', 'explain' => true),
						'log_incomming_links'	=> array('lang' => 'LOG_INCOMMING_LINKS',	'validate' => 'bool',	'type' => 'radio:yes_no', 'explain' => true),
						
						'legend4'				=> 'FILE_SETTINGS',
						'cms_upload_path'		=> array('lang' => 'UPLOAD_DIR',			'validate' => 'wpath',	'type' => 'text:25:100', 'explain' => true),
					)
				);
			break;

			default:
				trigger_error('NO_MODE', E_USER_ERROR);
			break;
		}

		$this->new_config = $config;
		$cfg_array = (isset($_REQUEST['config'])) ? utf8_normalize_nfc(request_var('config', array('' => ''), true)) : $this->new_config;
		$error = array();

		// We validate the complete config if whished
		validate_config_vars($display_vars['vars'], $cfg_array, $error);

		if ($submit && !check_form_key($form_key))
		{
			$error[] = $user->lang['FORM_INVALID'];
		}
		// Do not write values if there is an error
		if (sizeof($error))
		{
			$submit = false;
		}

		// We go through the display_vars to make sure no one is trying to set variables he/she is not allowed to...
		foreach ($display_vars['vars'] as $config_name => $null)
		{
			if (!isset($cfg_array[$config_name]) || strpos($config_name, 'legend') !== false)
			{
				continue;
			}

			$this->new_config[$config_name] = $config_value = $cfg_array[$config_name];

			if ($submit)
			{
				set_config($config_name, $config_value);
			}
		}

		if ($submit)
		{
			add_log('admin', 'LOG_CONFIG_' . strtoupper($mode));

			trigger_error($user->lang['CONFIG_UPDATED'] . adm_back_link($this->u_action));
		}

		$this->tpl_name = 'acp_board';
		$this->page_title = $display_vars['title'];

		$template->assign_vars(array(
			'L_TITLE'			=> $user->lang[$display_vars['title']],
			'L_TITLE_EXPLAIN'	=> $user->lang[$display_vars['title'] . '_EXPLAIN'],

			'S_ERROR'			=> (sizeof($error)) ? true : false,
			'ERROR_MSG'			=> implode('<br />', $error),

			'U_ACTION'			=> $this->u_action)
		);

		// Output relevant page
		foreach ($display_vars['vars'] as $config_key => $vars)
		{
			if (!is_array($vars) && strpos($config_key, 'legend') === false)
			{
				continue;
			}

			if (strpos($config_key, 'legend') !== false)
			{
				$template->assign_block_vars('options', array(
					'S_LEGEND'		=> true,
					'LEGEND'		=> (isset($user->lang[$vars])) ? $user->lang[$vars] : $vars)
				);

				continue;
			}

			$type = explode(':', $vars['type']);

			$l_explain = '';
			if ($vars['explain'] && isset($vars['lang_explain']))
			{
				$l_explain = (isset($user->lang[$vars['lang_explain']])) ? $user->lang[$vars['lang_explain']] : $vars['lang_explain'];
			}
			else if ($vars['explain'])
			{
				$l_explain = (isset($user->lang[$vars['lang'] . '_EXPLAIN'])) ? $user->lang[$vars['lang'] . '_EXPLAIN'] : '';
			}

			$content = build_cfg_template($type, $config_key, $this->new_config, $config_key, $vars);

			if (empty($content))
			{
				continue;
			}

			$template->assign_block_vars('options', array(
				'KEY'			=> $config_key,
				'TITLE'			=> (isset($user->lang[$vars['lang']])) ? $user->lang[$vars['lang']] : $vars['lang'],
				'S_EXPLAIN'		=> $vars['explain'],
				'TITLE_EXPLAIN'	=> $l_explain,
				'CONTENT'		=> $content,
				)
			);

			unset($display_vars['vars'][$config_key]);
		}

		if ($mode == 'auth')
		{
			$template->assign_var('S_AUTH', true);

			foreach ($auth_plugins as $method)
			{
				if ($method && file_exists($phpbb_root_path . 'includes/auth/auth_' . $method . '.' . $phpEx))
				{
					$method = 'acp_' . $method;
					if (function_exists($method))
					{
						$fields = $method($this->new_config);

						if ($fields['tpl'])
						{
							$template->assign_block_vars('auth_tpl', array(
								'TPL'	=> $fields['tpl'])
							);
						}
						unset($fields);
					}
				}
			}
		}
	}

	/**
	* CMS disable option and message
	*/
	function cms_disable($value, $key)
	{
		global $user;

		$radio_ary = array(1 => 'YES', 0 => 'NO');
		
		// Bit of a hack, but it works
		return '<script type="text/javascript" src="style/cms.js"></script>' . h_radio('config[cms_disable]', $radio_ary, $value) . '<br /><input id="' . $key . '" type="text" name="config[cms_disable_msg]" maxlength="255" size="40" value="' . $this->new_config['cms_disable_msg'] . '" />';
	}
	
	/**
	* Select email notifications method
	*/
	function select_email_notifications($value, $key = '')
	{
		global $user, $config;

		$radio_ary = array(
			EMAIL_NOTIFICATIONS_NONE			=> 'EMAIL_NOTIFICATIONS_NONE',
			EMAIL_NOTIFICATIONS_ALL				=> 'EMAIL_NOTIFICATIONS_ALL',
			EMAIL_NOTIFICATIONS_CONTRIBUTORS	=> 'EMAIL_NOTIFICATIONS_CONTRIBUTORS',
		);
		
		return '<script type="text/javascript" src="style/cms.js"></script>' . h_radio('config[email_notifications]', $radio_ary, $value, $key);
	}
}

?>
