<?php
/////// DEBUG ONLY /////////
//include('../SSI.php');
/////// DEBUG ONLY /////////



/*
	Centre of gravity/lift Calculator
	----------------------------------
NOTE: uses same database as the performance calculator
	
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
require('subs_input.php');

// bale out if not a club member
if (!$is_club_member) 
{
	echo $club_join_msg; 
	return true;
}

global $data, $err_array, $selName; 	// declare these as global at next higher level so 
										// it's available to functions in this code!!!
	// static variable definitions:
	$roAir = '1.2266'; // air density
	$roWater = '1025';  // water density
	$showtable = false; // don't show detailed drag table to normal viewers



/*
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
*/


				


	$data['userid'] = $user_info['id'];

	$data['twinFan'] = 'twinFan';
	$data['skirt'] = 'finger';
	$data['directFeed'] = '0';
	$data['prop'] = '0';
	
	// load default craft data first
	$data['hullLength']  = '4';
	$data['hullWidth']  = '1.6';  // note this is the width at the bow!!
	$data['sternChamf'] = '0.4';
	
	// skirt dims
	$data['bowskirtfront'] = '0.1';
	$data['dividerfront'] = '1';
	$data['bowradius'] = '1.1';  // radius of bow skirt
	$data['divradius'] = '1.1';
	$data['contactoffset'] = '0.02';  // contact point offset  inside hard hull
	

/* 
calc'ed by CofG
	$designWeight  = '330';
	$cruiseWeight  = '300';
*/

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
	$data['thrustY'] = '1.2'; // height of thrust line on cushion

	// weights  & positions
	$data['hull'] = array(
		array('qty' => '1', 'title' => 'Bare hull', 'M' => '100', 'X' => '2'),
		array('qty' => '1', 'title' => 'Duct', 'M' => '25', 'X' => '3.5'),
		array('qty' => '1', 'title' => 'Elevator', 'M' => '1', 'X' => '3.9'),
		array('qty' => '1', 'title' => 'Elevator Bars', 'M' => '1', 'X' => '3.8'),
		array('qty' => '2', 'title' => 'Rudder Bars', 'M' => '2.5', 'X' => '3.8'),
		array('qty' => '2', 'title' => 'Rudders', 'M' => '3.5', 'X' => '3.9'),
		);
	$data['engines'] = array(
		array('qty' => '1', 'title' => 'Thrust Engine', 'M' => '60', 'X' => '3.5'),
		array('qty' => '1', 'title' => 'Thrust fan', 'M' => '10', 'X' => '3.7'),
		array('qty' => '1', 'title' => 'Thrust fan hub', 'M' => '2', 'X' => '3.6'),
		array('qty' => '1', 'title' => 'Thrust fan pulleys', 'M' => '2.5', 'X' => '3.6'),
		array('qty' => '1', 'title' => 'Thrust fan frame', 'M' => '8', 'X' => '3.5'),
		array('qty' => '1', 'title' => 'Thrust fan guard', 'M' => '4', 'X' => '3.5'),
		array('qty' => '1', 'title' => 'Lift Engine', 'M' => '33', 'X' => '3'),
		array('qty' => '1', 'title' => 'Lift fan', 'M' => '7', 'X' => '2.7'),
		array('qty' => '1', 'title' => 'Lift fan hub', 'M' => '2', 'X' => '2.7'),
		array('qty' => '1', 'title' => 'Lift fan pulleys', 'M' => '0', 'X' => '2.7'),
		array('qty' => '1', 'title' => 'Lift fan guard', 'M' => '2', 'X' => '2.7'),
		array('qty' => '1', 'title' => 'Engine cover', 'M' => '6', 'X' => '3.5'),
		);
	$data['misc'] = array( 
		array('qty' => '1', 'title' => 'Skirt', 'M' => '100', 'X' => '2'),
		array('qty' => '1', 'title' => 'Windscreen', 'M' => '25', 'X' => '.7'),
		array('qty' => '1', 'title' => 'Instruments', 'M' => '4', 'X' => '1'),
		array('qty' => '1', 'title' => 'Radio', 'M' => '2.5', 'X' => '1'),
		array('qty' => '1', 'title' => 'GPS', 'M' => '2', 'X' => '1.1'),
		array('qty' => '1', 'title' => 'Steering column', 'M' => '1', 'X' => '1.1'),
		array('qty' => '1', 'title' => 'Steering wheel/bars', 'M' => '1', 'X' => '1'),
		array('qty' => '1', 'title' => 'Battery', 'M' => '6', 'X' => '2'),
		);
	$data['pass_forward'] = array(
		array('qty' => '1', 'title' => 'Driver', 'M' => '75', 'X' => '1'),
		array('qty' => '0', 'title' => 'Passenger (next to driver)', 'M' => '75', 'X' => '1'),
		array('qty' => '27', 'title' => 'Fuel (litres)', 'M' => '0.8', 'X' => '3'),
		array('qty' => '1', 'title' => 'Equipment & tools etc.', 'M' => '25', 'X' => '1.5'),
		); 
	$data['pass_rearward'] = array(
		array('qty' => '1', 'title' => 'Driver', 'M' => '75', 'X' => '0.5'),
		array('qty' => '0', 'title' => 'Passengers (next to driver)', 'M' => '75', 'X' => '1'),
		array('qty' => '0', 'title' => 'Passenger (row 2)', 'M' => '75', 'X' => '1.5'),
		array('qty' => '2', 'title' => 'Passenger (row 3)', 'M' => '75', 'X' => '3'),
		array('qty' => '27', 'title' => 'Fuel (litres)', 'M' => '.8', 'X' => '3.1'),
		array('qty' => '1', 'title' => 'Equipment & tools etc.', 'M' => '25', 'X' => '1.5'),
		);

// these will be calculated by the performance section!!!
/*
	$data['thrust_max'] = array('M' => '0', 'Y' => '0');
	$data['thrust_cruise'] = array('M' => '0', 'Y' => '0');
	$data['thrust_min'] = array('M' => '0', 'Y' => '0');
*/

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
	while ($res = $smcFunc['db_fetch_assoc']($result)) 
		$craftList[] = $res['craftName'];

	//check if the calculate button was clicked
	if (isset($_POST['docalc']))
	{
		// validate the form and load the working variables from the form POST data
		$err_array = array(); // array for any validation error strings

		get_post('craftName');
		if ($data['craftName'] == '') 
			$err_array['craftName'] = 'please enter a name for this craft design';
		// check if the user is accidentally over-writing an existing craft type
		if ($selName == 'New' AND in_array($data['craftName'],$craftList))
			$err_array['craftName'] = 'this design already exists in the database - please enter a unique name'; 

		get_post('twinFan','2');
		get_post('skirt','1');
		get_post('directFeed','1');
		get_post('prop','2');
		
		if ($data['prop'] AND $data['twinFan']=='int') 
			$err_array['prop'] = 'You can\'t use a prop for thrust in an integrated craft';

		// validate the form numeric input data
		// note that the order of the checks is importanty as sdome cariables are sued to check others so must be entered first
		
		validatenumber('hullLength','200','1');
		// assume same width if not entered.
		validatenumber('hullWidth',$data['hullLength'],'0.1');
		if ($data['hullWidth'] > $data['hullLength']) 
			$err_array['hullWidth'] = 'Hull width must be LESS than the length!';
		validatenumber('sternChamf', ($data['hullWidth'] / 2));
		
		validatenumber('maxSpeed','100','10');
		validatenumber('frontalArea','100','0.5');
		validatenumber('dragCoeff','1','0.01');
		validatenumber('skirtGap','1','0.001');
		validatenumber('splitterHeight','1','0.05'); 
		
		// feed holes fields are optional unless indirect feed
		if ($data['directFeed']) 
			$data['hole1qty']  = $data['hole2qty'] = $data['hole3qty'] = $data['hole1size'] = $data['hole2size'] = $data['hole3size'] = '';
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
		validatenumber('thrustY','10', '0.6');
		
		validatenumber('tPower','30000');

		// skirt stuff...
		validatenumber('bowskirtfront', $data['hullLength']); 
		validatenumber('dividerfront', $data['hullLength']); 
		validatenumber('bowradius', 1e6, $data['hullWidth'] / 2);
		validatenumber('divradius', 1e6, $data['hullWidth'] /2);
		validatenumber('contactoffset', $data['hullWidth'] / 2);  
//dbug ($_POST);
		// now check the user-entered arrays
		foreach (array('hull', 'engines', 'misc', 'pass_forward', 'pass_rearward') as $group)
		{
			if (isset($_REQUEST[$group]))
			{
				foreach ($_REQUEST[$group] as $index => $row)
				{			
					if (empty($row['qty']))
						unset($_REQUEST [$group][$index]); // remove rows with zero-qty items
					else
					{
						if (!is_numeric($row['qty'])
								OR $row['qty'] > 100)
							$err_array[$group][$index]['qty'] = "Qty must be 1 or more and less than 100";						
						if (!is_numeric($row['M']) 
								OR $row['M'] < 0.1 
								OR $row['M'] > 1000 )
							$err_array[$group][$index]['M'] = "Weight must be greater than 100g and less than 1000Kg";
						if (!is_numeric($row['X']) 
								OR $row['X'] < 0 
								OR $row['X'] > ($data['hullLength'] + 2) )
							$err_array[$group][$index]['X'] = "Distance be greater than zero and less than " . ($data['hullLength'] + 2); // no more than 2m outside hull!!!!
					}
				}
				$data[$group] = array_values($_REQUEST[$group]); // re-index in case we removed some of them
			}
		}
// dbug($err_array);
//dbug($data);
			// if valid save into database for this user
		if (empty($err_array))
		{
			$data['date'] = time(); // update modify date
			$sdata = $data;  // make a copy for save operation
			$sdata = array_filter($sdata); // remove null/false valuse
			// encoded values 
			foreach (array('hull', 'engines', 'misc', 'pass_forward', 'pass_rearward') as $group)
				$sdata[$group] = json_encode(array_values($sdata[$group]));

			$showresult = true; // do the calcs and display the result
			$rep_keys = array_fill_keys(array_keys($sdata),'string'); // column names as keys 	
			$smcFunc['db_insert']('replace',
				"{db_prefix}hcb_craftdesign",
				$rep_keys,
				array_values($sdata),
				''
			);
			$showresult = true; // do the calcs and display the result as we have got valid data
			$craftList[] = $selName = $data['craftName']; // add/use new name
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
				$ndata = $smcFunc['db_fetch_assoc']($result);  // load data for this design
				if (empty($ndata))
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
			// overwrite default values for everything (should only happen 1st time for each design)
			// or if something new is required but hasn't been saved before)
			foreach ($ndata as $key=>$d)
			{
				if (!empty($d))
				{
					if (in_array($key, array('hull', 'engines', 'misc', 'pass_forward', 'pass_rearward')))
						$d = json_decode($d, true); // return assoc array
					$data[$key] = $d;
				}
			}
			$showresult = true; // do the calcs and display the result - we've got valid saved data
		}
	}

// ------------------------ html output ---------------------------
// ------------------------ html output ---------------------------

	$dis = ''; // view only if non-empty
//dbug($data);
	
	require('CGcalc_output.php');

	
//---------------------------------------------------------------------

function dbug($var) { echo '<pre>' . print_r($var,true) . '</pre>'; }
