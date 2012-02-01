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
class cms_site_map_info
{
	function module()
	{
		return array(
			'filename'	=> 'cms_site_map',
			'title'		=> 'CMS_SITE_MAP',
			'version'	=> '0.0.1',
			'modes'		=> array(
				'site_map'		=> array('title' => 'CMS_SITE_MAP'),
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
