<?php
/**
*
* @package cms
* @copyright (c) 2012 Michael Cullum (Unknown Bliss of http://michaelcullum.com)
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
* @package module_install
*/
class cms_news_info
{
	function module()
	{
		return array(
			'filename'	=> 'cms_news',
			'title'		=> 'CMS_NEWS',
			'version'	=> '0.0.1',
			'modes'		=> array(
				'index'	=> array(
					'title'	=> 'CMS_NEWS',
					'html'	=> true,
					'acp'	=> array(
						'basename'	=> 'cms_news',
						'mode'		=> 'news',
					),
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
