<?php
/**
*
* cms_settings [English]
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
	
	'SERVER_URL_SETTINGS'	=> 'Server URL settings',
	
	'CMS_PATH'			=> 'CMS Path',
	'CMS_PATH_EXPLAIN'	=> 'The path where the CMS is located relative to the domain name, e.g. <samp>/cms</samp>. Don’t include a trailing slash.',
	
	'MOD_REWRITE'			=> 'Use mod_rewrite',
	'MOD_REWRITE_EXPLAIN'	=> 'Use the Apache rewrite module on your server to produce well-formatted URLs.',
	
	'FORCE_WWW'			=> 'Force www',
	'FORCE_WWW_EXPLAIN'	=> 'This will force the www. prefix on URLs.',
	
	'DISABLE_CMS'			=> 'Disable CMS',
	'DISABLE_CMS_EXPLAIN'	=> 'This will make the CMS unavailable to users. You can also enter a short (255 character) message to display if you wish. The CMS will remain enabled even if the board is disabled.',
	
	
	'NOTIFICATION_SETTINGS'			=> 'Notification settings',
	
	'EMAIL_NOTIFICATIONS'				=> 'Email notifications',
	'EMAIL_NOTIFICATIONS_EXPLAIN'		=> 'Send email notifications to administrators whenever a page is edited.',
	'EMAIL_NOTIFICATIONS_NONE'			=> 'None',
	'EMAIL_NOTIFICATIONS_ALL'			=> 'All',
	'EMAIL_NOTIFICATIONS_CONTRIBUTORS'	=> 'Contributors',
	
	'SEND_LINKBACKS'			=> 'Send linkbacks',
	'SEND_LINKBACKS_EXPLAIN'	=> 'External websites you link to will be notified that you link to them, using the pingbacks and trackbacks protocol, if the target site support it.',
	
	'LOG_INCOMMING_LINKS'			=> 'Log incomming links',
	'LOG_INCOMMING_LINKS_EXPLAIN'	=> 'If enabled, links from external sites will be shown in the sites log and the page version control. Disable for high traffic sites.',
	
	'PAGE_SETTINGS'	=> 'Page settings',
	
	'ENABLE_VERSION_CONTROL'			=> 'Enable version control',
	'ENABLE_VERSION_CONTROL_EXPLAIN'	=> 'Version control will track edits to pages made by users. Disable it if you do not need this functionality to reduce the database size. Any existing versions will still be stored if this is disabled.',
	
	'CMS_PARSE_TEMPLATE'			=> 'Parse pages as templates',
	'CMS_PARSE_TEMPLATE_EXPLAIN'	=> 'Allows template markup to be used within pages, as well as <code>&lt;?php ?&gt;</code> and <code>INCLUDEPHP</code> statements (if allow PHP in templates is enabled).',
	
	'EDITOR_SETTINGS'				=> 'Editor settings',
	'PREVIEW_STYLE'					=> 'Preview style',
	'PREVIEW_STYLE_EXPLAIN'			=> 'Shows the effect the current style will have on your page.',
	'ENABLE_SPELLCHECKER'			=> 'Enable spellchecker',
	'ENABLE_SPELLCHECKER_EXPLAIN'	=> 'Uses the Google spellchecker service to check spelling.',
	'BR_NEWLINES'					=> 'Force single newlines instead of paragraphs',
	'BR_NEWLINES_EXPLAIN'			=> 'Single newlines make the editor behave more like a word processor, although using paragraphs are a better idea.',
	
	
	'FILE_SETTINGS'	=> 'File settings',
	
	'UPLOAD_DIR'			=> 'Upload directory',
	'UPLOAD_DIR_EXPLAIN'	=> 'Storage path for uploads. Please note that if you change this directory while already having uploaded files you need to manually copy the files to their new location.',
));

?>
