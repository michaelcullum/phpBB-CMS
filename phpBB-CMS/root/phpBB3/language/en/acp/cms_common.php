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
	
	'ACP_CAT_CMS'				=> 'CMS',
	'ACP_CAT_CMS_GENERAL'		=> 'General',
	'ACP_CAT_CMS_CONFIGURATION'	=> 'CMS configuration',
	
	'ACP_CAT_CMS_MODULES'		=> 'Modules',
	
	'ACP_CMS_INDEX'				=> 'CMS index',
	'ACP_CMS_MAIN'				=> 'CMS index',
	'ACP_CMS_SETTINGS'			=> 'CMS settings',
	'ACP_CMS_WRITING_SETTINGS'	=> 'Writing settings',
	'ACP_CAT_CMS_PAGES'			=> 'Pages',
	'ACP_CMS_PAGE_EDITOR'		=> 'Page editor',
	'ACP_CMS_UPLOAD'			=> 'Upload',
	
	'WELCOME_PHPBB_CMS'		=> 'Welcome to phpBB CMS',
	'CMS_INTRO'				=> 'phpBB CMS is a simple, yet powerful CMS that integrates seamlessly with phpBB. This screen will give you a quick overview of all the various statistics of your CMS.',
	
	'ACP_CMS_PAGE_EDITOR_EXPLAIN'		=> 'Here you are able to create, edit and delete pages.',
	'ACP_CMS_SETTINGS_EXPLAIN'			=> 'Here you can determine the basic settings for the CMS; server configuration and URLs.',
	'ACP_CMS_WRITING_SETTINGS_EXPLAIN'	=> 'Here you can determine the settings for writing on the CMS.',
	
	'SITE_ROOT'			=> 'Root',
	'PAGE'				=> 'Page',
	'PAGES'				=> 'Pages',
	'PAGE_PATH'			=> 'Path',
	'PAGE_ENABLED'		=> 'Enabled',
	'PAGE_DISABLED'		=> 'Disabled',
	'PAGE_DISPLAY'		=> 'Page displayed',
	
	'VERSION'		=> 'Version',
	'VERSIONS'		=> 'Versions',
	'PAGE_TITLE'	=> 'Title',
	'PAGE_SLUG'		=> 'Slug',
	'VERSION_DESC'	=> 'Description',
	
	'VERSION_TYPE'				=> 'Type',
	'VERSION_TYPE_HTML'			=> 'HTML',
	'VERSION_TYPE_CATEGORY'		=> 'Category',
	'VERSION_TYPE_FILE'			=> 'File',
	'VERSION_TYPE_FILE_INFO'	=> 'File (%s)',
	'VERSION_TYPE_MODULE'		=> 'Module',
	'VERSION_TYPE_LINK'			=> 'Link',
	
	'NO_PAGES'			=> 'You have not yet created any pages. Until pages are created, your site will not be accessable.',
	'NO_ENABLED_PAGES'	=> 'You have created pages, but none of them are enabled. Until there are enabled pages, your site will not be accessable.',
	
	'CMS_STATS'				=> 'CMS statistics',
	'NUMBER_PAGES'			=> 'Number of pages',
	'NUMBER_VERSIONS'		=> 'Number of versions',
	'NUMBER_VIEWS'			=> 'Number of views',
	'NUMBER_HTML_PAGES'		=> 'Number of HTML pages',
	'NUMBER_URL_PAGES'		=> 'Number of URL pages',
	'NUMBER_CUSTOM_PAGES'	=> 'Number of custom pages',
	'VIEWS_PER_DAY'			=> 'Views per day',
	'CMS_STARTED'			=> 'CMS started',
	'CMS_VERSION'			=> 'CMS version',
	'TINY_MCE_VERSION'		=> 'TinyMCE version',
	
	'RESET_VIEWS'				=> 'Reset page views',
	'RESET_VIEWS_CONFIRM'		=> 'Are you sure you wish to reset the views counter?',
	'RESET_CMS_DATE'			=> 'Reset CMS’s start date',
	'RESET_CMS_DATE_CONFIRM'	=> 'Are you sure you wish to reset the CMS’s start date?',
	'RESYNC_CMS_STATS'			=> 'Resynchronise statistics',
	'RESYNC_CMS_STATS_CONFIRM'	=> 'Are you sure you wish to resynchronise statistics?',
	'RESYNC_CMS_STATS_EXPLAIN'	=> 'Recalculates the total number of pages, versions and views.',
	'RESYNC_PAGE_DATA'			=> 'Resynchronise page data',
	'RESYNC_PAGE_DATA_CONFIRM'	=> 'Are you sure you wish to resynchronise page data?',
	'RESYNC_PAGE_DATA_EXPLAIN'	=> 'Recalculates the home page, URLs and log history.',
	
	'LOCKS'			=> 'Locks',
	'LOCKS_EXPLAIN'	=> 'The following pages are locked for editing by other users.',
	
	'CMS_LOG'				=> 'Logged CMS actions',
	'CMS_LOG_INDEX_EXPLAIN'	=> 'This gives an overview of the last five actions carried out by board administrators on the CMS. A full copy of the log can be viewed from the appropriate menu item or following the link below.',
	
	'POPULAR_PAGES'			=> 'Popular pages',
	'POPULAR_PAGES_EXPLAIN'	=> 'This is a list of most popular pages on the site, based on views.',
	'PAGE_VIEWS_INFO'		=> '(%.2f%% of site views / %.2f views per day)',
	
	'INCOMMING_LINKS'			=> 'Incomming links',
	'INCOMMING_LINKS_EXPLAIN'	=> 'This is a list of the top incomming links from external sites, based on refers.',
	'LINK_EXTERNAL_URL'			=> 'External URL',
	'LINK_REFERS'				=> 'Refers',
	'REFERS_DAY'				=> '(%.2f refers per day)',
	
	'LOG_INTERNAL_BROKEN_LINK'	=> '<strong>Internal broken link on page</strong><br /> “%1$s”<br /> linking to “%2$s”',
	'LOG_EXTERNAL_BROKEN_LINK'	=> '<strong>External broken link on page</strong><br /> “%1$s”<br /> linking to “%2$s”',
	
	'LOG_RESYNC_CMS_STATS'			=> '<strong>Page statistics resynchronised</strong>',
	'LOG_RESYNC_PAGE_DATA'			=> '<strong>Page data resynchronised</strong>',
	'LOG_RESET_PAGE_VIEWS'			=> '<strong>Page views reset</strong>',
	'LOG_RESET_CMS_DATE'			=> '<strong>CMS start date reset</strong>',
	'LOG_CONFIG_CMS_SETTINGS'		=> '<strong>Altered CMS settings</strong>',
	'LOG_CONFIG_WRITING_SETTINGS'	=> '<strong>Altered writing settings</strong>',
	
	'LOG_INCOMMING_LINK'		=> '<strong>New link</strong> from %1$s<br />» %2$s',
	'LOG_PAGE_TITLE'			=> '<strong>Page title changed</strong><br />» from %1$s to %2$s',
	'LOG_PAGE_SLUG'				=> '<strong>Page slug changed</strong><br />» from %1$s to %2$s » %3$s',
	'LOG_PAGE_PARENT'			=> '<strong>Page parent changed</strong> from %1$s to %2$s<br />» %3$s',
	'LOG_PAGE_DISABLE'			=> '<strong>Page disabled</strong><br />» %s',
	'LOG_PAGE_ENABLE'			=> '<strong>Page enabled</strong><br />» %s',
	'LOG_PAGE_NAV_DISPLAY'		=> '<strong>Show page in navigation</strong><br />» %s',
	'LOG_PAGE_NAV_HIDE'			=> '<strong>Hide page in navigation</strong><br />» %s',
	'LOG_PAGE_STYLE'			=> '<strong>Page style changed</strong> from %1$s to %2$s<br />» %3$s',
	'LOG_PAGE_MOVE_DOWN'		=> '<strong>Page moved down</strong><br />» %1$s below %2$s',
	'LOG_PAGE_MOVE_UP'			=> '<strong>Page moved up</strong><br />» %1$s above %2$s',
	'LOG_PAGE_CONTENTS_ENABLE'	=> '<strong>Contents table enabled</strong><br />» %s',
	'LOG_PAGE_CONTENTS_DISABLE'	=> '<strong>Contents table disabled</strong><br />» %s',
	'LOG_PAGE_REMOVED'			=> '<strong>Page removed</strong><br />» %s',
	'LOG_PAGE_ADD'				=> '<strong>Page added</strong><br />» %s',
	'LOG_PAGE_EDIT'				=> '<strong>Page edited</strong><br />» %s',
	'LOG_VERSION_ADD'			=> '<strong>Version added</strong><br />»  V%1$s &bull; %2$s',
	'LOG_VERSION_REMOVED'		=> '<strong>Version removed</strong><br />» V%1$s &bull; %2$s',
	'LOG_PAGE_REVERT'			=> '<strong>Reverted page</strong> %1$s<br />» from V%2$s to V%3$s',
	'LOG_CMS_INSTALL'			=> '<strong>Installed phpBB CMS %s</strong>',
	'LOG_CMS_UPDATE'			=> '<strong>Updated phpBB CMS to version %s</strong>',
	
	'LOG_INCOMMING_LINK_INFO'			=> '<strong>New link</strong> from %1$s',
	'LOG_PAGE_TITLE_INFO'				=> '<strong>Title changed</strong> » from %1$s to %2$s',
	'LOG_PAGE_SLUG_INFO'				=> '<strong>Slug changed</strong> » from %1$s to %2$s',
	'LOG_PAGE_PARENT_INFO'				=> '<strong>Parent changed</strong> from %1$s to %2$s',
	'LOG_PAGE_DISABLE_INFO'				=> '<strong>Page disabled</strong>',
	'LOG_PAGE_ENABLE_INFO'				=> '<strong>Page enabled</strong>',
	'LOG_PAGE_NAV_DISPLAY_INFO'			=> '<strong>Show page in navigation</strong>',
	'LOG_PAGE_NAV_HIDE_INFO'			=> '<strong>Hide page in navigation</strong>',
	'LOG_PAGE_STYLE_INFO'				=> '<strong>Style changed</strong> from %1$s to %2$s',
	'LOG_PAGE_MOVE_DOWN_INFO'			=> '<strong>Page moved down</strong> » %1$s below %2$s',
	'LOG_PAGE_MOVE_UP_INFO'				=> '<strong>Page moved up</strong> » %1$s above %2$s',
	'LOG_PAGE_CONTENTS_ENABLE_INFO'		=> '<strong>Contents table enabled</strong>',
	'LOG_PAGE_CONTENTS_DISABLE_INFO'	=> '<strong>Contents table disabled</strong>',
	'LOG_PAGE_ADD_INFO'					=> '<strong>Page added</strong>',
	'LOG_PAGE_EDIT_INFO'				=> '<strong>Page edited</strong>',
	'LOG_VERSION_ADD_INFO'				=> '<strong>Version added</strong> » V%1$s',
	'LOG_VERSION_REMOVED_INFO'			=> '<strong>Version removed</strong> » V%1$s',
	'LOG_PAGE_REVERT_INFO'				=> '<strong>Reverted page</strong> from V%2$s to V%3$s',
));

?>
