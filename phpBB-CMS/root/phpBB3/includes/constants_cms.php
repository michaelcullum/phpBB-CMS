<?php
/**
*
* @package phpBB3
* @copyright (c) 2012 Michael Cullum (Unknown Bliss of http://michaelcullum.com)
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
* @ignore
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

// Page states
define('PAGE_ENABLED', 1);
define('PAGE_DISABLED', 0);

// Page types
define('VERSION_TYPE_HTML', 0);
define('VERSION_TYPE_CATEGORY', 1);
define('VERSION_TYPE_FILE', 2);
define('VERSION_TYPE_LINK', 3);
define('VERSION_TYPE_MODULE', 4);

// Upload types
define('UPLOAD_TYPE_FILE', 0);
define('UPLOAD_TYPE_URL', 1);

// Image sizes
define('IMAGE_SIZE_ORIGINAL', 0);
define('IMAGE_SIZE_THUMBNAIL', 1);
define('IMAGE_SIZE_SMALL', 2);
define('IMAGE_SIZE_MEDIUM', 3);
define('IMAGE_SIZE_LARGE', 4);

// Link types
define('LINK_TYPE_URL', 0);
define('LINK_TYPE_PAGE', 1);
define('LINK_TYPE_PHPBB', 2);

// Notification options
define('EMAIL_NOTIFICATIONS_NONE', 0);
define('EMAIL_NOTIFICATIONS_ALL', 1);
define('EMAIL_NOTIFICATIONS_CONTRIBUTORS', 2);

// Pagebreaks
define('PAGEBREAK_SEPARATOR', '<!-- PAGEBREAK -->');

// Default character used instead of a space in aliases
// “+”, “-” and “_” make nice seperators, whatever tickles your fancy
define('SPACE_SEPARATOR', '-');

// Table names
define('PAGES_TABLE',				$table_prefix . 'pages');
define('PAGES_LINKS_TABLE',			$table_prefix . 'pages_links');
define('PAGES_LOG_TABLE',			$table_prefix . 'pages_log');
define('PAGES_URLS_TABLE',			$table_prefix . 'pages_urls');
define('PAGES_VERSIONS_TABLE',		$table_prefix . 'pages_versions');
?>
