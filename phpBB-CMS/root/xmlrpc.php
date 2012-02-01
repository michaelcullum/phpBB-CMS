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
define('IN_PHPBB', true);
define('IN_CMS', true);
$cms_root_path = (defined('CMS_ROOT_PATH')) ? CMS_ROOT_PATH : './';
$phpEx = substr(strrchr(__FILE__, '.'), 1);

if (!file_exists($cms_root_path . 'config.' . $phpEx))
{
	die("<p>The config.$phpEx file could not be found.</p>");
}

require($cms_root_path . 'config.' . $phpEx);

if (!file_exists($phpbb_root_path . 'common.' . $phpEx))
{
	die("<p>Could not find the phpBB directory. Check the path is set correctly in config.$phpEx</p>");
}

require($phpbb_root_path . 'common.' . $phpEx);
require($phpbb_root_path . 'includes/ixr.' . $phpEx);

/**
* XMLRPC server implementation
* @package phpBB3
*/
class xmlrpc_server extends IXR_Server
{
	/**
	* Define the methods XMLRPC the server accepts
	*
	* @return xmlrpc_server
	*/
	function xmlrpc_server()
	{
		$this->methods = array(
			// PingBack
			'pingback.ping' => 'this:pingback_ping',
			'pingback.extensions.getPingbacks' => 'this:pingback_extensions_getPingbacks',
		);
	}

	function serve_request()
	{
		$this->IXR_Server($this->methods);
	}

	/**
	* Retrieves a pingback and registers it.
	*
	* @param array $args Method parameters.
	* @return array
	*/
	function pingback_ping($args)
	{
		global $db, $config;

		$from_url = $args[0];
		$to_url   = $args[1];

		// Check if the page linked to is in our site
		if(!strpos($to_url, str_replace(array('http://www.','http://','https://www.','https://'), '', generate_cms_url())))
		{
			return new IXR_Error(17, 'The source URI does not contain a link to the target URI, and so cannot be used as a source.');
		}
		
		$page_url = url_to_page_url($to_url);
		
		$sql = 'SELECT page_id, page_title, page_url, version_id
			FROM ' . PAGES_TABLE . '
			WHERE ' . ( ($page_url) ? "page_url = '" . $db->sql_escape($page_url) . "'" : 'page_id = ' . $config['home_page'] ) . '
				AND page_enabled = 1
				AND parent_enabled = 1';
		$result = $db->sql_query($sql);
		
		if (!($page = $db->sql_fetchrow($result)))
		{
			// Page does not exist
	  		return new IXR_Error(33, 'The specified target URL cannot be used as a target. It either doesn&#8217;t exist, or it is not a pingback-enabled resource.');
		}
		
		// Allow the linking server time to publish, just incase its not clever enough to send pingbacks *after* it publishes
		sleep(1);
		
		$data = array(
			'page_id'		=> $page['page_id'],
			'page_title'	=> $page['page_title'],
			'page_url'		=> generate_cms_url(true, true) . generate_url($page['page_id'], $page['page_url'], false),
			'version_id'	=> $page['version_id'],
			'url'			=> $from_url,
		);
		
		$result = add_linkback($data);
		
		if($result === true)
		{
			return 'Pingback registered.';
		}
		
		switch($result)
		{
			case 16:
				 return new IXR_Error(16, 'The source URI does not exist.');
			break;
			
			case 17:
				return new IXR_Error(17, 'The source URI does not contain a link to the target URI, and so cannot be used as a source.');
			break;
			
			case 48:
				return new IXR_Error(48, 'The pingback has already been registered.');
			break;
	  	}
	}

	/**
	* Retrieve array of URLs that linking to a given URL
	*
	* Specs on http://www.aquarionics.com/misc/archives/blogite/0198.html
	*
	* @param array $args Method parameters.
	* @return array
	*/
	function pingback_extensions_getPingbacks($args)
	{
		global $db, $config;
		$url = $args;
		
		$page_url = url_to_page_url($url);
		
		$sql = 'SELECT page_id
			FROM ' . PAGES_TABLE . '
			WHERE ' . ( ($page_url) ? "page_url = '" . $db->sql_escape($page_url) . "'" : 'page_id = ' . $config['home_page'] ) . '
				AND page_enabled = 1
				AND parent_enabled = 1';
		$result = $db->sql_query($sql);

		if (!($page_id = $db->sql_fetchfield('page_id')))
		{
	  		return new IXR_Error(32, 'The specified target URL does not exist.');
		}
		
		$sql = 'SELECT link_url
			FROM ' . PAGES_LINKS_TABLE . '
			WHERE link_page_id = ' . $page_id . '
				AND link_processed = 1';
		$result = $db->sql_query($sql);
		
		$linkbacks = array();
		while ($row = $db->sql_fetchrow($result))
		{
			$linkbacks[] = $row['link_url'];
		}

		return $linkbacks;
	}
}

$xmlrpc_server = new xmlrpc_server();
$xmlrpc_server->serve_request();

/**
* Extract a page URL from a complete URL
*/
function url_to_page_url($url)
{
	global $config;
	
	$url_parts = parse_url($url);
	
	// Is the page_url in the query?
	$page_url = (isset($url_parts['query'])) ? array_search('', parse_str($url_parts['query'])) : false;
	
	if($page_url !== false)
	{
		return $page_url;
	}
	
	// The page_url was not passed in the URL, parse from the URL path
	// Strip out the CMS path
	$page_url = (isset($url_parts['path'])) ? substr($url_parts['path'], strlen($config['cms_path']) + 1) : '';
	
	// Strip a trailing slash
	$page_url = (substr($page_url, -1) == '/') ? substr($page_url, 0, -1) : $page_url;
	
	return $page_url;
}
?>
