<?php

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
	'integrate_pre_include' => '$sourcedir/StopForumSpam.php',
	'integrate_pre_load' => 'SFS::hook_pre_load',
	'integrate_admin_areas' => 'SFS::hook_admin_areas',
	'integrate_modify_modifications' => 'SFS::hook_modify_modifications',
	'integrate_register' => 'SFS::hook_register',
	'integrate_manage_logs' => 'SFS::hook_manage_logs'
);

foreach ($hooks as $hook => $func)
	remove_integration_function($hook, $func);