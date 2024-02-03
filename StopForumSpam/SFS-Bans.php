<?php

/**
 * The Ban class for Stop Forum Spam
 * @package StopForumSpam
 * @author SleePy <sleepy @ simplemachines (dot) org>
 * @copyright 2023
 * @license 3-Clause BSD https://opensource.org/licenses/BSD-3-Clause
 * @version 1.5.0
 */
class SFSB
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
	 * Automatically add a IP ban.
	 *
	 * @param string $ip_address The IP address of the spammer.
	 *
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.5.0
	 * @return bool True upon success, false otherwise.
	 */
	public static function AddNewIpBan(string $ip_address): bool
	{
		return (self::selfClass())->BanNewIP($ip_address);
	}

	/**
	 * Admin wants to create a ban group.
	 *
	 * @param bool $noChecks Skip the sanity checks.
	 *
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.5.0
	 * @return bool True upon success, false otherwise.
	 */
	public static function AdminCreateBanGroup(bool $noChecks = false): bool
	{
		return (self::selfClass())->createBanGroup($noChecks);
	}

	/**
	 * They have triggered a automatic IP ban, lets do it.
	 * In newer versions we attempt to use more of the APIs, but fall back as needed.
	 *
	 * @param string $ip_address The IP address of the spammer.
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.0
	 * @return bool True upon success, false otherwise.
	 */
	public function BanNewIP(string $ip_address): bool
	{
		// Did we loose our Ban Group? Try to fix this.
		if (!empty($this->modSettings['sfs_ipcheck_autoban']) && empty($this->modSettings['sfs_ipcheck_autoban_group'])) {
			$this->createBanGroup();
		}

		// Still no Ban Group? Bail out.
		if (empty($this->modSettings['sfs_ipcheck_autoban']) || empty($this->modSettings['sfs_ipcheck_autoban_group'])) {
			return false;
		}

		$this->SFSclass->loadSources('ManageBans');

		// Did this work?
		if ($this->doBanNewSpammer($ip_address)) {
			// Log this.  The log will show from the user/guest and ip of spammer.
			logAction('ban', [
				'ip_range' => $ip_address,
				'new' => 1,
				'source' => 'sfs',
			]);

			// Let things know we need updated ban data.
			updateSettings(['banLastUpdated' => time()]);
			updateBanMembers();
		}

		return true;
	}

	/**
	 * Create a Ban Group if needed to handle automatic IP bans.
	 * We attempt to use the known ban function to create bans, otherwise we just fall back to a standard insert.
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.0
	 * @return bool True upon success, false otherwise.
	 */
	private function createBanGroup(bool $noChecks = false): bool
	{
		// Is this disabled? Don't do it.
		if (empty($noChecks) && empty($this->modSettings['sfs_ipcheck_autoban'])) {
			return false;
		}

		$id_ban_group = $this->getBanGroup();

		if (!empty($id_ban_group)) {
			updateSettings(['sfs_ipcheck_autoban_group' => $id_ban_group]);

			return true;
		}

		$this->SFSclass->loadSources('ManageBans');

		// Ban Information, this follows the format from the function.
		$ban_info = [
			'name' => substr($this->SFSclass->txt('sfs_ban_group_name'), 0, 20),
			'cannot' => [
				'access' => 1,
				'register' => 1,
				'post' => 1,
				'login' => 1,
			],
			'db_expiration' => 'NULL',
			'reason' => $this->SFSclass->txt('sfs_ban_group_reason'),
			'notes' => $this->SFSclass->txt('sfs_ban_group_notes'),
		];

		// If we can shortcut this..
		$ban_group_id = 0;

		if (function_exists('insertBanGroup')) {
			$ban_group_id = insertBanGroup($ban_info);
		}

		// Fall back.
		if (is_array($ban_group_id) || empty($ban_group_id)) {
			$ban_group_id = $this->createBanGroupDirect($ban_info);
		}

		// Didn't work? Try again later.
		if (empty($ban_group_id)) {
			return false;
		}

		updateSettings(['sfs_ipcheck_autoban_group' => $ban_group_id]);

		return true;
	}

	/**
	 * Try to get the ban group.
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.5.0
	 * @return int The ban group id.
	 */
	private function getBanGroup(): ?int
	{
		// Maybe just got unlinked, if we can find the matching name, relink it.
		$request = $this->smcFunc['db_query'](
			'',
			'
			SELECT id_ban_group
			FROM {db_prefix}ban_groups
			WHERE name = {string:new_ban_name}
			LIMIT 1',
			[
				'new_ban_name' => substr($this->SFSclass->txt('sfs_ban_group_name'), 0, 20),
			],
		);

		if ($this->smcFunc['db_num_rows']($request) == 1) {
			$ban_data = $this->smcFunc['db_fetch_assoc']($request);
			$this->smcFunc['db_free_result']($request);

			if (!empty($ban_data['id_ban_group'])) {
				return $ban_data['id_ban_group'];
			}
		} else {
			$this->smcFunc['db_free_result']($request);
		}

		return null;
	}

	/**
	 * We failed to create a ban group via the API, do it manually.
	 *
	 * @param array $ban_info The ban info
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.2
	 * @return bool True upon success, false otherwise.
	 */
	private function createBanGroupDirect(array $ban_info): int
	{
		$this->smcFunc['db_insert'](
			'',
			'{db_prefix}ban_groups',
			[
				'name' => 'string-20', 'ban_time' => 'int', 'expire_time' => 'raw', 'cannot_access' => 'int', 'cannot_register' => 'int',
				'cannot_post' => 'int', 'cannot_login' => 'int', 'reason' => 'string-255', 'notes' => 'string-65534',
			],
			[
				$ban_info['name'], time(), $ban_info['db_expiration'], $ban_info['cannot']['access'], $ban_info['cannot']['register'],
				$ban_info['cannot']['post'], $ban_info['cannot']['login'], $ban_info['reason'], $ban_info['notes'],
			],
			['id_ban_group'],
			1,
		);

		return $this->smcFunc['db_insert_id']('{db_prefix}ban_groups', 'id_ban_group');
	}

	/**
	 * Do the ban logic.  Handle it for SMF 2.1 or 2.0
	 *
	 * @param string $ip_address The IP address of the spammer.
	 *
	 * @internal
	 * @CalledIn SMF 2.0, SMF 2.1
	 * @version 1.5.0
	 * @since 1.5.0
	 * @return bool True upon success, false otherwise.
	 */
	private function doBanNewSpammer(string $ip_address): bool
	{
		// SMF 2.1 has some easier to use logic.
		if (function_exists('addTriggers')) {
			return $this->BanNewIPSMF21($ip_address);
		}

			return $this->BanNewIPSMF20($ip_address);
	}

	/**
	 * Ban a IP with using some functions that exist in SMF 2.1.
	 *
	 * @param string $ip_address The IP address of the spammer.
	 *
	 * @internal
	 * @CalledIn SMF 2.0
	 * @version 1.5.0
	 * @since 1.2
	 * @return bool True upon success, false otherwise.
	 */
	private function BanNewIPSMF21(string $ip_address): bool
	{
		// We don't call checkExistingTriggerIP as it induces a fatal error.
		$request = $this->smcFunc['db_query'](
			'',
			'
			SELECT bg.id_ban_group, bg.name
			FROM {db_prefix}ban_groups AS bg
			INNER JOIN {db_prefix}ban_items AS bi ON
				(bi.id_ban_group = bg.id_ban_group)
				AND ip_low = {inet:ip_low} AND ip_high = {inet:ip_high}
			LIMIT 1',
			[
				'ip_low' => $ip_address,
				'ip_high' => $ip_address,
			],
		);

		// Alredy exists, bail out.
		if ($this->smcFunc['db_num_rows']($request) != 0) {
			$this->smcFunc['db_free_result']($request);

			return false;
		}

		// The trigger info.
		$triggers = [
			[
				'ip_low' => $ip_address,
				'ip_high' => $ip_address,
			],
		];

		// Add it.
		addTriggers($this->modSettings['sfs_ipcheck_autoban_group'], $triggers);

		return true;
	}

	/**
	 * We need to fall back to standard db inserts to ban a user as the functions don't exist.
	 *
	 * @param string $ip_address The IP address of the spammer.
	 *
	 * @internal
	 * @CalledIn SMF 2.0
	 * @version 1.5.0
	 * @since 1.2
	 * @return bool True upon success, false otherwise.
	 */
	private function BanNewIPSMF20(string $ip_address): bool
	{
		$ip_parts = ip2range($ip_address);

		// Not valid? Get out.
		if (count($ip_parts) != 4) {
			return false;
		}

		// We don't call checkExistingTriggerIP as it induces a fatal error.
		$request = $this->smcFunc['db_query'](
			'',
			'
			SELECT bg.id_ban_group, bg.name
			FROM {db_prefix}ban_groups AS bg
			INNER JOIN {db_prefix}ban_items AS bi ON
				(bi.id_ban_group = bg.id_ban_group)
				AND ip_low1 = {int:ip_low1} AND ip_high1 = {int:ip_high1}
				AND ip_low2 = {int:ip_low2} AND ip_high2 = {int:ip_high2}
				AND ip_low3 = {int:ip_low3} AND ip_high3 = {int:ip_high3}
				AND ip_low4 = {int:ip_low4} AND ip_high4 = {int:ip_high4}
			LIMIT 1',
			[
				'ip_low1' => $ip_parts[0]['low'],
				'ip_high1' => $ip_parts[0]['high'],
				'ip_low2' => $ip_parts[1]['low'],
				'ip_high2' => $ip_parts[1]['high'],
				'ip_low3' => $ip_parts[2]['low'],
				'ip_high3' => $ip_parts[2]['high'],
				'ip_low4' => $ip_parts[3]['low'],
				'ip_high4' => $ip_parts[3]['high'],
			],
		);

		// Alredy exists, bail out.
		if ($this->smcFunc['db_num_rows']($request) != 0) {
			$this->smcFunc['db_free_result']($request);

			return false;
		}

		$ban_triggers = [[
			$this->modSettings['sfs_ipcheck_autoban_group'],
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
		]];

		$this->smcFunc['db_insert'](
			'',
			'{db_prefix}ban_items',
			[
				'id_ban_group' => 'int', 'ip_low1' => 'int', 'ip_high1' => 'int', 'ip_low2' => 'int', 'ip_high2' => 'int',
				'ip_low3' => 'int', 'ip_high3' => 'int', 'ip_low4' => 'int', 'ip_high4' => 'int', 'hostname' => 'string-255',
				'email_address' => 'string-255', 'id_member' => 'int',
			],
			$ban_triggers,
			['id_ban'],
		);

		return true;
	}
}
