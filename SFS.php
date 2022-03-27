<?php

/**
 * The Main class for Stop Forum Spam
 * @package StopForumSpam
 * @author SleePy <sleepy @ simplemachines (dot) org>
 * @copyright 2019
 * @license 3-Clause BSD https://opensource.org/licenses/BSD-3-Clause
 * @version 1.2
 */
class SFS
{
	/**
	 * @var array Our settings information used on saving/changing settings.
	 */
	private $changedSettings = array();
	private $extraVerificationOptions = array();

	/**
	 * @var string Name of the software and its version.  This is so we can branch out from the same base.
	 */
	private $softwareName = 'smf';
	private $softwareVersion = '2.1';

	/**
	 * @var array The block Types.
	 */
	private $blockTypeMap = array(
		'username' => 1,
		'email' => 2,
		'ip' => 3
	);

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
		global $smcFunc, $sourcedir;

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
	 * @version 1.2
	 * @since 1.0
	 * @uses create_control_verification - Hook SMF2.0
	 * @uses integrate_create_control_verification_test - Hook SMF2.1
	 * @return bool True is success, no other bool is expeicifcly defined yet.
	 */
	public function checkVerificationTest(array $thisVerification, array &$verification_errors): bool
	{
		global $user_info, $modSettings;

		// Registration is skipped as we process that differently.
		if ($thisVerification['id'] == 'register')
			return true;

		// Get our options data.
		$options = $this->getVerificationOptions();

		// Key => Extended checks.
		$verificationMap = array(
			'post' => true,
			'report' => true,
			'search' => $user_info['is_guest'] || empty($user_info['posts']) || $user_info['posts'] < $modSettings['sfs_verfOptMemPostThreshold'],
		);

		foreach ($verificationMap as $key => $extendedChecks)
			if ($thisVerification['id'] == $key && in_array($key, $options))
				return call_user_func(array($this, 'checkVerificationTest' . ucfirst($key)));

		// Others areas.  We have to play a guessing game here.
		return $this->checkVerificationTestExtra($thisVerification);
	}

	/**
	 * The hook to setup profile menu.
	 *
	 * @param array $profile_areas All the profile areas.
	 *
	 * @api
	 * @CalledIn SMF 2.1
	 * @See SFS::setupProfileMenu
	 * @version 1.1
	 * @since 1.1
	 * @uses integrate_pre_profile_areas - Hook SMF2.1
	 * @return void the passed $profile_areas is modified.
	 */
	public static function hook_pre_profile_areas(array &$profile_areas): void
	{
		global $smcFunc;
		$smcFunc['classSFS']->setupProfileMenu($profile_areas);
	}

	/**
	 * The hook to setup profile menu.
	 *
	 * @param array $profile_areas All the profile areas.
	 *
	 * @api
	 * @CalledIn SMF 2.1
	 * @version 1.1
	 * @since 1.1
	 * @uses integrate_pre_profile_areas - Hook SMF2.1
	 * @return void the passed $profile_areas is modified.
	 */
	public function setupProfileMenu(array &$profile_areas): void
	{
		$profile_areas['info']['areas']['sfs'] = [
			'label' => $this->txt('sfs_profile'),
			'file' => 'SFS.php',
			'icon' => 'sfs.webp',
			'function' => 'SFS::ProfileTrackSFS',
			'permission' => [
				'own' => ['moderate_forum'],
				'any' => ['moderate_forum'],
			],
		];

		// SMF 2.0 can't call objects or classes.
		if ($this->versionCheck('2.0', 'smf'))
		{
			function ProfileTrackSFS20(int $memID)
			{
				return SFS::ProfileTrackSFS($memID);
			}
			$profile_areas['info']['areas']['sfs']['function'] = 'ProfileTrackSFS20';
		}
	}

	/**
	 * The caller for a profile check.
	 *
	 * @param int $memID The id of the member we are checking.
	 *
	 * @api
	 * @CalledIn SMF 2.1
	 * @version 1.1
	 * @since 1.1
	 * @return void the passed $profile_areas is modified.
	 */
	public static function ProfileTrackSFS(int $memID): void
	{
		global $smcFunc;
		$smcFunc['classSFS']->TrackSFS($memID);
	}

	/**
	 * The caller for a profile check.
	 *
	 * @param int $memID The id of the member we are checking.
	 *
	 * @api
	 * @CalledIn SMF 2.1
	 * @version 1.1
	 * @since 1.1
	 * @return void the passed $profile_areas is modified.
	 */
	public function TrackSFS(int $memID): void
	{
		global $user_profile, $context, $smcFunc, $scripturl, $modSettings, $sourcedir;

		isAllowedTo('moderate_forum');

		// We need this stuff.
		$context['sfs_allow_submit'] = !empty($modSettings['sfs_enablesubmission']) && !empty($modSettings['sfs_apikey']);
		$context['token_check'] = 'sfs_submit-' . $memID;
		$cache_key = 'sfs_check_member-' . $memID;

		// Are we submitting this?
		if ($context['sfs_allow_submit'] && (isset($_POST['sfs_submit']) || isset($_POST['sfs_submitban'])))
		{
			checkSession();
			if (!$this->versionCheck('2.0', 'smf'))
				validateToken($context['token_check'], 'post');

			$data = [
				'username' => $user_profile[$memID]['real_name'],
				'email' => $user_profile[$memID]['email_address'],
				'ip_addr' => $user_profile[$memID]['member_ip'],
				'api_key' => $modSettings['sfs_apikey']
			];
			$post_data = http_build_query($data, '', '&');

			// SMF 2.0 has the fetch_web_data in the Subs-Packages, 2.1 it is in Subs.php.
			if ($this->versionCheck('2.0', 'smf'))
				require_once($sourcedir . '/Subs-Package.php');

			// Now we have a URL, lets go get it.
			$result = fetch_web_data('https://www.stopforumspam.com/add', $post_data);

			if (strpos($result, 'data submitted successfully') === false)
				$context['submission_failed'] = $this->txt('sfs_submission_error');
			else if (isset($_POST['sfs_submitban']))
				redirectexit($scripturl . '?action=admin;area=ban;sa=add;u=' . $memID);
			else
				$context['submission_success'] = $this->txt('sfs_submission_success');
		}
	
		// CHeck if we have this info.
		if (($cache = cache_get_data($cache_key)) === null || ($response = $this->decodeJSON($cache, true)) === null)
		{
			$checks = [
				['username' => $user_profile[$memID]['real_name']],
				['email' => $user_profile[$memID]['email_address']],
				['ip' => $user_profile[$memID]['member_ip']],
				['ip' => $user_profile[$memID]['member_ip2']],
			];

			$requestURL = $this->buildServerURL();
			$this->buildCheckPath($requestURL, $checks, 'profile');
			$response = (array) $this->sendSFSCheck($requestURL, $checks, 'profile');
		
			cache_put_data($cache_key, $this->encodeJSON($response), 600);
		}

		// Prepare for the template.
		$context['sfs_overall'] = (bool) $response['success'];
		$context['sfs_checks'] = $response;
		unset($context['sfs_checks']['success']);

		if ($context['sfs_allow_submit'])
		{
			$context['sfs_submit_url'] = $scripturl . '?action=profile;area=sfs;u=' . $memID;
			if (!$this->versionCheck('2.0', 'smf'))
				createToken($context['token_check'], 'post');
			else
				unset($context['token_check']);
		}

		loadTemplate('StopForumSpam');
		$context['sub_template'] = 'profile_tracksfs';
	}

	/**
	 * Test for a standard post.
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.1
	 * @since 1.1
	 * @return bool True is success, no other bool is expeicifcly defined yet.
	 */
	private function checkVerificationTestPost(): bool
	{
		global $user_info, $modSettings;

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
		elseif (empty($user_info['posts']) || $user_info['posts'] < $modSettings['sfs_verfOptMemPostThreshold'])
			return $this->sfsCheck(array(
				array('username' => $user_info['username']),
				array('email' => $user_info['email']),
				array('ip' => $user_info['ip']),
				array('ip' => $user_info['ip2']),
			), 'post');
		else
			return true;
	}

	/**
	 * Test for a report.
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.1
	 * @since 1.1
	 * @return bool True is success, no other bool is expeicifcly defined yet.
	 */
	private function checkVerificationTestReport(): bool
	{
		global $user_info;

		$email = !isset($_POST['email']) ? '' : trim($_POST['email']);

		return $this->sfsCheck(array(
			array('email' => $email),
			array('ip' => $user_info['ip']),
			array('ip' => $user_info['ip2']),
		), 'post');
	}

	/**
	 * Test for a Search.
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.1
	 * @since 1.1
	 * @return bool True is success, no other bool is expeicifcly defined yet.
	 */
	private function checkVerificationTestSearch(): bool
	{
		global $user_info;

		return $this->sfsCheck(array(
			array('ip' => $user_info['ip']),
			array('ip' => $user_info['ip2']),
		), 'search');
	}

	/**
	 * Test for extras, customizations and other areas that we want to tie in.
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.1
	 * @since 1.1
	 * @return bool True is success, no other bool is expeicifcly defined yet.
	 */
	private function checkVerificationTestExtra(array $thisVerification): bool
	{
		global $user_info;

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
					$checks[] = array('username' => $_POST[$searchKey]);
					break;
				}

			// Can we find a email?
			$possibleUserNames = array('email', 'emailaddress', 'email_address');
			foreach ($possibleUserNames as $searchKey)
				if (!empty($_POST[$searchKey]))
				{
					$checks[] = array('email' => $_POST[$searchKey]);
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
	 * @version 1.2
	 * @since 1.0
	 * @return bool True is success, no other bool is expeicifcly defined yet.
	 */
	private function sfsCheck(array $checks, string $area = null): bool
	{
		global $smcFunc, $context, $modSettings;

		$requestURL = $this->buildServerURL();

		// Lets build our data set, always send it as a bulk.
		$singleCheckFound = $this->buildCheckPath($requestURL, $checks, $area);

		// No checks found? Can't do this.
		if (empty($singleCheckFound))
		{
			$this->logAllStats('error', $checks, 'error');
			log_error($this->txt('sfs_request_failure_nodata') . ':' . $requestURL, 'critical');
			return true;
		}

		// Send it off.
		$response = $this->sendSFSCheck($requestURL, $checks, $area);
		$requestBlocked = '';

		$checkMap = array(
			'ip' => !empty($modSettings['sfs_ipcheck']) && !empty($response['ip']),
			'username' => !empty($modSettings['sfs_usernamecheck']) && !empty($response['username']),
			'email' => !empty($modSettings['sfs_emailcheck']) && !empty($response['email'])
		);

		// Run all the checks, if we should.
		foreach ($checkMap as $key => $checkEnabled)
			if (empty($requestBlocked) && $checkEnabled)
				$requestBlocked = call_user_func(array($this, 'sfsCheck_' . $key), $response[$key], $area);

		// Log all the stats?  Debug mode here.
		$this->logAllStats('all', $checks, $requestBlocked);

		// At this point, we have checked everything, do what needs to be done for our good person.
		if (empty($requestBlocked))
			return true;

		// You are a bad spammer, don't tell them what was blocked.
		fatal_error($this->txt('sfs_request_blocked'));
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
	 * @version 1.2
	 * @since 1.1
	 * @return array data we received back, could be a empty array.
	 */
	private function sendSFSCheck(string $requestURL, array $checks, string $area = null): array
	{
		global $sourcedir;

		// SMF 2.0 has the fetch_web_data in the Subs-Packages, 2.1 it is in Subs.php.
		if ($this->versionCheck('2.0', 'smf'))
			require_once($sourcedir . '/Subs-Package.php');

		// Now we have a URL, lets go get it.
		$result = fetch_web_data($requestURL);
		if ($result === false)
		{
			$this->logAllStats('error', $checks, 'failure');
			log_error($this->txt('sfs_request_failure') . ':' . $requestURL, 'critical');
			return true;
		}

		$response = $this->decodeJSON($result);

		// No data received, log it and let them through.
		if (empty($response))
		{
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
	 * @version 1.2
	 * @since 1.1
	 * @return string Request Blocked data if any
	 */
	private function sfsCheck_ip(array $ips, string $area = ''): string
	{
		global $modSettings, $smcFunc;

		$requestBlocked = '';
		foreach ($ips as $check)
		{
			// They appeared! Block this.
			if (empty($check['appears']))
				continue;

			// Ban them because they are black listed?
			$autoBlackListResult = '0';
			if (!empty($modSettings['sfs_ipcheck_autoban']) && !empty($check['frequency']) && $check['frequency'] == 255)
				$autoBlackListResult = $this->BanNewIP($check['value']);

			$this->logBlockedStats('ip', $check);
			$requestBlocked = 'ip,' . $smcFunc['htmlspecialchars']($check['value']) . ',' . ($autoBlackListResult ? 1 : 0);
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
	 * @version 1.2
	 * @since 1.1
	 * @return string Request Blocked data if any
	 */
	private function sfsCheck_username(array $usernames, string $area = ''): string
	{
		global $modSettings, $smcFunc;

		$requestBlocked = '';
		foreach ($usernames as $check)
		{
			// Combine with $area we could also require admin approval above thresholds on things like register.
			if (empty($check['appears']))
				continue;

			$shouldBlock = true;

			// We are not confident that they should be blocked.
			if (!empty($modSettings['sfs_username_confidence']) && !empty($check['confidence']) && $area == 'register' && (float) $modSettings['sfs_username_confidence'] > (float) $check['confidence'])
			{
				$this->logAllStats('all', $check, 'username,' . $smcFunc['htmlspecialchars']($check['value']) . ',' . $check['confidence']);
				$shouldBlock = false;
			}

			// Block them.
			if ($shouldBlock)
			{
				$this->logBlockedStats('username', $check);
				$requestBlocked = 'username,' . $smcFunc['htmlspecialchars']($check['value']) . ',' . $check['confidence'];
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
	 * @version 1.2
	 * @since 1.1
	 * @return string Request Blocked data if any
	 */
	private function sfsCheck_email(array $email, string $area = ''): string
	{
		global $modSettings, $smcFunc;

		$requestBlocked = '';
		foreach ($email as $check)
		{
			if (empty($check['appears']))
				continue;

			$this->logBlockedStats('email', $check);
			$requestBlocked = 'email,' . $smcFunc['htmlspecialchars']($check['value']);
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
	 * @version 1.2
	 * @since 1.0
	 * @return bool True we found something to check, false nothing..  $requestURL will be updated with the new data.
	 */
	private function buildCheckPath(string &$requestURL, array $checks, string $area = null): bool
	{
		global $context, $modSettings;

		$singleCheckFound = false;
		foreach ($checks as $chk)
		{
			foreach ($chk as $type => $value)
			{
				// Hold up, we are not processing this check.
				if (in_array($type, array('email', 'username', 'ip')) && empty($modSettings['sfs_' . $type . 'check']))
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
	 * @version 1.0
	 * @since 1.0
	 * @return bool True is success, no other bool is expeicifcly defined yet.
	 */
	private function logBlockedStats(string $type, array $check): void
	{
		global $smcFunc, $user_info;

		// What type of log is this?
		$blockType = isset($this->blockTypeMap[$type]) ? $this->blockTypeMap[$type] : 99;

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
				$this->encodeJSON($check),
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
	 * @version 1.2
	 * @since 1.0
	 * @return bool True is success, no other bool is expeicifcly defined yet.
	 */
	private function logAllStats(string $type, array $checks, string $DebugMessage): void
	{
		global $modSettings, $smcFunc, $user_info;

		if ($type == 'all' && empty($modSettings['sfs_log_debug']))
			return;

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
	public function decodeJSON(string $requestData): array
	{
		global $smcFunc;

		// Do we have $smcFunc?  It handles errors and logs them as needed.
		if (isset($smcFunc['json_decode']) && is_callable($smcFunc['json_decode']))
			return $smcFunc['json_decode']($requestData, true);
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
	 * json JSON data and return it.
	 * If we have $smcFunc['json_encode'], we use this as it handles errors natively.
	 * For all others, we simply ensure a proper array is returned in the event of a error.
	 *
	 * @param array $requestData A properly formatted json string.
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.1
	 * @since 1.1
	 * @return string The stringified array.
	 */
	public function encodeJSON(array $requestData): string
	{
		global $smcFunc;

		// Do we have $smcFunc?  It handles errors and logs them as needed.
		if (isset($smcFunc['json_encode']) && is_callable($smcFunc['json_encode']))
			return $smcFunc['json_encode']($requestData);
		// Back to the basics.
		else
		{
			$data = @json_encode($requestData);

			// We got a error, return nothing.  Don't log this, not worth it.
			if (json_last_error() !== JSON_ERROR_NONE)
				return null;
			return $data;
		}
	}

	/**
	 * Build the SFS Server URL based on our configuration setup.
	 *
	 * @internal
	 * @link: https://www.stopforumspam.com/usage
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.2
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
		$server = $this->sfsServerMapping()[$modSettings['sfs_region']];

		// Build the base URL, we always use json responses.
		$url = 'https://' . $server['host'] . '/api?json';

		// All the SFS Urls => How we toggle them.
		$sfsMap = array(
			'nobadall' => !empty($modSettings['sfs_wildcard_email']) && !empty($modSettings['sfs_wildcard_username']) && !empty($modSettings['sfs_wildcard_ip']),
			'notorexit' => !empty($modSettings['sfs_tor_check']) && $modSettings['sfs_tor_check'] == 1,
			'badtorexit' => !empty($modSettings['sfs_tor_check']) && $modSettings['sfs_tor_check'] == 2,
		);
		foreach ($sfsMap as $val => $key)
			if (!empty($key))
				$url .= '&' . $val;

		// Maybe only certain wildcards are ignored?
		if (empty($sfsMap['nobadall']))
		{
			$ignoreMap = array(
				'nobadusername' => !empty($modSettings['sfs_wildcard_email']),
				'nobademail' => !empty($modSettings['sfs_wildcard_username']),
				'nobadip' => !empty($modSettings['sfs_wildcard_ip']),
			);

			foreach ($ignoreMap as $val => $key)
				if (!empty($key))
					$url .= '&' . $val;
		}

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
	public function sfsServerMapping($returnType = null)
	{
		// Global list of servers.
		$serverList = array(
			0 => array(
				'region' => 'global',
				'label' => $this->txt('sfs_region_global'),
				'host' => 'api.stopforumspam.org',
			),
			1 => array(
				'region' => 'us',
				'label' => $this->txt('sfs_region_us'),
				'host' => 'us.stopforumspam.org',
			),
			2 => array(
				'region' => 'eu',
				'label' => $this->txt('sfs_region_eu'),
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
	private function getVerificationOptions(): array
	{
		global $user_info, $modSettings;

		$optionsKey = $user_info['is_guest'] ? 'sfs_verification_options' : 'sfs_verOptionsMembers';
		$optionsKeyExtra = $user_info['is_guest'] ? 'sfs_verification_options_extra' : 'sfs_verOptionsMemExtra';

		// Standard options.
		if ($this->versionCheck('2.0', 'smf') && !empty($modSettings[$optionsKey]))
			$options = safe_unserialize($modSettings[$optionsKey]);
		elseif (!empty($modSettings[$optionsKey]))
			$options = $this->decodeJSON($modSettings[$optionsKey]);

		if (empty($options))
			$options = array();

		// Extras.
		if (!empty($modSettings[$optionsKeyExtra]))
		{
			$this->extraVerificationOptions = explode(',', $modSettings[$optionsKeyExtra]);

			if (!empty($this->extraVerificationOptions))
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
			'sfs_verfOptMemPostThreshold' => 5
		);

		// SMF 2.0 is serialized, SMF 2.1 is json.
		$encodeFunc = 'json_encode';
		if ($this->versionCheck('2.0', 'smf'))
			$encodeFunc = 'serialize';

		$defaultSettings['sfs_verification_options'] = $encodeFunc(array('post'));

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

		return true;
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
		global $txt;

		// Load the language if its not here already.
		if (!isset($txt[$key]))
			$this->loadLanguage();

		if (!isset($txt[$key]))
			return '';

		return $txt[$key];
	}

	/**
	 * Create a Ban Group if needed to handle automatic IP bans.
	 * We attempt to use the known ban function to create bans, otherwise we just fall back to a standard insert.
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.2
	 * @since 1.0
	 * @return bool True upon success, false otherwise.
	 */
	public function createBanGroup(bool $noChecks = false): bool
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
				'new_ban_name' => substr($this->txt('sfs_ban_group_name'), 0, 20),
			)
		);
		if ($smcFunc['db_num_rows']($request) == 1)
		{
			$ban_data = $smcFunc['db_fetch_assoc']($request);
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
			'name' => substr($this->txt('sfs_ban_group_name'), 0, 20),
			'cannot' => array(
				'access' => 1,
				'register' => 1,
				'post' => 1,
				'login' => 1,
			),
			'db_expiration' => 'NULL',
			'reason' => $this->txt('sfs_ban_group_reason'),
			'notes' => $this->txt('sfs_ban_group_notes')
		);

		// If we can shortcut this..
		$ban_group_id = 0;
		if (function_exists('insertBanGroup'))
			$ban_group_id = insertBanGroup($ban_info);

		// Fall back.
		if (is_array($ban_group_id) || empty($ban_group_id))
			$ban_group_id = $this->createBanGroupDirect($ban_info);

		// Didn't work? Try again later.
		if (empty($ban_group_id))
			return false;

		updateSettings(array('sfs_ipcheck_autoban_group' => $ban_group_id));
		return true;
	}

	/**
	 * We failed to create a ban group via the API, do it manually.
	 *
	 * @param array $ban_info The ban info
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.2
	 * @since 1.2
	 * @return bool True upon success, false otherwise.
	 */
	public function createBanGroupDirect(array $ban_info): int
	{
		global $smcFunc;

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
		return $smcFunc['db_insert_id']('{db_prefix}ban_groups', 'id_ban_group');
	}

	/**
	 * They have triggered a automatic IP ban, lets do it.
	 * In newer versions we attempt to use more of the APIs, but fall back as needed.
	 *
	 * @param string $ip_address The IP address of the spammer.
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.2
	 * @since 1.0
	 * @return bool True upon success, false otherwise.
	 */
	private function BanNewIP(string $ip_address): bool
	{
		global $smcFunc, $modSettings, $sourcedir;

		// Did we loose our Ban Group? Try to fix this.
		if (!empty($modSettings['sfs_ipcheck_autoban']) && empty($modSettings['sfs_ipcheck_autoban_group']))
			$this->createBanGroup();

		// Still no Ban Group? Bail out.
		if (empty($modSettings['sfs_ipcheck_autoban']) || empty($modSettings['sfs_ipcheck_autoban_group']))
			return false;

		require_once($sourcedir . '/ManageBans.php');

		// If we have it, use the standard function.
		if (function_exists('addTriggers'))
			$result = $this->BanNewIPSMF21($ip_address);
		// Go old school.
		else
			$result = $this->BanNewIPSMF20($ip_address);

		// Did this work?
		if ($result)
		{
			// Log this.  The log will show from the user/guest and ip of spammer.
			logAction('ban', array(
				'ip_range' => $ip_address,
				'new' => 1,
				'source' => 'sfs'
			));

			// Let things know we need updated ban data.
			updateSettings(array('banLastUpdated' => time()));
			updateBanMembers();
		}

		return true;
	}

	/**
	 * Ban a IP with using some functions that exist in SMF 2.1.
	 *
	 * @param string $ip_address The IP address of the spammer.
	 *
	 * @internal
	 * @CalledIn SMF 2.0
	 * @version 1.2
	 * @since 1.2
	 * @return bool True upon success, false otherwise.
	 */
	private function BanNewIPSMF21(string $ip_address): bool
	{
		global $smcFunc, $modSettings;

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

		return true;
	}

	/**
	 * We need to fall back to standard db inserts to ban a user as the functions don't exist.
	 *
	 * @param string $ip_address The IP address of the spammer.
	 *
	 * @internal
	 * @CalledIn SMF 2.0
	 * @version 1.2
	 * @since 1.2
	 * @return bool True upon success, false otherwise.
	 */
	private function BanNewIPSMF20(string $ip_address): bool
	{
		global $smcFunc, $modSettings;

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

		$ban_triggers = array(array(
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
		));

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

		return true;
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
		if (in_array($variable, array('softwareName', 'softwareVersion')))
			return $this->{$variable};
	}
}