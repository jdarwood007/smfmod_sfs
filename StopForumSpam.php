<?php

/**
 * The Main class for Stop Forum Spam
 * @package StopForumSpam
 * @author SleePy <sleepy @ simplemachines (dot) org>
 * @copyright 2023
 * @license 3-Clause BSD https://opensource.org/licenses/BSD-3-Clause
 * @version 1.5.0
 */
class SFS
{
	/**
	 * @var array Our settings information used on saving/changing settings.
	 */
	private array $changedSettings = [];
	private array $extraVerificationOptions = [];

	/**
	 * @var string Name of the software and its version.  This is so we can branch out from the same base.
	 */
	private string $softwareName = 'smf';
	private string $softwareVersion = '2.1';

	/**
	 * @var array The block Types.
	 */
	private array $blockTypeMap = [
		'username' => 1,
		'email' => 2,
		'ip' => 3,
	];

	/**
	 * @var string The url we are requesting.
	 */
	private string $requestURL = '';

	/**
	 * @var array Default settings.
	 */
	private array $defaultSettings = [
		'sfs_enabled' => 1,
		'sfs_expire' => 90,
		'sfs_emailcheck' => 1,
		'sfs_username_confidence' => 50.01,
		'sfs_region' => 0,
		'sfs_verfOptMemPostThreshold' => 5,
	];

	/*
	 * SMF variables we will load into here for easy reference later.
	*/
	private ?string $scripturl;
	private array $context;
	private array $smcFunc;
	/* This is array in "theory" only.  SMF sometimes will null this when pulling from cache and causes an error */
	private ?array $modSettings;
	private ?array $user_info;
	private ?array $txt;

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
	 */
	public static function hook_pre_load(): void
	{
		$GLOBALS['smcFunc']['classSFS'] = self::selfClass();

		// SMF 2.0 needs some help.
		if ($GLOBALS['smcFunc']['classSFS']->versionCheck('2.0')) {
			$GLOBALS['smcFunc']['classSFS']->loadSources([
				'SFS-Admin',
				'SFS-Logs',
				'SFS-Profile',
			]);
		}
	}

	/**
	 * Creates a self reference to the SFS Log class for use later.
	 *
	 * @version 1.2
	 * @since 1.2
	 * @return object The SFS Log class is returned.
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
		// Is this SMF 2.0?
		if (!function_exists('loadCacheAccelerator')) {
			$this->softwareVersion = '2.0';
		}

		foreach (['scripturl', 'context', 'smcFunc', 'txt', 'modSettings', 'user_info'] as $f) {
			$this->{$f} = &$GLOBALS[$f];
		}

		// Setup the defaults.
		$this->loadDefaults();
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
	 * @version 1.5.0
	 * @since 1.0
	 * @uses integrate_register - Hook SMF2.1
	 * @uses integrate_register - Hook SMF2.0
	 * @return bool True is success, no other bool is expeicifcly defined yet.
	 */
	private function checkRegisterRequest(array &$regOptions, array &$theme_vars): bool
	{
		// Admins are not spammers.. usually.
		if ($regOptions['interface'] == 'admin') {
			return true;
		}

		// Pass everything and let us handle what options we pass on.  We pass the register_vars as these are what we have cleaned up.
		return $this->sfsCheck([
			['username' => $regOptions['register_vars']['member_name']],
			['email' => $regOptions['register_vars']['email_address']],
			['ip' => $regOptions['register_vars']['member_ip']],
			['ip' => $regOptions['register_vars']['member_ip2']],
		], 'register');
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
	 * @version 1.5.0
	 * @since 1.0
	 * @uses create_control_verification - Hook SMF2.0
	 * @uses integrate_create_control_verification_test - Hook SMF2.1
	 * @return bool True is success, no other bool is expeicifcly defined yet.
	 */
	public function checkVerificationTest(array $thisVerification, array &$verification_errors): bool
	{
		// Registration is skipped as we process that differently.
		if ($thisVerification['id'] == 'register') {
			return true;
		}

		// Get our options data.
		$options = $this->getVerificationOptions();

		// Key => Extended checks.
		$verificationMap = [
			'post' => true,
			'report' => true,
			'search' => $this->user_info['is_guest'] || empty($this->user_info['posts']) || $this->user_info['posts'] < $this->modSettings['sfs_verfOptMemPostThreshold'],
		];

		foreach (array_filter($verificationMap, function ($extendedChecks, $key) use ($thisVerification, $options) {
			return $thisVerification['id'] == $key && in_array($key, $options);
		}, ARRAY_FILTER_USE_BOTH) as $key => $extendedChecks) {
			return call_user_func([$this, 'checkVerificationTest' . ucfirst($key)]);
		}

		// Others areas.  We have to play a guessing game here.
		return $this->checkVerificationTestExtra($thisVerification);
	}

	/**
	 * Test for a standard post.
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.1
	 * @return bool True is success, no other bool is expeicifcly defined yet.
	 */
	private function checkVerificationTestPost(): bool
	{
		// Guests!
		if ($this->user_info['is_guest']) {
			$guestname = !isset($_POST['guestname']) ? '' : trim(normalize_spaces(sanitize_chars($_POST['guestname'], 1, ' '), true, true, ['no_breaks' => true, 'replace_tabs' => true, 'collapse_hspace' => true]));
			$email = !isset($_POST['email']) ? '' : trim($_POST['email']);

			// SMF will take care of these if we are checking them.
			if (!empty($this->modSettings['sfs_emailcheck']) && empty($modSettings['guest_post_no_email']) && empty($email)) {
				return false;
			}

			if (!empty($this->modSettings['sfs_usernamecheck']) && empty($guestname)) {
				return false;
			}

			return $this->sfsCheck([
				['username' => $guestname],
				['email' => $email],
				['ip' => $this->user_info['ip']],
				['ip' => $this->user_info['ip2']],
			], 'post');

		}

		// Members and they don't have enough posts?
		if (empty($this->user_info['posts']) || $this->user_info['posts'] < $this->modSettings['sfs_verfOptMemPostThreshold']) {
			return $this->sfsCheck([
				['username' => $this->user_info['username']],
				['email' => $this->user_info['email']],
				['ip' => $this->user_info['ip']],
				['ip' => $this->user_info['ip2']],
			], 'post');
		}

			return true;
	}

	/**
	 * Test for a report.
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.1
	 * @return bool True is success, no other bool is expeicifcly defined yet.
	 */
	private function checkVerificationTestReport(): bool
	{
		$email = !isset($_POST['email']) ? '' : trim($_POST['email']);

		return $this->sfsCheck([
			['email' => $email],
			['ip' => $this->user_info['ip']],
			['ip' => $this->user_info['ip2']],
		], 'post');
	}

	/**
	 * Test for a Search.
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.1
	 * @return bool True is success, no other bool is expeicifcly defined yet.
	 */
	private function checkVerificationTestSearch(): bool
	{
		return $this->sfsCheck([
			['ip' => $this->user_info['ip']],
			['ip' => $this->user_info['ip2']],
		], 'search');
	}

	/**
	 * Test for extras, customizations and other areas that we want to tie in.
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.1
	 * @return bool True is success, no other bool is expeicifcly defined yet.
	 */
	private function checkVerificationTestExtra(array $thisVerification): bool
	{
		foreach (array_filter($this->extraVerificationOptions, function ($option) use ($thisVerification) {return $thisVerification['id'] == $option; })  as $option) {
			// Always try to send off IPs.
			$checks = [
				['ip' => $this->user_info['ip']],
				['ip' => $this->user_info['ip2']],
			];

			// Can we find a username?
			$possibleUserNames = ['username', 'user_name', 'user', 'name', 'realname'];
			$searchKey = current(array_filter($possibleUserNames, function ($k) {return !empty($_POST[$k]); }));
			$checks[] = ['username' => $_POST[$searchKey]];

			// Can we find a email?
			$possibleEmails = ['email', 'emailaddress', 'email_address'];
			$searchKey = current(array_filter($possibleEmails, function ($k) {return !empty($_POST[$k]); }));
			$checks[] = ['email' => $_POST[$searchKey]];

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
	 * @version 1.5.0
	 * @since 1.0
	 * @return bool True is success, no other bool is expeicifcly defined yet.
	 */
	private function sfsCheck(array $checks, ?string $area = null): bool
	{
		// Send it off.
		$response = $this->SendSFS($checks, $area);

		// No checks found? Can't do this.
		if ($response === []) {
			$this->logAllStats('error', $checks, 'error');
			log_error($this->txt('sfs_request_failure_nodata') . ':' . $this->buildServerURL(), 'critical');

			return true;
		}

		$requestBlocked = '';

		// Are we requiring multiple checks.
		if (!empty($this->modSettings['sfs_required']) && $this->modSettings['sfs_required'] != 'any') {
			$requestBlocked = $this->sfsCheckMultiple($response, $area);
		}
		// Otherwise we will check anything enabled and if any match, its found
		else {
			$requestBlocked = $this->sfsCheckSingle($response, $area);
		}

		// Log all the stats?  Debug mode here.
		$this->logAllStats('all', $checks, $requestBlocked);

		// At this point, we have checked everything, do what needs to be done for our good person.
		if (empty($requestBlocked)) {
			return true;
		}

		// You are a bad spammer, but don't tell them what was blocked.
		fatal_error($this->txt('sfs_request_blocked'), false);
	}

	/**
	 * The caller for a Send check.
	 *
	 * @param array $checks The data we are checking.
	 *
	 * @api
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.4.0
	 * @return array The results of the check.
	 */
	public function SendSFS(array $checks, ?string $area = null): array
	{
		$requestURL = $this->buildServerURL();

		// Lets build our data set, always send it as a bulk.
		$singleCheckFound = $this->buildCheckPath($requestURL, $checks, $area);

		// No checks found? Can't do this.
		if (empty($singleCheckFound)) {
			return [];
		}

		// Send this off.
		return $this->sendSFSCheck($requestURL, $checks, $area);
	}

	/**
	 * Validate the checks for multiple conditions.
	 *
	 * @param array $response All the resposnes we received.
	 * @param string $area The area this is coming from.
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.5.0
	 * @return string The requests that matched as blocked.
	 */
	private function sfsCheckMultiple(array $response, ?string $area = null): string
	{
		// When requiring multiple checks, we require all to match.
		$requiredChecks = explode('|', $this->modSettings['sfs_required']);
		$requestBlocked = '';
		$result = true;

		foreach ($requiredChecks as $key) {
			$test = call_user_func([$this, 'sfsCheck_' . $key], $response[$key] ?? [], $area);
			$requestBlocked .= !empty($test) ? $test . '|' : '';
			$result &= !empty($test);
		}

		// Not all checks passed, so we will allow it.
		if (!$result) {
			return '';
		}

		return $requestBlocked;
	}

	/**
	 * Validate the checks for a single condition.
	 *
	 * @param array $response All the resposnes we received.
	 * @param string $area The area this is coming from.
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.5.0
	 * @return string The request that matched as blocked.
	 */
	private function sfsCheckSingle(array $response, ?string $area = null): string
	{
		$checkMap = [
			'ip' => !empty($this->modSettings['sfs_ipcheck']) && !empty($response['ip']),
			'username' => !empty($this->modSettings['sfs_usernamecheck']) && !empty($response['username']),
			'email' => !empty($this->modSettings['sfs_emailcheck']) && !empty($response['email']),
		];

		$requestBlocked = '';

		foreach ($checkMap as $key => $checkEnabled) {
			if (empty($requestBlocked) && $checkEnabled) {
				$requestBlocked = call_user_func([$this, 'sfsCheck_' . $key], $response[$key], $area);
			}
		}

		return $requestBlocked;
	}

	/**
	 * Send off the request to SFS and receive a response back
	 *
	 * @param string $requestURL The initial url we will send.
	 * @param array $checks All the possible checks we would like to preform.
	 * @param string $area The area this is coming from.
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.1
	 * @return array data we received back, could be a empty array.
	 */
	private function sendSFSCheck(string $requestURL, array $checks, ?string $area = null): array
	{
		// SMF 2.0 has the fetch_web_data in the Subs-Packages, 2.1 it is in Subs.php.
		if ($this->versionCheck('2.0', 'smf')) {
			$this->loadSources('Subs-Package');
		}

		// Now we have a URL, lets go get it.
		$result = fetch_web_data($requestURL);

		if ($result === false) {
			$this->logAllStats('error', $checks, 'failure');
			log_error($this->txt('sfs_request_failure') . ':' . $requestURL, 'critical');

			return true;
		}

		$response = $this->decodeJSON($result);

		// No data received, log it and let them through.
		if (empty($response)) {
			$this->logAllStats('error', $checks, 'failure');
			log_error($this->txt('sfs_request_failure') . ':' . $requestURL, 'critical');

			return true;
		}

		return $response;
	}

	/**
	 * Run checks for IPs
	 *
	 * @param array $ips All the IPs we are checking.
	 * @param string $area If defined the area we are checking.
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.1
	 * @return string Request Blocked data if any
	 */
	private function sfsCheck_ip(array $ips, string $area = ''): string
	{
		$this->loadSources(['SFS-Bans']);

		$requestBlocked = '';

		foreach (array_filter($ips, function ($check) {return !empty($check['appears']); }) as $check) {
			// Ban them because they are black listed?
			$autoBlackListResult = '0';

			if (!empty($this->modSettings['sfs_ipcheck_autoban']) && !empty($check['frequency']) && $check['frequency'] == 255) {
				$autoBlackListResult = SFSB::AddNewIpBan($check['value']);
			}

			$this->logBlockedStats('ip', $check);
			$requestBlocked = 'ip,' . $this->smcFunc['htmlspecialchars']($check['value']) . ',' . ($autoBlackListResult ? 1 : 0);
			break;
		}

		return $requestBlocked;
	}

	/**
	 * Run checks for Usernames
	 *
	 * @param array $usernames All the usernames we are checking.
	 * @param string $area If defined the area we are checking.
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.1
	 * @return string Request Blocked data if any
	 */
	private function sfsCheck_username(array $usernames, string $area = ''): string
	{
		$requestBlocked = '';

		foreach (array_filter($usernames, function ($check) {return !empty($check['appears']); }) as $check) {
			// Combine with $area we could also require admin approval above thresholds on things like register.
			$shouldBlock = true;

			// We are not confident that they should be blocked.
			if (!empty($this->modSettings['sfs_username_confidence']) && !empty($check['confidence']) && $area == 'register' && (float) $this->modSettings['sfs_username_confidence'] > (float) $check['confidence']) {
				$this->logAllStats('all', $check, 'username,' . $this->smcFunc['htmlspecialchars']($check['value']) . ',' . $check['confidence']);
				$shouldBlock = false;
			}

			// Block them.
			if ($shouldBlock) {
				$this->logBlockedStats('username', $check);
				$requestBlocked = 'username,' . $this->smcFunc['htmlspecialchars']($check['value']) . ',' . $check['confidence'];
				break;
			}
		}

		return $requestBlocked;
	}

	/**
	 * Run checks for Email
	 *
	 * @param array $email All the email we are checking.
	 * @param string $area If defined the area we are checking.
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.1
	 * @return string Request Blocked data if any
	 */
	private function sfsCheck_email(array $email, string $area = ''): string
	{
		$requestBlocked = '';

		foreach (array_filter($email, function ($check) {return !empty($check['appears']); }) as $check) {
			$this->logBlockedStats('email', $check);
			$requestBlocked = 'email,' . $this->smcFunc['htmlspecialchars']($check['value']);
			break;
		}

		return $requestBlocked;
	}

	/**
	 * Run checks against the SFS database.
	 *
	 * @param string $requestURL The initial url we will send.
	 * @param array $checks All the possible checks we would like to preform.
	 * @param string $area The area this is coming from.
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.0
	 * @return bool True we found something to check, false nothing..  $requestURL will be updated with the new data.
	 */
	private function buildCheckPath(string &$requestURL, array $checks, ?string $area = null): bool
	{
		$singleCheckFound = false;

		foreach ($checks as $chk) {
			// Hold up, we are not processing this check.
			$chk = array_filter($chk, function ($value, $type) {return !(in_array($type, ['email', 'username', 'ip']) && empty($this->modSettings['sfs_' . $type . 'check'])); }, ARRAY_FILTER_USE_BOTH);

			// No value? Can't do this.
			$chk = array_filter($chk, function ($value) {return !empty($value); });

			foreach ($chk as $type => $value) {
				// Emails and usernames must be UTF-8, Only a issue with SMF 2.0.
				if (!$this->context['utf8'] && ($type == 'email' || $type == 'username')) {
					$requestURL .= '&' . $type . '[]=' . iconv($this->context['character_set'], 'UTF-8//IGNORE', $value);
				} else {
					$requestURL .= '&' . $type . '[]=' . urlencode($value);
				}

				$singleCheckFound = true;
			}
		}

		return $singleCheckFound;
	}

	/**
	 * Log that this was blocked.
	 *
	 * @param string $type Either username, email, or ip.  Anything else gets marked uknown.
	 * @param array $check The check data we are logging.
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.0
	 * @return bool True is success, no other bool is expeicifcly defined yet.
	 */
	private function logBlockedStats(string $type, array $check): void
	{
		$this->smcFunc['db_insert'](
			'',
			'{db_prefix}log_sfs',
			[
				'id_type' => 'int',
				'log_time' => 'int',
				'url' => 'string',
				'id_member' => 'int',
				'username' => 'string',
				'email' => 'string',
				'ip' => 'string',
				'ip2' => 'string',
				'checks' => 'string',
				'result' => 'string',
			],
			[
				$this->blockTypeMap[$type] ?? 99, // Blocked request
				time(),
				$this->smcFunc['htmlspecialchars']($_SERVER['REQUEST_URL']),
				$this->user_info['id'],
				$type == 'username' ? $check['value'] : '',
				$type == 'email' ? $check['value'] : '',
				$type == 'ip' ? $check['value'] : $this->user_info['ip'],
				$this->user_info['ip2'],
				$this->encodeJSON($check),
				'Blocked',
			],
			['id_sfs', 'id_type'],
		);
	}

	/**
	 * Debug logging that this was blocked..
	 *
	 * @param string $type Either error or all, currently ignored.
	 * @param string $DebugMessage Debugging message, sometimes just is error or failure, otherwise a comma separated of what request was blocked.
	 * @param array $check The check data we are logging.
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.0
	 * @return bool True is success, no other bool is expeicifcly defined yet.
	 */
	private function logAllStats(string $type, array $checks, string $DebugMessage): void
	{
		if ($type == 'all' && empty($this->modSettings['sfs_log_debug'])) {
			return;
		}

		$this->smcFunc['db_insert'](
			'',
			'{db_prefix}log_sfs',
			[
				'id_type' => 'int',
				'log_time' => 'int',
				'url' => 'string',
				'id_member' => 'int',
				'username' => 'string',
				'email' => 'string',
				'ip' => 'string',
				'ip2' => 'string',
				'checks' => 'string',
				'result' => 'string',
			],
			[
				0, // Debug type.
				time(),
				$this->smcFunc['htmlspecialchars']($_SERVER['REQUEST_URL']),
				$this->user_info['id'],
				'', // Username
				'', // email
				$this->user_info['ip'],
				$this->user_info['ip2'],
				json_encode($checks),
				$DebugMessage,
			],
			['id_sfs', 'id_type'],
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
	 * @version 1.5.0
	 * @since 1.0
	 * @return array The parsed json string is now an array.
	 */
	public function decodeJSON(string $requestData): array
	{
		// Do we have $smcFunc?  It handles errors and logs them as needed.
		if (isset($this->smcFunc['json_decode']) && is_callable($this->smcFunc['json_decode'])) {
			return $this->smcFunc['json_decode']($requestData, true);
		}
		// Back to the basics.


			$data = @json_decode($requestData, true);

			// We got a error, return nothing.  Don't log this, not worth it.
			if (json_last_error() !== JSON_ERROR_NONE) {
				return [];
			}

			return $data;

	}

	/**
	 * json JSON data and return it.
	 * If we have $smcFunc['json_encode'], we use this as it handles errors natively.
	 * For all others, we simply ensure a proper array is returned in the event of a error.
	 *
	 * @param array $requestData A properly formatted json string.
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.1
	 * @return string The stringified array.
	 */
	public function encodeJSON(array $requestData): string
	{
		// Do we have $smcFunc?  It handles errors and logs them as needed.
		if (isset($this->smcFunc['json_encode']) && is_callable($this->smcFunc['json_encode'])) {
			return $this->smcFunc['json_encode']($requestData);
		}
		// Back to the basics.


			$data = @json_encode($requestData);

			// We got a error, return nothing.  Don't log this, not worth it.
			if (json_last_error() !== JSON_ERROR_NONE) {
				return null;
			}

			return $data;

	}

	/**
	 * Build the SFS Server URL based on our configuration setup.
	 *
	 * @internal
	 * @link: https://www.stopforumspam.com/usage
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.0
	 * @return array The parsed json string is now an array.
	 */
	private function buildServerURL(): string
	{
		// If we build this once, don't do it again.
		if (!empty($this->requestURL)) {
			return $this->requestURL;
		}

		// Get our server info.
		$server = $this->sfsServerMapping()[$this->modSettings['sfs_region']];

		// Build the base URL, we always use json responses.
		$this->requestURL = 'https://' . $server['host'] . '/api?json';

		// All the SFS Urls => How we toggle them.
		$sfsMap = [
			'nobadall' => !empty($this->modSettings['sfs_wildcard_email']) && !empty($this->modSettings['sfs_wildcard_username']) && !empty($this->modSettings['sfs_wildcard_ip']),
			'notorexit' => !empty($this->modSettings['sfs_tor_check']) && $this->modSettings['sfs_tor_check'] == 1,
			'badtorexit' => !empty($this->modSettings['sfs_tor_check']) && $this->modSettings['sfs_tor_check'] == 2,
		];

		// Maybe only certain wildcards are ignored?
		if (empty($sfsMap['nobadall'])) {
			$sfsMap += [
				'nobadusername' => !empty($this->modSettings['sfs_wildcard_email']),
				'nobademail' => !empty($this->modSettings['sfs_wildcard_username']),
				'nobadip' => !empty($this->modSettings['sfs_wildcard_ip']),
			];
		}

		// Do we have to filter out from lastseen?
		if (!empty($this->modSettings['sfs_expire'])) {
			$sfsMap['expire=' . (int) $this->modSettings['sfs_expire']] = true;
		}

		foreach ($sfsMap as $val => $key) {
			if (!empty($key)) {
				$this->requestURL .= '&' . $val;
			}
		}

		return $this->requestURL;
	}

	/**
	 * Setup our possible SFS hosts.
	 *
	 * @internal
	 * @link: https://www.stopforumspam.com/usage
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.0
	 * @return array The list of servers.
	 */
	public function sfsServerMapping($returnType = null)
	{
		// Global list of servers.
		$serverList = [
			0 => [
				'region' => 'global',
				'label' => $this->txt('sfs_region_global'),
				'host' => 'api.stopforumspam.org',
			],
			1 => [
				'region' => 'us',
				'label' => $this->txt('sfs_region_us'),
				'host' => 'us.stopforumspam.org',
			],
			2 => [
				'region' => 'eu',
				'label' => $this->txt('sfs_region_eu'),
				'host' => 'europe.stopforumspam.org',
			],
		];

		// Configs only need the labels.
		if ($returnType == 'config') {
			// array_column does not preserve keys, but this is in order already.
			return array_column($serverList, 'label');
		}

		return $serverList;
	}

	/**
	 * Our possible verification options.
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.0
	 * @return array The list of servers.
	 */
	private function getVerificationOptions(): array
	{
		$optionsKey = $this->user_info['is_guest'] ? 'sfs_verification_options' : 'sfs_verOptionsMembers';
		$optionsKeyExtra = $this->user_info['is_guest'] ? 'sfs_verification_options_extra' : 'sfs_verOptionsMemExtra';

		// Standard options.
		$options = $this->Decode($this->modSettings[$optionsKey] ?? '');

		if (empty($options) || !is_array($options)) {
			$options = [];
		}

		// Extras.
		if (!empty($this->modSettings[$optionsKeyExtra])) {
			$this->extraVerificationOptions = explode(',', $this->modSettings[$optionsKeyExtra]);

			if (!empty($this->extraVerificationOptions)) {
				$options = array_merge($options, $this->extraVerificationOptions);
			}
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
	 * @version 1.5.0
	 * @since 1.0
	 */
	public function loadDefaults(bool $undo = false): bool
	{
		$this->defaultSettings['sfs_verification_options'] = $this->Stringify(['post']);

		// We undoing this? Maybe a save?
		if ($undo) {
			foreach ($this->changedSettings as $key => $value) {
				unset($this->modSettings[$key], $this->changedSettings[$key]);
			}

			return true;
		}

		// Enabled settings.
		foreach ($this->defaultSettings as $key => $value) {
			if (!isset($this->modSettings[$key])) {
				$this->changedSettings[$key] = null;
				$this->modSettings[$key] = $value;
			}
		}

		return true;
	}

	/**
	 * We undo the defaults letting us save the admin page properly.
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.0
	 */
	public function unloadDefaults(): bool
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
	public function versionCheck(array|string $version, string $software = 'smf'): bool
	{
		// We can't do this if the software doesn't match.
		if ($software !== $this->softwareName) {
			return false;
		}

		// Allow multiple versions to pass.
		foreach ((array) $version as $v) {
			if ($v == $this->softwareVersion) {
				return true;
			}
		}

		// No match? False.
		return false;
	}

	/**
	 * A global function for loading our lanague up.
	 * Placeholder to allow easier additional loading or other software/versions to change this as needed.
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.0
	 */
	public function loadLanguage(array|string $languages = 'StopForumSpam'): string
	{
		return loadLanguage(implode('+', (array) $languages));
	}

	/**
	 * A global function for loading $txt strings.
	 *
	 * @param string $key The key of the text string we want to load.
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.1
	 * @since 1.1
	 * @return string The text string.
	 */
	public function txt($key): string
	{
		// Load the language if its not here already.
		if (!isset($this->txt[$key])) {
			$this->loadLanguage();
		}

		if (!isset($this->txt[$key])) {
			return '';
		}

		return $this->txt[$key];
	}

	/*
	 * Wrapper for our encoding function.
	 *
	 * @api
	 * @version 1.5.0
	 * @since 1.5.0
	*/
	private function Stringify($data): string
	{
		$encodeFunc = 'json_encode';

		if ($this->versionCheck('2.0', 'smf')) {
			$encodeFunc = 'serialize';
		}

		return $encodeFunc($data);
	}

	/*
	 * Wrapper for our decoding function.
	 *
	 * @api
	 * @version 1.5.0
	 * @since 1.5.0
	*/
	private function Decode(string $data): ?array
	{
		if (empty($data)) {
			return null;
		}

		if ($this->versionCheck('2.0', 'smf') && !empty($data)) {
			return safe_unserialize($data);
		}

		if (!empty($data)) {
			return $this->decodeJSON($data);
		}
	}

	/*
	 * Wrapper for validateToken.
	 *
	 * @api
	 * @version 1.5.0
	 * @since 1.5.0
	 * @param array $sourcesThe list of additional sources to load.
	*/
	public function createToken($action, $type = 'post'): ?array
	{
		if (!$this->versionCheck('2.0', 'smf')) {
			return createToken($action, $type);
		}

		return null;
	}

	/*
	 * Wrapper for createToken.
	 *
	 * @api
	 * @version 1.5.0
	 * @since 1.5.0
	 * @param array $sourcesThe list of additional sources to load.
	*/
	public function validateToken($action, $type = 'post', $reset = true): bool
	{
		if (!$this->versionCheck('2.0', 'smf')) {
			return validateToken($action, $type, $reset);
		}

		return true;
	}

	/*
	 * Load additional sources files.
	 *
	 * @version 1.5.0
	 * @since 1.5.0
	 * @param array $sourcesThe list of additional sources to load.
	*/
	public function loadSources(array|string $sources): void
	{
		array_map(function ($rs) {
			require_once $GLOBALS['sourcedir'] . DIRECTORY_SEPARATOR . strtr($rs, ['SFS' => 'StopForumSpam' . DIRECTORY_SEPARATOR . 'SFS']) . '.php';
		}, (array) $sources);
	}

	/*
	 * Load additional template files.
	 * There are 2 way to pass multiple languages in.  A single string or an array.
	 *
	 * @version 1.5.0
	 * @since 1.5.0
	 * @calls: $sourcedir/Load.php:loadTemplate
	 * @param $languages array|string The list of languages to load.
	*/
	public function loadTemplate(array|string $templates): void
	{
		array_map(function ($t) {
			loadTemplate($t);
		}, (array) $templates);
	}

	/**
	 * Get some data
	 *
	 * @param string $variable The data we are looking for..
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.2
	 * @since 1.2
	 * @return bool True upon success, false otherwise.
	 */
	public function get(string $variable)
	{
		if (in_array($variable, ['softwareName', 'softwareVersion'])) {
			return $this->{$variable};
		}
	}

	/**
	 * The hook to setup quick buttons menu.
	 *
	 * @param array $profile_areas All the mod buttons.
	 *
	 * @api
	 * @CalledIn SMF 2.1
	 * @version 1.5.0
	 * @since 1.4.0
	 * @uses integrate_prepare_display_context - Hook SMF2.1
	 */
	public static function hook_prepare_display_context(&$output, &$message, $counter): void
	{
		global $smcFunc, $scripturl, $context;

		$output['quickbuttons']['more']['sfs'] = [
			'label' => $smcFunc['classSFS']->txt('sfs_admin_area'),
			'href' => $scripturl . '?action=profile;area=sfs;u=' . $output['member']['id'] . ';msg=' . $output['id'],
			'icon' => 'sfs',
			'show' => $context['can_moderate_forum'],
		];
	}

	/**
	 * We don't do any mod buttons, just use this to inject some CSS.
	 *
	 * @param array $mod_buttons All the mod buttons.
	 *
	 * @api
	 * @CalledIn SMF 2.1
	 * @version 1.4.0
	 * @since 1.4.0
	 * @uses integrate_mod_buttons - Hook SMF2.1
	 */
	public static function hook_mod_buttons(&$mod_buttons): void
	{
		global $settings;

		addInlineCss('.main_icons.sfs::before { background: url(' . $settings['default_images_url'] . '/admin/sfs.webp) no-repeat; background-size: contain;}');
	}
}
