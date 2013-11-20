<?php
// Hovercraft Specification DataBase
// data entry system used by Club members

// ######################### REQUIRE BACK-END ############################
global $smcFunc, $user_info, $sourcedir, $boardurl, $boarddir;

global $data, $err_array, $selName, $edit, $craftList; 	// declare these as global at next higher level so 
														// they are available to functions in this code!!!

require($sourcedir . '/hcc_defs.php');

// bale out if not a club member
if (!$is_club_member) 
{
	echo $club_join_msg; 
	return true;
}

include('subs_input.php');

/*
	// create the table if it doesn't exist
	$result = $smcFunc['db_query']('', "
		CREATE TABLE IF NOT EXISTS 
		{db_prefix}hcb_craftdata
		(
		approved TINYINT,
		
		userid int NOT NULL, date INT NOT NULL,

		manufacturer VARCHAR(100), website VARCHAR(100), craftType VARCHAR(100), manuf VARCHAR(20), imageurl VARCHAR(100),
		
		seats INT, hullLength FLOAT, hullWidth FLOAT, hullHeight FLOAT, hoverHeight FLOAT, skirtType TINYINT, emptyWeight INT, hovheight FLOAT
		
		liftEngineType VARCHAR(100), liftEnginePower FLOAT, liftType TINYINT, liftDevice TINYINT, 
		
		thrustEngineType VARCHAR(100), thrustEnginePower FLOAT, thrustType TINYINT, maxStaticThrust FLOAT,

		srcMax TINYINT, maxPayload FLOAT, maxPayloadSpeed FLOAT, maxPayloadConditions TINYINT, maxPayloadConsumption FLOAT, maxPayloadHumpTime SMALLINT,
		
		srcCruise TINYINT, cruisePayload FLOAT, cruiseSpeed FLOAT, cruiseConditions TINYINT, cruiseConsumption FLOAT, cruiseHumpTime SMALLINT, cruiseNoise25m FLOAT, 
		
		srcSpeed TINYINT, maxSpeed FLOAT, maxConsumption  NOT NULL, maxNoise25m	FLOAT, maxHillStartAngle FLOAT,  
		
		PRIMARY KEY(craftType)
		)
	");
*/
	$data = array_fill_keys(array(
	'manufacturer','website','manuf','imageurl','seats' ,'hullLength' ,'hullWidth','hullHeight','hoverHeight' 
	,'skirtType','emptyWeight' ,'liftEngineType' 
	,'liftEnginePower','liftType','liftDevice','thrustEngineType' 
	,'thrustEnginePower','thrustType','maxStaticThrust','srcMax' 
	,'maxPayload','maxPayloadSpeed','maxPayloadConditions','maxPayloadConsumption','maxPayloadHumpTime' 
	,'srcCruise','cruisePayload','cruiseSpeed','cruiseConditions','cruiseConsumption','cruiseHumpTime','cruiseNoise25m' 
	,'srcSpeed','maxSpeed','maxConsumption','maxNoise25m','maxHillStartAngle'
		),'');
	$data['userid'] = $user_info['id'];
	$data['craftType'] = '';

	// officials can edit anyones entries and also get auto-approved
	if (in_array($hcc_official , $user_info['groups']))
	{
		$data['approved'] = '1'; // viewable
		$edit = true;
	}
	else
	{
		$data['approved'] = '2'; // hidden
		$edit = false;
	}

	// see if a craft has been selected
	if (!isset($_REQUEST['selName']))		
		$selName = 'New';
	else // selname specified
	{
		$selName = $_REQUEST['selName'];
		if (isset($_POST['delete']) AND $selName != 'New') // non-officials can only delete their own unapproved entries!
		{
			// delete the currently selected craft
			$result = $smcFunc['db_query']('', 'DELETE FROM {db_prefix}hcb_craftdata WHERE craftType = \'' . $smcFunc['db_escape_string']($selName) . '\'' . ($edit ? '' : ' AND userid = \'' . $user_info['id'] . '\' AND approved=\'2\'' ));
			$selName = 'New';
		}
	}
	
	load_list();		// get any saved craft data for this (or all) members first?

	//check if the save button was clicked
	if (isset($_POST['dosave']))
	{	
		// validate the form and load the working variables from the form POST data
		$err_array = array(); // array for any validation error strings

		if ($edit) get_post('approved');
		
		get_post('craftType');
		if ($data['craftType'] == '') $err_array['craftType'] = 'please enter a name for this craft model';
		// check if the user is accidentally over writing an existing craft type
		if ($selName == 'New' AND in_array($data['craftType'],array_keys($craftList)))
			$err_array['craftType'] = 'this craft already exists in the database - please enter a unique name'; 
	
		get_post('manufacturer'); // sanitised name
		if ($data['manufacturer'] == '') $err_array['manufacturer'] = 'please enter the manufacturers name';

		if (empty($_POST['manuf'])) 
			$err_array['manuf'] = 'please select which form(s) this craft is (or was) available in';
		else
			$data['manuf'] = implode(',',$_POST['manuf']); // convert post array to a comma delimited string
		validateurl('website');
		validatenumber('seats','100');

		validateurl('imageurl');
		if ($data['imageurl'] != '' AND !is_array(@getimagesize($data['imageurl'])))
			$err_array['imageurl'] = 'This image URL doesn\'t seem to be valid or the website isn\'t available';

		validatenumber('hullLength','200','1');
		validatenumber('hullWidth','100','0.5');
		validatenumber('hullHeight','10','0.5');
		validatenumber('hoverHeight','10','0.01');
		get_post('skirtType');
		validatenumber('emptyWeight','1000');
		validatenumber('seats','50');
		
		get_post('liftEngineType');
		validatenumber('liftEnginePower','400');
		get_post('liftType');
		get_post('liftDevice');

		get_post('thrustEngineType');
		validatenumber('thrustEnginePower','400','1');
		get_post('thrustType');
		validatenumber('maxStaticThrust','1000');

		get_post('srcMax'); 
		validatenumber('maxPayload','999');
		validatenumber('maxPayloadSpeed','100');
		get_post('maxPayloadConditions');
		validatenumber('maxPayloadConsumption','99');
		validatenumber('maxPayloadHumpTime','999');
		
		get_post('srcCruise'); 
		validatenumber('cruisePayload','999');
		validatenumber('cruiseSpeed','100');
		get_post('cruiseConditions');
		validatenumber('cruiseConsumption','99');
		validatenumber('cruiseHumpTime','999');
		validatenumber('cruiseNoise25m','999');
		
		get_post('srcSpeed'); 
		validatenumber('maxSpeed','100');
		validatenumber('maxConsumption','99');
		validatenumber('maxNoise25m','999');
		
		validatenumber('maxHillStartAngle','90');

		// if valid save into database for this user
		if (empty($err_array))
		{
			$data['date'] = time(); // update modification date
			$data = array_filter($data); // remove null/false valuse
			$rep_keys = array_fill_keys(array_keys($data),'string-255'); // array of variable types 	
			// note that this function executes mysql_escape_string on 
			// data so adds ANOTHER set of backslashes!!!
			$smcFunc['db_insert']('replace',
				'{db_prefix}hcb_craftdata',
				$rep_keys,
				array_values($data),
				''
			);
			load_list();
			$selName = $data['craftType']; // use new name
			$saved = true;
			// send admin notification email IF this user isn't an official!
			if (!($edit))
			{
				$nmessage = 'On ' . date('F j Y',$data['date']) . ' the club member ' . $user_info['name'] . ' (member ID : ' . $user_info['username'] . ') added or modified the entry for the ' .  $data['craftType'] .  ' hovercraft.  Their email address is ' . $user_info['email'] . ' Please verify that the information they have entered is correct and, if it is, make the entry viewable';
				
				require_once($sourcedir. '/Subs-Post.php'); // email
				sendmail('info@hoverclub.org.uk', 'A HoverClub hovercraft database update notification',$nmessage, null, null, false, 2);
				AddMailQueue(true);		// this flushes the email data into the database!
			}
		}
	}
	else // initial form load OR craft type has been changed from select drop-down list 
	{
		if (!empty($selName) AND ($selName != 'New'))
		{
			// saved data is available so select the correct data set for this craft
			if (in_array($selName,array_keys($craftList)))
			{
				$result = $smcFunc['db_query']('', " SELECT * FROM {db_prefix}hcb_craftdata WHERE craftType = '" . $smcFunc['db_escape_string']($selName) . "'");
				$data = $smcFunc['db_fetch_assoc']($result);  // load data for this craft
				if (empty($data)) 
				{
					echo "FATAL ERROR - unable to find $selName craft!";
					return;
				}
			} 
			else 
			{
				echo "FATAL ERROR - unable to locate the data for the $selName craft!";
				return;
			}
		}
	}
//print_r($data);


// ------------------------ html output ---------------------------
// ------------------------ html output ---------------------------
	echo '
<p class="windowbg description" style="padding:0.5em 1em;">';
		if ($edit) echo
		'As a club official you may add new hovercraft to the database or edit existing entries.  You can also approve (make viewable) entries added by club members.
		<br />';
		else
			echo '
		To add a hovercraft in the HoverClub database, it must be possible to buy the craft, planset or kit <strong>at this time</strong>.  All new entries will be approved by a club official before appearing in the list.<br / ><br />You can add a new entry to the hovercraft database below OR edit any unapproved entries you have added.<br />';
	echo	'
</p>
<br /><form name="form" method="post" id="creator">
	<div class="content">
		<dl>';
	// add color to unapproved entries
	$params = array();
	foreach ($craftList as $craft)
		$params[] = ($craft['approved']=='2' ? ' style="color:red;font-weight:bold;"' : '');
		
	doselinput(array_keys($craftList), 'selName', $selName, 'Select craft', 'Select a craft to edit from this list or select "New" to add a new craft model to the database',true, $params);
	
	dotextinput('craftType', 40, 50, 'Craft type and/or model', (isset($data['date']) ? 'Last modified ' . date('r',$data['date']) . ' by ' . $craftList[$data['craftType']]['real_name'] : ''));
	if ($edit) doradioinput('approved', array('1'=>'Viewable','2'=>'Hidden'),'Select whether this entry should be viewable to all visitors - select hidden to make it invisible.  Hidden entries can only be edited by the creator AND any club official, viewable entries can only be edited by club officials','Entry viewable');
	echo '
		<dt>
			<div align="center">
				<input value="Save Data" type="submit" name="dosave" value="topsave" class="button_submit"/>';
			if (!empty($err_array)) 
				echo '<br /><p><div style="font-family:Verdana; font-size:12px; color:red;">There are errors in the data you have entered - they are marked in red above</div></p>'; 
			elseif (isset($saved)) 
				echo '<p><div style="font-family:Verdana; font-size:12px; color:green;">The data for ' . ucfirst($data['craftType']) . ' has been saved.</div></p>';
			echo '
			</div>
		</dt>
		<dd>';
		if ($selName != '' AND $selName!='New')
			echo '
			<input type="submit" class="button_submit" style="color:red;" name="delete" value="Delete this craft" onclick="return (confirm(\'Are you sure you want to delete the entry for ' . $selName . '?\'));"/>';
		echo '
		</dd>';
	echo '</dl>
		<hr class=!"hrcolor" size="1" />

		<dl>
			<dd><strong>BASIC SPECIFICATIONS</strong></dd>
			<dt></dt>
		</dl>
		<dl>';
	dotextinput('manufacturer', 40, 100, 'Manufacturer', 'Enter the name of the manufacturer or supplier');
	dotextinput('website', 40, 100, 'Web Site URL', 'Manufacturers web site');
	docheckinput('manuf', array('Factory built','Plan set','Kit','Partial kit','Other','No longer available',), 'You can select more than one', 'Availability', true);
	dotextinput('hullLength', 10, 10, 'Craft length', 'Hard structure length', 'metres');
	dotextinput('hullWidth', 10, 10, 'Craft width', 'Hard structure width (off-cushion)', 'metres');
	dotextinput('hullHeight', 10, 10, 'Craft Height', 'Minimum height off cushion.', 'metres');
	dotextinput('hoverHeight', 10, 10, 'Hover height', 'Hard hull clearnce - the minimum height between the underside of the hull and the surface when the craft is on full hover.', 'metres');
	dotextinput('emptyWeight', 10, 10, 'Empty Craft Weight', 'Weight without fuel or any payload.', 'Kg');
	dotextinput('seats',12,2,'Number of seats','Maximum number of occupants');
	dotextinput('imageurl',40,100,'Picture of craft','URL of picture of the craft (right click an image on a web site and "copy image URL/address/location" then paste it here)');
	if ($data['imageurl']!='')
		echo '
			<dt>
			</dt>
			<dd>
				<img src="' . $data['imageurl'] . '" height="100" />
			</dd>';
	echo '</dl>
		<hr class=!"hrcolor" size="1" />

		<dl>
			<dd><strong>LIFT SYSTEM</strong></dd>
			<dt></dt>
		</dl>
		<dl>';
	doradioinput('skirtType',array('Segmented-Finger','Bag','Loop & Finger'),'', 'Skirt Type',true);
	dotextinput('liftEngineType',40,100,'Lift engine','Make and/or model of lift engine (leave blank if single engine craft)');
	dotextinput('liftEnginePower',6,6,'Lift engine power output','','HP');
	doradioinput('liftType',array('Integrated','Dedicated lift engine', 'Shares thrust engine',' Other type'), '', 'Lift drive type',false);
	doradioinput('liftDevice',array('Axial Fan','Centrifugal Fan','Other type'),'','Lift air device',true);
	echo '</dl>

		<hr class=!"hrcolor" size="1" />
		<dl>
			<dd><strong>THRUST SYSTEM</strong></dd>
			<dt></dt>
		</dl>
		<dl>';
	dotextinput('thrustEngineType',40,100,'Thrust engine','Make and/or model of main or thrust engine');
	dotextinput('thrustEnginePower',6,6,'Engine power output','','HP');
	doradioinput('thrustType',array('Fan','Propeller','Other'),'', 'Thrust Device',false);
	dotextinput('maxStaticThrust',6,6,'Max. static thrust','','lbs');
	echo '</dl>
		<hr class=!"hrcolor" size="1" />

		<dl>
			<dd><strong>FULL LOAD PEFORMANCE</strong></dd>
			<dt></dt>
		</dl>
		<dl>';
	doSrcRadio('srcMax');
	dotextinput('maxPayload',6,6,'Max. Payload (people & gear)','The absolute max. payload permitted','Kg');
	dotextinput('maxPayloadSpeed',6,6,'Max. speed with Max. Payload','Maximum speed with full load','MPH');
	doBeaufort('maxPayloadConditions','Max. Beaufort sea state at Max. Payload - including when off-cushion');
	dotextinput('maxPayloadConsumption',6,6,'Fuel consumption at Max. Payload','gallons per hour', 'GPH');
	dotextinput('maxPayloadHumpTime',4,4,'Hump time','Time to get over hump in max. operating state (see above) with Max Payload','seconds');
	echo '</dl>
		<hr class=!"hrcolor" size="1" />

		<dl>
			<dd><strong>CRUISE LOAD PEFORMANCE</strong></dd>
			<dt></dt>
		</dl>
		<dl>';
	doSrcRadio('srcCruise');
	dotextinput('cruisePayload',6,6,'cruise. Payload (people & gear)','The absolute max. payload permitted','Kg');
	dotextinput('cruiseSpeed',6,6,'Max. speed with cruise payload','Maximum speed with full load','MPH');
	doBeaufort('cruiseConditions','Max. Beaufort sea state at cruise payload - including when off-cushion');
	dotextinput('cruiseConsumption',6,6,'Fuel consumption at cruise payload','gallons per hour', 'GPH');
	dotextinput('cruiseHumpTime',4,4,'Hump time','Time to get over hump in cruise operating state (see above) with cruise payload','seconds');
	dotextinput('cruiseNoise25m',4,4,'Max. noise level at cruise speed @ 25m','','dBA');
	echo '</dl>
		<hr class=!"hrcolor" size="1" />

		<dl>
			<dd><strong>MAX. PEFORMANCE</strong></dd>
			<dt></dt>
		</dl>
		<dl>';
	doSrcRadio('srcSpeed');
		dotextinput('maxSpeed',6,6,'Maximum possible speed','','MPH');
		dotextinput('maxConsumption',6,6,'Fuel consumption at maximum speed','gallons per hour','GPH');
		dotextinput('maxNoise25m',4,4,'Max. noise level at maximum speed @ 25m','','dBA');
		dotextinput('maxHillStartAngle',4,4,'Max. hill climb angle','From s standing start on a hill','degrees');
	echo '
		</dl>';

	echo '
		<div align="center">
			<input value="Save Data" type="submit" name="dosave" value="botsave" class="button_submit"/>';
		if (!empty($err_array)) 
			echo '<br /><p><div style="font-family:Verdana; font-size:12px; color:red;">There are errors in the data you have entered - they are marked in red above</div></p>'; 
		elseif (isset($saved)) 
			echo '<p><div style="font-family:Verdana; font-size:12px; color:green;">The data for ' . ucfirst($data['craftType']) . ' has been saved.</div></p>';
		echo '
		</div>
	</div>
</form';
// +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
// +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
// +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

function doBeaufort($var,$title)
{
		global $data;
		doselinput(array('0','1','2','3','4','5','6','7','8','9','10','11','12'), $var, (isset($data[$var]) ? $data[$var] : ''), $title, '', false);
}

function doSrcRadio($var)
{
	doradioinput($var, array('Manufacturer','User','Calculated','Other'),'Who supplied this information?','Data source:',true);
}

// load craft list 
// if an official ($edit) then load all craft otherwise only craft entered by current user
// returns array with key = crafttype and data = array(real_name,approved)
function load_list()
{
	global $craftList, $smcFunc, $user_info, $edit;
	$craftList = array(); // empty existing array
	$craftList['New'] = array('real_name'=>$user_info['name'],'approved'=>($edit ? '1' : '2')); // add default new onto start of craft list array!
	$result = $smcFunc['db_query']('', '
		SELECT craftType, approved, real_name 
		FROM {db_prefix}hcb_craftdata AS craft
		LEFT JOIN {db_prefix}members AS members ON members.id_member = craft.userid
		' . ($edit ? '' : ' WHERE approved=\'2\' AND userid=\'' . $user_info['id'] . '\'') . '
		ORDER BY craftType');
	while ($res = $smcFunc['db_fetch_assoc']($result)) $craftList[$res['craftType']] = array('real_name'=>$res['real_name'], 'approved'=>$res['approved']);
}


?>