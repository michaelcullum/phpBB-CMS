<?php
/**
*
* cms_page_editor [English]
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
	
	'NO_PAGE_ID'			=> 'No page id specified.',
	'NO_VERSION_ID_DIFF'	=> 'You must choose 2 versions to compare.',
	'PARENT_NO_EXIST'		=> 'The parent is invalid.',
	'NO_PARENT'				=> 'No parent',
	'CURRENT_PAGE'			=> 'Current page',
	
	'PAGE_INFO'	=> 'Page info',
	'PARENT'	=> 'Parent',
	
	'PAGE_URL'				=> 'Page URL',
	'VERSION_CONTROL'		=> 'Version control',
	'CURRENT_VERSION'		=> 'Current version',
	'EDITS'					=> 'Edits',
	
	'LINKS'				=> 'Links',
	'NO_LINKS'			=> 'No links',
	'LINKS_INTERNAL'	=> '%d internal',
	'LINKS_EXTERNAL'	=> '%d external',
	
	'VISIBILITY'			=> 'Visibility',
	'VISABILITY_DISPLAY'	=> 'display in navigation',
	'VISABILITY_HIDE'		=> 'hide from navigation',
	
	'LINK_TYPE'			=> 'Link type',
	'LINK_TYPE_URL'		=> 'External URL',
	'LINK_TYPE_PAGE'	=> 'Internal page',
	'LINK_TYPE_PHPBB'	=> 'phpBB Index',
	
	'PAGE_SLUG_EXPLAIN'			=> 'Produces the URL used to access the page. Slugs are restricted to any letter, number and spacer (-, +, _, ., [ and ]).',
	'PARENT_EXPLAIN'			=> 'Pages can be arranged in hierarchies, so that pages can belong to another page. A page can be infinately deeply nested.',
	'PAGE_ENABLED_EXPLAIN'		=> 'Only enabled pages will be accessible to users. Disabling a page also disables any decendants of the page.',
	'PAGE_DISPLAY_EXPLAIN'		=> 'Displaying the page will list it in the site\'s navigation.',
	'VERSION_DESC_EXPLAIN'		=> 'A summary of the action viewable to adminstrators only, e.g. “fixed spelling”, “added image”, etc',
	'LINK_URL_EXPLAIN'			=> 'Full URL (including the protocol, i.e.: <samp>http://</samp>) to the destination location that the page will take the user, e.g.: <samp>http://www.phpbb.com/</samp>.',
	'VERSION_EXISTING_EXPLAIN'	=> 'The content of an existing page will be used.',
	
	'SAVE_DRAFT'	=> 'Save draft',
	'DRAFT'			=> 'Draft',
	
	'HTML_OPTIONS'		=> 'HTML options',
	'UPLOAD_OPTIONS'	=> 'Upload options',
	'MODULE_OPTIONS'	=> 'Module options',
	'LINK_OPTIONS'		=> 'Link options',
	'VERSION_OPTIONS'	=> 'Version options',
	
	'ADVANCED'		=> 'Advanced »',
	'READ'			=> 'read',
	
	'PAGE_STYLE'	=> 'Page style',
	'DEFAULT_STYLE'	=> 'Default style',
	
	'PAGE_CONTENTS_TABLE'			=> 'Table of contents',
	'PAGE_CONTENTS_TABLE_EXPLAIN'	=> 'Display an automatically generated table of contents for the page.',
	
	'UPLOAD'				=> 'Upload',
	'UPLOAD_TYPE'			=> 'Upload type',
	'UPLOAD_FILE'			=> 'Upload from your machine',
	'UPLOAD_URL'			=> 'Upload from a URL',
	'UPLOAD_URL_EXPLAIN'	=> 'Enter the URL of the location containing the file. The file will be copied to this site.',
	'DISABLED_INSERT'		=> 'The page, or one of its parents is disabled. Enable it to link to it.',
	
	'IMAGE_OPTIONS'			=> 'Image options',
	'IMAGE_SIZE'			=> 'Image size',
	'IMAGE_SIZE_SMALL'		=> 'Small (%d x %d)',
	'IMAGE_SIZE_MEDIUM'		=> 'Medium (%d x %d)',
	'IMAGE_SIZE_LARGE'		=> 'Large (%d x %d)',
	'IMAGE_SIZE_ORIGINAL'	=> 'Original (%d x %d)',
	
	'UPLOAD_FILE'	=> 'Upload file',
	'UPLOAD_IMAGE'	=> 'Upload image',
	
	'INSERT'		=> 'Insert',
	'INSERT_FILE'	=> 'Insert file',
	'INSERT_IMAGE'	=> 'Insert image',
	
	'CHOOSE_MODE'			=> 'Choose module mode',
	'CHOOSE_MODE_EXPLAIN'	=> 'Choose the modules mode being used.',
	'CHOOSE_MODULE'			=> 'Choose module',
	'CHOOSE_MODULE_EXPLAIN'	=> 'Choose the file being called by this module.',
	'MODULE_ADMIN'			=> 'Module administration',
	'SAVE_MODULE_ADMIN'		=> 'Save page and go to module administration',
	
	'HIDDEN_PAGE'		=> 'Hidden page',
	'PAGE_LOCKED'		=> 'This page was locked for editing on %1$s by %2$s.',
	
	'INFO'				=> 'Info',
	'REVERT'			=> 'Revert',
	'REVERT_LOCK'		=> '<strong>This page was locked for editing on %1$s by %2$s.</strong><br />Are you sure you want to revert this page?',
	'SELECT_PAGE'		=> 'Select a page',
	'CREATE_PAGE'		=> 'Create new page',
	'ADD_PAGE'			=> 'Add Page',
	'ADD_PAGE_EXPLAIN'	=> 'Create a new page',
	'PAGE_INFO'			=> 'Page Info',
	'EDIT_PAGE'			=> 'Edit Page',
	'EDIT_PAGE_EXPLAIN'	=> 'Here you are able to edit the page.',
	'LOCKED'			=> 'Locked',
	'EDIT_LOCK'			=> '<strong>This page was locked for editing on %1$s by %2$s.</strong><br />Are you sure you want to edit this page?',
	
	'PAGE_TITLE_EMPTY'					=> 'You must enter a title for this page.',
	'PAGE_TITLE_TOO_LONG'				=> 'The title is too long, it must be less than 40 characters.',
	'PAGE_SLUG_EMPTY'					=> 'You must enter an slug for this page.',
	'PAGE_SLUG_TOO_LONG'				=> 'The slug is too long, it must be less than 40 characters.',
	'PAGE_SLUG_UNAVAILABLE'				=> 'The slug chosen is already in use.',
	'PAGE_SLUG_PHPBB'					=> 'The slug chosen is the same as the path used by phpBB.',
	'PAGE_SLUG_FILE'					=> 'The slug chosen is the same as a file.',
	'PAGE_URL_TOO_LONG'					=> 'The URL for the page is too long, it must be less than 255 characters. Shorten the slug of the page or its parents.',
	'INVALID_CHARS_PAGE_SLUG'			=> 'The slug contains forbidden characters.',
	'VERSION_FILE_EMPTY'				=> 'You must specify a file or url to upload for this page.',
	'VERSION_DESC_TOO_LONG'				=> 'The description is too long, it must be less than 255 characters.',
	'LINK_URL_EMPTY'					=> 'You must specify a link for this page.',
	'LINK_URL_TOO_LONG'					=> 'The link is too long, it must be less than 255 characters.',
	'INVALID_PHP_VERSION_HTML'			=> 'The page uses embeded PHP. Embedded PHP is not enabled. Either disable the page, remove the PHP or enable it in the settings.',
	'INVALID_PHP_FORMAT_VERSION_HTML'	=> 'To embed PHP, you must use the format <code>&lt;?php ?&gt;</code>, not <code>&lt;!-- PHP --&gt; &lt;!-- ENDPHP --&gt;</code>, due to the way TinyMCE handles entities.',
	'LOCK_LOG_ACTION'					=> 'Since you started editing this page, the user “%1$s” performed the action: “%2$s” on %3$s.',
	
	'MALFORMED_URL'	=> 'The link, “%1$s”, is a malformed URL.',
	'BROKEN_URL'	=> 'The link, “%1$s”, is a broken URL.',
	
	'PAGE_ADDED'		=> 'Page successfully added.',
	'PAGE_EDITED'		=> 'Page successfully edited.',
	'PAGE_DELETED'		=> 'Page successfully removed.',
	
	'VERSION_SINCE_DELETED'	=> 'This page was since deleted on %1$s by %2$s.',
	
	'CANNOT_REMOVE_PAGE'		=> 'Unable to remove page, it has assigned children. Please remove or move all children before performing this action.',
	'DEACTIVATED_PAGE'			=> 'Deactivated page',
	'DELETE_PAGE'				=> 'Delete page',
	'DELETE_PAGE_CONFIRM'		=> 'Are you sure you want to remove this page?',
	'DELETE_PAGE_LOCK'			=> '<strong>This page was locked for editing on %1$s by %2$s.</strong><br />Are you sure you want to remove this page?',
	'DELETE_VERSION'			=> 'Delete version',
	'DELETE_VERSION_CONFIRM'	=> 'Are you sure you want to remove this version?',
	'DELETE_VERSION_LOCK'		=> '<strong>This page was locked for editing on %1$s by %2$s.</strong><br />Are you sure you want to remove this version?',
	'VERSION_DELETED'			=> 'Version successfully removed.',
	'VERSION_EDITOR'			=> 'Version Editor',
	'NAVIGATE_AWAY'				=> 'The changes you made will be lost if you navigate away from this page.',
	'DISABLE_PAGE_CONFIRM'		=> 'Disable page',
	
	'COMPARE_VERSIONS'		=> 'Compare versions',
	'DIFF_EXPLAIN'			=> 'Comparing versions will highlight the differences between the two versions of a page, showing the changes made.',
	'DIFF_SAME_VERSIONS'	=> 'You cannot compare a version with itself.',
	'DIFF_TYPE'				=> 'Diff type',
	'DIFF_TYPE_TEXT'		=> 'Text',
	'DIFF_TYPE_HTML'		=> 'HTML',
	'DIFF_NOT_SUPPORTED'	=> 'Chosen diff mode is not supported',
	
	'LINKS_EXPLAIN'		=> 'This shows any pages linking to the current page. You can filter the list by internal or external links (links from other websites), and sort by the time, page or number of refers.',
	
	'ALL'				=> 'All',
	'INTERNAL_LINKS'	=> 'Internal links',
	'EXTERNAL_LINKS'	=> 'External links',
	
	'LINK_BREAKAGE'	=> 'Potential link breakage',
	'LINKING_TO'	=> '(linking to %s)',
	'VIEW_LINKS'	=> 'View links »',
	
	'LINK_BREAKAGE_INTERNAL_EXTERNAL'	=> 'This page and any children are referenced by the pages below. If you delete or disable this page, you will break these links. To prevent this, delete the references in the following pages. It is also referenced by %d external pages from other websites.',
	'LINK_BREAKAGE_INTERNAL'			=> 'This page and any children are referenced by the pages below. If you delete or disable this page, you will break these links. To prevent this, delete the references in the following pages.',
	'LINK_BREAKAGE_EXTERNAL'			=> 'This page and any children are referenced by %d external pages from other websites. If you delete or disable this page, you will break these links.',
	
	'VERSION_BROKEN_LINK'	=> 'This version has 1 reference to deleted pages. Edit the version to remove the reference.',
	'VERSION_BROKEN_LINKS'	=> 'This version has %d references to deleted pages. Edit the version to remove the references.',
	
	'PINGBACK_ERROR_0'	=> 'Generic fault.',
	'PINGBACK_ERROR_16'	=> 'The source URI does not exist.',
	'PINGBACK_ERROR_17'	=> 'The source URI does not contain a link to the target URI, and so cannot be used as a source.',
	'PINGBACK_ERROR_32'	=> 'The specified target URI does not exist.',
	'PINGBACK_ERROR_33'	=> 'The specified target URI cannot be used as a target. It either doesn’t exist, or it is not a pingback-enabled resource.',
	'PINGBACK_ERROR_48'	=> 'The pingback has already been registered.',
	'PINGBACK_ERROR_49'	=> 'Access denied.',
	'PINGBACK_ERROR_50'	=> 'The server could not communicate with an upstream server, or received an error from an upstream server, and therefore could not complete the request.',
));

?>
