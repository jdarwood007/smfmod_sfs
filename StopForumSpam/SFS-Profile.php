<?php

/**
 * The Profile class for Stop Forum Spam
 * @package StopForumSpam
 * @author SleePy <sleepy @ simplemachines (dot) org>
 * @copyright 2023
 * @license 3-Clause BSD https://opensource.org/licenses/BSD-3-Clause
 * @version 1.5.0
 */
class SFSP
{
	private $SFSclass = null;

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

	private int $memID;
	private ?array $user_profile;

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
	 */
	public static function hook_pre_profile_areas(array &$profile_areas): void
	{
		(self::selfClass())->setupProfileMenu($profile_areas);
	}

	/**
	 * The hook to setup profile menu.
	 *
	 * @param array $profile_areas All the profile areas.
	 *
	 * @api
	 * @CalledIn SMF 2.1
	 * @version 1.5.0
	 * @since 1.1
	 * @uses integrate_pre_profile_areas - Hook SMF2.1
	 */
	public function setupProfileMenu(array &$profile_areas): void
	{
		$profile_areas['info']['areas']['sfs'] = [
			'label' => $this->SFSclass->txt('sfs_profile'),
			'file' => 'StopForumSpam' . DIRECTORY_SEPARATOR . 'SFS-Profile.php',
			'icon' => 'sfs.webp',
			'function' => 'SFSP::ProfileTrackSFS',
			'permission' => [
				'own' => ['moderate_forum'],
				'any' => ['moderate_forum'],
			],
		];

		// SMF 2.0 can't call objects or classes.
		if ($this->SFSclass->versionCheck('2.0', 'smf')) {
			function ProfileTrackSFS20(int $memID)
			{
				SFSP::ProfileTrackSFS($memID);
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
	 */
	public static function ProfileTrackSFS(int $memID): void
	{
		(self::selfClass())->TrackSFS($memID);
	}

	/**
	 * Setup the User Profile data for later..
	 *
	 * @param int $memID The current profile.
	 *
	 * @api
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.1
	 * @uses integrate_pre_profile_areas - Hook SMF2.1
	 */
	public function loadUser(int $memID): void
	{
		$this->user_profile = $GLOBALS['user_profile'][$memID] ?? null;
		$this->memID = $memID;
	}

	/**
	 * The caller for a profile check.
	 *
	 * @param int $memID The id of the member we are checking.
	 *
	 * @api
	 * @CalledIn SMF 2.1
	 * @version 1.5.0
	 * @since 1.1
	 */
	public function TrackSFS(int $memID): void
	{
		$this->loadUser($memID);

		isAllowedTo('moderate_forum');

		// We need this stuff.
		$this->context['sfs_allow_submit'] = !empty($this->modSettings['sfs_enablesubmission']) && !empty($this->modSettings['sfs_apikey']);
		$this->context['token_check'] = 'sfs_submit-' . $this->memID;
		$cache_key = 'sfs_check_member-' . $this->memID;

		// Do we have a message?
		if (isset($_GET['msg']) && intval($_GET['msg']) > 0) {
			$row = $this->TrackSFSMessage((int) $_GET['msg']);
			$cache_key .= '-msg' . ((int) $_GET['msg']);
		}

		$this->context['reason'] = $this->smcFunc['htmlspecialchars']($row['post_body'] ?? '');

		// Are we submitting this?
		if ($this->context['sfs_allow_submit'] && (isset($_POST['sfs_submit']) || isset($_POST['sfs_submitban']))) {
			checkSession();
			$this->SFSclass->validateToken($this->context['token_check'], 'post');

			$data = [
				'username' => $row['poster_name'] ?? $this->user_profile['real_name'],
				'email' => $row['poster_email'] ?? $this->user_profile['email_address'],
				'ip_addr' => $row['poster_ip'] ?? $this->user_profile['member_ip'],
				'api_key' => $this->modSettings['sfs_apikey'],
			];
			$this->TrackSFSSubmit($data);
		}

		// Check if we have this info.
		if (($cache = cache_get_data($cache_key)) === null || ($response = $this->SFSclass->decodeJSON((string) $cache)) === null) {
			$checks = [
				['username' => $row['poster_name'] ?? $this->user_profile['real_name']],
				['email' => $row['poster_email'] ?? $this->user_profile['email_address']],
				['ip' => $row['poster_ip'] ?? $this->user_profile['member_ip']],
				['ip' => $this->user_profile['member_ip2']],
			];

			$response = (array) $this->SFSclass->SendSFS($checks, 'profile');
			cache_put_data($cache_key, $this->SFSclass->encodeJSON($response), 600);
		}

		// Prepare for the template.
		$this->context['sfs_overall'] = (bool) $response['success'];
		$this->context['sfs_checks'] = $response;
		unset($this->context['sfs_checks']['success']);

		if ($this->context['sfs_allow_submit']) {
			$this->context['sfs_submit_url'] = $this->scripturl . '?action=profile;area=sfs;u=' . $memID;

			if (is_null($this->SFSclass->createToken($this->context['token_check'], 'post'))) {
				unset($this->context['token_check']);
			}
		}

		$this->SFSclass->loadTemplate('StopForumSpam');
		$this->context['sub_template'] = 'profile_tracksfs';
	}

	/**
	 * Get data from a message we are tracking.
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.5.0
	 * @return ?array Poster data.
	 */
	private function TrackSFSMessage(int $id_msg): ?array
	{
		$row = null;

		$request = $this->smcFunc['db_query'](
			'',
			'
			SELECT poster_name, poster_email, poster_ip, body
			FROM {db_prefix}messages
			WHERE id_msg = {int:id_msg}
				AND (
					id_member = {int:id_member}
					OR id_member = 0
				)
				AND {query_see_message_board}
			',
			[
				'id_msg' => $id_msg,
				'id_member' => $this->memID,
				'actor_is_admin' => $this->context['user']['is_admin'] ? 1 : 0,
			],
		);

		if ($this->smcFunc['db_num_rows']($request) == 1) {
			$row = $this->smcFunc['db_fetch_assoc']($request);
			$row['poster_ip'] = inet_dtop($row['poster_ip']);
		}
		$this->smcFunc['db_free_result']($request);

		return $row;
	}

	/**
	 * Get data from a message we are tracking.
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.5.0
	 * @return ?array Poster data.
	 */
	private function TrackSFSSubmit(array $data): void
	{
		$post_data = http_build_query($data, '', '&');

		// SMF 2.0 has the fetch_web_data in the Subs-Packages, 2.1 it is in Subs.php.
		if ($this->SFSclass->versionCheck('2.0', 'smf')) {
			$this->SFSclass->loadSources('Subs-Package');
		}

		// Now we have a URL, lets go get it.
		$result = fetch_web_data('https://www.stopforumspam.com/add', $post_data);

		if ($result === false || strpos($result, 'data submitted successfully') === false) {
			$this->context['submission_failed'] = $this->SFSclass->txt('sfs_submission_error');
		} elseif (isset($_POST['sfs_submitban'])) {
			redirectexit($this->scripturl . '?action=admin;area=ban;sa=add;u=' . $this->memID);
		} else {
			$this->context['submission_success'] = $this->SFSclass->txt('sfs_submission_success');
		}
	}
}
