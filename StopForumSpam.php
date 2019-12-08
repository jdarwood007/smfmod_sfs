<?php

/**
 * The Main class for Stop Forum Spam
 * @package StopForumSpam
 * @author SleePy <sleepy @ simplemachines (dot) org>
 * @copyright 2019
 * @license 3-Clause BSD https://opensource.org/licenses/BSD-3-Clause
 * @version 1.0.1
 */
class SFS
{
	/**
	 * @var string URLS we need to SFS for UI presentation.
	 */
	private $urlSFSipCheck = 'https://www.stopforumspam.com/ipcheck/%1$s';
	private $urlSFSsearch = 'https://www.stopforumspam.com/search/%1$s';


	/**
	 * @var string Name of the software and its version.  This is so we can branch out from the same base.
	 */
	private $softwareName = 'smf';
	private $softwareVersion = '2.1';

	/**
	 * @var string The URL for the admin page.
	 */
	private $adminPageURL = null;

	/**
	 * @var array Our settings information used on saving/changing settings.
	 */
	private $changedSettings = array();
	private $extraVerificationOptions = array();

	/**
	 * @var mixed Search area handling.
	 */
	private $search_params = array();
	private $search_params_column = '';

	/**
	 * @var int How long we disable removing logs.
	 */
	private $hoursDisabled = 24;

	/**
	 * Simple setup for the class to be used later correctly.
	 * This simply loads the class into $smcFunc and we can grab this anywhere else later.
	 *
	 * @api
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.0
	 * @since 1.0
	 * @uses integrate_pre_load - Hook SMF2.0
	 * @uses integrate_pre_load - Hook SMF2.1
	 * @return void No return is generated
	 */
	public static function hook_pre_load(): void
	{
		global $smcFunc;
		
		$smcFunc['classSFS'] = new SFS();
	}

	/**
	 * Build the class, figure out what software/version we have.
	 * Loads up the defaults.
	 *
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.0
	 * @since 1.0
	 * @return void No return is generated
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

	/**
	 * Creates the hook to the class for the admin areas.
	 *
	 * @param array $admin_areas A associate array from the software with all valid admin areas.
	 *
	 * @api
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @see SFS::setupAdminAreas()
	 * @version 1.0
	 * @since 1.0
	 * @uses integrate__admin_areas - Hook SMF2.0
	 * @uses integrate__admin_areas - Hook SMF2.1
	 * @return void No return is generated
	 */
	public static function hook_admin_areas(array &$admin_areas)
	{
		global $smcFunc;
		return $smcFunc['classSFS']->setupAdminAreas($admin_areas);
	}

	/**
	 * Startup the Admin Panels Additions.
	 * Where things appear are based on what software/version you have.
	 *
	 * @param array $admin_areas A associate array from the software with all valid admin areas.
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.0
	 * @since 1.0
	 * @uses integrate__admin_areas - Hook SMF2.0
	 * @uses integrate__admin_areas - Hook SMF2.1
	 * @return void No return is generated
	 */
	private function setupAdminAreas(array &$admin_areas): void
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
	}

	/**
	 * Setup the Modification's setup page.
	 * For some versions, we put the logs into the modifications sections, its easier.
	 *
	 * @param array $subActions A associate array from the software with all valid modification sections.
	 *
	 * @api
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @see SFS::setupModifyModifications()
	 * @version 1.0
	 * @since 1.0
	 * @uses integrate_modify_modifications - Hook SMF2.0
	 * @uses integrate_modify_modifications - Hook SMF2.1
	 * @return void No return is generated
	 */
	public static function hook_modify_modifications(array &$subActions)
	{
		global $smcFunc;
		return $smcFunc['classSFS']->setupModifyModifications($subActions);
	}

	/**
	 * Setup the Modifications section links.
	 * For some versions we add the logs here as well.
	 *
	 * @param array $subActions A associate array from the software with all valid modification sections.
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.0
	 * @since 1.0
	 * @uses integrate_modify_modifications - Hook SMF2.0
	 * @uses integrate_modify_modifications - Hook SMF2.1
	 * @return void No return is generated
	 */
	private function setupModifyModifications(array &$subActions): void
	{
		$subActions['sfs'] = 'SFS::startupAdminConfiguration';

		// Only in SMF 2.0 do we drop logs here.
		if ($this->versionCheck('2.0', 'smf'))
			$subActions['sfslog'] = 'SFS::startupLogs';
	}

	/**
	 * The configuration caller.
	 *
	 * @param bool $return_config If true, returns the configuration options for searches.
	 *
	 * @api
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @see SFS::setupSFSConfiguration
	 * @version 1.0
	 * @since 1.0
	 * @uses integrate_modify_modifications - Hook SMF2.0
	 * @uses integrate_modify_modifications - Hook SMF2.1
	 * @return void No return is generated
	 */
	public static function startupAdminConfiguration(bool $return_config = false)
	{
		global $smcFunc;
		return $smcFunc['classSFS']->setupSFSConfiguration($return_config);
	}

	/**
	 * The actual settings page.
	 *
	 * @param bool $return_config If true, returns the configuration options for searches.
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.0
	 * @since 1.0
	 * @uses integrate_modify_modifications - Hook SMF2.0
	 * @uses integrate_modify_modifications - Hook SMF2.1
	 * @return void No return is generated
	 */
	private function setupSFSConfiguration(bool $return_config = false): array
	{
		global $txt, $scripturl, $context, $settings, $sc, $modSettings;

		$config_vars = array(
				array('title', 'sfsgentitle', 'label' => $txt['sfs_general_title']),

				array('check', 'sfs_enabled'),
				array('int', 'sfs_expire'),
			'',
				array('check', 'sfs_emailcheck'),
			'',
				array('check', 'sfs_usernamecheck'),
				array('int', 'sfs_username_confidence'),
			'',
				array('check', 'sfs_ipcheck'),
				array('check', 'sfs_ipcheck_autoban'),
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
			'',
				array('check', 'sfs_log_debug'),
		);

		if ($return_config)
			return $config_vars;

		// Saving?
		if (isset($_GET['save']))
		{
			// Turn the defaults off.
			$this->unloadDefaults();
			checkSession();

			// If we are automatically banning IPs, make sure we have a ban group.
			if (isset($_POST['sfs_ipcheck_autoban']) && empty($modSettings['sfs_ipcheck_autoban_group']))
				$this->createBanGroup(true);

			saveDBSettings($config_vars);

			writeLog();
			redirectexit($this->adminPageURL);
		}

		$context['post_url'] = $this->adminPageURL . ';save';

		prepareDBSettingContext($config_vars);

		return array();
	}

	/**
	 * In some software/versions, we can hook into the logs section.
	 * In others we hook into the modifications settings.
	 *
	 * @param bool $return_config If true, returns empty array to prevent breaking old SMF installs.
	 *
	 * @api
	 * @CalledIn SMF 2.1
	 * @See SFS::startupLogs
	 * @version 1.0
	 * @since 1.0
	 * @uses integrate_manage_logs - Hook SMF2.1
	 * @return void No return is generated
	 */
	public static function hook_manage_logs(array &$log_functions):void
	{
		global $smcFunc;

		// Add our logs sub action.
		$log_functions['sfslog'] = array('StopForumSpam.php', 'startupLogs');

		// Add it to the menu as well.
		$context[$context['admin_menu_name']]['tab_data']['tabs']['sfslog'] = array(
			'description' => $txt['sfs_admin_logs'],
		);
	}

	/**
	 * Log startup caller.
	 * This has a $return_config just for simply complying with properly for searching the admin panel.
	 *
	 * @param bool $return_config If true, returns empty array to prevent breaking old SMF installs.
	 *
	 * @api
	 * @CalledIn SMF 2.1
	 * @See SFS::loadLogs
	 * @version 1.0
	 * @since 1.0
	 * @uses hook_manage_logs - Hook SMF2.1
	 * @uses setupModifyModifications - Injected SMF2.0
	 * @return void No return is generated
	 */
	public static function startupLogs(bool $return_config = false): array
	{
		global $smcFunc;

		return $smcFunc['classSFS']->loadLogs();
	}

	/**
	 * Actually show the logs.
	 * This has a $return_config just for simply complying with properly for searching the admin panel.
	 *
	 * @param bool $return_config If true, returns empty array to prevent breaking old SMF installs.
	 *
	 * @api
	 * @CalledIn SMF2.0, SMF 2.1
	 * @See SFS::getSFSLogEntries
	 * @See SFS::getSFSLogEntriesCount
	 * @version 1.0
	 * @since 1.0
	 * @uses hook_manage_logs - Hook SMF2.1
	 * @uses setupModifyModifications - Injected SMF2.0
	 * @return void No return is generated
	 */
	public function loadLogs(bool $return_config = false): array
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
			'url' => 'l.url',
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
				'url' => array(
					'header' => array(
						'value' => $txt['sfs_log_header_url'],
						'class' => 'lefttext',
					),
					'data' => array(
						'db' => 'url',
						'class' => 'smalltext',
						'style' => 'word-break: break-all;',
					),
					'sort' => array(
						'default' => 'l.url DESC',
						'reverse' => 'l.url',
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

		return array();
	}

	/**
	 * Get the log data and returns it ready to go for GenericList handling.
	 *
	 * @param int $start The index for where we offset or start at for the list
	 * @param int $items_per_page How many items we are going to show on this page.
	 * @param string $sort The column we are sorting by.
	 * @param string $query_string The search string we are using to filter log data.
	 * @param array $query_params Extra parameters for searching.
	 *
	 * @api
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @See SFS::loadLogs
	 * @version 1.0
	 * @since 1.0
	 * @uses hook_manage_logs - Hook SMF2.1
	 * @uses setupModifyModifications - Injected SMF2.0
	 * @return void No return is generated
	 */
	public function getSFSLogEntries(int $start, int $items_per_page, string $sort, string $query_string = '', array $query_params = array()): array
	{
		global $context, $smcFunc, $txt;

		// Fetch all of our logs.
		$result = $smcFunc['db_query']('', '
			SELECT
				l.id_sfs,
				l.id_type,
				l.log_time,
				l.url,
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
				'url' => preg_replace('~http(s)?://~i', 'hxxp\\1://', $row['url']),
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

			// If we know what check triggered this, link it up to be searched.
			if ($row['id_type'] == 1)
				$entries[$row['id_sfs']]['checks'] = '<a href="' . sprintf($this->urlSFSsearch, $checksDecoded['value']) . '">' . $checksDecoded['value'] . '</a>';
			elseif ($row['id_type'] == 2)
				$entries[$row['id_sfs']]['checks'] = '<a href="' . sprintf($this->urlSFSsearch, $checksDecoded['value']) . '">' . $checksDecoded['value'] . '</a>';
			elseif ($row['id_type'] == 3)
				$entries[$row['id_sfs']]['checks'] = '<a href="' . sprintf($this->urlSFSsearch, $checksDecoded['value']) . '">' . $checksDecoded['value'] . '</a>';
			// No idea what triggered it, parse it out cleanly.  Could be debug data as well.
			else
			{
				$entries[$row['id_sfs']]['checks'] = '';

				foreach ($checksDecoded as $key => $vkey)
					foreach ($vkey as $key => $value)
						$entries[$row['id_sfs']]['checks'] .= ucfirst($key) . ':' . $value . '<br>';					
			}

			// This tells us what it matched on exactly.
			if (strpos($row['result'], ',') !== false)
			{
				list($resultType, $resultMatch, $extra) = explode(',', $row['result'] . ',,,');
				$entries[$row['id_sfs']]['result'] = sprintf($txt['sfs_log_matched_on'], $resultType, $resultMatch);

				// If this was a IP ban, note it.
				if ($resultType == 'ip' && !empty($extra))
					$entries[$row['id_sfs']]['result'] .= ' ' . $txt['sfs_log_auto_banned'];			
				if ($resultType == 'username' && !empty($extra))
					$entries[$row['id_sfs']]['result'] .= ' ' . sprintf($txt['sfs_log_confidence'], $extra);			
			}
			else
				$entries[$row['id_sfs']]['result'] = $row['result'];
			
		}
		$smcFunc['db_free_result']($result);

		return $entries;
	}

	/**
	 * Get the log counts and returns it ready to go for GenericList handling.
	 *
	 * @param string $query_string The search string we are using to filter log data.
	 * @param array $query_params Extra parameters for searching.
	 *
	 * @api
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @See SFS::loadLogs
	 * @version 1.0
	 * @since 1.0
	 * @uses hook_manage_logs - Hook SMF2.1
	 * @uses setupModifyModifications - Injected SMF2.0
	 * @return void No return is generated
	 */
	public function getSFSLogEntriesCount(string $query_string = '', array $query_params = array()): int
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

		return (int) $entry_count;
	}

	/**
	 * Remove all logs, except those less than 24 hours old.
	 *
	 * @api
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @See SFS::loadLogs
	 * @version 1.0
	 * @since 1.0
	 * @return void No return is generated
	 */
	private function removeAllLogs(): void
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

	/**
	 * Remove specific logs, except those less than 24 hours old.
	 *
	 * @param array $entries A array of the ids that we want to remove.
	 *
	 * @api
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @See SFS::loadLogs
	 * @version 1.0
	 * @since 1.0
	 * @return void No return is generated
	 */
	private function removeLogs(array $entries): void
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

	/**
	 * Handle registration events.
	 *
	 * @param array $regOptions An array from the software with all the registration optins we are going to use to register.
	 * @param array $theme_vars An array from the software with all the possible theme settings we are going to use to register.
	 *
	 * @api
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @CalledAt: action=signup, action=admin;area=regcenter;sa=register
	 * @See SFS::checkRegisterRequest
	 * @version 1.0
	 * @since 1.0
	 * @uses integrate_register - Hook SMF2.1
	 * @uses integrate_register - Hook SMF2.0
	 * @return bool True is success, no other bool is expeicifcly defined yet.
	 */
	public static function hook_register(array &$regOptions, array &$theme_vars): bool
	{
		global $smcFunc;
		return $smcFunc['classSFS']->checkRegisterRequest($regOptions, $theme_vars);
	}

	/**
	 * Something is attempting to register, we should check them out.
	 *
	 * @param array $regOptions An array from the software with all the registration optins we are going to use to register.
	 * @param array $theme_vars An array from the software with all the possible theme settings we are going to use to register.
	 *
	 * @api
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @CalledAt: action=signup, action=admin;area=regcenter;sa=register
	 * @See SFS::checkRegisterRequest
	 * @version 1.0
	 * @since 1.0
	 * @uses integrate_register - Hook SMF2.1
	 * @uses integrate_register - Hook SMF2.0
	 * @return bool True is success, no other bool is expeicifcly defined yet.
	 */
	private function checkRegisterRequest(array &$regOptions, array &$theme_vars): bool
	{
		// Admins are not spammers.. usually.
		if ($regOptions['interface'] == 'admin')
			return true;

		// Get our language in here.
		$this->loadLanguage();

		// Pass everything and let us handle what options we pass on.  We pass the register_vars as these are what we have cleaned up.
		return $this->sfsCheck(array(
			array('username' => $regOptions['register_vars']['member_name']),
			array('email' => $regOptions['register_vars']['email_address']),
			array('ip' => $regOptions['register_vars']['member_ip']),
			array('ip' => $regOptions['register_vars']['member_ip2']),
		), 'register');
	}

	/**
	 * The caller for a verification test.
	 *
	 * @param array $thisVerification An array from the software with all the verification information we have.
	 * @param array $verification_errors An errors which exist from verification.
	 *
	 * @api
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @See SFS::checkVerificationTest
	 * @version 1.0
	 * @since 1.0
	 * @uses integrate_create_control_verification_test - Hook SMF2.1
	 * @return bool True is success, no other bool is expeicifcly defined yet.
	 */
	public static function hook_create_control_verification_test(array $thisVerification, array &$verification_errors): bool
	{
		global $smcFunc;
		return $smcFunc['classSFS']->checkVerificationTest($thisVerification, $verification_errors);
	}

	/**
	 * The caller for a verification test.
	 * SMF 2.0 calls this directly as we have no good hook.
	 *
	 * @param array $thisVerification An array from the software with all the verification information we have.
	 * @param array $verification_errors An errors which exist from verification.
	 *
	 * @api
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.0
	 * @since 1.0
	 * @uses create_control_verification - Hook SMF2.0
	 * @uses integrate_create_control_verification_test - Hook SMF2.1
	 * @return bool True is success, no other bool is expeicifcly defined yet.
	 */
	public function checkVerificationTest(array $thisVerification, array &$verification_errors): bool
	{
		global $user_info;

		// Registration is skipped as we process that differently.
		if ($thisVerification['id'] == 'register')
			return true;

		// Get our language in here.
		$this->loadLanguage();

		// Get our options data.
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

		return true;
	}

	/**
	 * Run checks against the SFS database.
	 *
	 * @param array $checks All the possible checks we would like to preform.
	 * @param string $area The area this is coming from.
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.0
	 * @since 1.0
	 * @return bool True is success, no other bool is expeicifcly defined yet.
	 */
	private function sfsCheck(array $checks, string $area = null): bool
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
				// They appeared! Block this.
				if (!empty($check['appears']))
				{
					// Ban them because they are black listed?
					$autoBlackListResult = '0';
					if (!empty($modSettings['sfs_ipcheck_autoban']) && !empty($check['frequency']) && $check['frequency'] == 255)
						$autoBlackListResult = $this->BanNewIP($check['value']);

					$this->logBlockedStats('ip', $check);
					$requestBlocked = 'ip,' . $smcFunc['htmlspecialchars']($check['value']) . ',' . ($autoBlackListResult ? 1 : 0);
					break;
				}
			}
		}

		// If we didn't match a IP, handle Usernames only if we are supposed to, this is just a double check.
		if (empty($requestBlocked) && !empty($modSettings['sfs_usernamecheck']) && !empty($response['username']))
		{
			foreach ($response['username'] as $check)
			{
				// Combine with $area we could also require admin approval above thresholds on things like register.
				if (!empty($check['appears']))
				{
					$shouldBlock = true;
					$confidenceLevel = 0;

					// They meet the confidence level, block them.
					if (!empty($modSettings['sfs_username_confidence']) && !empty($check['confidence']) && $area == 'register' && (float) $modSettings['sfs_username_confidence'] <= (float) $check['confidence'])
						$confidenceLevel = $check['confidence'];
					// We are not confident that they should be blocked.
					if (!empty($modSettings['sfs_username_confidence']) && !empty($check['confidence']) && $area == 'register' && (float) $modSettings['sfs_username_confidence'] > (float) $check['confidence'])
					{
						// Incase we need to debug this.
						if (!empty($modSettings['sfs_log_debug']))
							$this->logAllStats('all', $checks, 'username,' . $smcFunc['htmlspecialchars']($check['value']) . ',' . $check['confidence']);

						$shouldBlock = false;
					}

					// Block them.
					if ($shouldBlock)
					{
						$this->logBlockedStats('username', $check);
						$requestBlocked = 'username,' . $smcFunc['htmlspecialchars']($check['value']) . ',' . $confidenceLevel;
						break;
					}
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
		if (empty($requestBlocked))
			return true;

		// You are a bad spammer, don't tell them what was blocked.
		$this->loadLanguage();
		fatal_error($txt['sfs_request_blocked']);
	}

	/**
	 * Log that this was blocked.
	 *
	 * @param string $type Either username, email, or ip.  Anything else gets marked uknown.
	 * @param array $check The check data we are logging.
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.0
	 * @since 1.0
	 * @return bool True is success, no other bool is expeicifcly defined yet.
	 */
	private function logBlockedStats(string $type, array $check): void
	{
		global $smcFunc, $user_info;

		// What type of log is this?
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
			array(
				'id_type' => 'int',
				'log_time' => 'int',
				'url' => 'string',
				'id_member' => 'int',
				'username' => 'string',
				'email' => 'string',
				'ip' => 'string',
				'ip2' => 'string',
				'checks' => 'string',
				'result' => 'string'
			),
			array(
				$blockType, // Blocked request
				time(),
				$smcFunc['htmlspecialchars']($_SERVER['REQUEST_URL']),
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

	/**
	 * Debug logging that this was blocked..
	 *
	 * @param string $type Either error or all, currently ignored.
	 * @param array $check The check data we are logging.
	 * @param string $DebugMessage Debugging message, sometimes just is error or failure, otherwise a comma separated of what request was blocked.
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.0
	 * @since 1.0
	 * @return bool True is success, no other bool is expeicifcly defined yet.
	 */
	private function logAllStats(string $type, array $checks, string $DebugMessage): void
	{
		global $smcFunc, $user_info;

		$smcFunc['db_insert']('',
			'{db_prefix}log_sfs',
			array(
				'id_type' => 'int',
				'log_time' => 'int',
				'url' => 'string',
				'id_member' => 'int',
				'username' => 'string',
				'email' => 'string',
				'ip' => 'string',
				'ip2' => 'string',
				'checks' => 'string',
				'result' => 'string'
			),
			array(
				0, // Debug type.
				time(),
				$smcFunc['htmlspecialchars']($_SERVER['REQUEST_URL']),
				$user_info['id'],
				'', // Username
				'', // email
				$user_info['ip'],
				$user_info['ip2'],
				json_encode($checks),
				$DebugMessage,
				),
			array('id_sfs', 'id_type')
		);
	}

	/**
	 * Decode JSON data and return it.
	 * If we have $smcFunc['json_decode'], we use this as it handles errors natively.
	 * For all others, we simply ensure a proper array is returned in the event of a error.
	 *
	 * @param string $requestData A properly formatted json string.
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.0
	 * @since 1.0
	 * @return array The parsed json string is now an array.
	 */
	private function decodeJSON(string $requestData): array
	{
		global $smcFunc;

		// Do we have $smcFunc?  It handles errors and logs them as needed.
		if (isset($smcFunc['json_decode']) && is_callable($smcFunc['json_decode']))
			return $smcFunc['json_decode']($request, true);
		// Back to the basics.
		else
		{
			$data = @json_decode($requestData, true);

			// We got a error, return nothing.  Don't log this, not worth it.
			if (json_last_error() !== JSON_ERROR_NONE)
				return array();
			return $data;
		}
	}

	/**
	 * Build the SFS Server URL based on our configuration setup.
	 *
	 * @internal
	 * @link: https://www.stopforumspam.com/usage
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.0
	 * @since 1.0
	 * @return array The parsed json string is now an array.
	 */
	private function buildServerURL(): string
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

		// Do we have to filter out from lastseen?
		if (!empty($modSettings['sfs_expire']))
			$url .= '&expire=' . (int) $modSettings['sfs_expire'];

		return $url;
	}

	/**
	 * Setup our possible SFS hosts.
	 *
	 * @internal
	 * @link: https://www.stopforumspam.com/usage
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.0
	 * @since 1.0
	 * @return array The list of servers.
	 */
	private function sfsServerMapping($returnType = null)
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

	/**
	 * Our possible verification options.
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.0
	 * @since 1.0
	 * @return array The list of servers.
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

	/**
	 * Our possible default options.
	 * We don't specify them all, just ones that make sense for code development.
	 *
	 * @param bool $undo If true, we reverse any defaults we set.  Makes the admin page work.
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.0
	 * @since 1.0
	 * @return void Nothing is returned, we inject into $modSettings.
	 */
	public function loadDefaults($undo = false)
	{
		global $modSettings;

		// Specify the defaults, but only non empties.
		$defaultSettings = array(
			'sfs_enabled' => 1,
			'sfs_expire' => 90,
			'sfs_emailcheck' => 1,
			'sfs_username_confidence' => 50.01,
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

	/**
	 * We undo the defaults letting us save the admin page properly.
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.0
	 * @since 1.0
	 * @return void Nothing is returned, we inject into $modSettings.
	 */
	public function unloadDefaults()
	{
		return $this->loadDefaults(true);
	}

	/**
	 * Checks if we are matching an array of versions against a specific version.
	 *
	 * @param string|array $version The version to check, this is converted to an array later on.
	 * @param string $software The software we are matching against.
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.0
	 * @since 1.0
	 * @return bool True if we matched a version, false otherwise.
	 */
	public function versionCheck($version, string $software = 'smf'): bool
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

	/**
	 * A global function for loading our lanague up.
	 * Placeholder to allow easier additional loading or other software/versions to change this as needed.
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.0
	 * @since 1.0
	 * @return void No return is generated here.
	 */
	public function loadLanguage(): void
	{
		// Load the langauge.
		loadLanguage('StopForumSpam');
	}

	/**
	 * Handle searching for logs.
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.0
	 * @since 1.0
	 * @return void No return is generated here.
	 */
	private function handleLogSearch(): void
	{
		global $context, $txt;

		// If we have some data from a search, lets bring it back out.
		if (!empty($_REQUEST['params']) && empty($_REQUEST['is_search']))
		{
			$this->search_params = base64_decode(strtr($_REQUEST['params'], array(' ' => '+')));
			$this->search_params = $this->JSONDecode($this->search_params);
		}

		// What we can search.
		$searchTypes = array(
			'url' => array('sql' => 'l.url', 'label' => $txt['sfs_log_search_url']),
			'member' => array('sql' => 'mem.real_name', 'label' => $txt['sfs_log_search_member']),
			'username' => array('sql' => 'l.username', 'label' => $txt['sfs_log_search_username']),
			'email' => array('sql' => 'l.email', 'label' => $txt['sfs_log_search_email']),
			'ip' => array('sql' => 'lm.ip', 'label' => $txt['sfs_log_search_ip']),
			'ip2' => array('sql' => 'lm.ip2', 'label' => $txt['sfs_log_search_ip2'])
		);

		// What we want to search for.
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

	/**
	 * Create a Ban Group if needed to handle automatic IP bans.
	 * We attempt to use the known ban function to create bans, otherwise we just fall back to a standard insert.
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.1
	 * @since 1.0
	 * @return bool True upon success, false otherwise.
	 */
	private function createBanGroup(bool $noChecks = false): bool
	{
		global $smcFunc, $modSettings, $sourcedir, $txt;

		// Is this disabled? Don't do it.
		if (empty($noChecks) && empty($modSettings['sfs_ipcheck_autoban']))
			return false;

		// Maybe just got unlinked, if we can find the matching name, relink it.
		$request = $smcFunc['db_query']('', '
			SELECT id_ban_group
			FROM {db_prefix}ban_groups
			WHERE name = {string:new_ban_name}
			LIMIT 1',
			array(
				'new_ban_name' => substr($txt['sfs_ban_group_name'], 0, 20),
			)
		);
		if ($smcFunc['db_num_rows']($request) == 1)
		{
			$ban_data = $smcFunc['db_fetch_assoc']($result);
			$smcFunc['db_free_result']($request);

			if (!empty($ban_data['id_ban_group']))
			{
				updateSettings(array('sfs_ipcheck_autoban_group' => $ban_data['id_ban_group']));
				return true;
			}
		}
		$smcFunc['db_free_result']($request);

		require_once($sourcedir . '/ManageBans.php');

		// Ban Information, this follows the format from the function.
		$ban_info = array(
			'name' => substr($txt['sfs_ban_group_name'], 0, 20),
			'cannot' => array(
				'access' => 1,
				'register' => 1,
				'post' => 1,
				'login' => 1,
			),
			'db_expiration' => 'NULL',
			'reason' => $txt['sfs_ban_group_reason'],
			'notes' => $txt['sfs_ban_group_notes']
		);

		// If we can shortcut this..
		$ban_group_id = 0;
		if (function_exists('insertBanGroup'))
			$ban_group_id = insertBanGroup($ban_info);

		// Fall back.
		if (is_array($ban_group_id) || empty($ban_group_id))
		{
			$smcFunc['db_insert']('',
				'{db_prefix}ban_groups',
				array(
					'name' => 'string-20', 'ban_time' => 'int', 'expire_time' => 'raw', 'cannot_access' => 'int', 'cannot_register' => 'int',
					'cannot_post' => 'int', 'cannot_login' => 'int', 'reason' => 'string-255', 'notes' => 'string-65534',
				),
				array(
					$ban_info['name'], time(), $ban_info['db_expiration'], $ban_info['cannot']['access'], $ban_info['cannot']['register'],
					$ban_info['cannot']['post'], $ban_info['cannot']['login'], $ban_info['reason'], $ban_info['notes'],
				),
				array('id_ban_group'),
				1
			);
			$ban_group_id = $smcFunc['db_insert_id']('{db_prefix}ban_groups', 'id_ban_group');
		}

		// Didn't work? Try again later.
		if (empty($ban_group_id))
			return false;

		updateSettings(array('sfs_ipcheck_autoban_group' => $ban_group_id));
		return true;
	}

	/**
	 * They have triggered a automatic IP ban, lets do it.
	 * In newer versions we attempt to use more of the APIs, but fall back as needed.
	 *
	 * @param string $ip_address The IP address of the spammer.
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.1
	 * @since 1.0
	 * @return bool True upon success, false otherwise.
	 */
	private function BanNewIP(string $ip_address): bool
	{
		global $smcFunc, $modSettings, $sourcedir;

		// Is this disabled? Don't do it.
		if (empty($modSettings['sfs_ipcheck_autoban']))
			return false;

		// Did we loose our Ban Group? Try to fix this.
		if (empty($modSettings['sfs_ipcheck_autoban_group']))
			$this->createBanGroup();

		// Still no Ban Group? Bail out.
		if (empty($modSettings['sfs_ipcheck_autoban_group']))
			return false;

		require_once($sourcedir . '/ManageBans.php');

		// If we have it, use the standard function.
		if (function_exists('insertBanGroup'))
		{
			// We don't call checkExistingTriggerIP as it induces a fatal error.
			$request = $smcFunc['db_query']('', '
				SELECT bg.id_ban_group, bg.name
				FROM {db_prefix}ban_groups AS bg
				INNER JOIN {db_prefix}ban_items AS bi ON
					(bi.id_ban_group = bg.id_ban_group)
					AND ip_low = {inet:ip_low} AND ip_high = {inet:ip_high}
				LIMIT 1',
				array(
					'ip_low' => $ip_address,
					'ip_high' => $ip_address,
				)
			);
			// Alredy exists, bail out.
			if ($smcFunc['db_num_rows']($request) != 0)
			{
				$smcFunc['db_free_result']($request);
				return false;
			}

			// The trigger info.
			$triggers = array(
				array(
					'ip_low' => $ip_address,
					'ip_high' => $ip_address,
				)
			);

			// Add it.
			addTriggers($modSettings['sfs_ipcheck_autoban_group'], $triggers);
		}
		// Go old school.
		else
		{
			$ip_parts = ip2range($ip_address);

			// Not valid? Get out.
			if (count($ip_parts) != 4)
				return false;

			// We don't call checkExistingTriggerIP as it induces a fatal error.
			$request = $smcFunc['db_query']('', '
				SELECT bg.id_ban_group, bg.name
				FROM {db_prefix}ban_groups AS bg
				INNER JOIN {db_prefix}ban_items AS bi ON
					(bi.id_ban_group = bg.id_ban_group)
					AND ip_low1 = {int:ip_low1} AND ip_high1 = {int:ip_high1}
					AND ip_low2 = {int:ip_low2} AND ip_high2 = {int:ip_high2}
					AND ip_low3 = {int:ip_low3} AND ip_high3 = {int:ip_high3}
					AND ip_low4 = {int:ip_low4} AND ip_high4 = {int:ip_high4}
				LIMIT 1',
				array(
					'ip_low1' => $ip_parts[0]['low'],
					'ip_high1' => $ip_parts[0]['high'],
					'ip_low2' => $ip_parts[1]['low'],
					'ip_high2' => $ip_parts[1]['high'],
					'ip_low3' => $ip_parts[2]['low'],
					'ip_high3' => $ip_parts[2]['high'],
					'ip_low4' => $ip_parts[3]['low'],
					'ip_high4' => $ip_parts[3]['high'],
				)
			);
			// Alredy exists, bail out.
			if ($smcFunc['db_num_rows']($request) != 0)
			{
				$smcFunc['db_free_result']($request);
				return false;
			}

			$ban_triggers[] = array(
				$modSettings['sfs_ipcheck_autoban_group'],
				$ip_parts[0]['low'],
				$ip_parts[0]['high'],
				$ip_parts[1]['low'],
				$ip_parts[1]['high'],
				$ip_parts[2]['low'],
				$ip_parts[2]['high'],
				$ip_parts[3]['low'],
				$ip_parts[3]['high'],
				'',
				'',
				0,
			);

			$smcFunc['db_insert']('',
				'{db_prefix}ban_items',
				array(
					'id_ban_group' => 'int', 'ip_low1' => 'int', 'ip_high1' => 'int', 'ip_low2' => 'int', 'ip_high2' => 'int',
					'ip_low3' => 'int', 'ip_high3' => 'int', 'ip_low4' => 'int', 'ip_high4' => 'int', 'hostname' => 'string-255',
					'email_address' => 'string-255', 'id_member' => 'int',
				),
				$ban_triggers,
				array('id_ban')
			);
		}

		// Log this.  The log will show from the user/guest and ip of spammer.
		logAction('ban', array(
			'ip_range' => $ip_address,
			'new' => 1,
			'source' => 'sfs'
		));

		// Let things know we need updated ban data.
		updateSettings(array('banLastUpdated' => time()));
		updateBanMembers();

		return true;
	}
}