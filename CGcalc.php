<?php

/*
	Centre of gravity/lift Calculator
	----------------------------------
	
 has new form data been entered?
 		yes->validate the data 
			not valid-> display error messages and the form
			valid->	save it into the database for this user.
			process the graph and display the form
 else (no data)
		any saved database data available?
			no-> display form
			yes-> load it into the form variables
					display graph & form
*/


// ######################### REQUIRE BACK-END ############################
global $smcFunc, $user_info, $sourcedir, $boardurl, $boarddir;
// hoverclub globals
require($sourcedir . '/hcc_defs.php');

// bale out if not a club member
if (!$is_club_member) 
{
	echo $club_join_msg; 
	return true;
}

include('CGsubs_input.php');

global $data, $err_array, $selName; 	// declare these as global at next higher level so 
								// it's available to functions in this code!!!
		// static variable definitions:
		$roAir = '1.2266'; // air density
		$roWater = '1025';  // water density
		$showtable = false; // don;t show detailed drag table to normal viwers
/*
	// create the table craftdesign if it doesn't exist
	$result = $smcFunc['db_query']('', "
		CREATE TABLE IF NOT EXISTS 
		{db_prefix}hcb_craftdesign
		(
		userid INT(10) UNSIGNED NOT NULL, 
		craftName VARCHAR(30) NOT NULL,
		date INT(10) NOT NULL,
		rectShape BOOLEAN,
		hullLength VARCHAR(10) NOT NULL,
		hullWidth VARCHAR(10) NOT NULL,
		designWeight VARCHAR(10) NOT NULL,
		cruiseWeight VARCHAR(10) NOT NULL,
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
*/

	$data['userid'] = $user_info['id'];
	// these values muts be coverted from numeric to string/bool when 
	// reading or writing to the database to maintain compatibility
	$twinFanArray = array('1'=>'twinEng','2'=>'twinFan', '3'=>'int');
	$data['twinFan'] = '2';
	$skirtArray = array('1'=>'finger','2'=>'bag');
	$data['skirt'] = '1';
	$rectShapeArray = array('1'=>true,'2'=>false);
	$data['rectShape'] = '1';
	$directFeedArray = array('1'=>true,'2'=>false);
	$data['directFeed'] = '1';
	$propArray = array('1'=>true,'2'=>false); // prop or fan
	$data['prop'] = '2';
	
	// load default craft data first
	$data['hullLength']  = '3.048';
	$data['hullWidth']  = '1.8228';
	$data['designWeight']  = '330';
	$data['cruiseWeight']  = '300';
	$data['maxSpeed']  = '40';
	$data['frontalArea']  = '3';
	$data['dragCoeff']  = '0.4';
	$data['skirtGap']  = '0.019';
	$data['splitterHeight']  = '0.3';
	$data['hole1qty']  = '56';
	$data['hole1size']  = '0.075';
	$data['hole2qty']  = '10';
	$data['hole2size']  = '0.05';
	$data['hole3qty']  = '5';
	$data['hole3size']  = '0.03';
	$data['reserve'] = '50';
	$data['fanDiam']  = '0.9';
	$data['tPower']  = '21';

	// see if a craft has been selected
	if (isset($_POST['selName']))		
	{
		$selName = trim($_POST['selName']);
		if (isset($_POST['delete']) AND $selName != 'New')
		{
			// delete the currently selected craft
			$result = $smcFunc['db_query']('', 'DELETE FROM {db_prefix}hcb_craftdesign WHERE craftName = \'' . $smcFunc['db_escape_string']($selName) . '\' AND userid = \'' . $user_info['id'] . '\'');
			$selName = 'New';
		}
	}
	else
		$selName = 'New';
	
	// get list of designs for this user
	$craftList = array();
	$result = $smcFunc['db_query']('', 'SELECT craftName FROM {db_prefix}hcb_craftdesign WHERE userid = \'' . $user_info['id'] . '\' ORDER BY craftName');
	while ($res = $smcFunc['db_fetch_assoc']($result)) $craftList[] = $res['craftName'];

	//check if the calculate button was clicked
	if (isset($_POST['docalc']))
	{
		// validate the form and load the working variables from the form POST data
		$err_array = array(); // array for any validation error strings

		get_post('craftName');
		if ($data['craftName'] == '') $err_array['craftName'] = 'please enter a name for this craft design';
		// check if the user is accidentally over writing an existing craft type
		if ($selName == 'New' AND in_array($data['craftName'],$craftList))
			$err_array['craftName'] = 'this design already exists in the database - please enter a unique name'; 

		get_post('twinFan','2');
		get_post('skirt','1');
		get_post('rectShape','1');
		get_post('directFeed','1');
		get_post('prop','2');
		
		if ($propArray[$data['prop']] AND $twinFanArray[$data['twinFan']]=='int') $err_array['prop'] = 'You can\'t use a prop for thrust in an integrated craft';

		// validate the form numeric input data
		validatenumber('hullLength','200','1');
		validatenumber('hullWidth','100','0.1');
		if ($data['hullWidth'] > $data['hullLength']) $err_array['hullWidth'] = 'Hull width must be LESS than the length!';
		
		validatenumber('designWeight','9999','1');
		validatenumber('cruiseWeight','9999','1');
		if ($data['cruiseWeight'] > $data['designWeight']) $err_array['cruiseWeight'] = 'Cruise weight must be less than the maximum design weight';
		
		validatenumber('maxSpeed','100','10');
		validatenumber('frontalArea','100','0.5');
		validatenumber('dragCoeff','1','0.01');
		validatenumber('skirtGap','1','0.001');
		validatenumber('splitterHeight','1','0.05'); 
		
		// feed holes fields are optional unless indirect feed
		if ($directFeedArray[$data['directFeed']]) 
		{
			$data['hole1qty']  = $data['hole2qty'] = $data['hole3qty'] = $data['hole1size'] = $data['hole2size'] = $data['hole3size'] = '';
		}
		else
		{
			validatenumber('hole1qty','300','1'); 
			validatenumber('hole1size','2','0.001'); // must have SOME holes if plenum fed!! 
			if (!empty($_POST['hole2qty']) AND $_POST['hole2qty'] != '0')
			{
				validatenumber('hole2qty','300','1'); 
				validatenumber('hole2size','2','0.001'); 
				if (!empty($_POST['hole3qty']) AND $_POST['hole3qty'] != '0')
				{
					validatenumber('hole3qty','300','1'); validatenumber('hole3size','2','0.001');
				} 
				else {$data['hole3qty']  = '';$data['hole3size'] ='';}
			} 
			else 
				{$data['hole2qty']  = $data['hole2size'] = $data['hole3qty'] = $data['hole3size'] = '';}
		}

		validatenumber('reserve','200');
		validatenumber('fanDiam','10');
		validatenumber('tPower','30000');
	
		// if valid save into database for this user
		if (empty($err_array))
		{
			$showresult = true; // do the calcs and display the result
			$data['date'] = time(); // update modify date
			$data = array_filter($data); // remove null/false valuse
			$ddata = $data ; // make copy
			// convert data types for database save
			$ddata['twinFan'] = $twinFanArray[$data['twinFan']]; // convert value
			$ddata['skirt'] = $skirtArray[$data['skirt']]; // convert value
			$ddata['rectShape'] = $rectShapeArray[$data['rectShape']]; // convert value
			$ddata['directFeed'] = $directFeedArray[$data['directFeed']]; // convert value
			$ddata['prop'] = $propArray[$data['prop']]; // convert value
			
			$rep_keys = array_fill_keys(array_keys($ddata),'string-255'); // array of variable types 	

			$smcFunc['db_insert']('replace',
				"{db_prefix}hcb_craftdesign",
				$rep_keys,
				array_values($ddata),
				''
			);
			$showresult = true; // do the calcs and display the result as we have got valid data
			$result = $smcFunc['db_query']('', " SELECT craftName FROM {db_prefix}hcb_craftdesign WHERE userid = " . $user_info['id']);
			$craftList = array(); // reload list to include new craft design
			while ($res = $smcFunc['db_fetch_assoc']($result)) $craftList[]=$res['craftName'];
			$selName = $data['craftName']; // use new name
			$saved = true;
		}
	}
	else // initial form load OR craft type has been changed from select drop-down list 
	{
		if (!empty($selName) AND ($selName != 'New'))
		{
			// saved data is available so select the correct data set for this craft
			if (in_array($selName,$craftList))
			{
				$result = $smcFunc['db_query']('', 
					' SELECT * FROM {db_prefix}hcb_craftdesign 
						WHERE userid = ' . $user_info['id'] . ' 
						AND craftName = \'' . $smcFunc['db_escape_string']($selName) . '\'');
				$ddata = $smcFunc['db_fetch_assoc']($result);  // load data for this design
							// convert data types for database save
				$data = $ddata; // copy to array
				$data['twinFan'] = array_search($ddata['twinFan'],$twinFanArray); // convert value
				$data['skirt'] = array_search($ddata['skirt'],$skirtArray); // convert value
				$data['rectShape'] = array_search($ddata['rectShape'],$rectShapeArray); // convert value
				$data['directFeed'] = array_search($ddata['directFeed'],$directFeedArray); // convert value
				$data['prop'] = array_search($ddata['prop'],$propArray); // convert value
				if (empty($data))
				{
					echo !"FATAL ERROR - unable to locate the data for the $selName craft!";
					return;
				}
			}
			else 
			{
				echo "FATAL ERROR - unable to locate the data for the $selName craft!";
				return;
			}
			$showresult = true; // do the calcs and display the result as we have got valid saved data
		}
	}

// ------------------------ html output ---------------------------
// ------------------------ html output ---------------------------

	$dis = ''; // view only if non-empty
	
	require('calc_output.php');
?>
