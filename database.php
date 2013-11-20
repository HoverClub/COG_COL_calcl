<?php
if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('SMF'))
	die('<b>Error:</b> Cannot install - please verify you put this in the same place as SMF\'s index.php.');

	global $smcFunc, $boarddir;

	// add the club scheduled task
	$smcFunc['db_query']('',"
		REPLACE INTO {db_prefix}scheduled_tasks
			(id_task, next_time, time_offset, time_regularity, time_unit, disabled, task)
			VALUES (NULL, 0, 4260, 1, 'd', 0, 'hcclub')
  	");

	// members hovercraft register table
	$smcFunc['db_query']('', "
		CREATE TABLE IF NOT EXISTS 
		{db_prefix}hcb_craftreg
		(
		userid INT(10) UNSIGNED NOT NULL, 
		ssreg VARCHAR(6) NOT NULL ,
		name varchar(25) NOT NULL ,
		mmsi varchar(9) NOT NULL ,
		shipname text NOT NULL ,
		regdate INT(10) UNSIGNED NOT NULL,
		expdate INT(10) UNSIGNED NOT NULL,
		color varchar(8) NOT NULL, 
		image varchar(100)
		)
	");


	// create the table craftdesign if it doesn't exist
	$smcFunc['db_query']('', "
		CREATE TABLE IF NOT EXISTS 
		{db_prefix}hcb_craftdesign
		(
		userid INT(10) UNSIGNED NOT NULL, 
		craftName VARCHAR(30) NOT NULL,
		date INT(10) NOT NULL,
		rectShape BOOLEAN,
		hullLength VARCHAR(10) NOT NULL,
		maxSpeed VARCHAR(5) NOT NULL,
		frontalArea VARCHAR(10) NOT NULL,
		dragCoeff VARCHAR(5) NOT NULL,
		skirtGap VARCHAR(5) NOT NULL,
		skirt VARCHAR(6) NOT NULL,
		twinFan VARCHAR(8) NOT NULL,
		splitterHeight VARCHAR(10) NOT NULL,
		directFeed BOOLEAN,
		hole1qty VARCHAR(5) NOT NULL,
		hole1size VARCHAR(5) NOT NULL,
		hole2qty VARCHAR(5) NOT NULL,
		hole2size VARCHAR(5) NOT NULL,
		hole3qty VARCHAR(5) NOT NULL,
		hole3size VARCHAR(5) NOT NULL,
		reserve VARCHAR(3) NOT NULL,
		prop BOOLEAN,
		fanDiam VARCHAR(5) NOT NULL,
		tPower VARCHAR(5) NOT NULL,
		PRIMARY KEY(userid, craftName)
		)
	");
	
	// work out if we need to add extra rows for CoG calc
	$newcols = array(
		'hull'=>'', 
		'engines'=>'', 
		'misc'=>'', 
		'pass_forward'=>'', 
		'pass_rearward'=>'',
		'designWeight'=>'',
		'sternChamf'=>'',
		'bowskirtfront'=>'',
		'dividerfront'=>'',
		'bowradius'=>'',
		'divradius'=>'',
		'contactoffset'=>'',
		'thrustY'=>'',
	);
	$_REQUEST  = $smcFunc['db_query']('', "SELECT * FROM {db_prefix}hcb_craftdesign LIMIT 1");
	$row = $smcFunc['db_fetch_assoc']($_REQUEST );
	foreach ($row as $key => $i)
		unset($newcols[$key]);

	if (!empty($newcols))
		// add extra columns for craft CoG calculator
		$smcFunc['db_query']('', "
			ALTER TABLE {db_prefix}hcb_craftdesign ADD COLUMN(" . implode(' TEXT NOT NULL, ', array_keys($newcols)) . " TEXT NOT NULL)
		");
		
	// get rid of any old columns we don't need - if they exist
	// $row has a list of all column
	if (array_key_exists('cruiseWeight', $row)) 
		$_REQUEST  = $smcFunc['db_query']('', "
				ALTER TABLE {db_prefix}hcb_craftdesign DROP COLUMN cruiseWeight");
	if (array_key_exists('designWeight', $row)) 
		$_REQUEST  = $smcFunc['db_query']('', "
				ALTER TABLE {db_prefix}hcb_craftdesign DROP COLUMN designWeight");
	if (array_key_exists('rectShape', $row))
		$_REQUEST  = $smcFunc['db_query']('', "
				ALTER TABLE {db_prefix}hcb_craftdesign DROP COLUMN rectShape");

	
	// create the hovercraft database table if it doesn't exist
	$result = $smcFunc['db_query']('', "
		CREATE TABLE IF NOT EXISTS 
		{db_prefix}hcb_craftdata
		(
		approved TINYINT,
		userid int NOT NULL, date INT NOT NULL,
		manufacturer VARCHAR(100), website VARCHAR(100), craftType VARCHAR(100), manuf VARCHAR(20), imageurl VARCHAR(100),
		seats INT, hullLength FLOAT, hullWidth FLOAT, hullHeight FLOAT, hoverHeight FLOAT, skirtType TINYINT, emptyWeight INT, hovheight FLOAT,
		liftEngineType VARCHAR(100), liftEnginePower FLOAT, liftType TINYINT, liftDevice TINYINT, 
		thrustEngineType VARCHAR(100), thrustEnginePower FLOAT, thrustType TINYINT, maxStaticThrust FLOAT,
		srcMax TINYINT, maxPayload FLOAT, maxPayloadSpeed FLOAT, maxPayloadConditions TINYINT, maxPayloadConsumption FLOAT, maxPayloadHumpTime SMALLINT,
		srcCruise TINYINT, cruisePayload FLOAT, cruiseSpeed FLOAT, cruiseConditions TINYINT, cruiseConsumption FLOAT, cruiseHumpTime SMALLINT, cruiseNoise25m FLOAT, 
		srcSpeed TINYINT, maxSpeed FLOAT, maxConsumption FLOAT, maxNoise25m FLOAT, maxHillStartAngle FLOAT, 
		PRIMARY KEY(craftType)
		)
	");	
