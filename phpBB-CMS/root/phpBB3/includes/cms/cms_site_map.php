<?php
/**
*
* @package cms
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
* @package cms
*/
class cms_site_map
{
	var $u_action;
	var $page;

	function main($id, $mode)
	{
		global $config, $db, $user, $auth, $template, $cache;
		global $phpbb_root_path, $phpbb_admin_path, $phpEx, $table_prefix;
		
		$this->tpl_name = 'cms_default';
		
		$template->assign_vars(array(
			'PAGE_CONTENT'	=> generate_page_list(),
		));
	}
}

?>
