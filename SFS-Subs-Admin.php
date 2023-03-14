<?php

/**
 * The Admin class for Stop Forum Spam
 * @package StopForumSpam
 * @author SleePy <sleepy @ simplemachines (dot) org>
 * @copyright 2019
 * @license 3-Clause BSD https://opensource.org/licenses/BSD-3-Clause
 * @version 1.0.1
 */
class SFSA
{
	public static $SFSAclass = null;
	private $SFSclass = null;

	/**
	 * @var string URLS we need to SFS for UI presentation.
	 */
	private $urlSFSipCheck = 'https://www.stopforumspam.com/ipcheck/%1$s';
	private $urlSFSsearch = 'https://www.stopforumspam.com/search/%1$s';

	/**
	 * @var string The URL for the admin page.
	 */
	private $adminPageURL = null;
	private $adminLogURL = null;
	private $adminTestURL = null;

	/**
	 * @var mixed Search area handling.
	 */
	private $search_types = array();
	private $search_params = array();
	private $search_params_column = '';
	private $search_params_string = null;
	private $search_params_type = null;
	private $canDeleteLogs = false;
	private $logSearch = array();

	/**
	 * @var int How long we disable removing logs.
	 */
	private $hoursDisabled = 24;

	/**
	 * Creates a self reference to the ASL class for use later.
	 *
	 * @version 1.0
	 * @since 1.0
	 * @return object The SFS Admin class is returned.
	 */
	public static function selfClass()
	{
		global $smcFunc;

		if (is_null(self::$SFSAclass))
		{
			if (!empty($smcFunc['SFSA']))
				self::$SFSAclass = $smcFunc['SFSA'];
			else
			{
				self::$SFSAclass = new SFSA();
				$smcFunc['SFSA'] = self::$SFSAclass;
			}
		}

		return self::$SFSAclass;
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
	
		$this->SFSclass = &$smcFunc['classSFS'];
	}

	/**
	 * Creates the hook to the class for the admin areas.
	 *
	 * @param array $admin_areas A associate array from the software with all valid admin areas.
	 *
	 * @api
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @see SFSA::setupAdminAreas()
	 * @version 1.0
	 * @since 1.0
	 * @uses integrate__admin_areas - Hook SMF2.0
	 * @uses integrate__admin_areas - Hook SMF2.1
	 * @return void No return is generated
	 */
	public static function hook_admin_areas(array &$admin_areas)
	{
		return self::selfClass()->setupAdminAreas($admin_areas);
	}

	/**
	 * Startup the Admin Panels Additions.
	 * Where things appear are based on what software/version you have.
	 *
	 * @param array $admin_areas A associate array from the software with all valid admin areas.
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.4.0
	 * @since 1.0
	 * @uses integrate__admin_areas - Hook SMF2.0
	 * @uses integrate__admin_areas - Hook SMF2.1
	 * @return void No return is generated
	 */
	private function setupAdminAreas(array &$admin_areas): void
	{
		global $scripturl;

		// Add the menu item.
		if ($this->SFSclass->versionCheck('2.0', 'smf'))
		{
			$this->adminPageURL = $scripturl . '?action=admin;area=modsettings;sa=sfs';
			$this->adminLogURL = $scripturl . '?action=admin;area=modsettings;sa=sfslog';
			$this->adminTestURL = $scripturl . '?action=admin;area=modsettings;sa=sfstest';

			$admin_areas['config']['areas']['modsettings']['subsections']['sfs'] = array(
				$this->SFSclass->txt('sfs_admin_area')
			);
			$admin_areas['config']['areas']['modsettings']['subsections']['sfslog'] = array(
				$this->SFSclass->txt('sfs_admin_logs')
			);
			$admin_areas['config']['areas']['modsettings']['subsections']['sfstest'] = array(
				$this->SFSclass->txt('sfs_admin_test')
			);
		}
		else
		{
			$this->adminPageURL = $scripturl . '?action=admin;area=modsettings;sa=sfs';
			$this->adminLogURL = $scripturl . '?action=admin;area=logs;sa=sfslog';
			$this->adminTestURL = $scripturl . '?action=admin;area=regcenter;sa=sfstest';

			$admin_areas['config']['areas']['modsettings']['subsections']['sfs'] = array(
				$this->SFSclass->txt('sfs_admin_area')
			);
			$admin_areas['members']['areas']['regcenter']['subsections']['sfstest'] = array(
				$this->SFSclass->txt('sfs_admin_test')
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
	 * @see SFSA::setupModifyModifications()
	 * @version 1.0
	 * @since 1.0
	 * @uses integrate_modify_modifications - Hook SMF2.0
	 * @uses integrate_modify_modifications - Hook SMF2.1
	 * @return void No return is generated
	 */
	public static function hook_modify_modifications(array &$subActions)
	{
		return self::selfClass()->setupModifyModifications($subActions);
	}

	/**
	 * Setup the Modifications section links.
	 * For some versions we add the logs here as well.
	 *
	 * @param array $subActions A associate array from the software with all valid modification sections.
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.4.0
	 * @since 1.0
	 * @uses integrate_modify_modifications - Hook SMF2.0
	 * @uses integrate_modify_modifications - Hook SMF2.1
	 * @return void No return is generated
	 */
	private function setupModifyModifications(array &$subActions): void
	{
		$subActions['sfs'] = 'SFSA::startupAdminConfiguration';

		// Only in SMF 2.0 do we drop logs here.
		if ($this->SFSclass->versionCheck('2.0', 'smf'))
		{
			$subActions['sfslog'] = 'SFSA::startupLogs';
			$subActions['sfstest'] = 'SFSA::startupTest';
		}
	}

	/**
	 * The configuration caller.
	 *
	 * @param bool $return_config If true, returns the configuration options for searches.
	 *
	 * @api
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @see SFSA::setupSFSConfiguration
	 * @version 1.0
	 * @since 1.0
	 * @uses integrate_modify_modifications - Hook SMF2.0
	 * @uses integrate_modify_modifications - Hook SMF2.1
	 * @return void No return is generated
	 */
	public static function startupAdminConfiguration(bool $return_config = false)
	{
		return self::selfClass()->setupSFSConfiguration($return_config);
	}

	/**
	 * The actual settings page.
	 *
	 * @param bool $return_config If true, returns the configuration options for searches.
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.4.0
	 * @since 1.0
	 * @uses integrate_modify_modifications - Hook SMF2.0
	 * @uses integrate_modify_modifications - Hook SMF2.1
	 * @return void No return is generated
	 */
	private function setupSFSConfiguration(bool $return_config = false): array
	{
		global $scripturl, $context, $settings, $sc, $modSettings;

		$config_vars = array(
				array('title', 'sfsgentitle', 'label' => $this->SFSclass->txt('sfs_general_title')),

				array('check', 'sfs_enabled'),
				array('int', 'sfs_expire'),
			'',
				array('select', 'sfs_required', array(
					'any' => $this->SFSclass->txt('sfs_required_any'),
					'email|ip' => $this->SFSclass->txt('sfs_required_email_ip'),
					'email|username' => $this->SFSclass->txt('sfs_required_email_username'),
					'username|ip' => $this->SFSclass->txt('sfs_required_username_ip'),
				)),
			'',
				array('check', 'sfs_emailcheck'),
				array('check', 'sfs_usernamecheck'),
				array('float', 'sfs_username_confidence', 'step' => '0.01'),
				array('check', 'sfs_ipcheck'),
				array('check', 'sfs_ipcheck_autoban'),
			'',
				array('select', 'sfs_region', $this->SFSclass->sfsServerMapping('config')),
			'',
				array('check', 'sfs_wildcard_email'),
				array('check', 'sfs_wildcard_username'),
				array('check', 'sfs_wildcard_ip'),
			'',
				array('select', 'sfs_tor_check', array(
					0 => $this->SFSclass->txt('sfs_tor_check_block'),
					1 => $this->SFSclass->txt('sfs_tor_check_ignore'),
					2 => $this->SFSclass->txt('sfs_tor_check_bad'),
				)),
			'',
				array('check', 'sfs_enablesubmission'),
				array('text', 'sfs_apikey'),
			'',
				array('title', 'sfsverftitle', 'label' => $this->SFSclass->txt('sfs_verification_title')),
				array('desc', 'sfsverfdesc', 'label' => $this->SFSclass->txt('sfs_verification_desc')),
				array('select', 'sfs_verification_options', array(
					'post' => $this->SFSclass->txt('sfs_verification_options_post'),
					'report' => $this->SFSclass->txt('sfs_verification_options_report'),
					'search' => $this->SFSclass->txt('sfs_verification_options_search'),
				), 'multiple' => true),			
				array('text', 'sfs_verification_options_extra', 'subtext' => $this->SFSclass->txt('sfs_verification_options_extra_subtext')),

			'',
				array('select', 'sfs_verOptionsMembers', array(
					'post' => $this->SFSclass->txt('sfs_verification_options_post'),
					'search' => $this->SFSclass->txt('sfs_verification_options_search'),
				), 'multiple' => true),
				array('text', 'sfs_verOptionsMemExtra', 'subtext' => $this->SFSclass->txt('sfs_verification_options_extra_subtext')),
				array('int', 'sfs_verfOptMemPostThreshold'),
			'',
				array('check', 'sfs_log_debug'),
		);

		if ($return_config)
			return $config_vars;

		// Saving?
		if (isset($_GET['save']))
		{
			// Turn the defaults off.
			$this->SFSclass->unloadDefaults();
			checkSession();

			// If we are automatically banning IPs, make sure we have a ban group.
			if (isset($_POST['sfs_ipcheck_autoban']) && empty($modSettings['sfs_ipcheck_autoban_group']))
				$this->SFSclass->createBanGroup(true);

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
	 * @param array $log_functions All possible log functions.
	 *
	 * @api
	 * @CalledIn SMF 2.1
	 * @See SFSA::startupLogs
	 * @version 1.0
	 * @since 1.0
	 * @uses integrate_manage_logs - Hook SMF2.1
	 * @return void No return is generated
	 */
	public static function hook_manage_logs(array &$log_functions): bool
	{
		// Add our logs sub action.
        $log_functions['sfslog'] = array('SFS-Subs-Logs.php', 'SFSL::startupLogs');

		return self::selfClass()->AddToLogMenu($log_functions);
	}

	/**
	 * Add the SFS logs to the log menu.
	 *
	 * @param array $log_functions All possible log functions.
	 *
	 * @CalledIn SMF 2.1
	 * @See SFSA::startupLogs
	 * @version 1.1
	 * @since 1.1
	 * @return void No return is generated
	 */
	public function AddToLogMenu(array &$log_functions): bool
	{
		global $context;

		$context[$context['admin_menu_name']]['tab_data']['tabs']['sfslog'] = array(
			'description' => $this->SFSclass->txt('sfs_admin_logs'),
		);

		return true;
	}

	/**
	 * Log startup caller.
	 * This has a $return_config just for simply complying with properly for searching the admin panel.
	 *
	 * @param bool $return_config If true, returns empty array to prevent breaking old SMF installs.
	 *
	 * @api
	 * @CalledIn SMF 2.1
	 * @See SFSA::loadLogs
	 * @version 1.0
	 * @since 1.0
	 * @uses hook_manage_logs - Hook SMF2.1
	 * @uses setupModifyModifications - Injected SMF2.0
	 * @return void No return is generated
	 */
	public static function startupLogs(bool $return_config = false): array
	{
		return self::selfClass()->loadLogs();
	}

	/**
	 * Actually show the logs.
	 * This has a $return_config just for simply complying with properly for searching the admin panel.
	 *
	 * @param bool $return_config If true, returns empty array to prevent breaking old SMF installs.
	 *
	 * @api
	 * @CalledIn SMF2.0, SMF 2.1
	 * @See SFSA::getSFSLogEntries
	 * @See SFSA::getSFSLogEntriesCount
	 * @version 1.0
	 * @since 1.0
	 * @uses hook_manage_logs - Hook SMF2.1
	 * @uses setupModifyModifications - Injected SMF2.0
	 * @return void No return is generated
	 */
	public function loadLogs(bool $return_config = false): array
	{
		global $context, $smcFunc, $sourcedir;

		// No Configs.
		if ($return_config)
			return array();

		loadLanguage('Modlog');

		$context['form_url'] = $this->adminLogURL;
		$context['log_url'] = $this->adminLogURL;
		$context['page_title'] = $this->SFSclass->txt('sfs_admin_logs');
		$this->canDeleteLogs = allowedTo('admin_forum');

		// Remove all..
		if ((isset($_POST['removeall']) || isset($_POST['delete'])) && $this->canDeleteLogs)
			$this->handleLogDeletes();

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
		$this->handleLogSearch($context['log_url']);

		require_once($sourcedir . '/Subs-List.php');

		$listOptions = array(
			'id' => 'sfslog_list',
			'title' => $this->SFSclass->txt('sfs_admin_logs'),
			'width' => '100%',
			'items_per_page' => '50',
			'no_items_label' => $this->SFSclass->txt('sfs_log_no_entries_found'),
			'base_href' => $context['log_url'],
			'default_sort_col' => 'time',
			'get_items' => $this->loadLogsGetItems(),
			'get_count' => $this->loadLogsGetCount(),
			// This assumes we are viewing by user.
			'columns' => array(
				'type' => $this->loadLogsColumnType(),
				'time' => $this->loadLogsColumnTime(),
				'url' => $this->loadLogsColumnURL(),
				'member' => $this->loadLogsColumnMember(),
				'username' => $this->loadLogsColumnUsername(),
				'email' => $this->loadLogsColumnEmail(),
				'ip' => $this->loadLogsColumnIP(),
				'ip2' => $this->loadLogsColumnIP(true),
				'checks' => $this->loadLogsColumnChecks(),
				'result' => $this->loadLogsColumnResult(),
				'delete' => $this->loadLogsColumnDelete(),
			),
			'form' => array(
				'href' => $context['form_url'],
				'include_sort' => true,
				'include_start' => true,
				'hidden_fields' => array(
					$context['session_var'] => $context['session_id'],
					'params' => $this->search_params
				),
			),
			'additional_rows' => array(
				$this->loadLogsGetAddtionalRow(),
			),
		);

		// Create the watched user list.
		createList($listOptions);

		$context['sub_template'] = 'show_list';
		$context['default_list'] = 'sfslog_list';

		return array();
	}

	/**
	 * Handle when we want to delete a log and what to do.
	 *
	 * @internal
	 * @CalledIn SMF2.0, SMF 2.1
	 * @version 1.1
	 * @since 1.1
	 * @return void Nothing is returned, the logs are deleted as requested and admin redirected.
	 */
	private function handleLogDeletes(): void
	{
		if (isset($_POST['removeall']) && $this->canDeleteLogs)
			$this->removeAllLogs();
		elseif (!empty($_POST['remove']) && isset($_POST['delete']) && $this->canDeleteLogs)
			$this->removeLogs(array_unique($_POST['delete']));
	}

	/**
	 * loadLogs - Get Items.
	 *
	 * @internal
	 * @CalledIn SMF2.0, SMF 2.1
	 * @version 1.1
	 * @since 1.1
	 * @return array The options for the get_items
	 */
	private function loadLogsGetItems(): array
	{
		return array(
			'function' => array($this, 'getSFSLogEntries'),
			'params' => array(
				(!empty($this->logSearch['string']) ? ' INSTR({raw:sql_type}, {string:search_string})' : ''),
				array('sql_type' => $this->search_params_column, 'search_string' => $this->logSearch['string']),
			),
		);
	}

	/**
	 * loadLogs - Get Count.
	 *
	 * @internal
	 * @CalledIn SMF2.0, SMF 2.1
	 * @version 1.1
	 * @since 1.1
	 * @return array The options for the get_items
	 */
	private function loadLogsGetCount(): array
	{
		return array(
			'function' => array($this, 'getSFSLogEntriesCount'),
			'params' => array(
				(!empty($this->logSearch['string']) ? ' INSTR({raw:sql_type}, {string:search_string})' : ''),
				array('sql_type' => $this->search_params_column, 'search_string' => $this->logSearch['string']),
			),
		);
	}

	/**
	 * loadLogs - Load an additional row, for mostly deleting stuff.
	 *
	 * @internal
	 * @CalledIn SMF2.0, SMF 2.1
	 * @version 1.1
	 * @since 1.1
	 * @return array The options for the get_items
	 */
	private function loadLogsGetAddtionalRow(): array
	{
		global $smcFunc;

		return array(
			'position' => 'below_table_data',
			'value' => '
				' . $this->SFSclass->txt('sfs_log_search') . ' (' . $this->logSearch['label'] . '):
				<input type="text" name="search" size="18" value="' . $smcFunc['htmlspecialchars']($this->logSearch['string']) . '" class="input_text" /> <input type="submit" name="is_search" value="' . $this->SFSclass->txt('modlog_go') . '" class="button_submit" />
				' . ($this->canDeleteLogs ? ' |
					<input type="submit" name="remove" value="' . $this->SFSclass->txt('modlog_remove') . '" class="button_submit" />
					<input type="submit" name="removeall" value="' . $this->SFSclass->txt('modlog_removeall') . '" class="button_submit" />' : ''),
		);
	}


	/**
	 * loadLogs - Column - Type.
	 *
	 * @internal
	 * @CalledIn SMF2.0, SMF 2.1
	 * @version 1.1
	 * @since 1.1
	 * @return array The options for the column
	 */
	private function loadLogsColumnType(): array
	{
		return array(
			'header' => array(
				'value' => $this->SFSclass->txt('sfs_log_header_type'),
				'class' => 'lefttext',
			),
			'data' => array(
				'db' => 'type',
				'class' => 'smalltext',
			),
			'sort' => array(
			),
		);
	}

	/**
	 * loadLogs - Column - Time.
	 *
	 * @internal
	 * @CalledIn SMF2.0, SMF 2.1
	 * @version 1.1
	 * @since 1.1
	 * @return array The options for the column
	 */
	private function loadLogsColumnTime(): array
	{
		return array(
			'header' => array(
				'value' => $this->SFSclass->txt('sfs_log_header_time'),
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
		);
	}

	/**
	 * loadLogs - Column - URL.
	 *
	 * @internal
	 * @CalledIn SMF2.0, SMF 2.1
	 * @version 1.1
	 * @since 1.1
	 * @return array The options for the column
	 */
	private function loadLogsColumnURL(): array
	{
		return array(
			'header' => array(
				'value' => $this->SFSclass->txt('sfs_log_header_url'),
				'class' => 'lefttext',
			),
			'data' => array(
				'db' => 'url',
				'class' => 'smalltext',
				'style' => 'word-break: break-word;',
			),
			'sort' => array(
				'default' => 'l.url DESC',
				'reverse' => 'l.url',
			),
		);
	}

	/**
	 * loadLogs - Column - Member.
	 *
	 * @internal
	 * @CalledIn SMF2.0, SMF 2.1
	 * @version 1.1
	 * @since 1.1
	 * @return array The options for the column
	 */
	private function loadLogsColumnMember(): array
	{
		return array(
			'header' => array(
				'value' => $this->SFSclass->txt('sfs_log_header_member'),
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
		);
	}

	/**
	 * loadLogs - Column - Username.
	 *
	 * @internal
	 * @CalledIn SMF2.0, SMF 2.1
	 * @version 1.1
	 * @since 1.1
	 * @return array The options for the column
	 */
	private function loadLogsColumnUsername(): array
	{
		return array(
			'header' => array(
				'value' => $this->SFSclass->txt('sfs_log_header_username'),
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
		);
	}

	/**
	 * loadLogs - Column - Email.
	 *
	 * @internal
	 * @CalledIn SMF2.0, SMF 2.1
	 * @version 1.1
	 * @since 1.1
	 * @return array The options for the column
	 */
	private function loadLogsColumnEmail(): array
	{
		return array(
			'header' => array(
				'value' => $this->SFSclass->txt('sfs_log_header_email'),
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
		);
	}

	/**
	 * loadLogs - Column - IP.
	 *
	 * @param string $ip2 If true, use ip2
	 * @internal
	 * @CalledIn SMF2.0, SMF 2.1
	 * @version 1.1
	 * @since 1.1
	 * @return array The options for the column
	 */
	private function loadLogsColumnIP(bool $ip2 = false): array
	{
		return array(
			'header' => array(
				'value' => $this->SFSclass->txt('sfs_log_header_ip' . ($ip2 ? '2' : '')),
				'class' => 'lefttext',
			),
			'data' => array(
				'db' => 'ip' . ($ip2 ? '2' : ''),
				'class' => 'smalltext',
			),
			'sort' => array(
				'default' => 'l.ip' . ($ip2 ? '2' : ''),
				'reverse' => 'l.ip' . ($ip2 ? '2' : '') . ' DESC',
			),
		);
	}

	/**
	 * loadLogs - Column - Checks.
	 *
	 * @internal
	 * @CalledIn SMF2.0, SMF 2.1
	 * @version 1.1
	 * @since 1.1
	 * @return array The options for the column
	 */
	private function loadLogsColumnChecks(): array
	{
		return array(
			'header' => array(
				'value' => $this->SFSclass->txt('sfs_log_checks'),
				'class' => 'lefttext',
			),
			'data' => array(
				'db' => 'checks',
				'class' => 'smalltext',
				'style' => 'word-break: break-word;',
			),
			'sort' => array(),
		);
	}

	/**
	 * loadLogs - Column - Result.
	 *
	 * @internal
	 * @CalledIn SMF2.0, SMF 2.1
	 * @version 1.1
	 * @since 1.1
	 * @return array The options for the column
	 */
	private function loadLogsColumnResult(): array
	{
		return array(
			'header' => array(
				'value' => $this->SFSclass->txt('sfs_log_result'),
				'class' => 'lefttext',
			),
			'data' => array(
				'db' => 'result',
				'class' => 'smalltext',
				'style' => 'word-break: break-word;',
			),
			'sort' => array(),
		);
	}

	/**
	 * loadLogs - Column - Delete.
	 *
	 * @internal
	 * @CalledIn SMF2.0, SMF 2.1
	 * @version 1.1
	 * @since 1.1
	 * @return array The options for the column
	 */
	private function loadLogsColumnDelete(): array
	{
		return array(
			'header' => array(
				'value' => '<input type="checkbox" name="all" class="input_check" onclick="invertAll(this, this.form);" />',
			),
			'data' => array(
				'function' => function($entry)
				{
					return '<input type="checkbox" class="input_check" name="delete[]" value="' . $entry['id'] . '"' . ($entry['editable'] ? '' : ' disabled="disabled"') . ' />';
				},
				'style' => 'text-align: center;',
			),
		);
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
	 * @See SFSA::loadLogs
	 * @version 1.0
	 * @since 1.0
	 * @uses hook_manage_logs - Hook SMF2.1
	 * @uses setupModifyModifications - Injected SMF2.0
	 * @return void No return is generated
	 */
	public function getSFSLogEntries(int $start, int $items_per_page, string $sort, string $query_string = '', array $query_params = array()): array
	{
		global $scripturl, $context, $smcFunc;

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

		$entries = array();
		while ($row = $smcFunc['db_fetch_assoc']($result))
		{
			$entries[$row['id_sfs']] = array(
				'id' => $row['id_sfs'],
				'type' => $this->SFSclass->txt('sfs_log_types_' . $row['id_type']),
				'time' => timeformat($row['log_time']),
				'url' => preg_replace('~http(s)?://~i', 'hxxp\\1://', $row['url']),
				'timestamp' => $row['log_time'],
				'member_link' => $row['id_member'] ? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>' : (empty($row['real_name']) ? ($this->SFSclass->txt('guest') . (!empty($row['extra']['member_acted']) ? ' (' . $row['extra']['member_acted'] . ')' : '')) : $row['real_name']),
				'username' => $row['username'],
				'email' => $row['email'],
				'ip' => '<a href="' . sprintf($this->urlSFSipCheck, $row['ip']) . '">' . $row['ip'] . '</a>',
				'ip2' => '<a href="' . sprintf($this->urlSFSipCheck, $row['ip2']) . '">' . $row['ip2'] . '</a>',
				'editable' => true, //time() > $row['log_time'] + $this->hoursDisabled * 3600,
				'checks_raw' => $row['checks'],
				'result_raw' => $row['result'],
			);

			$checksDecoded = $this->SFSclass->decodeJSON($row['checks']);

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

				foreach ($checksDecoded as $ckey => $vkey)
					foreach ($vkey as $key => $value)
						$entries[$row['id_sfs']]['checks'] .= ucfirst($key) . ':' . $value . '<br>';					
			}

			// This tells us what it matched on exactly.
			if (strpos($row['result'], ',') !== false)
			{
				list($resultType, $resultMatch, $extra) = explode(',', $row['result'] . ',,,');
				$entries[$row['id_sfs']]['result'] = sprintf($this->SFSclass->txt('sfs_log_matched_on'), $resultType, $resultMatch);

				// If this was a IP ban, note it.
				if ($resultType == 'ip' && !empty($extra))
					$entries[$row['id_sfs']]['result'] .= ' ' . $this->SFSclass->txt('sfs_log_auto_banned');			
				if ($resultType == 'username' && !empty($extra))
					$entries[$row['id_sfs']]['result'] .= ' ' . sprintf($this->SFSclass->txt('sfs_log_confidence'), $extra);			
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
	 * @See SFSA::loadLogs
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
	 * Get params
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.1
	 * @since 1.0
	 * @return string The column we are searching.
	 */
	public function get(string $var)
	{
		if (isset($this->{$var}))
			return $this->{$var};
	}

	/**
	 * Remove all logs, except those less than 24 hours old.
	 *
	 * @api
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @See SFSA::loadLogs
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
	 * @See SFSA::loadLogs
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
	 * Handle searching for logs.
	 *
	 * @param string $url The base_href
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.0
	 * @since 1.0
	 * @return void No return is generated here.
	 */
	private function handleLogSearch(string &$url): void
	{
		global $context, $txt;

		// If we have some data from a search, lets bring it back out.
		$this->search_params = $this->handleLogSearchParams();

		// What we can search.
		$this->search_types = $this->handleLogSearchTypes();
		$this->search_params_string = $this->handleLogSearchParamsString();
		$this->search_params_type = $this->handleLogSearchParamsType();

		$this->search_params_column = $this->search_types[$this->search_params_type]['sql'];

		// Setup the search context.
		$this->search_params = empty($this->search_params_string) ? '' : base64_encode(json_encode(array(
			'string' => $this->search_params_string,
			'type' => $this->search_params_type,
		)));
		$this->logSearch = array(
			'string' => $this->search_params_string,
			'type' => $this->search_params_type,
			'label' => $this->search_types[$this->search_params_type]['label'],
		);

		if (!empty($this->search_params))
			$url .= ';params=' . $this->search_params;
	}

	/**
	 * Handle Search Params
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.1
	 * @since 1.0
	 * @return bool True upon success, false otherwise.
	 */
	private function handleLogSearchParams(): array
	{
		// If we have something to search for saved, get it back out.
		if (!empty($_REQUEST['params']) && empty($_REQUEST['is_search']))
		{
			$search_params = base64_decode(strtr($_REQUEST['params'], array(' ' => '+')));
			$search_params = $this->SFSclass->decodeJSON($search_params);

			if (!empty($search_params))
				return $search_params;
		}
	
		return array();
	}

	/**
	 * Handle Search Types
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.1
	 * @since 1.0
	 * @return array The valid Search Types.
	 */
	private function handleLogSearchTypes(): array
	{
		return array(
			'url' => array('sql' => 'l.url', 'label' => $this->SFSclass->txt('sfs_log_search_url')),
			'member' => array('sql' => 'mem.real_name', 'label' => $this->SFSclass->txt('sfs_log_search_member')),
			'username' => array('sql' => 'l.username', 'label' => $this->SFSclass->txt('sfs_log_search_username')),
			'email' => array('sql' => 'l.email', 'label' => $this->SFSclass->txt('sfs_log_search_email')),
			'ip' => array('sql' => 'lm.ip', 'label' => $this->SFSclass->txt('sfs_log_search_ip')),
			'ip2' => array('sql' => 'lm.ip2', 'label' => $this->SFSclass->txt('sfs_log_search_ip2'))
		);
	}
 
	/**
	 * Handle Search Params String
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.1
	 * @since 1.0
	 * @return string What we are searching for, validated and cleaned.
	 */
	private function handleLogSearchParamsString(): string
	{
		if (!isset($this->search_params['string']) || (!empty($_REQUEST['search']) && $this->search_params['string'] != $_REQUEST['search']))
			return empty($_REQUEST['search']) ? '' : $_REQUEST['search'];
		else
			return $this->search_params['string'];
	}

	/**
	 * Handle Search Params Type
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.1
	 * @since 1.0
	 * @return string The column we are searching.
	 */
	private function handleLogSearchParamsType(): string
	{
		global $context;

		if (isset($_REQUEST['search_type']) || empty($this->search_params['type']) || !isset($this->search_types[$this->search_params['type']]))
			return isset($_REQUEST['search_type']) && isset($this->search_types[$_REQUEST['search_type']]) ? $_REQUEST['search_type'] : (isset($this->search_types[$context['order']]) ? $context['order'] : 'member');
		else
			return $this->search_params['type'];
	}

	/**
	 * In some software/versions, we can hook into the members registration center section.
	 * In others we hook into the modifications settings.
	 *
	 * @param array $subActions All possible sub actions.
	 *
	 * @api
	 * @CalledIn SMF 2.1
	 * @See SFSA::startupTest
	 * @version 1.4.0
	 * @since 1.4.0
	 * @uses integrate_manage_registrations - Hook SMF2.1
	 * @return void No return is generated
	 */
	public static function hook_manage_registrations(array &$subActions): bool
	{
		global $context;

		// Add our logs sub action.
		$subActions['sfstest'] = array('SFSA::startupTest', 'admin_forum');

		if (isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'sfstest' && allowedTo('admin_forum'))
			$context['sub_action'] = 'sfstest';

		return self::selfClass()->AddToRegCenterMenu($subActions);
	}

	/**
	 * Add the SFS Test to the regcenter menu.
	 *
	 * @param array $log_functions All possible log functions.
	 *
	 * @CalledIn SMF 2.1
	 * @See SFSA::startupTest
	 * @version 1.4.0
	 * @since 1.4.0
	 * @return void No return is generated
	 */
	public function AddToRegCenterMenu(array &$subActions): bool
	{
		global $context;

		$context[$context['admin_menu_name']]['tab_data']['tabs']['sfstest'] = array(
			'description' => $this->SFSclass->txt('sfs_admin_test_desc'),
		);

		return true;
	}

	/**
	 * Test API startup caller.
	 * This has a $return_config just for simply complying with properly for searching the admin panel.
	 *
	 * @param bool $return_config If true, returns empty array to prevent breaking old SMF installs.
	 *
	 * @api
	 * @CalledIn SMF 2.1
	 * @See SFSA::loadTestAPI
	 * @version 1.4.0
	 * @since 1.4.0
	 * @uses hook_manage_registrations - Hook SMF2.1
	 * @uses setupModifyModifications - Injected SMF2.0
	 * @return void No return is generated
	 */
	public static function startupTest(bool $return_config = false): array
	{
		return self::selfClass()->loadTestAPI();
	}

	/**
	 * Actually do the test API.
	 * This has a $return_config just for simply complying with properly for searching the admin panel.
	 *
	 * @param bool $return_config If true, returns empty array to prevent breaking old SMF installs.
	 *
	 * @api
	 * @CalledIn SMF2.0, SMF 2.1
	 * @version 1.4.0
	 * @since 1.4.0
	 * @uses hook_manage_registrations - Hook SMF2.1
	 * @uses setupModifyModifications - Injected SMF2.0
	 * @return void No return is generated
	 */
	public function loadTestAPI(bool $return_config = false): array
	{
		global $context, $smcFunc, $user_info;

		// No Configs.
		if ($return_config)
			return array();

		$context['token_check'] = 'sfs_testapi';
		$this->SFSclass->loadLanguage();

		// The reuslts output.
		$context['test_sent'] = isset($_POST['send']);
		$context['sfs_checks'] = array(
			'username' => array(
				0 => array(
					'enabled' => !empty($modSettings['sfs_usernamecheck']),
					'value' => !empty($_POST['username']) ? $smcFunc['htmlspecialchars']($_POST['username']) : $user_info['name'],
					'results' => null
				),
			),
			'email' => array(
				0 => array(
					'enabled' => !empty($modSettings['sfs_emailcheck']),
					'value' => !empty($_POST['email']) ? $smcFunc['htmlspecialchars']($_POST['email']) : $user_info['email'],
					'results' => null
				),
			),
			'ip' => array(
				0 => array(
					'enabled' => !empty($modSettings['sfs_ipcheck']),
					'value' => !empty($_POST['ip']) ? $smcFunc['htmlspecialchars']($_POST['ip']) : $user_info['ip'],
					'results' => null
				),
			),
		);

		// Sending the data?
		if ($context['test_sent'])
		{
			//checkSession();
			//if (!$this->SFSclass->versionCheck('2.0', 'smf'))
			//	validateToken($context['token_check'], 'post');

			$username = $smcFunc['htmlspecialchars']($_POST['username']);
			$email = $smcFunc['htmlspecialchars']($_POST['email']);
			$ip = $smcFunc['htmlspecialchars']($_POST['ip']);
				
			$response = $this->SFSclass->TestSFS(array(
					array('username' => $username),
					array('email' => $email),
					array('ip' => $ip),
			));

			// No checks found? Can't do this.
			if (empty($response) || !is_array($response) || empty($response['success']))
				$context['test_api_error'] = $this->SFSclass->txt('sfs_request_failure_nodata');
			else
				// Parse all the responses out.
				foreach($context['sfs_checks'] as $key => &$res)
					$res[0] += $response[$key][0];
		}

		// Load our template.
		loadTemplate('StopForumSpam');
		$context['sub_template'] = 'sfsa_testapi';

		$context['sfs_test_url'] = $this->adminTestURL;
		if (!$this->SFSclass->versionCheck('2.0', 'smf'))
			createToken($context['token_check'], 'post');
		else
			unset($context['token_check']);

		return array();
	}
}