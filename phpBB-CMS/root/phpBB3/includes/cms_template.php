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
* CMS template class
*/
class cms_template
{
	/** variable that holds all the data we'll be substituting into
	* the compiled templates. Takes form:
	* --> $this->_tpldata[block][iteration#][child][iteration#][child2][iteration#][variablename] == value
	* if it's a root-level variable, it'll be like this:
	* --> $this->_tpldata[.][0][varname] == value
	*/
	var $_tpldata = array('.' => array(0 => array()));
	var $_rootref;

	// Hash of filenames for each template handle.
	var $cachepath = '';
	
	// this will hash handle names to the compiled/uncompiled code for that handle.
	var $compiled_code = array();
	
	function cms_template()
	{
		global $phpbb_root_path, $template;
		
		// Steal all the variables for the template from the template class
		$this->_tpldata = $template->_tpldata;
		$this->_rootref = $template->_rootref;
		
		$this->cachepath = $phpbb_root_path . 'cache/cms_version_';
	}
	
	/**
	* Load template source from file
	* @access private
	*/
	function _tpl_load_file($version_id, $version_html)
	{
		global $phpbb_root_path, $phpEx;

		if (!class_exists('template_compile'))
		{
			include($phpbb_root_path . 'includes/functions_template.' . $phpEx);
		}
		
		$compile = new template_compile($this);
		
		$this->compiled_code[$version_id] = $compile->compile(trim($version_html));

		// Actually compile the code now.
		$this->compile_write($version_id, $this->compiled_code[$version_id]);
	}
	
	function _tpl_load(&$version_id, $version_html, $version_time)
	{
		global $user, $phpEx, $config;

		$filename = $this->cachepath . $version_id . '.' . $phpEx;
		
		// Recompile page if the original template is newer, otherwise load the compiled version
		if (file_exists($filename) && @filesize($filename) !== 0 && !(@filemtime($filename) < $version_time))
		{
			return $filename;
		}

		$this->_tpl_load_file($version_id, $version_html);
		return false;
	}
	
	/**
	* Include a separate template
	* @access private
	*/
	function _tpl_include($filename, $include = true)
	{
		global $template;
		return $template->_tpl_include($filename, $include);
	}
	
	/**
	* Write compiled file to cache directory
	* @access private
	*/
	function compile_write($version_id, $version_html)
	{
		global $phpEx;
		
		$filename = $this->cachepath . $version_id . '.' . $phpEx;

		$data = "<?php if (!defined('IN_PHPBB')) exit;" . ((strpos($data, '<?php') === 0) ? substr($data, 5) : ' ?>' . $version_html);

		if ($fp = @fopen($filename, 'wb'))
		{
			@flock($fp, LOCK_EX);
			@fwrite ($fp, $data);
			@flock($fp, LOCK_UN);
			@fclose($fp);

			phpbb_chmod($filename, CHMOD_READ | CHMOD_WRITE);
		}

		return;
	}
	
	/**
	* Display handle
	* @access public
	*/
	function display($version_id, $version_html, $version_time, $include_once = true)
	{
		if (defined('IN_ERROR_HANDLER'))
		{
			if ((E_NOTICE & error_reporting()) == E_NOTICE)
			{
				error_reporting(error_reporting() ^ E_NOTICE);
			}
		}

		if ($filename = $this->_tpl_load($version_id, $version_html, $version_time))
		{
			($include_once) ? include_once($filename) : include($filename);
		}
		else
		{
			eval(' ?>' . $this->compiled_code[$version_id] . '<?php ');
		}

		return true;
	}
	
	/**
	* Display the handle and assign the output to a template variable or return the compiled result.
	* @access public
	*/
	function assign_display($version_id, $version_html, $version_time, $template_var = '', $return_content = true, $include_once = false)
	{
		global $config, $template;
		
		if($config['tpl_allow_php'])
		{
			// Convert PHP tags to the phpBB format
			// We have to use inline PHP in the traditional format, because tinyMCE converts entities on PHP code between comments
			// This matches the information gathered from the internal PHP lexer
			$version_html = preg_replace('#<\?php(?:\r\n?|[ \n\t])(.*)?\?>#s', '<!-- PHP -->\\1\\2<!-- ENDPHP -->', $version_html);
		}
		
		ob_start();
		$this->display($version_id, $version_html, $version_time, $include_once);
		$contents = ob_get_clean();

		if ($return_content)
		{
			return $contents;
		}

		$template->assign_var($template_var, $contents);

		return true;
	}
	
	/**
	* Include a php-file
	* @access private
	*/
	function _php_include($filename)
	{
		global $template;
		
		$template->_php_include($filename);
	}
}
?>
