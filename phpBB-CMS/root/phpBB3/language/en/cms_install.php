<?php
/**
*
* cms_install [English]
*
* @package language
* @copyright (c) 2012 Michael Cullum (Unknown Bliss of http://michaelcullum.com)
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
* DO NOT CHANGE
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = array();
}

// DEVELOPERS PLEASE NOTE
//
// All language files should use UTF-8 as their encoding and the files must not contain a BOM.
//
// Placeholders can now contain order information, e.g. instead of
// 'Page %s of %s' you can (and should) write 'Page %1$s of %2$s', this allows
// translators to re-order the output of data while ensuring it remains correct
//
// You do not need this where single placeholders are used, e.g. 'Message %d' is fine
// equally where a string contains only two placeholders which are used to wrap text
// in a url you again do not need to specify an order e.g., 'Click %sHERE%s' is fine

$lang = array_merge($lang, array(
	'PHPBB_CMS'	=> 'phpBB CMS',
	
	'INSTALL_PHPBB_CMS'				=> 'Install phpBB CMS',
	'INSTALL_PHPBB_CMS_CONFIRM'		=> 'This installer will complete the installation of phpBB CMS onto your board. You must have copied all the files supplied with phpBB CMS to proceed.',
	'UPDATE_PHPBB_CMS'				=> 'Update phpBB CMS',
	'UPDATE_PHPBB_CMS_CONFIRM'		=> 'This will update phpBB CMS to the current version.',
	'UNINSTALL_PHPBB_CMS'			=> 'Uninstall phpBB CMS',
	'UNINSTALL_PHPBB_CMS_CONFIRM'	=> 'This will completely remove phpBB CMS from your board, including any pages you have created. You may want to remove the CMS files from your board once the uninstall is complete.',
	
	'ADD_DEMO_PAGES'			=> 'Add demo pages',
	'WRITE_CMS_CONFIG'			=> 'Write CMS config file: %s',
	'LOG_ACTION_CMS_INSTALL'	=> 'Log install: phpBB CMS %s',
	'LOG_ACTION_CMS_UPDATE'		=> 'Log update: phpBB CMS to version %s',
	'LOG_ACTION_CMS_UNINSTALL'	=> 'Remove CMS log entries',
	'WRITE_CMS_CONFIG_FAIL'		=> 'Fail: Open the file and follow the instructions to complete installation.',
	'INDEX_CONTENT'				=> '<h2 id="welcome-to-phpbb-cms">Welcome to phpBB CMS</h2>
<p>Congratulations, you have successfully installed phpBB CMS. phpBB CMS integrates seamlessly with your forum to give you a powerful and lightweight CMS.</p>',
	'COMMUNITY'					=> 'Community',
));

?>
