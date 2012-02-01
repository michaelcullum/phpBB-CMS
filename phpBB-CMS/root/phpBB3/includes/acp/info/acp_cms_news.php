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
class acp_cms_news_info
{
	function module()
	{
		return array(
			'filename'	=> 'acp_cms_news',
			'title'		=> 'ACP_CMS_NEWS',
			'version'	=> '0.0.1',
			'modes'		=> array(
				'news'	=> array(
					'title'		=> 'ACP_CMS_NEWS',
					'auth'		=> 'acl_a_cms',
					'cat'		=> array('ACP_CMS_CAT_MODULES'),
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
