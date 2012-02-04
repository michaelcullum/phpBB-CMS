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
class acp_cms_settings_info
{
	function module()
	{
		return array(
			'filename'	=> 'acp_cms_settings',
			'title'		=> 'ACP_CMS_SETTINGS',
			'version'	=> '0.0.1',
			'modes'		=> array(
				'cms_settings'		=> array(
					'title'	=> 'ACP_CMS_SETTINGS',
					'auth'	=> 'acl_a_cms',
					'cat'	=> array('ACP_CMS_GENERAL')
				),
				'writing_settings'	=> array(
					'title'	=> 'ACP_CMS_WRITING_SETTINGS',
					'auth'	=> 'acl_a_cms',
					'cat'	=> array('ACP_CMS_CONFIGURATION')
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
