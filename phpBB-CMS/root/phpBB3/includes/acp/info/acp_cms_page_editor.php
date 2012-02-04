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
class acp_cms_page_editor_info
{
	function module()
	{
		return array(
			'filename'	=> 'acp_cms_page_editor',
			'title'		=> 'ACP_CMS_PAGE_EDITOR',
			'version'	=> '0.0.1',
			'modes'		=> array(
				'page_editor'	=> array(
					'title'	=> 'ACP_CMS_PAGE_EDITOR',
					'auth'	=> 'acl_a_cms',
					'cat'	=> array('ACP_CMS_PAGES')),
				'upload'		=> array(
					'title'		=> 'ACP_CMS_UPLOAD',
					'auth'		=> 'acl_a_cms',
					'cat'		=> array('ACP_CMS_PAGES'),
					'display'	=> false,
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
