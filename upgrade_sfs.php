<?php
/**
 * Upgrade logic Stop Forum Spam
 * @package StopForumSpam
 * @author SleePy <sleepy @ simplemachines (dot) org>
 * @copyright 2024
 * @license 3-Clause BSD https://opensource.org/licenses/BSD-3-Clause
 * @version 1.5.7
 */

global $modSettings;

// If we have found SSI.php and we are outside of SMF, then we are running standalone.
if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
{
	require_once(dirname(__FILE__) . '/SSI.php');
}
// If we are outside SMF and can't find SSI.php, then throw an error
elseif (!defined('SMF'))
{
	die('<b>Error:</b> Cannot uninstall - please verify you put this file in the same place as SMF\'s SSI.php.');
}

// If you upgraded from 2.1.0,
if (version_compare(SMF_VERSION, '2.1.0', '>=') && substr($modSettings['sfs_verification_options'], 0, 2) === 'a:') {
	$converted = json_encode(safe_unserialize($modSettings['sfs_verification_options']));
	
	if (!empty($converted)) {
		updateSettings(['sfs_verification_options' => $converted]);
	}
}