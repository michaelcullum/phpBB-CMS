<?php
/**
*
* @package cms
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
* @package cms
*/
class cms_news
{
	var $u_action;
	var $page;

	function main($id, $mode)
	{
		global $config, $db, $user, $auth, $template, $cache, $attachments;
		global $phpbb_root_path, $phpbb_admin_path, $phpEx, $table_prefix;
		
		$user->add_lang('viewtopic');
		
		include_once($phpbb_root_path . 'includes/functions_admin.' . $phpEx);
		include_once($phpbb_root_path . 'includes/functions_display.' . $phpEx);
		
		$this->tpl_name = 'cms_news';
		
		$start	= request_var('start', 0);
		
		$forum_ids = array($config['news_forum']);
		
		if ($config['news_display_children'] = true)
		{
			$forum_children = array();
			foreach ($forum_ids as &$forum_id)
			{
				if(!$auth->acl_get('f_read', $forum_id))
				{
					unset($forum_id);
					continue;
				}
				
				$children = get_forum_branch($forum_id, 'children');
				foreach ($children as $row)
				{
					if(!$auth->acl_get('f_read', $row['forum_id']))
					{
						continue;
					}
					
					$forum_children[] = $row['forum_id'];
				}
			}
			
			$forum_ids = array_merge($forum_ids, $forum_children);
		}
		
		$forum_ids = array_unique($forum_ids);
		
		$sql = 'SELECT p.*, t.*
			FROM ' . POSTS_TABLE . ' p
			JOIN ' . TOPICS_TABLE . ' t
				ON t.topic_first_post_id = p.post_id
			WHERE ' . $db->sql_in_set('t.forum_id', $forum_ids) . '
				AND t.topic_status <> ' . ITEM_MOVED . '
				AND t.topic_approved = 1
			ORDER BY t.topic_type DESC, topic_time DESC';
		$result = $db->sql_query_limit($sql, $config['posts_per_page'], $start);
		
		$post_row = $attach_ids = array();
		while($row = $db->sql_fetchrow($result))
		{
			if ($row['post_attachment'] && $config['allow_attachments'] && $auth->acl_get('f_download', $row['forum_id']))
			{
				$attach_ids[] = $row['post_id'];
				
				if ($row['post_approved'])
				{
					$has_attachments = true;
				}
			}
			
			$post_row[] = $row;
		}
		
		$attachments = array();
		if (sizeof($attach_ids))
		{
			if ($auth->acl_get('u_download'))
			{
				$sql = 'SELECT *
					FROM ' . ATTACHMENTS_TABLE . '
					WHERE ' . $db->sql_in_set('post_msg_id', $attach_ids) . '
						AND in_message = 0';
				$result = $db->sql_query($sql);
		
				while ($row = $db->sql_fetchrow($result))
				{
					$attachments[$row['post_msg_id']][] = $row;
				}
				
				$db->sql_freeresult($result);
			}
			else
			{
				$display_notice = true;
			}
		}
		
		foreach($post_row as $row)
		{
			$bbcode_options    = (($row['enable_bbcode']) ? OPTION_FLAG_BBCODE : 0) + (($row['enable_smilies']) ? OPTION_FLAG_SMILIES : 0) + (($row['enable_magic_url']) ? OPTION_FLAG_LINKS : 0);
			
			$row['post_text']	= censor_text($row['post_text']);
			$row['post_text']	= generate_text_for_display($row['post_text'], $row['bbcode_uid'], $row['bbcode_bitfield'], $bbcode_options);
			$row['post_text']	= bbcode_nl2br($row['post_text']);
			$row['post_text']	= smiley_text($row['post_text']);
			
			$template->assign_block_vars('postrow', array(
				'TOPIC_TITLE'	=> censor_text($row['topic_title']),
				'TOPIC_TEXT'	=> $row['post_text'],
				'TOPIC_AUTHOR'	=> get_username_string('full', $row['topic_poster'], $row['topic_first_poster_name'], $row['topic_first_poster_colour']),
				'TOPIC_DATE'	=> $user->format_date($row['topic_time']),
				'U_VIEW_TOPIC'	=> append_sid("{$phpbb_root_path}viewtopic.$phpEx", 'f=' . $row['forum_id'] . '&amp;t=' . $row['topic_id']),
			));
			
			if(isset($attachments[$row['post_id']]))
			{
				foreach ($attachments[$row['post_id']] as $attachment)
				{
					$template->assign_block_vars('postrow.attachment', array(
						'DISPLAY_ATTACHMENT'	=> $attachment
					));
				}
			}
		}
		
		$sql = 'SELECT COUNT(t.topic_id) total_topics
			FROM ' . TOPICS_TABLE . ' t
			WHERE ' . $db->sql_in_set('forum_id', $forum_ids) . '
				AND t.topic_status <> ' . ITEM_MOVED . '
				AND t.topic_approved = 1';
		$result = $db->sql_query($sql);
		$total_posts = (int) $db->sql_fetchfield('total_topics');
		
		$pagination_url = append_sid(generate_url($this->page['page_id'], $this->page['page_url']));
		$pagination = generate_pagination($pagination_url, $total_posts, $config['posts_per_page'], $start);
		
		$s_hidden_fields = array(
			'fid'	=> $forum_ids,
		);
		
		$template->assign_vars(array(
			'PAGE_CONTENT'	=> $this->page['version_html'],
			
			'PAGINATION' 	=> $pagination,
			'PAGE_NUMBER' 	=> on_page($total_posts, $config['posts_per_page'], $start),
			'TOTAL_POSTS'	=> ($total_posts == 1) ? $user->lang['VIEW_TOPIC_POST'] : sprintf($user->lang['VIEW_TOPIC_POSTS'], $total_posts),
			
			'S_SEARCHBOX_ACTION'	=> append_sid("{$phpbb_root_path}search.$phpEx"),
			'S_HIDDEN_FIELDS'		=> build_hidden_fields($s_hidden_fields),
			
			'U_VIEW_FORUM'	=> append_sid("{$phpbb_root_path}viewforum.$phpEx", 'f=' . $forum_ids[0]),
		));
	}
}
?>
