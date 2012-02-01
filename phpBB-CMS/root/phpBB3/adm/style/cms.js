// May as well...
if (document.getElementById('page-header'))
{
	document.getElementById('page-header').style.background = 'url("images/phpbbcms_logo.gif") top left no-repeat';
}

/**
* Set the slug field with a clean version of the slug
*/
function generate_slug(value)
{
	var page_slug = document.getElementById('page_slug');
	
	if ((set_slug == false || page_slug.value == '') && value != '')
	{
		set_slug = false;
		page_slug.value = value.toLowerCase().replace(/ /g, space_separator).replace(new RegExp('[^a-zA-Z0-9+._-]'), '');
	}
}

/**
* Extract the filename from a path/URL
*/
function extract_filename(path)
{
	return path.substring(path.lastIndexOf('/') + 1);
}

/**
* Determine the value of a radio button
* Use like radio_value(document.getElementById('form_id').radio_name)
*/
function radio_value(radio_name)
{
	for (var i = 0; i < radio_name.length; i++)
	{
		if (radio_name[i].checked)
		{
			return radio_name[i].value;
		}
	}
}

/**
* Onload function to grab form values for editor
*/
function edit_onload()
{
	// Hide advanced settings by default
	dE('advanced_options', -1);
	
	// Hide/Show any relevant options
	display_options();
	display_upload_options();
	display_link_options();
	
	// Grab a copy of all the values before editing
	page_title = document.getElementById('page_title').value;
	page_slug = document.getElementById('page_slug').value;
	version_type = document.getElementById('version_type').value;
	parent_id = document.getElementById('parent_id').value;
	page_enabled = radio_value(document.getElementById('page_edit').page_enabled);
	page_display = radio_value(document.getElementById('page_edit').page_display);
	version_html = document.getElementById('version_html').value;
	version_module_basename = document.getElementById('version_module_basename').value;
	version_module_mode = document.getElementById('version_module_mode').value;
	version_link_url = document.getElementById('version_link_url').value;
	version_link_id = document.getElementById('version_link_id').value;
}

/**
* Unload function for page editor for confirm prompt
*/
function edit_unload()
{
	if (confirm_on_exit)
	{
		// If the user has changed anything, we need to alert them
		confirm_on_exit = false;
		confirm_on_exit = ( page_title != document.getElementById('page_title').value  ) ? true : confirm_on_exit;
		confirm_on_exit = ( page_slug != document.getElementById('page_slug').value  ) ? true : confirm_on_exit;
		confirm_on_exit = ( version_type != document.getElementById('version_type').value  ) ? true : confirm_on_exit;
		confirm_on_exit = ( parent_id != document.getElementById('parent_id').value  ) ? true : confirm_on_exit;
		confirm_on_exit = ( page_enabled != radio_value(document.getElementById('page_edit').page_enabled)  ) ? true : confirm_on_exit;
		confirm_on_exit = ( page_display != radio_value(document.getElementById('page_edit').page_display)  ) ? true : confirm_on_exit;
		confirm_on_exit = ( version_html != tinyMCE.get('version_html').getContent()  ) ? true : confirm_on_exit;
		confirm_on_exit = ( version_module_basename != document.getElementById('version_module_basename').value  ) ? true : confirm_on_exit;
		confirm_on_exit = ( version_module_mode != document.getElementById('version_module_mode').value  ) ? true : confirm_on_exit;
		confirm_on_exit = ( version_link_url != document.getElementById('version_link_url').value  ) ? true : confirm_on_exit;
		confirm_on_exit = ( version_link_id != document.getElementById('version_link_id').value  ) ? true : confirm_on_exit;
		
		if (confirm_on_exit)
		{
			return navigate_away;
		}
	}
}

/**
* Update form fields, based on the version type
*/
function display_options()
{
	value = document.getElementById('version_type').value;
	
	if (value == version_type_html)
	{
		dE('style_options', 1);
		dE('contents_table_options', 1);
		
		dE('html_options', 1);
		dE('upload_options', -1);
		dE('module_options', -1);
		dE('link_options', -1);
		
		// Focus on the editor
		tinyMCE.execCommand('mceFocus', false, 'version_html');
	}
	else if (value == version_type_category)
	{
		dE('style_options', 1);
		dE('contents_table_options', -1);
		
		dE('html_options', -1);
		dE('upload_options', -1);
		dE('module_options', -1);
		dE('link_options', -1);
	}
	else if (value == version_type_file)
	{
		dE('style_options', -1);
		dE('contents_table_options', -1);
		
		dE('html_options', -1);
		dE('upload_options', 1);
		dE('module_options', -1);
		dE('link_options', -1);
	}
	else if (value == version_type_module)
	{
		dE('style_options', 1);
		dE('contents_table_options', -1);
		
		dE('html_options', -1);
		dE('upload_options', -1);
		dE('module_options', 1);
		dE('link_options', -1);
		
		// Refresh the module options
		display_modes_options(document.getElementById('version_module_mode').value);
	}
	else if (value == version_type_link)
	{
		dE('style_options', -1);
		dE('contents_table_options', -1);
		
		dE('html_options', -1);
		dE('upload_options', -1);
		dE('module_options', -1);
		dE('link_options', 1);
		
		// Refresh the link options
		display_link_options(document.getElementById('version_module_mode').value);
	}
}

/**
* Update the upload options, based on the upload type
*/
function display_upload_options()
{
	value = radio_value(document.getElementById('page_edit').upload_type);
	
	if (value == upload_type_file)
	{
		dE('file_options', 1);
		dE('url_options', -1);
	}
	else if (value == upload_type_url)
	{
		dE('file_options', -1);
		dE('url_options', 1);
		
		document.getElementById('upload_url').focus();
	}
}

/**
* Update link options, based on the link type
*/
function display_link_options()
{
	value = radio_value(document.getElementById('page_edit').version_link_type);
	
	if (value == link_type_url)
	{
		dE('link_url_options', 1);
		dE('link_page_options', -1);
		
		// IE doesn't like us trying to focus on a hidden field. Fair enough.
		if(document.getElementById('version_type').value == version_type_link)
		{
			document.getElementById('version_link_url').focus();
		}
	}
	else if (value == link_type_page)
	{
		dE('link_url_options', -1);
		dE('link_page_options', 1);
	}
	else if (value == link_type_phpbb)
	{
		dE('link_url_options', -1);
		dE('link_page_options', -1);
	}
}

/**
* Initiate tinyMCE
*/
function tinymce_init()
{
	tinyMCE.init(
	{
		// General options
		mode : 'exact',
		elements : 'version_html',
		
		theme : 'advanced',
		plugins : tinymce_plugins,
	
		// Theme options
		theme_advanced_buttons1 : 'bold,italic,underline,strikethrough,|,bullist,numlist,blockquote,|,justifyleft,justifycenter,justifyright,justifyfull,|,link,unlink,upload_file,upload_image' + ( (s_enable_spellchecker) ? ',spellchecker' : '' ) + ',|,pagebreak',
		theme_advanced_buttons2 : 'formatselect,|,forecolor,|,paste,pastetext,pasteword,|,outdent,indent,|,undo,redo,removeformat,|,charmap,iespell,|,fullscreen,code,help',
		theme_advanced_buttons3 : '',
		theme_advanced_toolbar_location : 'top',
		theme_advanced_toolbar_align : 'center',
		theme_advanced_statusbar_location : 'bottom',
		theme_advanced_resizing : true,
		theme_advanced_resize_horizontal : false,
		theme_advanced_blockformats : 'p,address,pre,code,h2,h3,h4,h5,h6', // Don't let users choose h1
		
		button_tile_map : true,
		language : tinymce_lang,
		pagebreak_separator : pagebreak_separator,
		width : "100%",
		relative_urls : false,
		remove_script_host : true,
		document_base_url : base_url,
		file_browser_callback : 'cms_upload',
		
		// Preview the style?
		content_css : (s_preview_style) ? style_url : false,
		body_class : (s_preview_style) ? 'content post inner' : false,
		
		// Force br newlines?
		force_br_newlines : (s_br_newlines) ? true : false,
		forced_root_block : (s_br_newlines) ? '' : 'p',
		
		setup : function(ed)
		{
			// Add custom buttons for uploading files/images
			ed.addButton('upload_file',
			{
				title : upload_file,
				image : 'images/icon_upload_file.gif',
				onclick : function()
				{
					cms_upload(false, false, 'file', '');
				}
			});
			ed.addButton('upload_image',
			{
				title : upload_image,
				image : 'images/icon_upload_image.gif',
				onclick : function()
				{
					cms_upload(false, false, 'image', '', ed, false);
				}
			});
		}
	});
}

/**
* Upload launcher for tinyMCE
*/
function cms_upload(field_name, url, type, win, ed)
{
	tinyMCE.activeEditor.windowManager.open(
	{
		// Use an absolute path and "&" instead of "&amp;"
		file : u_cms_upload + '&action=' + type,
		title : acp_cms_upload,
		width : 760,
		height : 570,
		resizable : 'yes',
		inline : 'yes',
		close_previous : 'no',
		popup_css : false
	},
	{
		window : win,
		input : field_name
	});

	return false;
}

/*
Functions for comparing version radio buttons
Obtained from MediaWiki: http://www.mediawiki.org

	- modified to adhere to coding guidelines
	- integration into CMS design
*/

/**
* Functions for comparing versions
*/
function historyRadios(parent)
{
	var inputs = parent.getElementsByTagName('input');
	var radios = [];
	for (var i = 0; i < inputs.length; i++)
	{
		if (inputs[i].name == "version1" || inputs[i].name == "version2")
		{
			radios[radios.length] = inputs[i];
		}
	}
	return radios;
}

/**
* Check selection and tweak visibility/class onclick
*/
function diffcheck()
{
	var dli = false; // the li where the diff radio is checked
	var oli = false; // the li where the oldid radio is checked
	var hf = document.getElementById('versions');
	
	if (!hf)
	{
		return true;
	}
	
	var lis = hf.getElementsByTagName('td');
	for (var i = 0; i < lis.length; i++)
	{
		var inputs = historyRadios(lis[i]);
		if (inputs[1] && inputs[0])
		{
			if (inputs[1].checked || inputs[0].checked)
			{
				// this row has a checked radio button
				if (inputs[1].checked && inputs[0].checked && inputs[0].value == inputs[1].value)
				{
					return false;
				}
				
				if (oli)
				{
					// it's the second checked radio
					if (inputs[1].checked)
					{
						oli.className = "selected";
						return false;
					}
				}
				else if (inputs[0].checked)
				{
					return false;
				}
				
				if (inputs[0].checked)
				{
					dli = lis[i];
				}
				
				if (!oli)
				{
					inputs[0].style.visibility = 'hidden';
				}
				
				if (dli)
				{
					inputs[1].style.visibility = 'hidden';
				}
				
				lis[i].className = "selected";
				oli = lis[i];
			}
			else
			{
				// no radio is checked in this row
				if (!oli)
				{
					inputs[0].style.visibility = 'hidden';
				}
				else
				{
					inputs[0].style.visibility = 'visible';
				}
				
				if (dli)
				{
					inputs[1].style.visibility = 'hidden';
				}
				else
				{
					inputs[1].style.visibility = 'visible';
				}
				lis[i].className = "";
			}
		}
	}
	return true;
}

/**
* Attach event handlers to the input elements on history page
*/
function histrowinit()
{
	var hf = document.getElementById('versions');
	
	if (!hf)
	{
		return;
	}
	
	var lis = hf.getElementsByTagName('td');
	for (var i = 0; i < lis.length; i++)
	{
		var inputs = historyRadios(lis[i]);
		
		if (inputs[0] && inputs[1])
		{
			inputs[0].onclick = diffcheck;
			inputs[1].onclick = diffcheck;
		}
	}
	diffcheck();
}
