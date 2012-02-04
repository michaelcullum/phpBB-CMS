<?php
/**
*
* @package acp
* @copyright (c) 2012 Michael Cullum (Unknown Bliss of http://michaelcullum.com)
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
* @package module_install
*/
class acp_cms_main_info
{
	function module()
	{
		return array(
			'filename'	=> 'acp_cms_main',
			'title'		=> 'ACP_CMS_INDEX',
			'version'	=> '0.0.1',
			'modes'		=> array(
				'main'		=> array(
					'title'	=> 'ACP_CMS_INDEX',
					'auth'	=> 'acl_a_cms',
					'cat'	=> array('ACP_CAT_CMS_GENERAL')
				),
			),
		);
	}

	function install()
	{
	}

	function uninstall()
	{
	}
}

?>
