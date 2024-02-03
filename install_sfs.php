<?php
/**
 * The Main class for Stop Forum Spam
 * @package StopForumSpam
 * @author SleePy <sleepy @ simplemachines (dot) org>
 * @copyright 2019
 * @license 3-Clause BSD https://opensource.org/licenses/BSD-3-Clause
 */

// If we have found SSI.php and we are outside of SMF, then we are running standalone.
if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF')) {
	require_once dirname(__FILE__) . '/SSI.php';
} elseif (file_exists(getcwd() . '/SSI.php') && !defined('SMF')) {
	require_once getcwd() . '/SSI.php';
} elseif (!defined('SMF')) { // If we are outside SMF and can't find SSI.php, then throw an error
	die('<b>Error:</b> Cannot install - please verify you put this file in the same place as SMF\'s SSI.php.');
}

if (SMF == 'SSI') {
	db_extend('packages');
}

$table = [
	'table_name' => '{db_prefix}log_sfs',
	'columns' => [
		db_field('id_sfs', 'int', 0, true, true),
		db_field('id_type', 'tinyint', 0),
		db_field('log_time', 'int'),
		db_field('id_member', 'mediumint'),
		db_field('username', 'varchar', 255),
		db_field('email', 'varchar', 255),
		db_field('url', 'varchar', 255),
		db_field('ip', 'varchar', 255),
		db_field('ip2', 'varchar', 255),
		db_field('checks', 'mediumtext'),
		db_field('result', 'mediumtext'),
	],
	'indexes' => [
		[
			'columns' => ['id_sfs'],
			'type' => 'primary',
		],
		[
			'columns' => ['id_type'],
			'type' => 'index',
		],
	],
	'if_exists' => 'ignore',
	'error' => 'fatal',
	'parameters' => [],
];

$smcFunc['db_create_table']($table['table_name'], $table['columns'], $table['indexes'], $table['parameters'], $table['if_exists'], $table['error']);

/*
 * Calculates the proper settings to use in a column.
 *
 * @version 1.2
 * @since 1.0
*/
function db_field($name, $type, $size = 0, $unsigned = true, $auto = false)
{
	$fields = [
		'varchar' => db_field_varchar($size),
		'text' => db_field_text(),
		'mediumtext' => db_field_mediumtext(),
		'tinyint' => db_field_tinyint($unsigned, $auto),
		'smallint' => db_field_smallint($unsigned, $auto),
		'mediumint' => db_field_mediumint($unsigned, $auto),
		'int' => db_field_int($unsigned, $auto),
		'bigint' => db_field_bigint($unsigned, $auto),
	];

	$field = $fields[$type];
	$field['name'] = $name;

	return $field;
}

/*
 * Database Field - varchar.
 *
 * @version 1.2
 * @since 1.2
*/
function db_field_varchar($size = 0)
{
	return [
		'auto' => false,
		'type' => 'varchar',
		'size' => $size == 0 ? 50 : $size,
		'null' => false,
	];
}

/*
 * Database Field - text.
 *
 * @version 1.2
 * @since 1.2
*/
function db_field_text()
{
	return [
		'auto' => false,
		'type' => 'text',
		'null' => false,
	];
}

/*
 * Database Field - mediumtext.
 *
 * @version 1.2
 * @since 1.2
*/
function db_field_mediumtext()
{
	return [
		'auto' => false,
		'type' => 'mediumtext',
		'null' => false,
	];
}

/*
 * Database Field - tinyint.
 *
 * @version 1.2
 * @since 1.2
*/
function db_field_tinyint($unsigned = true, $auto = false)
{
	return [
		'auto' => $auto,
		'type' => 'tinyint',
		'default' => 0,
		'size' => empty($unsigned) ? 4 : 3,
		'unsigned' => $unsigned,
		'null' => false,
	];
}

/*
 * Database Field - small int.
 *
 * @version 1.2
 * @since 1.2
*/
function db_field_smallint($unsigned = true, $auto = false)
{
	return [
		'auto' => $auto,
		'type' => 'smallint',
		'default' => 0,
		'size' => empty($unsigned) ? 6 : 5,
		'unsigned' => $unsigned,
		'null' => false,
	];
}

/*
 * Database Field - mediumn int.
 *
 * @version 1.2
 * @since 1.2
*/
function db_field_mediumint($unsigned = true, $auto = false)
{
	return [
		'auto' => $auto,
		'type' => 'mediumint',
		'default' => 0,
		'size' => 8,
		'unsigned' => $unsigned,
		'null' => false,
	];
}

/*
 * Database Field - int.
 *
 * @version 1.2
 * @since 1.2
*/
function db_field_int($unsigned = true, $auto = false)
{
	return [
		'auto' => $auto,
		'type' => 'int',
		'default' => 0,
		'size' => empty($unsigned) ? 11 : 10,
		'unsigned' => $unsigned,
		'null' => false,
	];
}

/*
 * Database Field - big int.
 *
 * @version 1.2
 * @since 1.2
*/
function db_field_bigint($unsigned = true, $auto = false)
{
	return [
		'auto' => $auto,
		'type' => 'bigint',
		'default' => 0,
		'size' => 21,
		'unsigned' => $unsigned,
		'null' => false,
	];
}
