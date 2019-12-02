<?php

class SFS
{
	/* Some URLs */
	private $urlSFSipCheck = 'https://www.stopforumspam.com/ipcheck/%1$s';
	private $urlSFSsearch = 'https://www.stopforumspam.com/search/%1$s';

	/* Our Software/Version Info defaults */
	private $softwareName = 'smf';
	private $softwareVersion = '2.1';

	/* The admin page url */
	private $adminPageURL = null;

	/* Settings we defaulted*/
	private $changedSettings = array();
	private $extraVerificationOptions = array();

	/* Search stuff */
	private $search_params = array();
	private $search_params_column = '';

	/* Logs Disabled for */
	private $hoursDisabled = 24;

	/* Startup the class so we can call it later
		@hook: SMF2.0: integrate_admin_areas
		@hook: SMF2.1: integrate_admin_areas
		@CalledIn: SMF 2.0, SMF 2.1
	*/
	public static function hook_pre_load()
	{
		global $smcFunc;
		
		$smcFunc['classSFS'] = new SFS();
	}

	/*
		We do this once we construct
		@CalledIn: SMF 2.0, SMF 2.1
	*/
	public function __construct()
	{
		global $smcFunc;

		// Is this SMF 2.0?
		if (!function_exists('loadCacheAccelerator'))
			$this->softwareVersion = '2.0';

		// Setup the defaults.
		$this->loadDefaults();
	}

	/*
		Admin Panel areas addition
		@CalledIn: SMF 2.0, SMF 2.1
		@hook: SMF2.0: integrate__admin_areas
		@hook: SMF2.1: integrate__admin_areas
	*/
	public static function hook_admin_areas(&$admin_areas)
	{
		global $smcFunc;
		return $smcFunc['classSFS']->setupAdminAreas($admin_areas);
	}

	/*
		Does the actual setup of the admin areas
		@CalledIn: SMF 2.0, SMF 2.1
	*/
	public function setupAdminAreas(&$admin_areas)
	{
		global $txt, $scripturl;

		// Get our language in here.
		$this->loadLanguage();

		// Add the menu item.
		if ($this->versionCheck('2.0', 'smf'))
		{
			$this->adminPageURL = $scripturl . '?action=admin;area=modsettings;sa=sfs';
			$this->adminLogURL = $scripturl . '?action=admin;area=modsettings;sa=sfslog';

			$admin_areas['config']['areas']['modsettings']['subsections']['sfs'] = array(
				$txt['sfs_admin_area']
			);
			$admin_areas['config']['areas']['modsettings']['subsections']['sfslog'] = array(
				$txt['sfs_admin_logs']
			);
		}
		else
		{
			$this->adminPageURL = $scripturl . '?action=admin;area=securitysettings;sa=sfs';
			$this->adminLogURL = $scripturl . '?action=admin;area=logs;sa=sfslog';

			$admin_areas['config']['areas']['securitysettings']['subsections']['sfs'] = array(
				$txt['sfs_admin_area']
			);
			$admin_areas['config']['areas']['securitysettings']['subsections']['sfslog'] = array(
				$txt['sfs_admin_logs']
			);
		}

		return;
	}

	/*
		Only do this for 2.0, but we put it in the mod section.
		@hook: SMF2.0: integrate_modify_modifications
		@hook: SMF2.1: 
		@CalledIn: SMF 2.0
	*/
	public static function hook_modify_modifications(&$subActions)
	{
		global $smcFunc;
		return $smcFunc['classSFS']->setupModifyModifications($subActions);
	}

	/*
		Setup the Configuration page.
		@CalledIn: SMF 2.0
	*/
	public function setupModifyModifications(&$subActions)
	{
		$subActions['sfs'] = 'SFS::startupAdminConfiguration';

		// Only in SMF 2.0 do we drop logs here.
		if ($this->versionCheck('2.0', 'smf'))
			$subActions['sfslog'] = 'SFS::startupLogs';

		return;
	}

	/*
		Only need to do this for SMF 2.0, SMF 2.1 calls hook_manage_logs
		@hook: SMF2.0: integrate_modify_modifications
		@CalledIn: SMF 2.0
	*/
	public static function startupAdminConfiguration($return_config = false)
	{
		global $smcFunc;
		return $smcFunc['classSFS']->setupSFSConfiguration($return_config);
	}

	/*
		The settings page.
		@CalledIn: SMF 2.0, SMF 2.1
	*/
	public function setupSFSConfiguration($return_config = false)
	{
		global $txt, $scripturl, $context, $settings, $sc, $modSettings;

		$config_vars = array(
				array('title', 'sfsgentitle', 'label' => $txt['sfs_general_title']),

				array('check', 'sfs_enabled'),
				array('check', 'sfs_log_debug'),
			'',
				array('check', 'sfs_emailcheck'),
				array('check', 'sfs_ipcheck'),
				array('check', 'sfs_usernamecheck'),
			'',
				array('select', 'sfs_region', $this->sfsServerMapping('config')),
			'',
				array('check', 'sfs_wildcard_email'),
				array('check', 'sfs_wildcard_username'),
				array('check', 'sfs_wildcard_ip'),
			'',
				array('select', 'sfs_tor_check', array(
					0 => $txt['sfs_tor_check_block'],
					1 => $txt['sfs_tor_check_ignore'],
					2 => $txt['sfs_tor_check_bad'],
				)),

			'',
				array('title', 'sfsverftitle', 'label' => $txt['sfs_verification_title']),
				array('desc', 'sfsverfdesc', 'label' => $txt['sfs_verification_desc']),
				array('select', 'sfs_verification_options', array(
					'post' => $txt['sfs_verification_options_post'],
					'report' => $txt['sfs_verification_options_report'],
					'search' => $txt['sfs_verification_options_search'],
				), 'multiple' => true),			
				array('text', 'sfs_verification_options_extra', 'subtext' => $txt['sfs_verification_options_extra_subtext']),

			'',
				array('select', 'sfs_verification_options_members', array(
					'post' => $txt['sfs_verification_options_post'],
					'search' => $txt['sfs_verification_options_search'],
				), 'multiple' => true),
				array('text', 'sfs_verification_options_membersextra', 'subtext' => $txt['sfs_verification_options_extra_subtext']),
				array('int', 'sfs_verification_options_members_post_threshold'),
		);

		if ($return_config)
			return $config_vars;

		// Saving?
		if (isset($_GET['save']))
		{
			// Turn the defaults off.
			$this->unloadDefaults();
			checkSession();

			saveDBSettings($config_vars);

			writeLog();
			redirectexit($this->adminPageURL);
		}

		$context['post_url'] = $this->adminPageURL . ';save';

		prepareDBSettingContext($config_vars);

		return;
	}

	/*
		In SMF 2.1 we do this hook.
		@hook: SMF2.0:
		@hook: SMF2.1: integrate_manage_logs
		@CalledIn: SMF 2.1
	*/
	public static function hook_manage_logs(&$log_functions)
	{
		global $smcFunc;

		$log_functions['sfslog'] = array('StopForumSpam.php', 'startupLogs');

		$context[$context['admin_menu_name']]['tab_data']['tabs']['sfslog'] = array(
			'description' => $txt['sfs_admin_logs'],
		);

		return;
	}

	/*
		Show the logs as called by SMF from either hook_manage_logs (SMF 2.1) or setupModifyModifications (SMF 2.0)
	*/
	public static function startupLogs($return_config = false)
	{
		global $smcFunc;

		// No Configs.
		if ($return_config)
			return array();

		return $smcFunc['classSFS']->loadLogs();
	}

	/*
		Actually load up logs
	*/
	public function loadLogs($return_config = false)
	{
		global $context, $txt, $smcFunc, $sourcedir;

		// No Configs.
		if ($return_config)
			return array();

		loadLanguage('Modlog');

		$context['url_start'] = $this->adminLogURL;
		$context['page_title'] = $txt['sfs_admin_logs'];
		$context['can_delete'] = allowedTo('admin_forum');

		// Remove all..
		if (isset($_POST['removeall']) && $context['can_delete'])
			$this->removeAllLogs();
		elseif (!empty($_POST['remove']) && isset($_POST['delete']) && $context['can_delete'])
			$this->removeLogs(array_unique($_POST['delete']));

		$sort_types = array(
			'id_type' =>'l.id_type',
			'log_time' => 'l.log_time',
			'member' => 'mem.id_member',
			'username' => 'l.username',
			'email' => 'l.email',
			'ip' => 'l.ip',
			'ip2' => 'l.ip2',
		);

		$context['order'] = isset($_REQUEST['sort']) && isset($sort_types[$_REQUEST['sort']]) ? $_REQUEST['sort'] : 'time';

		// Handle searches.
		$this->handleLogSearch();

		require_once($sourcedir . '/Subs-List.php');

		$listOptions = array(
			'id' => 'sfslog_list',
			'title' => $txt['sfs_admin_logs'],
			'width' => '100%',
			'items_per_page' => '50',
			'no_items_label' => $txt['sfs_log_no_entries_found'],
			'base_href' => $context['url_start'] . (!empty($context['search_params']) ? ';params=' . $context['search_params'] : ''),
			'default_sort_col' => 'time',
			'get_items' => array(
				'function' => array($this, 'getSFSLogEntries'),
				'params' => array(
					(!empty($this->search_params['string']) ? ' INSTR({raw:sql_type}, {string:search_string})' : ''),
					array('sql_type' => $this->search_params_column, 'search_string' => $this->search_params['string']),
				),
			),
			'get_count' => array(
				'function' => array($this, 'getSFSLogEntriesCount'),
				'params' => array(
					(!empty($this->search_params['string']) ? ' INSTR({raw:sql_type}, {string:search_string})' : ''),
					array('sql_type' => $this->search_params_column, 'search_string' => $this->search_params['string']),
				),
			),
			// This assumes we are viewing by user.
			'columns' => array(
				'type' => array(
					'header' => array(
						'value' => $txt['sfs_log_header_type'],
						'class' => 'lefttext',
					),
					'data' => array(
						'db' => 'type',
						'class' => 'smalltext',
					),
					'sort' => array(
					),
				),
				'time' => array(
					'header' => array(
						'value' => $txt['sfs_log_header_time'],
						'class' => 'lefttext',
					),
					'data' => array(
						'db' => 'time',
						'class' => 'smalltext',
					),
					'sort' => array(
						'default' => 'l.log_time DESC',
						'reverse' => 'l.log_time',
					),
				),
				'member' => array(
					'header' => array(
						'value' => $txt['sfs_log_header_member'],
						'class' => 'lefttext',
					),
					'data' => array(
						'db' => 'member_link',
						'class' => 'smalltext',
					),
					'sort' => array(
						'default' => 'mem.id_member',
						'reverse' => 'mem.id_member DESC',
					),
				),
				'username' => array(
					'header' => array(
						'value' => $txt['sfs_log_header_username'],
						'class' => 'lefttext',
					),
					'data' => array(
						'db' => 'username',
						'class' => 'smalltext',
					),
					'sort' => array(
						'default' => 'l.username',
						'reverse' => 'l.username DESC',
					),
				),
				'email' => array(
					'header' => array(
						'value' => $txt['sfs_log_header_email'],
						'class' => 'lefttext',
					),
					'data' => array(
						'db' => 'email',
						'class' => 'smalltext',
					),
					'sort' => array(
						'default' => 'l.email',
						'reverse' => 'l.email DESC',
					),
				),
				'ip' => array(
					'header' => array(
						'value' => $txt['sfs_log_header_ip'],
						'class' => 'lefttext',
					),
					'data' => array(
						'db' => 'ip',
						'class' => 'smalltext',
					),
					'sort' => array(
						'default' => 'l.ip',
						'reverse' => 'l.ip DESC',
					),
				),
				'ip2' => array(
					'header' => array(
						'value' => $txt['sfs_log_header_ip2'],
						'class' => 'lefttext',
					),
					'data' => array(
						'db' => 'ip2',
						'class' => 'smalltext',
					),
					'sort' => array(
						'default' => 'l.ip2',
						'reverse' => 'l.ip2 DESC',
					),
				),
				'checks' => array(
					'header' => array(
						'value' => $txt['sfs_log_checks'],
						'class' => 'lefttext',
					),
					'data' => array(
						'db' => 'checks',
						'class' => 'smalltext',
					),
					'sort' => array(),
				),
				'result' => array(
					'header' => array(
						'value' => $txt['sfs_log_result'],
						'class' => 'lefttext',
					),
					'data' => array(
						'db' => 'result',
						'class' => 'smalltext',
					),
					'sort' => array(),
				),
				'delete' => array(
					'header' => array(
						'value' => '<input type="checkbox" name="all" class="input_check" onclick="invertAll(this, this.form);" />',
					),
					'data' => array(
						'function' => create_function('$entry', '
							return \'<input type="checkbox" class="input_check" name="delete[]" value="\' . $entry[\'id\'] . \'"\' . ($entry[\'editable\'] ? \'\' : \' disabled="disabled"\') . \' />\';
						'),
						'style' => 'text-align: center;',
					),
				),
			),
			'form' => array(
				'href' => $context['url_start'],
				'include_sort' => true,
				'include_start' => true,
				'hidden_fields' => array(
					$context['session_var'] => $context['session_id'],
					'params' => $context['search_params']
				),
			),
			'additional_rows' => array(
				array(
					'position' => 'below_table_data',
					'value' => '
						' . $txt['sfs_log_search'] . ' (' . $context['search']['label'] . '):
						<input type="text" name="search" size="18" value="' . $smcFunc['htmlspecialchars']($context['search']['string']) . '" class="input_text" /> <input type="submit" name="is_search" value="' . $txt['modlog_go'] . '" class="button_submit" />
						' . ($context['can_delete'] ? ' |
							<input type="submit" name="remove" value="' . $txt['modlog_remove'] . '" class="button_submit" />
							<input type="submit" name="removeall" value="' . $txt['modlog_removeall'] . '" class="button_submit" />' : ''),
				),
			),
		);

		// Create the watched user list.
		createList($listOptions);

		$context['sub_template'] = 'show_list';
		$context['default_list'] = 'sfslog_list';
	}

	/*
		Get the Log entries
	*/
	public function getSFSLogEntries($start, $items_per_page, $sort, $query_string = '', $query_params = array())
	{
		global $context, $smcFunc, $txt;

		$result = $smcFunc['db_query']('', '
			SELECT
				l.id_sfs,
				l.id_type,
				l.log_time,
				l.id_member,
				l.username,
				l.email,
				l.ip,
				l.ip2,
				l.checks,
				l.result,
				mem.real_name,
				mg.group_name
			FROM {db_prefix}log_sfs AS l
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = l.id_member)
				LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = CASE WHEN mem.id_group = {int:reg_group_id} THEN mem.id_post_group ELSE mem.id_group END)
				WHERE id_type IS NOT NULL'
				. (!empty($query_string) ? '
					AND ' . $query_string : '') . '
			ORDER BY ' . $sort . '
			LIMIT ' . $start . ', ' . $items_per_page,
			array_merge($query_params, array(
				'reg_group_id' => 0,
			))
		);

		while ($row = $smcFunc['db_fetch_assoc']($result))
		{
			$entries[$row['id_sfs']] = array(
				'id' => $row['id_sfs'],
				'type' => $txt['sfs_log_types_' . $row['id_type']],
				'time' => timeformat($row['log_time']),
				'timestamp' => forum_time(true, $row['log_time']),
				'member_link' => $row['id_member'] ? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>' : (empty($row['real_name']) ? ($txt['guest'] . (!empty($row['extra']['member_acted']) ? ' (' . $row['extra']['member_acted'] . ')' : '')) : $row['real_name']),
				'username' => $row['username'],
				'email' => $row['email'],
				'ip' => '<a href="' . sprintf($this->urlSFSipCheck, $row['ip']) . '">' . $row['ip'] . '</a>',
				'ip2' => '<a href="' . sprintf($this->urlSFSipCheck, $row['ip2']) . '">' . $row['ip2'] . '</a>',
				'editable' => true, //time() > $row['log_time'] + $this->hoursDisabled * 3600,
				'checks_raw' => $row['checks'],
				'result_raw' => $row['result'],
			);

			$checksDecoded = $this->decodeJSON($row['checks']);

			// Checks, username
			if ($row['id_type'] == 1)
				$entries[$row['id_sfs']]['checks'] = '<a href="' . sprintf($this->urlSFSsearch, $checksDecoded['value']) . '">' . $checksDecoded['value'] . '</a>';
			elseif ($row['id_type'] == 2)
				$entries[$row['id_sfs']]['checks'] = '<a href="' . sprintf($this->urlSFSsearch, $checksDecoded['value']) . '">' . $checksDecoded['value'] . '</a>';
			elseif ($row['id_type'] == 3)
				$entries[$row['id_sfs']]['checks'] = '<a href="' . sprintf($this->urlSFSsearch, $checksDecoded['value']) . '">' . $checksDecoded['value'] . '</a>';
			else
			{
				$entries[$row['id_sfs']]['checks'] = '';

				foreach ($checksDecoded as $key => $vkey)
					foreach ($vkey as $key => $value)
						$entries[$row['id_sfs']]['checks'] .= ucfirst($key) . ':' . $value . '<br>';					
			}

			// $results
			if (strpos($row['result'], ',') !== false)
			{
				list($resultType, $resultMatch) = explode(',', $row['result']);
				$entries[$row['id_sfs']]['result'] = 'Matched on ' . $resultType . ' [' . $resultMatch . ']';
			}
			else
				$entries[$row['id_sfs']]['result'] = $row['result'];
			
		}
		$smcFunc['db_free_result']($result);

		return $entries;
	}

	/*
		Get the Counter Log entries
	*/
	public function getSFSLogEntriesCount($query_string = '', $query_params = array(), $log_type = 1)
	{
		global $smcFunc, $user_info;

		$result = $smcFunc['db_query']('', '
			SELECT COUNT(*)
			FROM {db_prefix}log_sfs AS l
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = l.id_member)
				LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = CASE WHEN mem.id_group = {int:reg_group_id} THEN mem.id_post_group ELSE mem.id_group END)
				WHERE id_type IS NOT NULL'
				. (!empty($query_string) ? '
					AND ' . $query_string : ''),
			array_merge($query_params, array(
				'reg_group_id' => 0,
			))
		);
		list ($entry_count) = $smcFunc['db_fetch_row']($result);
		$smcFunc['db_free_result']($result);

		return $entry_count;
	}

	/*
		Remove all logs, except for those 24 horus or newer.
	*/
	private function removeAllLogs()
	{
		global $smcFunc;

		checkSession();

		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_sfs
			WHERE log_time < {int:twenty_four_hours_wait}',
			array(
				'twenty_four_hours_wait' => time() - $this->hoursDisabled * 3600,
			)
		);
	}

	/*
		Remove specific logs, except for those 24 horus or newer.
	*/
	private function removeLogs($entries)
	{
		global $smcFunc;

		checkSession();

		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_sfs
			WHERE id_sfs IN ({array_string:delete_actions})
				AND log_time < {int:twenty_four_hours_wait}',
			array(
				'twenty_four_hours_wait' => time() - $this->hoursDisabled * 3600,
				'delete_actions' => $entries,
			)
		);
	}

	/*
		Handle registration events
		@CalledIn: SMF 2.0, SMF 2.1
		@calledAt: action=signup, action=admin;area=regcenter;sa=register
		@hook: SMF2.0: integrate_register
		@hook: SMF2.1: integrate_register
	*/
	public static function hook_register(&$regOptions, &$theme_vars)
	{
		global $smcFunc;
		return $smcFunc['classSFS']->checkRegisterRequest($regOptions, $theme_vars);
	}

	/*
		Something is attempting to register, we should check them out.
	*/
	public function checkRegisterRequest(&$regOptions, &$theme_vars)
	{
		// Admins are not spammers.. usually.
		if ($regOptions['interface'] == 'admin')
			return true;
		// Get our language in here.
		$this->loadLanguage();

		// Pass everything and let us handle what options we pass on.  We pass the register_vars as these are what we have cleaned up.
		$this->sfsCheck(array(
			array('username' => $regOptions['register_vars']['member_name']),
			array('email' => $regOptions['register_vars']['email_address']),
			array('ip' => $regOptions['register_vars']['member_ip']),
			array('ip' => $regOptions['register_vars']['member_ip2']),
		), 'register');
	}

	/*
		Handle verification events, except register.
		@CalledIn: SMF 2.1
		@hook: SMF2.1: integrate_create_control_verification_test
	*/
	public static function hook_create_control_verification_test($thisVerification, &$verification_errors)
	{
		global $smcFunc;
		$smcFunc['classSFS']->checkVerificationTest($thisVerification, $verification_errors);
	}
	
	/*
		Something is attempting to post, we should check them out.
		SMF 2.0 calls this directly as it doesnn't have a hook.
	*/
	public function checkVerificationTest($thisVerification, &$verification_errors)
	{
		global $user_info;

		// Registration is skipped as we process that differently.
		if ($thisVerification['id'] == 'register')
			return true;

		// Get our language in here.
		$this->loadLanguage();

		$options = $this->getVerificationOptions();

		// Posting?
		if ($thisVerification['id'] == 'post' && in_array('post', $options))
		{
			// Guests!
			if ($user_info['is_guest'])
			{
				$guestname = !isset($_POST['guestname']) ? '' : trim($_POST['guestname']);
				$email = !isset($_POST['email']) ? '' : trim($_POST['email']);

				return $this->sfsCheck(array(
					array('username' => $guestname),
					array('email' => $email),
					array('ip' => $user_info['ip']),
					array('ip' => $user_info['ip2']),
				), 'post');
				
			}
			// Members and they don't have enough posts?
			elseif (empty($user_info['posts']) || $user_info['posts'] < $modSettings['sfs_verification_options_members_post_threshold'])
				return $this->sfsCheck(array(
					array('username' => $user_info['username']),
					array('email' => $user_info['email']),
					array('ip' => $user_info['ip']),
					array('ip' => $user_info['ip2']),
				), 'post');
			else
				return true;
		}
		// reporting topics is only for guests.
		elseif ($thisVerification['id'] == 'report' && in_array('report', $options))
		{
			$email = !isset($_POST['email']) ? '' : trim($_POST['email']);

			return $this->sfsCheck(array(
				array('email' => $email),
				array('ip' => $user_info['ip']),
				array('ip' => $user_info['ip2']),
			), 'post');
		}
		// We should avoid this on searches, as we can only send ips.
		elseif ($thisVerification['id'] == 'search' && in_array('search', $options) && ($user_info['is_guest'] || empty($user_info['posts']) || $user_info['posts'] < $modSettings['sfs_verification_options_members_post_threshold']))
		{
			return $this->sfsCheck(array(
				array('ip' => $user_info['ip']),
				array('ip' => $user_info['ip2']),
			), 'search');
		}

		// Others areas.  We have to play a guessing game here.
		foreach ($this->extraVerificationOptions as $option)
		{
			// Not a match.
			if ($thisVerification['id'] != $option)
				continue;

			// Always try to send off IPs.
			$checks = array(
				array('ip' => $user_info['ip']),
				array('ip' => $user_info['ip2']),
			);

			// Can we find a username?
			$possibleUserNames = array('username', 'user_name', 'user', 'name', 'realname');
			foreach ($possibleUserNames as $searchKey)
				if (!empty($_POST[$searchKey]))
				{
					$checks[] = array('username', $_POST[$searchKey]);
					break;
				}

			// Can we find a email?
			$possibleUserNames = array('email', 'emailaddress', 'email_address');
			foreach ($possibleUserNames as $searchKey)
				if (!empty($_POST[$searchKey]))
				{
					$checks[] = array('email', $_POST[$searchKey]);
					break;
				}

			return $this->sfsCheck($checks, $option);
		}
die;
	}

	/*
		Check data against the SFS database
	*/
	public function sfsCheck($checks, $area = null)
	{
		global $sourcedir, $smcFunc, $context, $modSettings, $txt;

		$requestURL = $this->buildServerURL();

		// Lets build our data set, always send it as a bulk.
		$singleCheckFound = false;
		foreach ($checks as $chk)
		{
			foreach ($chk as $type => $value)
			{
				// Hold up, we are not processing this check.
				if (
					($type == 'email' && empty($modSettings['sfs_emailcheck'])) ||
					($type == 'username' && empty($modSettings['sfs_usernamecheck'])) ||
					($type == 'ip' && empty($modSettings['sfs_ipcheck']))
				)
					continue;

				// No value? Can't do this.
				if (empty($value))
					continue;

				// Emails and usernames must be UTF-8, Only a issue with SMF 2.0.
				if (!$context['utf8'] && ($type == 'email' || $type == 'username'))
					$requestURL .= '&' . $type . '[]=' . iconv($context['character_set'], 'UTF-8//IGNORE', $value);
				else
					$requestURL .= '&' . $type . '[]=' . urlencode($value);

				$singleCheckFound = true;
			}
		}

		// No checks found? Can't do this.
		if (empty($singleCheckFound))
		{
			$this->logAllStats('error', $checks, 'error');
			log_error($txt['sfs_request_failure_nodata'] . ':' . $requestURL, 'critical');
			return true;
		}

		// SMF 2.0 has the fetch_web_data in the Subs-Packages, 2.1 it is in Subs.php.
		if ($this->versionCheck('2.0', 'smf'))
			require_once($sourcedir . '/Subs-Package.php');
		
		// Now we have a URL, lets go get it.
		$response = $this->decodeJSON(fetch_web_data($requestURL));

		// No data received, log it and let them through.
		if (empty($response))
		{
			$this->logAllStats('error', $checks, 'failure');
			log_error($txt['sfs_request_failure'] . ':' . $requestURL, 'critical');
			return true;
		}

		$requestBlocked = false;

		// Handle IPs only if we are supposed to, this is just a double check.
		if (!empty($modSettings['sfs_ipcheck']) && !empty($response['ip']))
		{
			foreach ($response['ip'] as $check)
			{
				// !!! TODO: Frequency 255 is a blacklist, maybe add them to a generic ban list?
				if (!empty($check['appears']))
				{
					$this->logBlockedStats('ip', $check);
					$requestBlocked = 'ip,' . $smcFunc['htmlspecialchars']($check['value']);
					break;
				}
			}
		}

		// If we didn't match a IP, handle Usernames only if we are supposed to, this is just a double check.
		if (empty($requestBlocked) && !empty($modSettings['sfs_usernamecheck']) && !empty($response['username']))
		{
			foreach ($response['username'] as $check)
			{
				// !!! TODO: Expose confidence as a threshold?
				// Combine with $area we could also require admin approval above thresholds on things like register.
				// !!! TODO: Expose lastseen as a threshold?
				if (!empty($check['appears']))
				{
					$this->logBlockedStats('username', $check);
					$requestBlocked = 'username,' . $smcFunc['htmlspecialchars']($check['value']);
					break;
				}
			}
		}

		// If we didn't match a IP or username, handle Emails only if we are supposed to, this is just a double check.
		if (empty($requestBlocked) && !empty($modSettings['sfs_emailcheck']) && !empty($response['email']))
		{
			foreach ($response['email'] as $check)
			{
				if (!empty($check['appears']))
				{
					$this->logBlockedStats('email', $check);
					$requestBlocked = 'email,' . $smcFunc['htmlspecialchars']($check['value']);
					break;
				}
			}
		}


		// Log all the stats?  Debug mode here.
		if (!empty($modSettings['sfs_log_debug']))
			$this->logAllStats('all', $checks, $requestBlocked);

		// At this point, we have checked everything, do what needs to be done for our good person.
		if (!$requestBlocked)
			return true;

		// You are a bad spammer, don't tell them what was blocked.
		$this->loadLanguage();
		fatal_error($txt['sfs_request_blocked']);
	}

	/*
		Log the blocked request for later
	*/
	private function logBlockedStats($type, $check)
	{
		global $smcFunc, $user_info;

		switch($type)
		{
			case 'username':
				$blockType = 1;
				break;
			case 'email':
				$blockType = 2;
				break;
			case 'ip':
				$blockType = 3;
				break;
			default:
				$blockType = 99;
				break;
		}

		$smcFunc['db_insert']('',
			'{db_prefix}log_sfs',
			array('id_type' => 'int', 'log_time' => 'int', 'id_member' => 'int', 'username' => 'string', 'email' => 'string', 'ip' => 'string', 'ip2' => 'string', 'checks' => 'string', 'result' => 'string'),
			array(
				$blockType, // Blocked request
				time(),
				$user_info['id'],
				$type == 'username' ? $check['value'] : '',
				$type == 'email' ? $check['value'] : '',
				$type == 'ip' ? $check['value'] : $user_info['ip'],
				$user_info['ip2'],
				json_encode($check),
				'Blocked'
				),
			array('id_sfs', 'id_type')
		);
	}

	/*
		Log all the data for later.
	*/
	private function logAllStats($type, $checks, $requestBlocked)
	{
		global $smcFunc, $user_info;

		$smcFunc['db_insert']('',
			'{db_prefix}log_sfs',
			array('id_type' => 'int', 'log_time' => 'int', 'id_member' => 'int', 'username' => 'string', 'email' => 'string', 'ip' => 'string', 'ip2' => 'string', 'checks' => 'string', 'result' => 'string'),
			array(
				0, // Debug type.
				time(),
				$user_info['id'],
				'', // Username
				'', // email
				$user_info['ip'],
				$user_info['ip2'],
				json_encode($checks),
				$requestBlocked,
				),
			array('id_sfs', 'id_type')
		);
	}

	/*
		Decode JSON data.
		If we have $smcFunc['json_decode'] we use it as it can handle errors.
		Otherwise we do some basic stuff.
	*/
	private function decodeJSON($requestData)
	{
		global $smcFunc;

		// Do we have $smcFunc?  It handles errors and logs them as needed.
		if (isset($smcFunc['json_decode']) && is_callable($smcFunc['json_decode']))
			return $smcFunc['json_decode']($request, true);
		// Back to the basics.
		else
		{
			$data = @json_decode($requestData, true);

			// We got a error, return nothing.
			// !!! TODO: Log this?
			if (json_last_error() !== JSON_ERROR_NONE)
				return array();
			return $data;
		}
	}

	/*
		Build the base URL for the Stop Forum Spam website
		@resource: https://www.stopforumspam.com/usage
	*/
	public function buildServerURL()
	{
		global $modSettings;
		static $url = null;

		// If we build this once, don't do it again.
		if (!empty($url))
			return $url;

		// Get our server info.
		$this_server = $this->sfsServerMapping();
		$server = $this_server[$modSettings['sfs_region']];

		// Build the base URL, we always use json responses.
		$url = 'https://' . $server['host'] . '/api?json';

		// Ignore all wildcard checks?
		if (!empty($modSettings['sfs_wildcard_email']) && !empty($modSettings['sfs_wildcard_username'])  && !empty($modSettings['sfs_wildcard_ip']))
			$url .= '&nobadall';
		// Maybe only certain wildcards are ignored?
		else
		{
			// Ignoring Wildcard Emails?
			if (!empty($modSettings['sfs_wildcard_email']))
				$url .= '&nobadusername';

			// Ignoring Wildcard Usernames?
			if (!empty($modSettings['sfs_wildcard_username']))
				$url .= '&nobademail';

			// Ignoring Wildcard IPs?
			if (!empty($modSettings['sfs_wildcard_ip']))
				$url .= '&nobadip';
		}

		// Tor handling, ignore them all.  Not recommended...
		if (!empty($modSettings['sfs_tor_check']) && $modSettings['sfs_tor_check'] == 1)
			$url .= '&notorexit';
		// Only block bad exit nodes.
		elseif (!empty($modSettings['sfs_tor_check']) && $modSettings['sfs_tor_check'] == 2)
			$url .= '&badtorexit';
		// Default handling for Tor is to block all exit nodes, nothing needed here.

		return $url;
	}

	/*
		Setup our possible SFS hosts.
		@resource: https://www.stopforumspam.com/usage
	*/
	public function sfsServerMapping($returnType = null)
	{
		global $txt;

		// Global list of servers.
		$serverList = array(
			0 => array(
				'region' => 'global',
				'label' => $txt['sfs_region_global'],
				'host' => 'api.stopforumspam.org',
			),
			1 => array(
				'region' => 'us',
				'label' => $txt['sfs_region_us'],
				'host' => 'us.stopforumspam.org',
			),
			2 => array(
				'region' => 'eu',
				'label' => $txt['sfs_region_eu'],
				'host' => 'eruope.stopforumspam.org',
			),
		);

		// Configs only need the labels.
		if ($returnType == 'config')
		{
			$temp = array();
			foreach ($serverList as $id_server => $server)
				$temp[$id_server] = $server['label'];
			return $temp;
		}

		return $serverList;
	}

	/*
		Verification Options
	*/
	private function getVerificationOptions()
	{
		global $user_info, $modSettings;

		$optionsKey = $user_info['is_guest'] ? 'sfs_verification_options' : 'sfs_verification_options_members';
		$optionsKeyExtra = $user_info['is_guest'] ? 'sfs_verification_options_extra' : 'sfs_verification_options_membersextra';

		// Standard options.
		if ($this->versionCheck('2.0', 'smf') && !empty($modSettings[$optionsKey]))
			$options = safe_unserialize($modSettings[$optionsKey]);
		elseif (!empty($modSettings[$optionsKey]))
			$options = $this->decodeJSON($modSettings[$optionsKey]);
		else
			$options = array();

		// Extras.
		if (!empty($modSettings[$optionsKeyExtra])) 
		{
			$this->extraVerificationOptions = explode(',', $modSettings[$optionsKeyExtra]);
			$options = array_merge($options, $this->extraVerificationOptions);
		}

		return $options;
	}

	/*
		Defaults for SFS
		We don't specify all of them here, just what we need to make development easier.
	*/
	public function loadDefaults($undo = false)
	{
		global $modSettings;

		// Specify the defaults, but only non empties.
		$defaultSettings = array(
			'sfs_enabled' => 1,
			'sfs_emailcheck' => 1,
			'sfs_region' => 0,
			'sfs_verification_options_members_post_threshold' => 5,
		);

		// SMF 2.0 is serialized, SMF 2.1 is json.
		if ($this->versionCheck('2.0', 'smf'))
			$defaultSettings['sfs_verification_options'] = serialize(array('post'));
		else
			$defaultSettings['sfs_verification_options'] = json_encode(array('post'));
		
		// We undoing this? Maybe a save?
		if ($undo)
		{
			foreach ($this->changedSettings as $key => $value)
				unset($modSettings[$key], $this->changedSettings[$key]);
			return true;
		}

		// Enabled settings.
		foreach ($defaultSettings as $key => $value)
			if (!isset($modSettings[$key]))
			{
				$this->changedSettings[$key] = null;
				$modSettings[$key] = $value;
			}
	}

	/*
		Just a wrapper to tell defaults to undo.
	*/
	public function unloadDefaults()
	{
		return $this->loadDefaults(true);
	}

	/*
		Global function to check version and software matches.
		@CalledIn: SMF 2.0, SMF 2.1
	*/
	public function versionCheck($version, $software = 'smf')
	{
		// We can't do this if the software doesn't match.
		if ($software !== $this->softwareName)
			return false;

		// Allow multiple versions to pass.
		$version = (array) $version;
		foreach ($version as $v)
			if ($v == $this->softwareVersion)
				return true;

		// No match? False.
		return false;
	}

	/*
		Global loadLanguage function, should we want to split it out or need to load it differently
		@CalledIn: SMF 2.0, SMF 2.1
	*/
	public function loadLanguage()
	{
		// Load the langauge.
		loadLanguage('StopForumSpam');
	}

	/*
		Handle searching for logs
	*/
	private function handleLogSearch()
	{
		global $context, $txt;

		if (!empty($_REQUEST['params']) && empty($_REQUEST['is_search']))
		{
			$this->search_params = base64_decode(strtr($_REQUEST['params'], array(' ' => '+')));
			$this->search_params = $this->JSONDecode($this->search_params);
		}

		$searchTypes = array(
			'member' => array('sql' => 'mem.real_name', 'label' => $txt['sfs_log_search_member']),
			'username' => array('sql' => 'l.username', 'label' => $txt['sfs_log_search_username']),
			'email' => array('sql' => 'l.email', 'label' => $txt['sfs_log_search_email']),
			'ip' => array('sql' => 'lm.ip', 'label' => $txt['sfs_log_search_ip']),
			'ip2' => array('sql' => 'lm.ip2', 'label' => $txt['sfs_log_search_ip2'])
		);

		if (!isset($this->search_params['string']) || (!empty($_REQUEST['search']) && $this->search_params['string'] != $_REQUEST['search']))
			$this->search_params_string = empty($_REQUEST['search']) ? '' : $_REQUEST['search'];
		else
			$this->search_params_string = $this->search_params['string'];

		if (isset($_REQUEST['search_type']) || empty($this->search_params['type']) || !isset($searchTypes[$this->search_params['type']]))
			$this->search_params_type = isset($_REQUEST['search_type']) && isset($searchTypes[$_REQUEST['search_type']]) ? $_REQUEST['search_type'] : (isset($searchTypes[$context['order']]) ? $context['order'] : 'member');
		else
			$this->search_params_type = $this->search_params['type'];

		$this->search_params_column = $searchTypes[$this->search_params_type]['sql'];
		$this->search_params = array(
			'string' => $this->search_params_string,
			'type' => $this->search_params_type,
		);

		// Setup the search context.
		$context['search_params'] = empty($this->search_params['string']) ? '' : base64_encode(json_encode($this->search_params));
		$context['search'] = array(
			'string' => $this->search_params['string'],
			'type' => $this->search_params['type'],
			'label' => $searchTypes[$this->search_params_type]['label'],
		);

	}
}