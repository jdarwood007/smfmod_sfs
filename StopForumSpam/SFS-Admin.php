<?php

/**
 * The Admin class for Stop Forum Spam
 * @package StopForumSpam
 * @author SleePy <sleepy @ simplemachines (dot) org>
 * @copyright 2023
 * @license 3-Clause BSD https://opensource.org/licenses/BSD-3-Clause
 * @version 1.5.0
 */
class SFSA
{
	private $SFSclass = null;

	/**
	 * @var string URLS we need to SFS for UI presentation.
	 */
	private string $urlSFSipCheck = 'https://www.stopforumspam.com/ipcheck/%1$s';
	private string $urlSFSsearch = 'https://www.stopforumspam.com/search/%1$s';

	/**
	 * @var string The URL for the admin page.
	 */
	private ?string $adminPageURL = null;
	private ?string $adminLogURL = null;
	private ?string $adminTestURL = null;

	/*
	 * SMF variables we will load into here for easy reference later.
	*/
	private string $scripturl;
	private array $context;
	private array $smcFunc;
	/* This is array in "theory" only.  SMF sometimes will null this when pulling from cache and causes an error */
	private ?array $modSettings;
	private ?array $user_info;
	private ?array $txt;

	/**
	 * Creates a self reference to the ASL class for use later.
	 *
	 * @version 1.0
	 * @since 1.0
	 * @return object The SFS Admin class is returned.
	 */
	public static function selfClass(): self
	{
		if (!isset($GLOBALS['context']['instances'][__CLASS__])) {
			$GLOBALS['context']['instances'][__CLASS__] = new self();
		}

		return $GLOBALS['context']['instances'][__CLASS__];
	}

	/**
	 * Build the class, figure out what software/version we have.
	 * Loads up the defaults.
	 *
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.0
	 */
	public function __construct()
	{
		$this->scripturl = $GLOBALS['scripturl'];

		foreach (['context', 'smcFunc', 'txt', 'modSettings', 'user_info'] as $f) {
			$this->{$f} = &$GLOBALS[$f];
		}

		$this->SFSclass = &$this->smcFunc['classSFS'];
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
	 */
	public static function hook_admin_areas(array &$admin_areas): void
	{
		self::selfClass()->setupAdminAreas($admin_areas);
	}

	/**
	 * Startup the Admin Panels Additions.
	 * Where things appear are based on what software/version you have.
	 *
	 * @param array $admin_areas A associate array from the software with all valid admin areas.
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.0
	 * @uses integrate__admin_areas - Hook SMF2.0
	 * @uses integrate__admin_areas - Hook SMF2.1
	 */
	private function setupAdminAreas(array &$admin_areas): void
	{
		// The main config is the same.
		$this->adminPageURL = $this->scripturl . '?action=admin;area=modsettings;sa=sfs';
		$admin_areas['config']['areas']['modsettings']['subsections']['sfs'] = [
			$this->SFSclass->txt('sfs_admin_area'),
		];

		// Add the menu item.
		if ($this->SFSclass->versionCheck('2.0', 'smf')) {
			$this->adminLogURL = $this->scripturl . '?action=admin;area=modsettings;sa=sfslog';
			$this->adminTestURL = $this->scripturl . '?action=admin;area=modsettings;sa=sfstest';

			$admin_areas['config']['areas']['modsettings']['subsections']['sfslog'] = [
				$this->SFSclass->txt('sfs_admin_logs'),
			];
			$admin_areas['config']['areas']['modsettings']['subsections']['sfstest'] = [
				$this->SFSclass->txt('sfs_admin_test'),
			];
		} else {
			$this->adminLogURL = $this->scripturl . '?action=admin;area=logs;sa=sfslog';
			$this->adminTestURL = $this->scripturl . '?action=admin;area=regcenter;sa=sfstest';

			$admin_areas['maintenance']['areas']['logs']['subsections']['sfslog'] = [
				$this->SFSclass->txt('sfs_admin_logs'),
			];
			$admin_areas['members']['areas']['regcenter']['subsections']['sfstest'] = [
				$this->SFSclass->txt('sfs_admin_test'),
			];
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
	 */
	public static function hook_modify_modifications(array &$subActions): void
	{
		self::selfClass()->setupModifyModifications($subActions);
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
	 */
	private function setupModifyModifications(array &$subActions): void
	{
		$subActions['sfs'] = 'SFSA::startupAdminConfiguration';

		// Only in SMF 2.0 do we drop logs here.
		if ($this->SFSclass->versionCheck('2.0', 'smf')) {
			$this->SFSclass->loadSources(['SFS-Logs']);
			$subActions['sfslog'] = 'SFSL::startupLogs';
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
	 * @version 1.5.0
	 * @since 1.0
	 * @uses integrate_modify_modifications - Hook SMF2.0
	 * @uses integrate_modify_modifications - Hook SMF2.1
	 */
	private function setupSFSConfiguration(bool $return_config = false): array
	{
		$config_vars = $this->getConfiguration();

		if ($return_config) {
			return $config_vars;
		}

		// Saving?
		if (isset($_GET['save'])) {
			$this->saveConfiguration($config_vars);
		}

		$this->context['post_url'] = $this->adminPageURL . ';save';
		prepareDBSettingContext($config_vars);

		return [];
	}

	/**
	 * Get all the valid settings.
	 *
	 * @param array $config_vars The configuration variables..
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.5.0
	 */
	private function getConfiguration(): array
	{
		return [
			['title', 'sfsgentitle', 'label' => $this->SFSclass->txt('sfs_general_title')],

			['check', 'sfs_enabled'],
			['int', 'sfs_expire'],
			'',
			['select', 'sfs_required', [
				'any' => $this->SFSclass->txt('sfs_required_any'),
				'email|ip' => $this->SFSclass->txt('sfs_required_email_ip'),
				'email|username' => $this->SFSclass->txt('sfs_required_email_username'),
				'username|ip' => $this->SFSclass->txt('sfs_required_username_ip'),
			]],
			'',
			['check', 'sfs_emailcheck'],
			['check', 'sfs_usernamecheck'],
			['float', 'sfs_username_confidence', 'step' => '0.01'],
			['check', 'sfs_ipcheck'],
			['check', 'sfs_ipcheck_autoban'],
			'',
			['select', 'sfs_region', $this->SFSclass->sfsServerMapping('config')],
			'',
			['check', 'sfs_wildcard_email'],
			['check', 'sfs_wildcard_username'],
			['check', 'sfs_wildcard_ip'],
			'',
			['select', 'sfs_tor_check', [
				0 => $this->SFSclass->txt('sfs_tor_check_block'),
				1 => $this->SFSclass->txt('sfs_tor_check_ignore'),
				2 => $this->SFSclass->txt('sfs_tor_check_bad'),
			]],
			'',
			['check', 'sfs_enablesubmission'],
			['text', 'sfs_apikey'],
			'',
			['title', 'sfsverftitle', 'label' => $this->SFSclass->txt('sfs_verification_title')],
			['desc', 'sfsverfdesc', 'label' => $this->SFSclass->txt('sfs_verification_desc')],
			['select', 'sfs_verification_options', [
				'post' => $this->SFSclass->txt('sfs_verification_options_post'),
				'report' => $this->SFSclass->txt('sfs_verification_options_report'),
				'search' => $this->SFSclass->txt('sfs_verification_options_search'),
			], 'multiple' => true],
			['text', 'sfs_verification_options_extra', 'subtext' => $this->SFSclass->txt('sfs_verification_options_extra_subtext')],

			'',
			['select', 'sfs_verOptionsMembers', [
				'post' => $this->SFSclass->txt('sfs_verification_options_post'),
				'search' => $this->SFSclass->txt('sfs_verification_options_search'),
			], 'multiple' => true],
			['text', 'sfs_verOptionsMemExtra', 'subtext' => $this->SFSclass->txt('sfs_verification_options_extra_subtext')],
			['int', 'sfs_verfOptMemPostThreshold'],
			'',
			['check', 'sfs_log_debug'],
		];
	}

	/**
	 * Save the actual settings.
	 *
	 * @param array $config_vars The configuration variables..
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.5.0
	 */
	private function saveConfiguration(array $config_vars): void
	{
		// Turn the defaults off.
		$this->SFSclass->unloadDefaults();
		checkSession();

		// If we are automatically banning IPs, make sure we have a ban group.
		if (isset($_POST['sfs_ipcheck_autoban']) && empty($this->modSettings['sfs_ipcheck_autoban_group'])) {
			$this->SFSclass->loadSources('SFS-Bans');
			SFSB::AdminCreateBanGroup(true);
		}

		saveDBSettings($config_vars);

		writeLog();
		redirectexit($this->adminPageURL);
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
	 * @version 1.5.0
	 * @since 1.4.0
	 * @uses integrate_manage_registrations - Hook SMF2.1
	 */
	public static function hook_manage_registrations(array &$subActions): bool
	{
		return self::selfClass()->AddToRegCenterMenu($subActions);
	}

	/**
	 * Add the SFS Test to the regcenter menu.
	 *
	 * @param array $log_functions All possible log functions.
	 *
	 * @CalledIn SMF 2.1
	 * @See SFSA::startupTest
	 * @version 1.5.0
	 * @since 1.4.0
	 */
	public function AddToRegCenterMenu(array &$subActions): bool
	{
		// Add our logs sub action.
		$subActions['sfstest'] = ['SFSA::startupTest', 'admin_forum'];

		if (isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'sfstest' && allowedTo('admin_forum')) {
			$this->context['sub_action'] = 'sfstest';
		}

		$this->context[$this->context['admin_menu_name']]['tab_data']['tabs']['sfstest'] = [
			'description' => $this->SFSclass->txt('sfs_admin_test_desc'),
		];

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
	 */
	public static function startupTest(bool $return_config = false): array
	{
		return self::selfClass()->loadTestAPI($return_config);
	}

	/**
	 * Actually do the test API.
	 * This has a $return_config just for simply complying with properly for searching the admin panel.
	 *
	 * @param bool $return_config If true, returns empty array to prevent breaking old SMF installs.
	 *
	 * @api
	 * @CalledIn SMF2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.4.0
	 * @uses hook_manage_registrations - Hook SMF2.1
	 * @uses setupModifyModifications - Injected SMF2.0
	 */
	public function loadTestAPI(bool $return_config = false): array
	{
		// No Configs.
		if ($return_config) {
			return [];
		}

		$this->context['token_check'] = 'sfs_testapi';
		$this->SFSclass->loadLanguage();

		// The reuslts output.
		$this->context['test_sent'] = isset($_POST['send']);
		$this->context['sfs_checks'] = $this->loadTestApiChecks();

		// Sending the data?
		if ($this->context['test_sent']) {
			$this->peformTestApiCheck();
		}

		// Load our template.
		$this->SFSclass->loadTemplate('StopForumSpam');
		$this->context['sub_template'] = 'sfsa_testapi';

		$this->context['sfs_test_url'] = $this->adminTestURL;

		if (!$this->SFSclass->createToken($this->context['token_check'], 'post')) {
			unset($this->context['token_check']);
		}

		return [];
	}

	/**
	 * Load the Test API Checks.
	 *
	 * @api
	 * @CalledIn SMF2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.5.0
	 * @return array SFS Checks
	 */
	private function loadTestApiChecks(): array
	{
		return [
			'username' => [
				0 => [
					'enabled' => !empty($this->modSettings['sfs_usernamecheck']),
					'value' => !empty($_POST['username']) ? $this->smcFunc['htmlspecialchars']($_POST['username']) : $this->user_info['name'],
					'results' => null,
				],
			],
			'email' => [
				0 => [
					'enabled' => !empty($this->modSettings['sfs_emailcheck']),
					'value' => !empty($_POST['email']) ? $this->smcFunc['htmlspecialchars']($_POST['email']) : $this->user_info['email'],
					'results' => null,
				],
			],
			'ip' => [
				0 => [
					'enabled' => !empty($this->modSettings['sfs_ipcheck']),
					'value' => !empty($_POST['ip']) ? $this->smcFunc['htmlspecialchars']($_POST['ip']) : $this->user_info['ip'],
					'results' => null,
				],
			],
		];
	}

	/**
	 * Load the Test API Checks.
	 *
	 * @api
	 * @CalledIn SMF2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.5.0
	 * @return array SFS Checks
	 */
	private function peformTestApiCheck(): void
	{
		checkSession();
		$this->SFSclass->validateToken($this->context['token_check'], 'post');

		$username = $this->smcFunc['htmlspecialchars']($_POST['username']);
		$email = $this->smcFunc['htmlspecialchars']($_POST['email']);
		$ip = $this->smcFunc['htmlspecialchars']($_POST['ip']);

		$response = $this->SFSclass->SendSFS([
			['username' => $username],
			['email' => $email],
			['ip' => $ip],
		], 'test');

		// No checks found? Can't do this.
		if (empty($response) || !is_array($response) || empty($response['success'])) {
			$this->context['test_api_error'] = $this->SFSclass->txt('sfs_request_failure_nodata');
		} else {
			// Parse all the responses out.
			foreach ($this->context['sfs_checks'] as $key => &$res) {
				$res[0] += $response[$key][0] ?? [];
			}
		}
	}
}
