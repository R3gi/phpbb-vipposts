<?php

/**
 * This file is part of the VIP Posts extension package
 *
 * @copyright (c) 2016, Honza Remes
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 * @package ciakval/vipposts/event
 */

namespace ciakval\vipposts\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
	/** @var \phpbb\auth\auth */
	protected $auth;
	/** @var \phpbb\user */
	protected $user;
	/** @var \phpbb\request\request */
	protected $request;
	/** @var \phpbb\template\template */
	protected $template;
	/** @var \phpbb\config\config */
	protected $config;
	/**
	 * Constructor
	 *
	 * @param \phpbb\auth\auth			$auth		Auth object
	 * @param \phpbb\user				$user		User object
	 * @param \phpbb\request\request	$request	Request object 
	 * @param \phpbb\template\template	$template	Page template
	 * @return \ciakval\vipposts\event\listener
	 * @access public
	 */
	public function __construct(
		\phpbb\auth\auth $auth,
		\phpbb\user $user,
		\phpbb\request\request $request,
		\phpbb\template\template $template,
		\phpbb\config\config $config)
	{
		$this->auth = $auth;
		$this->user = $user;
		$this->request = $request;
		$this->template = $template;
		$this->config = $config;
	}

	/**
	 * Link handler functions to subscribed events
	 *
	 * @return array
	 */
	static public function getSubscribedEvents()
	{
		return array(
			'core.user_setup'	=> 'load_lang',
			'core.phpbb_content_visibility_get_visibility_sql_before'	=> 'limit_vip_posts',
			'core.permissions'					=> 'permissions',
			'core.posting_modify_template_vars'	=> 'posting_button',
			'core.submit_post_modify_sql_data'	=> 'posting',
			'core.viewtopic_assign_template_vars_before'	=> 'set_highlight',
			'core.viewtopic_post_rowset_data'	=> 'add_highlight',
			'core.viewtopic_modify_post_row'	=> 'push_highlight',
		);
	}

	/**
	 * Load extension language files
	 *
	 * @param \phpbb\event\data $event	User setup event
	 */
	public function load_lang($event)
	{
		$lang_set_ext = $event['lang_set_ext'];
		$lang_set_ext[] = array(
			'ext_name'	=> 'ciakval/vipposts',
			'lang_set'	=> 'common'
		);

		$event['lang_set_ext'] = $lang_set_ext;
	}

	/**
	 * Filter out VIP posts for non-VIP users
	 *
	 * @param \phpbb\event\data	$event	Visibility SQL manipulating event
	 */
	public function limit_vip_posts($event)
	{
		if ($event['mode'] == 'post')
		{
			if ($this->auth->acl_get('!u_vip_view'))
			{
				$event['where_sql'] = 'p.post_vip = 0 AND ';
			}
		}

	}

	/**
	 * Add extension permissions to the phpBB permission system
	 *
	 * @param \phpbb\event\data	$event	Permission event
	 */
	public function permissions($event)
	{
		$permissions = $event['permissions'];
		$permissions['u_vip_view'] = array('lang' => 'ACL_U_VIP_VIEW', 'cat' => 'misc');
		$permissions['u_vip_post'] = array('lang' => 'ACL_U_VIP_POST', 'cat' => 'post');
		$permissions['m_vip_set'] = array('lang' => 'ACL_M_VIP_SET', 'cat' => 'misc');
		$event['permissions'] = $permissions;
	}

	/**
	 * Set template variables to control displaying 'mark as VIP' button
	 *
	 * @param \phpbb\event\data	$event	Posting template variables modifying event
	 */
	public function posting_button($event)
	{
		// Set button text
		$this->user->add_lang_ext('ciakval/vipposts', 'common');

		$page_data = $event['page_data'];
		$page_data['S_CAN_VIPPOST'] = $this->auth->acl_get('u_vip_post');
		if ($event['post_data']['post_vip'])
		{
			$page_data['S_VIPPOST'] = 'checked="checked"';
		}
		$event['page_data'] = $page_data;
	}

	/**
	 * Propagate 'mark as VIP' post setting to the database
	 *
	 * @param \phpbb\event\data	$event	Post submission SQL manipulating event
	 */
	public function posting($event)
	{
		$post_vip = $this->request->variable('vippost', false);

		$sql_data = $event['sql_data'];
		$sql_data[POSTS_TABLE]['sql'] = array_merge($sql_data[POSTS_TABLE]['sql'], array(
			'post_vip' => $post_vip,
		));

		$event['sql_data'] = $sql_data;
	}

	/**
	 * Propagate highlight settings to the template
	 *
	 * @param \phpbb\event\data $event	Early viewtopic event
	 */
	public function set_highlight($event)
	{
		$this->template->assign_var('S_VIPPOSTS_HIGHLIGHT', $this->config['vipposts_highlight']);
	}

	/**
	 * Store the content of the DB 'post_vip' field in the rowset
	 *
	 * @param \phpbb\event\data $event	Post Rowset viewtopic event
	 */
	public function add_highlight($event)
	{
		$rowset = $event['rowset_data'];
		$rowset['post_vip'] = $event['row']['post_vip'];
		$event['rowset_data'] = $rowset;
	}

	/**
	 * Push the 'post_vip' field data from the rowset to the template
	 *
	 * @param \phpbb\event\data $event	Modify Post Row viewtopic event
	 */
	public function push_highlight($event)
	{
		$post_row = $event['post_row'];
		$post_row['POST_VIP'] = $event['row']['post_vip'];
		$event['post_row'] = $post_row;
	}
}
