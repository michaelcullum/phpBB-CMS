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

/**
* Page class
* @package phpBB3
*/
class page
{
	var $data			= array();
	var $old_data		= array();
	var $links			= array();
	var $link_urls		= array();
	var $file_data		= array();
	var $errors			= array();
	var $parent			= array();
	var $upload_file	= false;
	var $form_name		= 'upload_file';
	
	/**
	* Constructor
	* Init defaults
	*/
	function page()
	{
		$this->data = array(
			'page_id'					=> 0,
			'page_enabled'				=> 1,
			'page_display'				=> 1,
			'page_style'				=> 0,
			'page_contents_table'		=> 0,
			'parent_id'					=> 0,
			'page_title'				=> '',
			'page_slug'					=> '',
			
			'version_type'				=> VERSION_TYPE_HTML,
			'version_draft'				=> 0,
			'version_html'				=> '',
			'version_physical_filename'	=> '',
			'version_real_filename'		=> '',
			'version_extension'			=> '',
			'version_mimetype'			=> '',
			'version_image'				=> 0,
			'version_filesize'			=> 0,
			'version_module_basename'	=> '',
			'version_module_mode'		=> '',
			'version_module_ref'		=> 0,
			'version_link_type'			=> 0,
			'version_link_url'			=> '',
			'version_link_id'			=> 0,
			'version_checksum'			=> '',
			'version_desc'				=> '',
			
			'upload_url'				=> '',
		);
	}
	
	/**
	* Lock a page for editing to the user
	* @access public
	*/
	function lock($lock = true)
	{
		global $db, $user;
		
		$sql = 'UPDATE ' . PAGES_TABLE . '
			SET page_lock_id = ' . ( ($lock) ? $user->data['user_id'] : 0 ) . ', page_lock_time = ' . ( ($lock) ? time() : 0) . '
			WHERE page_id = ' . $this->data['page_id'];
		$db->sql_query($sql);
	}
	
	/**
	* Validates and normalizes data for saving
	* @access public
	*/
	function validate()
	{
		global $db, $user, $config, $cms_root_path;
		
		$action = ($this->data['page_id']) ? 'edit' : 'add';
		
		// Wipe the values of columns that does not apply for the version type
		switch ($this->data['version_type'])
		{
			case VERSION_TYPE_MODULE:
				// Check whether we should wipe the value of version_html or not
				$info = get_module_infos($this->data['version_module_basename']);
				$info = $info[$this->data['version_module_basename']];
				if (!isset($info['modes'][$this->data['version_module_mode']]['html']) || !$info['modes'][$this->data['version_module_mode']]['html'])
				{
					$this->data['version_html'] = '';
				}
			break;
		
			case VERSION_TYPE_LINK:
				if ($this->data['version_type'] == VERSION_TYPE_LINK && $this->data['version_link_type'] != LINK_TYPE_URL)
				{
					$this->data['version_link_url'] = '';
				}
			
				// No break
			case VERSION_TYPE_CATEGORY;
			case VERSION_TYPE_FILE;
				// No break
				$this->data['version_html'] = $this->data['version_module_basename'] = $this->data['version_module_mode'] = '';
			break;
		
			default:
				$this->data['version_module_basename'] = $this->data['version_module_mode'] = '';
			break;
		}
		
		// The user can only enter a version description if version control is enabled and they are editing
		$this->data['version_desc'] = ($config['version_control'] && $action == 'edit') ? $this->data['version_desc'] : '';
		
		// A version can only be a draft if we have version control and are editing an existing page
		// Users wishing to add a page without publishing it can created a disabled page
		$this->data['version_draft'] = ($config['version_control'] && $action == 'edit' && $this->data['version_draft']) ? 1 : 0;
		
		$this->data['version_number'] = ($config['version_control'] && isset($this->old_data['cur_version_number'])) ? ($this->old_data['cur_version_number'] + 1) : 1;
		
		$this->_replace_headings();
		
		if (!$this->data['page_title'])
		{
			$this->errors[] = $user->lang['PAGE_TITLE_EMPTY'];
		}
		elseif (utf8_strlen($this->data['page_title']) > 40)
		{
			$this->errors[] = $user->lang['PAGE_TITLE_TOO_LONG'];
		}
	
		if (strlen($this->data['page_slug']) > 40)
		{
			$this->errors[] = $user->lang['PAGE_SLUG_TOO_LONG'];
		}
	
		if (!$this->data['page_slug'])
		{
			$this->errors[] = $user->lang['PAGE_SLUG_EMPTY'];
		}
		elseif (!preg_match('#^[-\]_+.[a-zA-Z0-9]+$#u', $this->data['page_slug']))
		{
			// Technically, URLS can contain encoded spaces, however, this tends to look ugly so we do not allow it
			$this->errors[] = $user->lang['INVALID_CHARS_PAGE_SLUG'];
		}
	
		// Don't allow embedded PHP when the page is enabled and we're not processing embedded PHP (when we're not parsing pages as templates or not allowing PHP)
		if(preg_match('#<\?php(?:\r\n?|[ \n\t])(.*)?\?>#s', $this->data['version_html']) && $this->data['page_enabled'] && (!$config['cms_parse_template'] || !$config['tpl_allow_php']))
		{
			$this->errors[] = $user->lang['INVALID_PHP_VERSION_HTML'];
		}
	
		if(preg_match('#<!-- PHP -->(.*?)<!-- ENDPHP -->#s', $this->data['version_html']))
		{
			$this->errors[] = $user->lang['INVALID_PHP_FORMAT_VERSION_HTML'];
		}
	
		if (utf8_strlen($this->data['version_desc']) > 255)
		{
			$this->errors[] = $user->lang['VERSION_DESC_TOO_LONG'];
		}
	
		$this->upload_file = (isset($_FILES['upload_file']) && $_FILES['upload_file']['name'] != 'none' && trim($_FILES['upload_file']['name'])) ? true : false;
		$this->data['upload_url'] = (isset($this->data['upload_url'])) ? $this->data['upload_url'] : '';
	
		if ($this->data['version_type'] == VERSION_TYPE_FILE && ($this->upload_file || $this->data['upload_url']) )
		{
			$user->add_lang('posting');
		
			$this->_upload_file();
			$this->errors = array_merge($this->errors, $this->file_data['error']);
		
			if(!sizeof($this->errors))
			{
				$this->data = array_merge($this->data, array(
					'version_physical_filename'	=> $this->file_data['physical_filename'],
					'version_real_filename'		=> $this->file_data['real_filename'],
					'version_extension'			=> $this->file_data['extension'],
					'version_mimetype'			=> $this->file_data['mimetype'],
					'version_image'				=> ($this->file_data['file_image']) ? 1 : 0,
					'version_filesize'			=> $this->file_data['filesize'],
				));
			}
		}
		elseif ($this->data['version_type'] == VERSION_TYPE_FILE && !$this->upload_file && !$this->data['upload_url'])
		{
			if (!$this->data['page_id'] || $this->old_data['version_type'] != VERSION_TYPE_FILE)
			{
				// The version type is a file, no file/url has been specified and there is no previous file for the version
				$this->errors[] = $user->lang['VERSION_FILE_EMPTY'];
			}
			else
			{
				// We take the file info from the previous version
				$this->data = array_merge($this->data, array(
					'version_physical_filename'	=> $this->old_data['version_physical_filename'],
					'version_real_filename'		=> $this->old_data['version_real_filename'],
					'version_extension'			=> $this->old_data['version_extension'],
					'version_mimetype'			=> $this->old_data['version_mimetype'],
					'version_image'				=> $this->old_data['version_image'],
					'version_filesize'			=> $this->old_data['version_filesize'],
				));
			}
		}
		elseif ($this->data['version_type'] == VERSION_TYPE_LINK && $this->data['version_link_type'] == LINK_TYPE_URL)
		{
			if (strlen($this->data['version_link_url']) > 255)
			{
				$this->errors[] = $user->lang['LINK_URL_TOO_LONG'];
			}
			elseif (!$this->data['version_link_url'])
			{
				$this->errors[] = $user->lang['LINK_URL_EMPTY'];
			}
		}
	
		// Get the parent info
		if($this->data['parent_id'])
		{
			$sql = 'SELECT *
				FROM ' . PAGES_TABLE . '
				WHERE page_id = ' . $this->data['parent_id'];
			$result = $db->sql_query($sql);
	
			if(!($this->parent = $db->sql_fetchrow($result)))
			{
				$this->errors[] = $user->lang['PARENT_NO_EXIST'];
			}
		}
	
		// Check if there is a page using the same slug with the same parent
		$sql = 'SELECT 1
			FROM ' . PAGES_TABLE . ' p
			WHERE parent_id = ' . $this->data['parent_id'] . "
				AND page_slug = '" . $db->sql_escape($this->data['page_slug']) .  "'
				" . ( ($this->data['page_id']) ? ' AND p.page_id != ' . $this->data['page_id'] : '');
		$result = $db->sql_query($sql);
	
		if ($slug = $db->sql_fetchrow($result))
		{
			$this->errors[] = $user->lang['PAGE_SLUG_UNAVAILABLE'];
		}
		else
		{
			// Calculate the URL
			$this->data['page_url'] = ( ($this->data['parent_id']) ? $this->parent['page_url'] . '/' : '' ) . $this->data['page_slug'];
		
			// Check the slug the user is trying to use doens't work out the same as the path to phpBB
			$board_path = substr($config['script_path'], strlen($config['cms_path']) + 1);
		
			// We let the user use the slug if the page is a phpBB link anyway
			if ($board_path == $this->data['page_url'] . '/' && $this->data['version_type'] == VERSION_TYPE_LINK && $this->data['version_type_link'] != LINK_TYPE_PHPBB)
			{
				$this->errors[] = $user->lang['PAGE_SLUG_PHPBB'];
			}
			elseif(file_exists($cms_root_path . $this->data['page_url']))
			{
				$this->errors[] = $user->lang['PAGE_SLUG_FILE'];
			}
		}
		$db->sql_freeresult($result);
	
		if(isset($this->data['page_url']) && strlen($this->data['page_url']) > 255)
		{
			$this->errors[] = $user->lang['PAGE_URL_TOO_LONG'];
		}
	
		switch ($this->data['version_type'])
		{
			case VERSION_TYPE_HTML;
			case VERSION_TYPE_MODULE;
				$this->_parse_links();
				// No break
			case VERSION_TYPE_LINK;
				if ($this->data['version_type'] == VERSION_TYPE_LINK && $this->data['version_link_type'] != LINK_TYPE_PHPBB)
				{
					$this->data['version_link_url'] = ($this->data['version_link_type'] == LINK_TYPE_PAGE) ? get_page_url($this->data['version_link_id']) : $this->data['version_link_url'];
				
					$this->links = array(array(
						'link_url'		=> ($this->data['version_link_type'] == LINK_TYPE_URL) ? $this->data['version_link_url'] : '',
						'link_page_id'	=> ($this->data['version_link_type'] == LINK_TYPE_PAGE) ? $this->data['version_link_id'] : 0,
					));
				}
			
				if(isset($this->links))
				{
					$this->errors = array_merge($this->errors, $this->_process_links());
				}
			break;
		}
		
		return (sizeof($this->errors)) ? false : true;
	}
	
	/**
	* Extract all the hyper and image links in HTML
	* @access private
	*/
	function _parse_links()
	{
		// Parse text links
		$matches = preg_match_all("/<a[\s]+[^>]*?href[\s]?=[\s\"\']+(.*?)[\"\']+.*?>([^<]+|.*?)?<\/a>/i", $this->data['version_html'], $result);
		
		for($i = 0; $i < $matches; $i++)
		{
			$this->links[] = array(
				'link_url'		=> $result[1][$i],
				//'link_text'	=> $result[2][$i],
			);
		}
	
		// Match image links
		$matches = preg_match_all("/<img[\s]+[^>]*?src[\s]?=[\s\"\']+(.*?)[\"\']+.*?>/i", $this->data['version_html'], $result);
	
		for($i = 0; $i < $matches; $i++)
		{
			$this->links[] = array(
				'link_url'	=> $result[1][$i],
			);
		}
	}
	
	/**
	* Determine if links are internal or external, if they link to a page
	* Generates an array of errors for invalid links
	* @access private
	*/
	function _process_links()
	{
		global $cms_root_path, $user, $db, $config;
	
		$errors = $urls = array();
		foreach($this->links as $i => &$link)
		{
			if(isset($link['link_page_id']) && $link['link_page_id'])
			{
				continue;
			}
		
			$link['link_page_id'] = 0;
			$link['page'] = false;
			
			$url_parts = parse_url($link['link_url']);
			
			if ($url_parts === false)
			{
				// Malformed URL
				$errors[] = sprintf($user->lang['MALFORMED_URL'], $url);
			}
			elseif (empty($url_parts['scheme']) && empty($url_parts['host']) && isset($url_parts['path']))
			{
				// This is an internal URL to resolve
				$path = substr($url_parts['path'], ( strlen($config['cms_path']) + 1 ) );
				
				// We've already logged this page, remove it
				if(in_array($path, $urls))
				{
					unset($this->links[$i]);
					break;
				}
				
				$link['link_url'] = $urls[] = $path;
				$link['page'] = (file_exists($cms_root_path . $path) || $path == '') ? false : true;
			}
			else
			{
				// This is an external URL
				$errstr = $pingback_server = '';
				$errno = 0;

				// Get the file
				// We limit the size to 100 KB incase the user linked to a large file, so we don't have to download it all (;
				$link['responce'] = remote_request($link['link_url'], false, $errstr, $errno, 3, 102400);
			
				if($link['responce'] === false)
				{
					$errors[] = sprintf($user->lang['BROKEN_URL'], $link['link_url']);
				}
			}
		}
	
		if(sizeof($urls))
		{
			$sql = 'SELECT page_id, page_url
				FROM ' . PAGES_TABLE . '
				WHERE ' . $db->sql_in_set('page_url', $urls);
			$result = $db->sql_query($sql);
			
			while ($row = $db->sql_fetchrow($result))
			{
				$this->linked_urls[$row['page_url']] = $row['page_id'];
			}
		
			foreach($this->links as &$link)
			{
				$link['link_page_id'] = 0;
				if($link['page'])
				{
					if(isset($this->linked_urls[$link['link_url']]))
					{
						$link['link_page_id'] = $this->linked_urls[$link['link_url']];
					}
					else
					{
						$errors[] = sprintf($user->lang['BROKEN_URL'], $link['link_url']);
					}
					$link['link_url'] = '';
				}
			}
		}
		
		return $errors;
	}
	
	/**
	* Reformat links for storage
	* @access private
	*/
	function _format_links_for_storage()
	{
		$match = array(
			"/(<a[\s]+[^>]*?href[\s]?=[\s\"\']+)(.*?)([\"\']+.*?>([^<]+|.*?)?<\/a>)/i",
			"/(<img[\s]+[^>]*?src[\s]?=[\s\"\']+)(.*?)([\"\']+.*?>)/i",
		);
		
		$this->data['version_html'] = preg_replace_callback($match, array(&$this, '_format_links_for_storage_callback'), $this->data['version_html']);
	}
	
	/**
	* Callback method for $this->_format_links_for_storage()
	* @access private
	*/
	function _format_links_for_storage_callback($link)
	{
		global $config;
		
		$url_parts = parse_url($link[2]);
		
		if (empty($url_parts['scheme']) && empty($url_parts['host']) && isset($url_parts['path']))
		{
			$page_url = substr($url_parts['path'], ( strlen($config['cms_path']) + 1 ) );
			
			$url = 'cms://page:' . $this->linked_urls[$page_url] . '/' . $page_url;
			$url .= (isset($url_parts['query'])) ? '?' . $url_parts['query'] : '';
			$url .= (isset($url_parts['fragment'])) ? '#' . $url_parts['fragment'] : '';
			
			return $link[1] . $url . $link[3];
		}
		
		return $link[0];
	}
	
	/**
	* Assign an id attribute to any headings without an ID attribute
	*/
	function _replace_headings()
	{
		$this->data['version_html'] = preg_replace_callback('/<h([1-6])>([^<]+)<\/h[1-6]>/i', array(&$this, '_replace_headings_callback'), $this->data['version_html']);
	}
	
	/**
	* Callback function for $this->_replace_headings()
	* @access private
	*/
	function _replace_headings_callback($match)
	{
		// We use make_slug here - it works well at generating IDs
		return '<h' . $match[1] . ' id="' . make_slug(strip_tags($match[2])) . '">' . $match[2] . '</h' . $match[1] . '>';
	}
	
	/**
	* Updates the links in to an array of pages from their old URL, to their new URL
	* @access private
	*
	* @param array $page_ids Array of page_ids with changed URLS
	* @param array $old_urls Array of the page's old URL to update from
	* @param array $new_urls Array of the page's new URL to update to
	*/
	function _update_links($page_ids, $old_urls, $new_urls)
	{
		global $db, $config;
	
		$match = $replace = $urls = array();
		$num_pages = sizeof($page_ids);
		for($i = 0; $i < $num_pages; $i++)
		{
			$urls[$page_ids[$i]] = $new_urls[$i];
			
			// We don't actually bother making match/replace regexps to specifically match images/hrefs
			$match[] = '/cms:\/\/page:' .  $page_ids[$i] . '\/' . preg_quote($old_urls[$i], '/') . '/';
			$replace[] = 'cms://page:' . $page_ids[$i] . '/' . preg_quote($new_urls[$i]);
		}
	
		$sql = 'SELECT v.version_id, version_type, version_html, link_page_id
			FROM ' . PAGES_VERSIONS_TABLE . ' v
			JOIN ' . PAGES_LINKS_TABLE . ' l
				ON v.version_id = l.version_id
			WHERE ' . $db->sql_in_set('link_page_id', $page_ids);
		$result = $db->sql_query($sql);
	
		while ($row = $db->sql_fetchrow($result))
		{
			if($row['version_type'] == VERSION_TYPE_HTML)
			{
				$row['version_html'] = preg_replace($match, $replace, $row['version_html']);
				$row['version_link_url'] = '';
			}
			elseif($row['version_type'] == VERSION_TYPE_LINK)
			{
				$row['link_type'] = LINK_TYPE_PAGE;
				$row['version_link_url'] = $urls[$row['link_page_id']];
			}
			
			// We have to recalculate the checksum
			$page = new page;
			$page->data = $row;
			$page->_generate_version_checksum();
			
			$sql_ary = array(
				'version_html'		=> $row['version_html'],
				'version_link_url'	=> $row['version_link_url'],
				'version_checksum'	=> $page->data['version_checksum'],
			);
		
			$sql = 'UPDATE ' . PAGES_VERSIONS_TABLE . '
				SET ' . $db->sql_build_array('UPDATE', $sql_ary) . '
				WHERE version_id = ' . $row['version_id'];
			$db->sql_query($sql);
		}
	}
	
	/**
	* Generate a checksum for a page, based on its content
	* Stored in the version_checksum column in PAGES_VERSIONS_TABLE
	* @access private
	*/
	function _generate_version_checksum()
	{
		global $phpbb_root_path, $config;
	
		// Generate a hash for the content depending on what type it is
		switch ($this->data['version_type'])
		{
			case VERSION_TYPE_HTML:
				$this->data['version_checksum'] = md5($this->data['version_html']);
			break;
		
			case VERSION_TYPE_FILE:
				// Only calculate if a file has been uploaded, if not keep the old one... duh (;
				$this->data['version_checksum'] = ($this->upload_file || $this->data['upload_url']) ? md5($this->data['version_type'] . md5_file($phpbb_root_path . $config['cms_upload_path'] . '/' . $this->file_data['physical_filename'])) : $this->old_data['version_checksum'];
			break;
		
			case VERSION_TYPE_MODULE:
				// We need a hash that combines both the module_basename, mode and HTML
				$this->data['version_checksum'] = md5(md5($this->data['version_module_basename']) . md5($this->data['version_module_mode']) . md5($this->data['version_html']));
			break;
		
			case VERSION_TYPE_LINK:
				switch($this->data['version_link_type'])
				{
					case LINK_TYPE_URL:
						$this->data['version_checksum'] = md5($this->data['version_link_url']);
					break;
				
					case LINK_TYPE_PAGE:
						$this->data['version_checksum'] = md5($this->data['version_link_id']);
					break;
				
					case LINK_TYPE_PHPBB:
						$this->data['version_checksum'] = '';
					break;
				}
			break;
		
			default:
				$this->data['version_checksum'] = '';
			break;
		}
		
		// Make the content hash unique to the version type
		if($this->data['version_type'] != VERSION_TYPE_FILE)
		{
			$this->data['version_checksum'] = md5($this->data['version_type'] . $this->data['version_checksum']);
		}
	}
	
	/**
	* Upload File - file_data is generated here
	* Uses upload class
	*/
	function _upload_file()
	{
		global $auth, $user, $config, $db, $cache;
		global $cms_root_path, $phpbb_root_path, $phpEx;

		$this->file_data = array(
			'error'	=> array()
		);

		include_once($phpbb_root_path . 'includes/functions_upload.' . $phpEx);
		$upload = new fileupload();

		if ($config['check_attachment_content'])
		{
			$upload->set_disallowed_content(explode('|', $config['mime_triggers']));
		}
		
		$remote = (isset($this->data['upload_url']) && $this->data['upload_url']) ? true : false;

		if (!$remote)
		{
			$this->file_data['post_attach'] = ($upload->is_valid($this->form_name)) ? true : false;
		}
		else
		{
			$this->file_data['post_attach'] = true;
		}

		if (!$this->file_data['post_attach'])
		{
			$this->file_data['error'][] = $user->lang['NO_UPLOAD_FORM_FOUND'];
			return;
		}

		$extensions = obtain_upload_extensions();
	
		$upload->set_allowed_extensions(array_keys($extensions));

		$file = ($remote) ? $upload->remote_upload($this->data['upload_url']) : $upload->form_upload($this->form_name);

		if ($file->init_error)
		{
			$this->file_data['post_attach'] = false;
			return;
		}

		$cat_id = (isset($extensions[$file->get('extension')]['display_cat'])) ? $extensions[$file->get('extension')]['display_cat'] : ATTACHMENT_CATEGORY_NONE;

		// Make sure the image category only holds valid images...
		if ( $cat_id == ATTACHMENT_CATEGORY_IMAGE && !$file->is_image())
		{
			$file->remove();

			// If this error occurs a user tried to exploit an IE Bug by renaming extensions
			// Since the image category is displaying content inline we need to catch this.
			// We also don't let the user upload non images when TinyMCE is expecting an image
			$this->file_data['error'][] = $user->lang['ATTACHED_IMAGE_NOT_IMAGE'];
		}

		$file->clean_filename('unique', $user->data['user_id'] . '_');

		// Are we uploading an image *and* this image being within the image category? Only then perform additional image checks.
		$no_image = ($cat_id == ATTACHMENT_CATEGORY_IMAGE) ? false : true;
	
		$file->move_file($config['cms_upload_path'], false, $no_image);

		if (sizeof($file->error))
		{
			$file->remove();
			$this->file_data['error'] = array_merge($this->file_data['error'], $file->error);
			$this->file_data['post_attach'] = false;

			return;
		}

		$this->file_data['filesize'] = $file->get('filesize');
		$this->file_data['mimetype'] = $file->get('mimetype');
		$this->file_data['extension'] = $file->get('extension');
		$this->file_data['physical_filename'] = $file->get('realname');
		$this->file_data['real_filename'] = $file->get('uploadname');
		$this->file_data['filetime'] = time();
		$this->file_data['file_image'] = $file->is_image();

		// Check our complete quota
		if ($config['attachment_quota'])
		{
			if ($config['upload_dir_size'] + $file->get('filesize') > $config['attachment_quota'])
			{
				$this->file_data['error'][] = $user->lang['ATTACH_QUOTA_REACHED'];
				$this->file_data['post_attach'] = false;

				$file->remove();

				return;
			}
		}

		// Check free disk space
		if ($free_space = @disk_free_space($phpbb_root_path . $config['cms_upload_path']))
		{
			if ($free_space <= $file->get('filesize'))
			{
				$this->file_data['error'][] = $user->lang['ATTACH_QUOTA_REACHED'];
				$this->file_data['post_attach'] = false;

				$file->remove();

				return;
			}
		}
	
		$source = $file->get('destination_file');
		
		// Create thumbnail
		if ($this->file_data['file_image'])
		{
			$destination = $file->get('destination_path') . '/thumb_' . $file->get('realname');

			if (!$this->_create_resize($source, $destination, $file->get('mimetype'), IMAGE_SIZE_THUMBNAIL))
			{
				$this->file_data['thumbnail'] = 0;
			}
		
			// Create small
			$destination = $file->get('destination_path') . '/small_' . $file->get('realname');

			if (!$this->_create_resize($source, $destination, $file->get('mimetype'), IMAGE_SIZE_SMALL))
			{
				$this->file_data['small'] = 0;
			}
		
			// Create medium
			$destination = $file->get('destination_path') . '/medium_' . $file->get('realname');

			if (!$this->_create_resize($source, $destination, $file->get('mimetype'), IMAGE_SIZE_MEDIUM))
			{
				$this->file_data['medium'] = 0;
			}
		
			// Create large
			$destination = $file->get('destination_path') . '/large_' . $file->get('realname');

			if (!$this->_create_resize($source, $destination, $file->get('mimetype'), IMAGE_SIZE_LARGE))
			{
				$this->file_data['large'] = 0;
			}
		}
	}
	
	/**
	* Create Thumbnail
	*/
	function _create_resize($source, $destination, $mimetype, $max_width)
	{
		global $config, $phpbb_root_path, $phpEx;

		$min_filesize = (int) $config['img_min_thumb_filesize'];
		$img_filesize = (file_exists($source)) ? @filesize($source) : false;

		if (!$img_filesize || $img_filesize <= $min_filesize)
		{
			return false;
		}

		$dimension = @getimagesize($source);

		if ($dimension === false)
		{
			return false;
		}

		list($width, $height, $type, ) = $dimension;

		if (empty($width) || empty($height))
		{
			return false;
		}

		list($new_width, $new_height) = get_cms_img_size_format($width, $height, $max_width);

		// Do not create a thumbnail if the resulting width/height is bigger than the original one
		if ($new_width >= $width && $new_height >= $height)
		{
			return false;
		}

		$used_imagick = false;

		// Only use imagemagick if defined and the passthru function not disabled
		if ($config['img_imagick'] && function_exists('passthru'))
		{
			if (substr($config['img_imagick'], -1) !== '/')
			{
				$config['img_imagick'] .= '/';
			}

			@passthru(escapeshellcmd($config['img_imagick']) . 'convert' . ((defined('PHP_OS') && preg_match('#^win#i', PHP_OS)) ? '.exe' : '') . ' -quality 85 -geometry ' . $new_width . 'x' . $new_height . ' "' . str_replace('\\', '/', $source) . '" "' . str_replace('\\', '/', $destination) . '"');

			if (file_exists($destination))
			{
				$used_imagick = true;
			}
		}

		if (!$used_imagick)
		{
			include_once($phpbb_root_path . 'includes/functions_posting.' . $phpEx);
		
			$type = get_supported_image_types($type);
		
			if ($type['gd'])
			{
				// If the type is not supported, we are not able to create a thumbnail
				if ($type['format'] === false)
				{
					return false;
				}

				switch ($type['format'])
				{
					case IMG_GIF:
						$image = @imagecreatefromgif($source);
					break;

					case IMG_JPG:
						$image = @imagecreatefromjpeg($source);
					break;

					case IMG_PNG:
						$image = @imagecreatefrompng($source);
					break;

					case IMG_WBMP:
						$image = @imagecreatefromwbmp($source);
					break;
				}

				if ($type['version'] == 1)
				{
					$new_image = imagecreate($new_width, $new_height);

					if ($new_image === false)
					{
						return false;
					}
				
					imagecopyresized($new_image, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
				}
				else
				{
					$new_image = imagecreatetruecolor($new_width, $new_height);

					if ($new_image === false)
					{
						return false;
					}

					// Preserve alpha transparency (png for example)
					@imagealphablending($new_image, false);
					@imagesavealpha($new_image, true);

					imagecopyresampled($new_image, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
				}

				// If we are in safe mode create the destination file prior to using the gd functions to circumvent a PHP bug
				if (@ini_get('safe_mode') || @strtolower(ini_get('safe_mode')) == 'on')
				{
					@touch($destination);
				}

				switch ($type['format'])
				{
					case IMG_GIF:
						imagegif($new_image, $destination);
					break;

					case IMG_JPG:
						imagejpeg($new_image, $destination, 90);
					break;

					case IMG_PNG:
						imagepng($new_image, $destination);
					break;

					case IMG_WBMP:
						imagewbmp($new_image, $destination);
					break;
				}

				imagedestroy($new_image);
			}
			else
			{
				return false;
			}
		}

		if (!file_exists($destination))
		{
			return false;
		}

		return true;
	}
	
	/**
	* Update/Add page
	*
	* @access public
	* @param bool $run_inline If set to true, no logs will be written
	*/
	function save($run_inline = false)
	{
		global $db, $user, $config, $phpbb_hook;
		
		// You can hook into this if you want =]
		if ($phpbb_hook->call_hook(array(__CLASS__, __FUNCTION__), $run_inline))
		{
			if ($phpbb_hook->hook_return(array(__CLASS__, __FUNCTION__)))
			{
				return $phpbb_hook->hook_return_result(array(__CLASS__, __FUNCTION__));
			}
		}
		
		// Just a check
		if(sizeof($this->errors))
		{
			return;
		}
		
		$action = ($this->data['page_id']) ? 'edit' : 'add';
		$new_version = true;
		$revert = false;
		
		$this->_format_links_for_storage();
		$this->_generate_version_checksum();
		
		if ($this->data['page_id']
			&& $this->old_data['version_checksum'] == $this->data['version_checksum']
			&& !$this->upload_file
			&& (!isset($this->data['upload_url']) || !$this->data['upload_url']))
		{
			// We are not creating a new page and no changes have been made to the version
			$new_version = false;
		}
	
		if ($this->data['page_id']
			&& $new_version == false
			&& $this->old_data['parent_id'] == $this->data['parent_id']
			&& $this->old_data['page_enabled'] == $this->data['page_enabled']
			&& $this->old_data['page_display'] == $this->data['page_display']
			&& $this->old_data['page_title'] == $this->data['page_title']
			&& $this->old_data['page_slug'] == $this->data['page_slug']
			&& $this->old_data['page_style'] == $this->data['page_style']
			&& $this->old_data['page_contents_table'] == $this->data['page_contents_table'])
		{
			// Looks like we're not creating a new page either...
			return false;
		}
		
		// Start the transaction here
		$db->sql_transaction('begin');
		
		if ($this->data['page_id'] && $this->old_data['page_versions'] > 1)
		{
			// See if a version already exists with the same checksum
			$sql = 'SELECT version_id, version_number, version_draft
				FROM ' . PAGES_VERSIONS_TABLE . '
				WHERE page_id = ' . $this->data['page_id'] . "
					AND version_checksum = '" . $db->sql_escape($this->data['version_checksum']) . "'";
			$result = $db->sql_query($sql);
			$revert = $db->sql_fetchrow($result);
			$db->sql_freeresult($result);
		}
	
		$page_table_data = array(
			// If we're reverting, make sure we don't increment the version number; we're not creating a new version, so it won't change
			'cur_version_number'	=> ($revert || !$new_version) ? $this->old_data['cur_version_number'] : $this->data['version_number'],
			'page_enabled'			=> $this->data['page_enabled'],
			'page_display'			=> $this->data['page_display'],
			'parent_id'				=> $this->data['parent_id'],
			'parent_enabled'		=> (($this->data['parent_id'] && $this->parent['page_enabled'] && $this->parent['parent_enabled']) || !$this->data['parent_id']) ? 1 : 0,
			'parent_display'		=> (($this->data['parent_id'] && $this->parent['page_display'] && $this->parent['parent_display']) || !$this->data['parent_id']) ? 1 : 0,
			'page_title'			=> $this->data['page_title'],
			'page_slug'				=> $this->data['page_slug'],
			'page_url'				=> $this->data['page_url'],
			'page_last_mod'			=> ($this->data['version_draft']) ? $this->old_data['page_last_mod'] : time(),
			'page_style'			=> $this->data['page_style'],
			'page_contents_table'	=> $this->data['page_contents_table'],
			'page_lock_id'			=> 0,
			'page_lock_time'		=> 0,
		);

		if (!$this->data['page_id'])
		{
			// No page_id means we're creating a new page
			$page_table_data = array_merge($page_table_data, array(
				'page_time'		=> time(),
				'user_id'		=> $user->data['user_id'],
				'version_id'	=> 0,
				'page_versions'	=> 1,
				'page_edits'	=> 1,
			));
		
			if ($this->data['parent_id'])
			{
				$sql = 'UPDATE ' . PAGES_TABLE . "
					SET left_id = left_id + 2, right_id = right_id + 2
					WHERE left_id > {$this->parent['right_id']}";
				$db->sql_query($sql);

				$sql = 'UPDATE ' . PAGES_TABLE . "
					SET right_id = right_id + 2
					WHERE {$this->parent['left_id']} BETWEEN left_id AND right_id";
				$db->sql_query($sql);

				$this->data['left_id'] = $this->parent['right_id'];
				$this->data['right_id'] = ($this->parent['right_id'] + 1);
			}
			else
			{
				$sql = 'SELECT MAX(right_id) AS right_id
					FROM ' . PAGES_TABLE;
				$result = $db->sql_query($sql);
				$right_id = $db->sql_fetchfield('right_id');
				$db->sql_freeresult($result);

				$this->data['left_id'] = ($right_id + 1);
				$this->data['right_id'] = ($right_id + 2);
			}
		
			$page_table_data['left_id']		= $this->data['left_id'];
			$page_table_data['right_id']	= $this->data['right_id'];
		
			$sql = 'INSERT INTO ' . PAGES_TABLE . ' ' . $db->sql_build_array('INSERT', $page_table_data);
			$db->sql_query($sql);
			$this->data['page_id'] = $db->sql_nextid();
		
			set_config_count('num_pages', 1, true);
		
			add_page_log($run_inline, $this->data['page_id'], 0, 'admin', 'LOG_PAGE_ADD', $this->data['page_title']);
		}
		else
		{
			if($config['send_linkbacks'])
			{
				// Get all the links from the old version, so we know which URLs not to send linkbacks to
				$sql = 'SELECT link_url
					FROM ' . PAGES_LINKS_TABLE . ' l
					WHERE link_processed = 1
						AND page_id = ' . $this->data['page_id'];
				$result = $db->sql_query($sql);
				
				$processed_links = array();
				while ($link = $db->sql_fetchrow($result))
				{
					$processed_links[$link['link_url']] = true;
				}
			}
			
			if (!$run_inline && $this->old_data['page_enabled'] != $this->data['page_enabled'])
			{
				add_page_log($run_inline, $this->data['page_id'], 0, 'admin', 'LOG_PAGE_' . ( ($this->data['page_enabled']) ? 'ENABLE' : 'DISABLE' ) , $this->data['page_title']);
			}
		
			if (!$run_inline && $this->old_data['page_display'] != $this->data['page_display'])
			{
				add_page_log($run_inline, $this->data['page_id'], 0, 'admin', 'LOG_PAGE_NAV_' . ( ($this->data['page_display']) ? 'DISPLAY' : 'HIDE' ) , $this->data['page_title']);
			}
		
			if (!$run_inline && $this->old_data['page_title'] != $this->data['page_title'])
			{
				add_page_log($run_inline, $this->data['page_id'], 0, 'admin', 'LOG_PAGE_TITLE', $this->old_data['page_title'], $this->data['page_title']);
			}
		
			if (!$run_inline && $this->old_data['page_slug'] != $this->data['page_slug'])
			{
				add_page_log($run_inline, $this->data['page_id'], 0, 'admin', 'LOG_PAGE_SLUG', $this->old_data['page_slug'], $this->data['page_slug'], $this->data['page_title']);
			}
			
			if (!$run_inline && $this->old_data['page_contents_table'] != $this->data['page_contents_table'])
			{
				add_page_log($run_inline, $this->data['page_id'], 0, 'admin', 'LOG_PAGE_CONTENTS_' . ( ($this->data['page_contents_table']) ? 'ENABLE' : 'DISABLE' ), $this->data['page_title']);
			}
		
			if (!$run_inline && $this->old_data['page_style'] != $this->data['page_style'])
			{
				$sql = 'SELECT style_id, style_name
					FROM ' . STYLES_TABLE . '
					WHERE ' . $db->sql_in_set('style_id', array($this->old_data['page_style'], $this->data['page_style']));
				$result = $db->sql_query($sql);
			
				// Set defaults
				$styles = array(
					$this->old_data['page_style']	=> $user->lang['DEFAULT_STYLE'],
					$this->data['page_style']		=> $user->lang['DEFAULT_STYLE'],
				);
			
				while ($style = $db->sql_fetchrow($result))
				{
					$styles[$style['style_id']] = $style['style_name'];
				}
				$db->sql_freeresult($result);
			
				add_page_log($run_inline, $this->data['page_id'], 0, 'admin', 'LOG_PAGE_STYLE', $styles[$this->old_data['page_style']], $styles[$this->data['page_style']], $this->data['page_title']);
			}
		
			// If the version has changed and version control is enabled, we will be adding a new record, so update the number of versions
			$page_table_data['page_versions'] = ($new_version && $config['version_control'] && !$revert) ? ($this->old_data['page_versions'] + 1) : $this->old_data['page_versions'];
			$page_table_data['page_edits'] = ($new_version && !$revert) ? ($this->old_data['page_edits'] + 1) : $this->old_data['page_edits'];
			
			// The parent has been changed or the slug has been changed we need to rebuild the URL for any children and links
			if ($this->old_data['parent_id'] != $this->data['parent_id'] || $this->data['page_slug'] != $this->old_data['page_slug'])
			{
				// If the page has no children, don't bother trying to find children (;
				$moved_pages = ($this->old_data['left_id'] == ($this->old_data['right_id'] + 1)) ? array($this->old_data) : get_page_branch($this->data['page_id'], 'children');
			
				$moved_ids = $old_urls = $new_urls = $sql_ary = array();
				$num_moved_pages = sizeof($moved_pages);
				for ($i = 0; $i < $num_moved_pages; ++$i)
				{
					$moved_ids[] = $moved_pages[$i]['page_id'];
					$old_urls[] = $moved_pages[$i]['page_url'];
					
					// Update/add the record to the URLs table
					// We'll use this record to allow us redirect users using the old URL to the new URL
					$sql = 'SELECT page_id
						FROM ' . PAGES_URLS_TABLE . "
						WHERE url = '" . $db->sql_escape($moved_pages[$i]['page_url']) . "'";
					$result = $db->sql_query($sql);
					
					if($page_id = $db->sql_fetchfield('page_id'))
					{
						if($page_id != $moved_pages[$i]['page_id'])
						{
							$sql = 'UPDATE ' . PAGES_URLS_TABLE . '
								SET page_id = ' . $moved_pages[$i]['page_id'] . "
								WHERE url = '" . $db->sql_escape($moved_pages[$i]['page_url']) . "'";
							$db->sql_query($sql);
						}
					}
					else
					{
						// The record does not exist, add it
						// Put the record into an array and do a multi-insert at the end
						$sql_ary[] = array(
							'url'		=> $moved_pages[$i]['page_url'],
							'page_id'	=> $moved_pages[$i]['page_id'],
						);
					}
				}
			
				if (sizeof($sql_ary))
				{
					$db->sql_multi_insert(PAGES_URLS_TABLE, $sql_ary);
				}
			}
		
			$sql = 'UPDATE ' . PAGES_TABLE . '
				SET ' . $db->sql_build_array('UPDATE', $page_table_data) . "
				WHERE page_id = " . (int) $this->data['page_id'];
			$db->sql_query($sql);
		
			// The parent has been changed or the slug has been changed
			if ($this->old_data['parent_id'] != $this->data['parent_id'] || $this->data['page_slug'] != $this->old_data['page_slug'])
			{
				for ($i = 0; $i < $num_moved_pages; ++$i)
				{
					$new_url = get_page_url($moved_pages[$i]['page_id'], true);
					$new_urls[] = $new_url;
					
					// Update the URL for any children; the URL for the page itself will already be updated
					if ($moved_pages[$i]['page_id'] != $this->data['page_id'])
					{
						$sql = 'UPDATE ' . PAGES_TABLE . "
							SET page_url = '" . $db->sql_escape($new_url) . "'
							WHERE page_id = " . $moved_pages[$i]['page_id'];
						$db->sql_query($sql);
					}
				}
	
				// Update the links
				$this->_update_links($moved_ids, $old_urls, $new_urls);
			
				// Handle the left/right ids if we're changing the parent
				if($this->old_data['parent_id'] != $this->data['parent_id'])
				{
					move_page($moved_pages, $this->data['page_id'], $this->data['parent_id']);
				}
			}
		
			if ($this->old_data['parent_id'] != $this->data['parent_id'])
			{
				$sql = 'SELECT page_id, page_title
					FROM ' . PAGES_TABLE . ' p
					WHERE ' . $db->sql_in_set('page_id', array($this->old_data['parent_id'], $this->data['parent_id']));
				$result = $db->sql_query($sql);

				$titles = array(
					$this->old_data['parent_id']	=> $user->lang['SITE_ROOT'],
					$this->data['parent_id']		=> $user->lang['SITE_ROOT'],
				);
			
				while ($title = $db->sql_fetchrow($result))
				{
					$titles[$title['page_id']] = $title['page_title'];
				}
				$db->sql_freeresult($result);
			
				if(!$run_inline)
				{
					add_page_log($run_inline, $this->data['page_id'], 0, 'admin', 'LOG_PAGE_PARENT', $titles[$this->old_data['parent_id']], $titles[$this->data['parent_id']], $this->data['page_title']);
				}
			}
		
			if ($this->old_data['page_enabled'] != $this->data['page_enabled'] || $this->old_data['parent_id'] != $this->data['parent_id'])
			{
				// The page has been enabled/disabled or changed parent
				// Update the children, if any
				$sql = 'UPDATE ' . PAGES_TABLE . '
					SET parent_enabled = ' . ( ($page_table_data['page_enabled'] && $page_table_data['parent_enabled']) ? 1 : 0 ) . ',
					parent_display = ' . ( ($page_table_data['page_display'] && $page_table_data['parent_display']) ? 1 : 0 ) . "
					WHERE page_id <> {$this->data['page_id']}
						AND left_id BETWEEN {$this->old_data['left_id']} AND {$this->old_data['right_id']}";
				$db->sql_query($sql);
			}
		}
	
		if ($new_version == false)
		{
			// There are no changes made to the version, no need to insert a new record so finish here
			return true;
		}
		
		if ($revert)
		{
			// Rather than create a new version, simply revert
			revert_page($this->data['page_id'], $revert['version_id'], $revert['version_draft'], false);
			add_page_log(false, $this->data['page_id'], 0, 'admin', 'LOG_PAGE_REVERT', $this->data['page_title'], $this->old_data['version_number'], $revert['version_number']);
			
			return true;
		}
	
		$version_table_data = array(
			'page_id'					=> $this->data['page_id'],
			'version_number'			=> $this->data['version_number'],
			'version_type'				=> $this->data['version_type'],
			'version_draft'				=> $this->data['version_draft'],
			'version_html'				=> $this->data['version_html'],
			'version_physical_filename'	=> $this->data['version_physical_filename'],
			'version_real_filename'		=> $this->data['version_real_filename'],
			'version_extension'			=> $this->data['version_extension'],
			'version_mimetype'			=> $this->data['version_mimetype'],
			'version_image'				=> $this->data['version_image'],
			'version_filesize'			=> $this->data['version_filesize'],
			'version_module_basename'	=> $this->data['version_module_basename'],
			'version_module_mode'		=> $this->data['version_module_mode'],
			'version_link_type'			=> $this->data['version_link_type'],
			'version_link_url'			=> $this->data['version_link_url'],
			'version_link_id'			=> $this->data['version_link_id'],
			'version_checksum'			=> $this->data['version_checksum'],
			'user_id'					=> $user->data['user_id'],
			'version_time'				=> time(),
			'version_desc'				=> $this->data['version_desc'],
		);
	
		if ($config['version_control'] || $action == 'add')
		{
			// Version control is enabled or we are inserting a new page (or both!)
			// Either way we need a new version record
			$sql = 'INSERT INTO ' . PAGES_VERSIONS_TABLE . ' ' . $db->sql_build_array('INSERT', $version_table_data);
			$db->sql_query($sql);
		
			$page_table_data['version_id'] = $db->sql_nextid();
		
			if (!(($action == 'edit' && !$config['version_control']) || $this->data['version_draft']))
			{
				// Now set the new version_id for the pages table
				$sql = 'UPDATE ' . PAGES_TABLE . '
						SET version_id = ' . $page_table_data['version_id'] . '
						WHERE page_id = ' . (int) $this->data['page_id'];
				$db->sql_query($sql);
			}
		
			set_config_count('num_versions', 1, true);
		}
		else
		{
			// Version control is not enabled, rather than insert a new version, just update the current one
			$sql = 'UPDATE ' . PAGES_VERSIONS_TABLE . '
				SET ' . $db->sql_build_array('UPDATE', $version_table_data) . '
				WHERE version_id = ' . $this->old_data['version_id'];
			$db->sql_query($sql);
		
			// Delete the old links
			$sql = 'DELETE FROM ' . PAGES_LINKS_TABLE . '
				WHERE version_id = ' . $this->old_data['version_id'];
			$db->sql_query($sql);
		
			$page_table_data['version_id'] = $this->old_data['version_id'];
		}
	
		$this->data = array_merge($version_table_data, $page_table_data);
	
		// Refresh the home page
		if($this->data['page_enabled'] && $this->data['parent_id'] == 0)
		{
			refresh_home_page();
		}
	
		// The page is published
		// Committing the transaction before processing links
		$db->sql_transaction('commit');
	
		if(isset($this->links) && sizeof($this->links))
		{
			$u_page = generate_cms_url(true, true) . generate_url($this->data['page_id'], $this->data['page_url'], false);
		
			// Insert the links
			foreach($this->links as &$link)
			{
				// We don't send linkbacks when the page is a draft
				if(!isset($processed_links[$link['link_url']]) && !$link['link_page_id'] && $config['send_linkbacks'] && !$this->data['version_draft'])
				{
					send_linkback($u_page, $link['link_url'], $this->data['page_title'], $link['responce']);
				}
			
				// We don't want this to go into the DB
				unset($link['responce'], $link['page']);
			
				$link['link_processed'] = (!$this->data['version_draft']) ? 1 : 0;
				$link['version_id'] = $this->data['version_id'];
				$link['link_time'] = time();
			}
			$db->sql_multi_insert(PAGES_LINKS_TABLE, $this->links);
		}
	
		if ($config['version_control'])
		{
			add_page_log( ($action == 'edit' ? $run_inline : true), $this->data['page_id'], $this->data['version_id'], 'admin', 'LOG_VERSION_ADD', $this->data['version_number'], $this->data['page_title']);
		}
		elseif($action == 'edit')
		{
			add_page_log($run_inline, $this->data['page_id'], $this->data['version_id'], 'admin', 'LOG_PAGE_EDIT', $this->data['page_title']);
		}
	
		if (!$run_inline)
		{
			user_page_notification('edit', $this->data['page_id'], $this->data['page_title'], $this->data['page_url']);
		}
		
		return true;
	}
}
?>
