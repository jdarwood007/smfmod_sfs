<?php

/**
 * The Logs class for Stop Forum Spam
 * @package StopForumSpam
 * @author SleePy <sleepy @ simplemachines (dot) org>
 * @copyright 2023
 * @license 3-Clause BSD https://opensource.org/licenses/BSD-3-Clause
 * @version 1.5.0
 */
class SFSL
{
	private $SFSclass = null;
	private $SFSAclass = null;

	/**
	 * @var string URLS we need to SFS for UI presentation.
	 */
	private string $urlSFSipCheck = 'https://www.stopforumspam.com/ipcheck/%1$s';
	private string $urlSFSsearch = 'https://www.stopforumspam.com/search/%1$s';

	/**
	 * @var string The URL for the admin page.
	 */
	private ?string $adminLogURL = null;

	/**
	 * @var mixed Search area handling.
	 */
	private array $search_types = [];
	private /*string|array*/ $search_params = [];
	private array $logSearch = [];
	private string $sort_order = 'time';
	private string $search_params_column = '';
	private ?string $search_params_string = null;
	private ?string $search_params_type = null;
	private bool $canDeleteLogs = false;

	/**
	 * @var int How long we disable removing logs.
	 */
	private int $hoursDisabled = 24;

	/*
	 * SMF variables we will load into here for easy reference later.
	*/
	private string $scripturl;
	private array $context;
	private array $smcFunc;
	/* This is array in "theory" only.  SMF sometimes will null this when pulling from cache and causes an error */
	private ?array $modSettings;
	private ?array $txt;

	/**
	 * Creates a self reference to the SFS Log class for use later.
	 *
	 * @version 1.2
	 * @since 1.2
	 * @return object The SFS Log class is returned.
	 */
	public static function selfClass(): self
	{
		if (!isset($GLOBALS['context']['instances'][__CLASS__]))
			$GLOBALS['context']['instances'][__CLASS__] = new self();

		return $GLOBALS['context']['instances'][__CLASS__];
	}

	/**
	 * Build the class, figure out what software/version we have.
	 * Loads up the defaults.
	 *
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.2
	 * @return void No return is generated
	 */
	public function __construct()
	{
		$this->scripturl = $GLOBALS['scripturl'];
		foreach (['context', 'smcFunc', 'txt', 'modSettings'] as $f)
			$this->{$f} = &$GLOBALS[$f];

		$this->SFSclass = &$this->smcFunc['classSFS'];
		$this->SFSAclass = SFSA::selfClass();

		$this->getBaseUrl();
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
	 * @version 1.5.0
	 * @since 1.0
	 * @uses integrate_manage_logs - Hook SMF2.1
	 * @return void No return is generated
	 */
	public static function hook_manage_logs(array &$log_functions): bool
	{
		// Add our logs sub action.
		$log_functions['sfslog'] = ['StopForumSpam' . DIRECTORY_SEPARATOR . 'SFS-Logs.php', 'SFSL::startupLogs'];

		return self::selfClass()->AddToLogMenu($log_functions);
	}

	/**
	 * Add the SFS logs to the log menu.
	 *
	 * @param array $log_functions All possible log functions.
	 *
	 * @CalledIn SMF 2.1
	 * @See SFSA::startupLogs
	 * @version 1.5.0
	 * @since 1.1
	 * @return void No return is generated
	 */
	public function AddToLogMenu(array &$log_functions): bool
	{
		global $context;

		$context[$context['admin_menu_name']]['tab_data']['tabs']['sfslog'] = [
			'description' => $this->SFSclass->txt('sfs_admin_logs'),
		];

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
	 * @version 1.2
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
	 * @version 1.5.0
	 * @since 1.0
	 * @uses hook_manage_logs - Hook SMF2.1
	 * @uses setupModifyModifications - Injected SMF2.0
	 * @return void No return is generated
	 */
	public function loadLogs(bool $return_config = false): array
	{
		// No Configs.
		if ($return_config)
			return [];

		$this->SFSclass->loadLanguage('Modlog');

		$this->context['form_url'] = $this->adminLogURL;
		$this->context['log_url'] = $this->adminLogURL;
		$this->context['page_title'] = $this->SFSclass->txt('sfs_admin_logs');
		$this->canDeleteLogs = allowedTo('admin_forum');

		// Remove all..
		if ((isset($_POST['removeall']) || isset($_POST['delete'])) && $this->canDeleteLogs)
			$this->handleLogDeletes();

		$sort_types = $this->handleLogsGetSortTypes();

		$this->sort_order = isset($_REQUEST['sort']) && isset($sort_types[$_REQUEST['sort']]) ? $_REQUEST['sort'] : 'time';

		// Handle searches.
		$this->handleLogSearch($this->context['log_url']);

		$this->SFSclass->loadSources('Subs-List');

		$listOptions = $this->loadLogsListOptions();

		// Create the watched user list.
		createList($listOptions);

		$this->context['sub_template'] = 'show_list';
		$this->context['default_list'] = 'sfslog_list';

		return [];
	}

	/**
	 * Builds the list options data.
	 *
	 * @internal
	 * @CalledIn SMF2.0, SMF 2.1
	 * @See SFSA::getSFSLogEntries
	 * @See SFSA::getSFSLogEntriesCount
	 * @version 1.5.0
	 * @since 1.2
	 * @return array The list options data
	 */
	public function loadLogsListOptions(): array
	{
		$token = $this->SFSclass->createToken('sfs_logs');

		return [
			'id' => 'sfslog_list',
			'title' => $this->SFSclass->txt('sfs_admin_logs'),
			'width' => '100%',
			'items_per_page' => '50',
			'no_items_label' => $this->SFSclass->txt('sfs_log_no_entries_found'),
			'base_href' => $this->context['log_url'],
			'default_sort_col' => 'time',
			'get_items' => $this->loadLogsGetItems(),
			'get_count' => $this->loadLogsGetCount(),
			// This assumes we are viewing by user.
			'columns' => [
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
			],
			'form' => [
				'href' => $this->context['form_url'],
				'include_sort' => true,
				'include_start' => true,
				'token' => empty($token) ? null : 'sfs_logs',
				'hidden_fields' => [
					$this->context['session_var'] => $this->context['session_id'],
					'params' => $this->search_params
				],
			],
			'additional_rows' => [
				$this->loadLogsGetAddtionalRow(),
			],
		];
	}

	/**
	 * Handle when we want to delete a log and what to do.
	 *
	 * @internal
	 * @CalledIn SMF2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.1
	 * @return void Nothing is returned, the logs are deleted as requested and admin redirected.
	 */
	private function handleLogDeletes(): void
	{
		checkSession();
		$this->SFSclass->createToken('sfs_logs', 'post');

		if (isset($_POST['removeall']) && $this->canDeleteLogs)
			$this->removeAllLogs();
		elseif (!empty($_POST['remove']) && isset($_POST['delete']) && $this->canDeleteLogs)
			$this->removeLogs(array_unique($_POST['delete']));
	}

	/**
	 * loadLogs - Sort Types.
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.2
	 * @return array The valid Sort Types.
	 */
	private function handleLogsGetSortTypes(): array
	{
		return [
			'id_type' =>'l.id_type',
			'log_time' => 'l.log_time',
			'url' => 'l.url',
			'member' => 'mem.id_member',
			'username' => 'l.username',
			'email' => 'l.email',
			'ip' => 'l.ip',
			'ip2' => 'l.ip2',
		];
	}

	/**
	 * loadLogs - Get Items.
	 *
	 * @internal
	 * @CalledIn SMF2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.1
	 * @return array The options for the get_items
	 */
	private function loadLogsGetItems(): array
	{
		return [
			'function' => [$this, 'getSFSLogEntries'],
			'params' => [
				(!empty($this->logSearch['string']) ? ' INSTR({raw:sql_type}, {string:search_string})' : ''),
				['sql_type' => $this->search_params_column, 'search_string' => $this->logSearch['string']],
			],
		];
	}

	/**
	 * loadLogs - Get Count.
	 *
	 * @internal
	 * @CalledIn SMF2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.1
	 * @return array The options for the get_items
	 */
	private function loadLogsGetCount(): array
	{
		return [
			'function' => [$this, 'getSFSLogEntriesCount'],
			'params' => [
				(!empty($this->logSearch['string']) ? ' INSTR({raw:sql_type}, {string:search_string})' : ''),
				['sql_type' => $this->search_params_column, 'search_string' => $this->logSearch['string']],
			],
		];
	}

	/**
	 * loadLogs - Load an additional row, for mostly deleting stuff.
	 *
	 * @internal
	 * @CalledIn SMF2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.1
	 * @return array The options for the get_items
	 */
	private function loadLogsGetAddtionalRow(): array
	{
		return [
			'position' => 'below_table_data',
			'value' => '
				' . $this->SFSclass->txt('sfs_log_search') . ' (' . $this->logSearch['label'] . '):
				<input type="text" name="search" size="18" value="' . $this->smcFunc['htmlspecialchars']($this->logSearch['string']) . '" class="input_text" /> <input type="submit" name="is_search" value="' . $this->SFSclass->txt('modlog_go') . '" class="button_submit" />
				' . ($this->canDeleteLogs ? ' |
					<input type="submit" name="remove" value="' . $this->SFSclass->txt('modlog_remove') . '" class="button_submit" />
					<input type="submit" name="removeall" value="' . $this->SFSclass->txt('modlog_removeall') . '" class="button_submit" />' : ''),
		];
	}

	/**
	 * loadLogs - Column - Type.
	 *
	 * @internal
	 * @CalledIn SMF2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.1
	 * @return array The options for the column
	 */
	private function loadLogsColumnType(): array
	{
		return [
			'header' => [
				'value' => $this->SFSclass->txt('sfs_log_header_type'),
				'class' => 'lefttext',
			],
			'data' => [
				'db' => 'type',
				'class' => 'smalltext',
			],
			'sort' => [],
		];
	}

	/**
	 * loadLogs - Column - Time.
	 *
	 * @internal
	 * @CalledIn SMF2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.1
	 * @return array The options for the column
	 */
	private function loadLogsColumnTime(): array
	{
		return [
			'header' => [
				'value' => $this->SFSclass->txt('sfs_log_header_time'),
				'class' => 'lefttext',
			],
			'data' => [
				'db' => 'time',
				'class' => 'smalltext',
			],
			'sort' => [
				'default' => 'l.log_time DESC',
				'reverse' => 'l.log_time',
			],
		];
	}

	/**
	 * loadLogs - Column - URL.
	 *
	 * @internal
	 * @CalledIn SMF2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.1
	 * @return array The options for the column
	 */
	private function loadLogsColumnURL(): array
	{
		return [
			'header' => [
				'value' => $this->SFSclass->txt('sfs_log_header_url'),
				'class' => 'lefttext',
			],
			'data' => [
				'db' => 'url',
				'class' => 'smalltext',
				'style' => 'word-break: break-word;',
			],
			'sort' => [
				'default' => 'l.url DESC',
				'reverse' => 'l.url',
			],
		];
	}

	/**
	 * loadLogs - Column - Member.
	 *
	 * @internal
	 * @CalledIn SMF2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.1
	 * @return array The options for the column
	 */
	private function loadLogsColumnMember(): array
	{
		return [
			'header' => [
				'value' => $this->SFSclass->txt('sfs_log_header_member'),
				'class' => 'lefttext',
			],
			'data' => [
				'db' => 'member_link',
				'class' => 'smalltext',
			],
			'sort' => [
				'default' => 'mem.id_member',
				'reverse' => 'mem.id_member DESC',
			],
		];
	}

	/**
	 * loadLogs - Column - Username.
	 *
	 * @internal
	 * @CalledIn SMF2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.1
	 * @return array The options for the column
	 */
	private function loadLogsColumnUsername(): array
	{
		return [
			'header' => [
				'value' => $this->SFSclass->txt('sfs_log_header_username'),
				'class' => 'lefttext',
			],
			'data' => [
				'db' => 'username',
				'class' => 'smalltext',
			],
			'sort' => [
				'default' => 'l.username',
				'reverse' => 'l.username DESC',
			],
		];
	}

	/**
	 * loadLogs - Column - Email.
	 *
	 * @internal
	 * @CalledIn SMF2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.1
	 * @return array The options for the column
	 */
	private function loadLogsColumnEmail(): array
	{
		return [
			'header' => [
				'value' => $this->SFSclass->txt('sfs_log_header_email'),
				'class' => 'lefttext',
			],
			'data' => [
				'db' => 'email',
				'class' => 'smalltext',
			],
			'sort' => [
				'default' => 'l.email',
				'reverse' => 'l.email DESC',
			],
		];
	}

	/**
	 * loadLogs - Column - IP.
	 *
	 * @param string $ip2 If true, use ip2
	 * @internal
	 * @CalledIn SMF2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.1
	 * @return array The options for the column
	 */
	private function loadLogsColumnIP(bool $ip2 = false): array
	{
		return [
			'header' => [
				'value' => $this->SFSclass->txt('sfs_log_header_ip' . ($ip2 ? '2' : '')),
				'class' => 'lefttext',
			],
			'data' => [
				'db' => 'ip' . ($ip2 ? '2' : ''),
				'class' => 'smalltext',
			],
			'sort' => [
				'default' => 'l.ip' . ($ip2 ? '2' : ''),
				'reverse' => 'l.ip' . ($ip2 ? '2' : '') . ' DESC',
			],
		];
	}

	/**
	 * loadLogs - Column - Checks.
	 *
	 * @internal
	 * @CalledIn SMF2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.1
	 * @return array The options for the column
	 */
	private function loadLogsColumnChecks(): array
	{
		return [
			'header' => [
				'value' => $this->SFSclass->txt('sfs_log_checks'),
				'class' => 'lefttext',
			],
			'data' => [
				'db' => 'checks',
				'class' => 'smalltext',
				'style' => 'word-break: break-word;',
			],
			'sort' => [],
		];
	}

	/**
	 * loadLogs - Column - Result.
	 *
	 * @internal
	 * @CalledIn SMF2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.1
	 * @return array The options for the column
	 */
	private function loadLogsColumnResult(): array
	{
		return [
			'header' => [
				'value' => $this->SFSclass->txt('sfs_log_result'),
				'class' => 'lefttext',
			],
			'data' => [
				'db' => 'result',
				'class' => 'smalltext',
				'style' => 'word-break: break-word;',
			],
			'sort' => [],
		];
	}

	/**
	 * loadLogs - Column - Delete.
	 *
	 * @internal
	 * @CalledIn SMF2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.1
	 * @return array The options for the column
	 */
	private function loadLogsColumnDelete(): array
	{
		return [
			'header' => [
				'value' => '<input type="checkbox" name="all" class="input_check" onclick="invertAll(this, this.form);" />',
			],
			'data' => [
				'function' => function($entry)
				{
					return '<input type="checkbox" class="input_check" name="delete[]" value="' . $entry['id'] . '"' . ($entry['editable'] ? '' : ' disabled="disabled"') . ' />';
				},
				'style' => 'text-align: center;',
			],
		];
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
	 * @version 1.5.0
	 * @since 1.0
	 * @uses hook_manage_logs - Hook SMF2.1
	 * @uses setupModifyModifications - Injected SMF2.0
	 * @return void No return is generated
	 */
	public function getSFSLogEntries(int $start, int $items_per_page, string $sort, string $query_string = '', array $query_params = []): array
	{
		// Fetch all of our logs.
		$result = $this->smcFunc['db_query']('', '
			SELECT
				l.id_sfs, l.id_type, l.log_time, l.url, l.id_member, l.username, l.email, l.ip, l.ip2, l.checks, l.result,
				mem.real_name, mg.group_name
			FROM {db_prefix}log_sfs AS l
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = l.id_member)
				LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = CASE WHEN mem.id_group = {int:reg_group_id} THEN mem.id_post_group ELSE mem.id_group END)
				WHERE id_type IS NOT NULL'
				. (!empty($query_string) ? '
					AND ' . $query_string : '') . '
			ORDER BY ' . $sort . '
			LIMIT {int:start}, {int:items_per_page}',
			array_merge($query_params, [
				'start' => $start,
				'items_per_page' => $items_per_page,
				'reg_group_id' => 0,
			])
		);

		$entries = [];
		while ($row = $this->smcFunc['db_fetch_assoc']($result))
			$entries[$row['id_sfs']] = $this->getSFSLogPrepareEntry($row);
		$this->smcFunc['db_free_result']($result);

		return $entries;
	}

	/**
	 * Formats a log entry for display.
	 *
	 * @param array $row The raw row data.
	 *
	 * @api
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @See SFSA::getSFSLogEntries
	 * @version 1.5.0
	 * @since 1.2
	 * @return array An array of data ready to be sent to output
	 */
	public function getSFSLogPrepareEntry(array $row = []): array
	{
		$return = [
			'id' => $row['id_sfs'],
			'type' => $this->SFSclass->txt('sfs_log_types_' . $row['id_type']),
			'time' => timeformat($row['log_time']),
			'url' => preg_replace('~http(s)?://~i', 'hxxp\\1://', $row['url']),
			'timestamp' => $row['log_time'],
			'member_link' => $row['id_member'] ? '<a href="' . $this->scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>' : (empty($row['real_name']) ? ($this->SFSclass->txt('guest') . (!empty($row['extra']['member_acted']) ? ' (' . $row['extra']['member_acted'] . ')' : '')) : $row['real_name']),
			'username' => $row['username'],
			'email' => $row['email'],
			'ip' => '<a href="' . sprintf($this->urlSFSipCheck, $row['ip']) . '">' . $row['ip'] . '</a>',
			'ip2' => '<a href="' . sprintf($this->urlSFSipCheck, $row['ip2']) . '">' . $row['ip2'] . '</a>',
			'editable' => true, //time() > $row['log_time'] + $this->hoursDisabled * 3600,
			'checks_raw' => $row['checks'],
			'result_raw' => $row['result'],
		];

		$return['checks'] = $this->getSFSLogPrepareEntryChecks($row);
		$return['result'] = $this->getSFSLogPrepareEntryResult($row);

		return $return;
	}

	/**
	 * Processes the entry for the proper checks column.
	 *
	 * @param array $row The raw row data.
	 *
	 * @api
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @See SFSA::getSFSLogEntries
	 * @version 1.2
	 * @since 1.2
	 * @return string The formatted checks data.
	 */
	public function getSFSLogPrepareEntryChecks(array $row): string
	{
		$checksDecoded = $this->SFSclass->decodeJSON($row['checks']);

		// If we know what check triggered this, link it up to be searched.
		if ($row['id_type'] == 1)
			$checks = '<a href="' . sprintf($this->urlSFSsearch, $checksDecoded['value']) . '">' . $checksDecoded['value'] . '</a>';
		elseif ($row['id_type'] == 2)
			$checks = '<a href="' . sprintf($this->urlSFSsearch, $checksDecoded['value']) . '">' . $checksDecoded['value'] . '</a>';
		elseif ($row['id_type'] == 3)
			$checks = '<a href="' . sprintf($this->urlSFSsearch, $checksDecoded['value']) . '">' . $checksDecoded['value'] . '</a>';
		// No idea what triggered it, parse it out cleanly.  Could be debug data as well.
		else
		{
			$checks = '';
			foreach ($checksDecoded as $ckey => $vkey)
				foreach ($vkey as $key => $value)
					$checks .= ucfirst($key) . ':' . $value . '<br>';
		}

		return $checks;
	}

	/**
	 * Processes the entry for the proper results column.
	 *
	 * @param array $row The raw row data.
	 *
	 * @api
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @See SFSA::getSFSLogEntries
	 * @version 1.5.0
	 * @since 1.2
	 * @return string The formated results entry.
	 */
	public function getSFSLogPrepareEntryResult(array $row = []): string
	{
		// This tells us what it matched on exactly.
		if (strpos($row['result'], ',') === false)
			return $row['result'];

		$results = [];
		foreach (array_filter(explode('|', $row['result'] . '|'), function ($match) {return !empty($match);}) as $match)
		{
			list($resultType, $resultMatch, $extra) = explode(',', $match . ',,,');
			$res = sprintf($this->SFSclass->txt('sfs_log_matched_on'), $resultType, $resultMatch);

			// If this was a IP ban, note it.
			if ($resultType == 'ip' && !empty($extra))
				$res .= ' ' . $this->SFSclass->txt('sfs_log_auto_banned');
			elseif ($resultType == 'username' && !empty($extra))
				$res .= ' ' . sprintf($this->SFSclass->txt('sfs_log_confidence'), $extra);

			$results[] = $res;
		}

		return implode('<br>', $results);
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
	 * @version 1.5.0
	 * @since 1.0
	 * @uses hook_manage_logs - Hook SMF2.1
	 * @uses setupModifyModifications - Injected SMF2.0
	 * @return void No return is generated
	 */
	public function getSFSLogEntriesCount(string $query_string = '', array $query_params = []): int
	{
		$result = $this->smcFunc['db_query']('', '
			SELECT COUNT(*)
			FROM {db_prefix}log_sfs AS l
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = l.id_member)
				LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = CASE WHEN mem.id_group = {int:reg_group_id} THEN mem.id_post_group ELSE mem.id_group END)
				WHERE id_type IS NOT NULL'
				. (!empty($query_string) ? '
					AND ' . $query_string : ''),
			array_merge($query_params, [
				'reg_group_id' => 0,
			])
		);
		list ($entry_count) = $this->smcFunc['db_fetch_row']($result);
		$this->smcFunc['db_free_result']($result);

		return (int) $entry_count;
	}

	/**
	 * Get our Base url for the form.
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.5.0
	 * @return string The log url.
	 */
	private function getBaseUrl(): string
	{
		if (empty($this->adminLogURL))
		{
			if ($this->SFSclass->versionCheck('2.0', 'smf'))
				$this->adminLogURL = $this->scripturl . '?action=admin;area=modsettings;sa=sfslog';
			else
				$this->adminLogURL = $this->scripturl . '?action=admin;area=logs;sa=sfslog';
		}
		return $this->adminLogURL;
	}

	/**
	 * Remove all logs, except those less than 24 hours old.
	 *
	 * @api
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @See SFSA::loadLogs
	 * @version 1.5.0
	 * @since 1.0
	 * @return void No return is generated
	 */
	private function removeAllLogs(): void
	{
		$this->smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_sfs
			WHERE log_time < {int:twenty_four_hours_wait}',
			[
				'twenty_four_hours_wait' => time() - $this->hoursDisabled * 3600,
			]
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
	 * @version 1.5.0
	 * @since 1.0
	 * @return void No return is generated
	 */
	private function removeLogs(array $entries): void
	{
		$this->smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_sfs
			WHERE id_sfs IN ({array_string:delete_actions})
				AND log_time < {int:twenty_four_hours_wait}',
			[
				'twenty_four_hours_wait' => time() - $this->hoursDisabled * 3600,
				'delete_actions' => $entries,
			]
		);
	}

	/**
	 * Handle searching for logs.
	 *
	 * @param string $url The base_href
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.0
	 * @return void No return is generated here.
	 */
	private function handleLogSearch(string &$url): void
	{
		// If we have some data from a search, lets bring it back out.
		$this->search_params = $this->handleLogSearchParams();

		// What we can search.
		$this->search_types = $this->handleLogSearchTypes();
		$this->search_params_string = $this->handleLogSearchParamsString();
		$this->search_params_type = $this->handleLogSearchParamsType();

		$this->search_params_column = $this->search_types[$this->search_params_type]['sql'];

		// Setup the search context.
		$this->search_params = empty($this->search_params_string) ? '' : base64_encode(json_encode([
			'string' => $this->search_params_string,
			'type' => $this->search_params_type,
		]));
		$this->logSearch = [
			'string' => $this->search_params_string,
			'type' => $this->search_params_type,
			'label' => $this->search_types[$this->search_params_type]['label'],
		];

		if (!empty($this->search_params))
			$url .= ';params=' . $this->search_params;
	}

	/**
	 * Handle Search Params
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.0
	 * @return bool True upon success, false otherwise.
	 */
	private function handleLogSearchParams(): array
	{
		// If we have something to search for saved, get it back out.
		if (!empty($_REQUEST['params']) && empty($_REQUEST['is_search']))
		{
			$search_params = base64_decode(strtr($_REQUEST['params'], [' ' => '+']));
			$search_params = $this->SFSclass->decodeJSON($search_params);

			if (!empty($search_params))
				return $search_params;
		}

		return [];
	}

	/**
	 * Handle Search Types
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.0
	 * @return array The valid Search Types.
	 */
	private function handleLogSearchTypes(): array
	{
		return [
			'url' => ['sql' => 'l.url', 'label' => $this->SFSclass->txt('sfs_log_search_url')],
			'member' => ['sql' => 'mem.real_name', 'label' => $this->SFSclass->txt('sfs_log_search_member')],
			'username' => ['sql' => 'l.username', 'label' => $this->SFSclass->txt('sfs_log_search_username')],
			'email' => ['sql' => 'l.email', 'label' => $this->SFSclass->txt('sfs_log_search_email')],
			'ip' => ['sql' => 'lm.ip', 'label' => $this->SFSclass->txt('sfs_log_search_ip')],
			'ip2' => ['sql' => 'lm.ip2', 'label' => $this->SFSclass->txt('sfs_log_search_ip2')]
		];
	}

	/**
	 * Handle Search Params String
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.0
	 * @return string What we are searching for, validated and cleaned.
	 */
	private function handleLogSearchParamsString(): string
	{
		if (!empty($_REQUEST['search']) && ($this->search_params['string'] ?? '') != $_REQUEST['search'])
			return (string) $_REQUEST['search'];
		elseif (isset($this->search_params['string']))
			return $this->search_params['string'];
		else
			return '';
	}

	/**
	 * Handle Search Params Type
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.0
	 * @return string The column we are searching.
	 */
	private function handleLogSearchParamsType(): string
	{
		if (isset($_REQUEST['search_type']) && isset($this->search_types[$_REQUEST['search_type']]))
			return (string) $_REQUEST['search_type'];
		elseif (!empty($this->search_params['type']) && isset($this->search_types[$this->search_params['type']]))
			return $this->search_params['type'];
		elseif (isset($this->search_types[$this->sort_order]))
			return $this->sort_order;
		else
			 return 'member';
	}
}