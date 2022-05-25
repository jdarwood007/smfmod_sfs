<?php
/**
 * The Main class for Stop Forum Spam
 * @package StopForumSpam
 * @author SleePy <sleepy @ simplemachines (dot) org>
 * @copyright 2019
 * @license 3-Clause BSD https://opensource.org/licenses/BSD-3-Clause
 */

// If we have found SSI.php and we are outside of SMF, then we are running standalone.
if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif (file_exists(getcwd() . '/SSI.php') && !defined('SMF'))
	require_once(getcwd() . '/SSI.php');
elseif (!defined('SMF')) // If we are outside SMF and can't find SSI.php, then throw an error
	die('<b>Error:</b> Cannot install - please verify you put this file in the same place as SMF\'s SSI.php.');

if (SMF == 'SSI')
	db_extend('packages');

$hooks = array(
	// Main sections.
	'integrate_pre_include' => '$sourcedir/SFS.php',
	'integrate_pre_load' => 'SFS::hook_pre_load',
	'integrate_register' => 'SFS::hook_register',

	// Admin Sections.
	'integrate_admin_include' => '$sourcedir/SFS-Subs-Admin.php',
	'integrate_admin_areas' => 'SFSA::hook_admin_areas',
	'integrate_modify_modifications' => 'SFSA::hook_modify_modifications',
	'integrate_manage_logs' => 'SFSA::hook_manage_logs',

	// Profile Section.
	'integrate_profile_areas' => 'SFS::hook_pre_profile_areas'
);

foreach ($hooks as $hook => $func)
	remove_integration_function($hook, $func);