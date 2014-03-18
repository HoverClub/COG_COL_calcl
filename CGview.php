<?php

// This code allows viewing-only of ALL design data that has been saved in the database

// we use SESSION to pass data to the graph PHP script
// POST and GET both have issues with data!
// session has already been started by SSI.php!!!
// therefore the graph file MUST also use SSI.PHP!!!

// ######################### REQUIRE BACK-END ############################

global $smcFunc, $user_info, $sourcedir, $boardurl, $boarddir;
//require($sourcedir . '/phpgraphlib.php'); // graph drawing library
// hoverclub globals

global $err_array, $data; // neede to make sure they are available in functions defined here!
	
require($sourcedir . '/hcc_defs.php');

include('subs_input.php');

if (!in_array($hcc_official , $user_info['groups']))
{
	echo "Sorry, you can't use the hovercraft design viewer unless you are a club official!";
	return;
}

	// static variable definitions:
	$roAir = '1.2266'; // air density
	$roWater = '1025';  // water density
	$showtable = true ; // show detailed drag table 


	// get a list of saved data for ALL users
	$result = $smcFunc['db_query']('', "
		SELECT CONCAT(craftName, ' : ', member_name, ' : ', FROM_UNIXTIME(date,'%e %b %y')) AS fullName, userid 
		FROM {db_prefix}hcb_craftdesign as craft 
		LEFT JOIN {db_prefix}members AS members 
			ON members.id_member=craft.userid
		ORDER BY craft.date DESC");
	while ($res = $smcFunc['db_fetch_assoc']($result)) 
		$craftList[$res['fullName']] = $res['userid']; // key is full name (member_name:date:craftname), data is userid

	if (isset($_POST['selName']))
	{
		$selName = trim($_POST['selName']);
		if (isset($_POST['delete']))
		{
			$selArray = explode(' : ',$selName); // split into membername and craftname
			$selUserId = $craftList[$selName]; // get userid for this craft
			// delete the currently selected craft
			$result = $smcFunc['db_query']('', '
				DELETE FROM {db_prefix}hcb_craftdesign 
				WHERE craftName = \'' . $smcFunc['db_escape_string']($selArray[0]) . '\' 
					AND userid = \'' . $selUserId . '\'');
			unset($craftList[$selName]); // delete from list
			reset($craftList); // point to first entry
			$selName = key($craftList); // use first listed craft as default
		}
	}
	else
	{
		reset($craftList); // point to first entry
		$selName = key($craftList); // use first listed craft as default
	}

	$selArray = explode(' : ',$selName); // split into membername and craftname
	$selUserId = $craftList[$selName]; // get userid for this craft
	if (empty($selUserId))
	{
		echo "FATAL ERROR - unable to locate data for the $selArray[0] craft!";
		return;
	}

	// select the correct data set for this craft
	$result = $smcFunc['db_query']('', 
		'SELECT * FROM {db_prefix}hcb_craftdesign 
			WHERE userid = ' . $selUserId . ' 
			AND craftName = \'' . $smcFunc['db_escape_string']($selArray[0]) . '\'');
	$data = $smcFunc['db_fetch_assoc']($result);  // load data for this design
	if (empty($data))
	{
		echo "FATAL ERROR - unable to locate data for the $selArray[0] craft!";
		return;
	}
	foreach (array('hull', 'engines', 'misc', 'pass_forward', 'pass_rearward') as $group)
		$data[$group] = json_decode($data['group']);
	$showresult = true; // do the calcs and display the result as we have got valid saved data


// ------------------------ html output ---------------------------
// ------------------------ html output ---------------------------

	$dis = ' disabled="disabled"'; // disabled tag for input and select boxes
	
	require('calc_output.php');
?>
