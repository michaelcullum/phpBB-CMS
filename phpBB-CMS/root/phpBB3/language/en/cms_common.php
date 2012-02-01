<?php
/**
*
* cms_common [English]
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
//
// Some characters you may want to copy&paste:
// ’ » “ ” …
//

$lang = array_merge($lang, array(
	'SITE_INDEX'		=> 'Home',
	'NO_PAGE'			=> 'The page you selected does not exist.',
	
	'SELECT_PAGE'		=> 'Select a page',
	
	'CMS_DISABLE'		=> 'Sorry but this site is currently unavailable.',
	'CMS_DISABLED'		=> 'This CMS is currently disabled.',
	'CMS_NOT_INSTALLED'	=> 'Please use the <a href="%s">UMIL installer</a> to complete the installation.',
	
	'PAGE_ADM_ONLY'		=> 'This page is disabled and is only accessible to administrators.',
	'VERSION_ADM_ONLY'	=> 'This a previous version of the page, only accessible to administrators.',
	
	'VERSION_NUMBER'	=> 'V%s',
	'PAGE_CREATED'		=> 'Created',
	'PAGE_LAST_MOD'		=> 'Modified',
	'PAGE_VIEWS'		=> 'Views',
	'PRINT'				=> 'Print',
	'EDIT'				=> 'Edit',
));

?>
