<?php
/*
	Centre of gravity/lift Calculator
	----------------------------------

	basic calculations
	
	we need to calculate 
			2)	then the cushion area and perimeer (for lift pressure, etc.) from the skirt geometry
			3) then do the performance  calcs....
			5) followed by the CG calc using the calculated thrust power (max, cruise, min)

	
	calculate the cushion ares and perimeters from the hull/skirt dimensions supplied
	 cusion is divided into four sections:
		1. Rear cushion up to root of divider (if there is one)
		2. divider segment
		3. front section between div root and bow root
		4. bow segment
*/

	// calculae cusion area first
	
	// first make up array of coords for each section of the skirt profile
	// we start at the lower right corner (nearest the bow and work clockwise
	// leaving the front/bow-facing side open
	
	$rearSect = $frontSect = $divSeg = $bowSeg = array('coords'=>array(), 'area'=>0, 'centroid'=>0, 'ext_perimeter'=>0);

	$bowSeg['coords']['root'] = $data['bowradius'] - sqrt(pow($data['bowradius'],2) - (pow($data['hullWidth'] - (2 * $data['contactoffset']),2) / 4));
	// front right corner first
	if ($data['dual_comp'] == 'dual')
	{
		$divSeg['coords']['root'] = $data['divradius'] - sqrt(pow($data['divradius'],2) - (pow($data['hullWidth'] - (2 * $data['contactoffset']),2) / 4));
		$frontSect['coords'][] = array(
									'x'=> $bowSeg['coords']['root'] + $data['bowskirtfront'], 
									'y'=>$data['contactoffset']);
		$frontSect['coords'][] = $rearSect['coords'][] = array(
									'x'=> $divSeg['coords']['root'] + $data['dividerfront'], 
									'y'=> $data['contactoffset']);
	}
	else
		$rearSect['coords'][] = array(
									'x'=> $bowSeg['coords']['root'] + $data['bowskirtfront'], 
									'y'=>$data['contactoffset']);

	// rear corners(s) now
	if (empty($data['sternChamf']))
	{
		$rearSect['coords'][] = array(
									'x'=>$data['hullLength'] - $data['contactoffset'], 
									'y'=>$data['contactoffset']);
		$rearSect['coords'][] = array(
									'x'=>$data['hullLength'] - $data['contactoffset'], 
									'y'=>$data['hullWidth'] - $data['contactoffset']);
	}
	else
	{
		$sternoffset = sqrt(pow($data['sternChamf'], 2) / 2);
		$rearSect['coords'][] = array(
									'x'=> $data['hullLength'] - $data['contactoffset'] - $sternoffset, 
									'y'=>$data['contactoffset']);
		$rearSect['coords'][] = array(
									'x'=>$data['hullLength'] - $data['contactoffset'], 
									'y'=>$data['contactoffset'] + $sternoffset);
		$rearSect['coords'][] = array(
									'x'=>$data['hullLength'] - $data['contactoffset'], 
									'y'=>$data['hullWidth'] - $data['contactoffset'] - $sternoffset);
		$rearSect['coords'][] = array(
									'x'=> $data['hullLength'] - $data['contactoffset'] - $sternoffset, 
									'y'=>$data['hullWidth'] - $data['contactoffset']);
	}
	
	// front top corners
	if ($data['dual_comp'] == 'dual')
	{
		$frontSect['coords'][] = $rearSect['coords'][] = array('x'=> $divSeg['coords']['root'] + $data['dividerfront'], 'y'=>$data['hullWidth'] - $data['contactoffset']);

		$frontSect['coords'][] = array('x'=> $bowSeg['coords']['root'] + $data['bowskirtfront'], 'y'=>$data['hullWidth'] - $data['contactoffset']);
	}
	else // no partition so only one cushion
		$rearSect['coords'][] = array('x'=> $bowSeg['coords']['root'] + $data['bowskirtfront'], 'y'=>$data['hullWidth'] - $data['contactoffset']);
	
	// cushion areas & perimeters
	$rearSect['area'] = Area_Polynomial($rearSect['coords']);
	$rearSect['centroid'] =	Centroid_X_Polynomial($rearSect['coords']);
	$rearSect['ext_perimeter'] = Perimeter_Polygon($rearSect['coords']);

	$bowSeg['area'] = Area_Circular_Segment($data['hullWidth'] - ($data['contactoffset'] * 2), $data['bowradius']);
	$bowSeg['centroid'] = Centroid_Circular_Segment($data['hullWidth'] - ($data['contactoffset'] * 2), $data['bowradius'], $data['bowskirtfront'] + $bowSeg['coords']['root']);
	$bowSeg['ext_perimeter'] = Perimeter_Circular_Segment($data['hullWidth'] - ($data['contactoffset'] * 2), $data['bowradius']);
	

/*
 Combine/subtract the areas to create front and rear cushions and calc CoL for each
 
 There are three load cases for the cushion area and perimeter:

 1.	dual compartment - nose-heavy - where cushion area is at max size and pressure is as per weight
 2. dual compartment - rear loading - where cushion area is rear-only therefore pressure is higher and perimeter smaller.
 3. dual AND single compartment - maximum design weight with max cushion area & perimeter.
 
 We do the performance calcs on all three cases
	- resulting in different hump curves, thrust curves and, because of the different weights, lift power requirements.
*/
	$feedArea = 0;
	if (!empty ($data['feed_holes']) AND !$data['directFeed'])
		foreach ($data['feed_holes'] as $hole)
			$feedArea += $hole['qty'] * pi() * pow($hole['diam'],2)/4;

	$rearCushion = $frontCushion = array(
		'area'=> 0, 
		'centroid'=> 0
		);

	$maxLoad_CoG = getWeights(array('hull', 'engines', 'misc', 'max_load')); // always need the max load case!

	$maxLoad_RearCushion = $tailHvy_FrontCushion = $noseHvy_FrontCushion = $tailHvy_RearCushion = $noseHvy_RearCushion = array(
		'area'=> 0, 
		'pressure'=>0, 
		'airflow'=>0,
		'liftPower'=>0,
		);

	if ($data['dual_comp'] == 'dual')
	{
		$frontSect['area'] = Area_Polynomial($frontSect['coords']);
		$frontSect['centroid'] = Centroid_X_Polynomial($frontSect['coords']);
		// top and bottom edges are the only external sides!
		$frontSect['ext_perimeter'] = 2 * (($divSeg['coords']['root'] + $data['dividerfront']) - ($bowSeg['coords']['root'] + $data['bowskirtfront']));
		
		$divSeg['area'] = Area_Circular_Segment($data['hullWidth'] - ($data['contactoffset'] * 2), $data['divradius']); 
		$divSeg['centroid'] = Centroid_Circular_Segment($data['hullWidth'] - ($data['contactoffset'] * 2), $data['divradius'], $data['dividerfront'] + $divSeg['coords']['root']);
		$divSeg['ext_perimeter'] = Perimeter_Circular_Segment($data['hullWidth'] - ($data['contactoffset'] * 2), $data['divradius']);

		$rearCushion['area'] = $rearSect['area'] + $divSeg['area'];
		$rearCushion['centroid'] = (($rearSect['centroid'] * $rearSect['area']) + ($divSeg['centroid'] * $divSeg['area'])) / ($divSeg['area'] + $rearSect['area']);
		$frontCushion['area'] = $frontSect['area'] - $divSeg['area'] + $bowSeg['area'];
		$frontCushion['centroid'] = (($frontSect['centroid'] * $frontSect['area']) + ($bowSeg['centroid'] * $bowSeg['area']) - ($divSeg['centroid'] * $divSeg['area'])) / ($frontSect['area'] + $bowSeg['area'] - $divSeg['area']);
		
		$tailHvy_CoG = getWeights(array('hull', 'engines', 'misc', 'pass_rearward'));
		$noseHvy_CoG = getWeights(array('hull', 'engines', 'misc', 'pass_forward'));	
		
		// adjust front cushion AREA to balance craft on the CofG (i.e effectively moves the cushion centroid to match the CoG centroid)
		$maxLoad_RearCushion['area'] = $tailHvy_RearCushion['area'] = $noseHvy_RearCushion['area'] = $rearCushion['area'];

		// nose-heavy case
		$maxLoad_FrontCushion['area'] = (($rearCushion['area'] * $rearCushion['centroid']) - ($rearCushion['area'] * $maxLoad_CoG['cog']))
						/
						($noseHvy_CoG['cog'] - $frontCushion['centroid']);
		// tail-heavy case
		$tailHvy_FrontCushion['area'] = (($rearCushion['area'] * $rearCushion['centroid']) - ($rearCushion['area'] * $tailHvy_CoG['cog']))
						/
						($tailHvy_CoG['cog'] - $frontCushion['centroid']);
		
		// check that the change direction matches the front compartment air feed type!
		if (($tailHvy_FrontCushion['area'] > $frontCushion['area']) AND $data['feed_type']=='underskirt') // plenum pressure so can't fall!
			$warning_array['dual_comp'] = 'Pressure in front compartment has risen above the rear in tail heavy case - not possible with an underskirt air feed. Change the feed type or move postions of rear-heavy load.';
		elseif (($tailHvy_FrontCushion['area'] < $frontCushion['area']) AND $data['feed_type']!='underskirt') // skirt feed so can't rise!
			$warning_array['dual_comp'] = 'Pressure in front campartment has fallen below the rear in tail heavy case - this is not possible with an plenum air feed.   Change the feed type or move positions of rear-heavy load.';

		// check that the change direction matches the front compartment air feed type!
		if (($maxLoad_FrontCushion['area'] > $frontCushion['area']) AND $data['feed_type']=='underskirt') // plenum pressure so can't fall!
			$warning_array['dual_comp'] = 'Pressure in front compartment has risen above the rear in maximum load case - not possible with an underskirt air feed. Change the feed type or move postions of rear-heavy load.';
		elseif (($maxLoad_FrontCushion['area'] < $frontCushion['area']) AND $data['feed_type']!='underskirt') // skirt feed so can't rise!
			$warning_array['dual_comp'] = 'Pressure in front campartment has fallen below the rear in maximum load case - this is not possible with an plenum air feed.   Change the feed type or move positions of rear-heavy load.';

		// nose-heavy case
		$noseHvy_FrontCushion['area'] = (($rearCushion['area'] * $rearCushion['centroid']) - ($rearCushion['area'] * $noseHvy_CoG['cog']))
						/
						($noseHvy_CoG['cog'] - $frontCushion['centroid']);
		
		// check that the change direction matches the front compartment air feed type!
		if (($noseHvy_FrontCushion['area'] > $frontCushion['area']) AND $data['feed_type']=='underskirt') // plenum pressure max - so can't fall!
			$warning_array['dual_comp'] = 'Pressure in front compartment has risen above the rear in nose heavy case - not possible with an underskirt air feed. Change the feed type or move nose-heavy load positions.';
		elseif (($noseHvy_FrontCushion['area'] <<$frontCushion['area']) AND $data['feed_type']!='underskirt') // skirt feed so can't rise!
			$warning_array['dual_comp'] = 'Pressure in front campartment has fallen below the rear in nose heavy case - this is not possible with a plenum air feed.  Change the feed type or move nose-heavy load positions.';
		
		
		// calculate the pressures in each compartment from the relative change in the area
		$maxLoad_RearCushion['pressure'] = $maxLoad_CoG['mass'] / ($maxLoad_RearCushion['area'] + $maxLoad_FrontCushion['area']);
		$noseHvy_RearCushion['pressure'] = $noseHvy_CoG['mass'] / ($noseHvy_RearCushion['area'] + $noseHvy_FrontCushion['area']);
		$tailHvy_RearCushion['pressure'] = $tailHvy_CoG['mass'] / ($tailHvy_RearCushion['area'] + $noseHvy_FrontCushion['area']);

		// front comp. pressure is same force but over original larger/smaller area
		$maxLoad_FrontCushion['pressure'] = ($maxLoad_FrontCushion['area'] * $maxLoad_RearCushion['pressure']) / $frontCushion['area'];
		$noseHvy_FrontCushion['pressure'] = ($noseHvy_FrontCushion['area'] * $noseHvy_RearCushion['pressure']) / $frontCushion['area'];
		$tailHvy_FrontCushion['pressure'] = ($tailHvy_FrontCushion['area'] * $tailHvy_RearCushion['pressure']) / $rearCushion['area'];
		
/*
Air flow & lift power will depend on the skirt gap & pressure in each compartment

Dual compartment front compartment feed type:
	
	Underskirt (direct feed OR indirect feed):
		For an underskirt feed the flow INTO the front comp. (under divider) equals the flow OUT OF the front compartment (external sides) AND the flow through the rear comp. should include the feed to the front!  
		
		Indirect (plenum feed to rear):
			Using rear comp. pressure and skirt gap (rear external sides only), calc the flow and ADD onto front flow (it all comes from the rear feed holes).  Then calculate the plenum pressure using the flow rate and feee hole area.  Power can then be calculated.
			
		Direct (no plenum)
			Using rear comp. pressure and skirt gap (rear external sides only), calc the flow rate and ADD onto front flow (it comes from the rear comp.).  Then calculate the power from the flow rate and pressure.

	Plenum fed front :
		Calc the flow feed rate from the FRONT to the REAR using the pressure difference across the divider (front must be equal to OR higher than rear) and the divider length.  It should then be ADDED to the rear external loss.  The plenum must be at least the same pressure as the front compartment and will be equal to the rear (same plenum!!) - front feed holes can be calculated using front comp pressure and flow rate.
 
				
Single compartment:
	Direct feed:
		Flow rate 7 pwr determined by skirt gap (only one compartment)
	
	Plenum feed:
		Flow is determined by skirt gap, power by plenum feed hole area (results in higher pressure).

*/

		// calculate air flow through each compartment
		// air flow is loss from the external (atmosphere exposed) sides of the cushion area PLUS (or minus) the loss/gain from the divider
		$maxLoad_FrontCushion['airflow'] = 
			liftAirFlow($data['skirtGap'], $frontSect['ext_perimeter'] + $bowSeg['ext_perimeter'], $maxLoad_FrontCushion['pressure'], $data['skirt']);
			+
			liftAirFlow($data['skirtGap'], $divSeg['ext_perimeter'], $maxLoad_FrontCushion['pressure'] - $maxLoad_RearCushion['pressure'], $data['skirt']);
		$tailHvy_FrontCushion['airflow'] = 
			liftAirFlow($data['skirtGap'], $frontSect['ext_perimeter'] + $bowSeg['ext_perimeter'], $tailHvy_FrontCushion['pressure'], $data['skirt']);
			+
			liftAirFlow($data['skirtGap'], $divSeg['ext_perimeter'], $tailHvy_FrontCushion['pressure'] - $tailHvy_RearCushion['pressure'], $data['skirt']);
		$noseHvy_FrontCushion['airflow'] = 
			liftAirFlow($data['skirtGap'], $frontSect['ext_perimeter'] + $bowSeg['ext_perimeter'], $noseHvy_FrontCushion['pressure'], $data['skirt']);
			+
			liftAirFlow($data['skirtGap'], $divSeg['ext_perimeter'], $noseHvy_FrontCushion['pressure'] - $noseHvy_RearCushion['pressure'], $data['skirt']);

		$maxLoad_RearCushion['airflow'] = 
			liftAirFlow($data['skirtGap'], $rearSect['ext_perimeter'], $maxLoad_RearCushion['pressure'], $data['skirt']);
			+
			liftAirFlow($data['skirtGap'], $divSeg['ext_perimeter'], $maxLoad_RearCushion['pressure'] - $maxLoad_FrontCushion['pressure'], $data['skirt']);
		$tailHvy_RearCushion['airflow'] = 
			liftAirFlow($data['skirtGap'], $rearSect['ext_perimeter'], $tailHvy_RearCushion['pressure'], $data['skirt']);
			+
			liftAirFlow($data['skirtGap'], $divSeg['ext_perimeter'], $tailHvy_RearCushion['pressure'] - $tailHvy_FrontCushion['pressure'], $data['skirt']);
		$noseHvy_RearCushion['airflow'] = 
			liftAirFlow($data['skirtGap'], $rearSect['ext_perimeter'], $tailHvy_FrontCushion['pressure'], $data['skirt']);
			+
			liftAirFlow($data['skirtGap'], $divSeg['ext_perimeter'], $tailHvy_RearCushion['pressure'] - $tailHvy_FrontCushion['pressure'], $data['skirt']);

// now calc lift power required for each compartment
// @@@ this requires the feed area for plenum-fed craft (i.e. feed holes sizes)
// for a plenum feed we should be able to calc the plenum pressure as being the maximum of the front and rear cushion pressures (plenum has to be able to "pump" either comp. to this pressure level)
//.. .then work out hole area to maintain this pressure from the total airflow?

		$maxLoad_FrontCushion['liftPower'] = LiftPower(
					$maxLoad_FrontCushion['pressure'], 
					$maxLoad_FrontCushion['airflow'], 
					$feedArea, 
					$data['directFeed'], 
					$data['reserve'] / 100, 
					$data['skirt']);  

		$tailHvy_FrontCushion['liftPower'] = LiftPower(
					$tailHvy_FrontCushion['pressure'], 
					$tailHvy_FrontCushion['airflow'], 
					$feedArea, 
					$data['directFeed'], 
					$data['reserve'] / 100, 
					$data['skirt']);  

		$noseHvy_FrontCushion['liftPower'] = LiftPower(
					$noseHvy_FrontCushion['pressure'], 
					$noseHvy_FrontCushion['airflow'], 
					$feedArea, 
					$data['directFeed'], 
					$data['reserve'] / 100, 
					$data['skirt']);  

		$maxLoad_RearCushion['liftPower'] = LiftPower(
					$maxLoad_RearCushion['pressure'], 
					$maxLoad_RearCushion['airflow'], 
					$feedArea, 
					$data['directFeed'], 
					$data['reserve'] / 100, 
					$data['skirt']);  

		$tailHvy_RearCushion['liftPower'] = LiftPower(
					$tailHvy_RearCushion['pressure'], 
					$tailHvy_RearCushion['airflow'], 
					$feedArea, 
					$data['directFeed'], 
					$data['reserve'] / 100, 
					$data['skirt']);  

		$noseHvy_RearCushion['liftPower'] = LiftPower(
					$noseHvy_RearCushion['pressure'], 
					$noseHvy_RearCushion['airflow'], 
					$feedArea, 
					$data['directFeed'], 
					$data['reserve'] / 100, 
					$data['skirt']);  

		

//  ... for a plenum feed, calculate the plenum pressure required for the front compartment and check it's possible
// ... based on the loss area (skirt gap) & pressure in each compartment

		
// Check the front compartmentment supports this pressure (it could be higher or lower than main comp depending on whether it's a plenum feed or a under-skirt feed).
	

//dbug($bowSeg,'bowseg');
//dbug($frontSect,'frontsect');
//dbug($divSeg,'divseg');
//dbug($rearSect,'rearsect');
	

		
	}
	else
	{
		// only one cushion area (rear)
		$rearCushion['area'] = $maxLoad_RearCushion['area'] = $rearSect['area'] + $bowSeg['area'];
		$maxLoad_RearCushion['centroid'] = (($rearSect['centroid'] * $rearSect['area']) + ($bowSeg['centroid'] * $bowSeg['area'])) / ($bowSeg['area'] + $rearSect['area']);
		$maxLoad_RearCushion['pressure'] = $maxLoad_CoG['mass'] / $maxLoad_RearCushion['area'];
		$maxLoad_RearCushion['airflow'] = liftAirFlow($data['skirtGap'], $rearSect['ext_perimeter'] + $bowSeg['ext_perimeter'], $maxLoad_RearCushion['pressure'], $data['skirt']);
		$maxLoad_RearCushion['liftPower'] = LiftPower(
					$maxLoad_RearCushion['pressure'], 
					$maxLoad_RearCushion['airflow'], 
					$feedArea, 
					$data['directFeed'], 
					$data['reserve'] / 100, 
					$data['skirt']
					);
	}

dbug($maxLoad_RearCushion,'max rear');
dbug($maxLoad_FrontCushion,'max front');
dbug($noseHvy_RearCushion,'nose hvy rear');
dbug($noseHvy_FrontCushion,'nose hvy front');
dbug($tailHvy_RearCushion,'tail hvy rear');
dbug($tailHvy_FrontCushion,'tail hvy front');
dbug($noseHvy_CoG,'nose COG');
dbug($tailHvy_CoG,'rear COG');
dbug($maxLoad_CoG,'max COG');



dbug($rearCushion, 'cushion rear');
dbug($frontCushion, 'cushion front'); 


	

	
	










	$alldata = $titles = array(); // graph data & plot titles
	$cases = array(
		'Nose Heavy'=> array( 
			'result'=>array(), 
			'cushionArea'=>$frontCushion['area'] + $rearCushion['area'],
			'perimeter'=>$rearSect['ext_perimeter'] + $frontSect['ext_perimeter'] + $divSeg['ext_perimeter'] + $bowSeg['ext_perimeter'],
			'mass'=>$noseHvy_CoG['mass'],
			),

		'Tail Heavy'=> array( 
			'result'=>array(), 
			'cushionArea'=>$frontCushion['area'] + $rearCushion['area'],
			'perimeter'=>$rearSect['ext_perimeter'] + $frontSect['ext_perimeter'] + $divSeg['ext_perimeter'] + $bowSeg['ext_perimeter'],
			'mass'=>$noseHvy_CoG['mass'],
			),

		'Nose Heavy'=> array( 
			'result'=>array(), 
			'cushionArea'=>$frontCushion['area'] + $rearCushion['area'],
			'perimeter'=>$rearSect['ext_perimeter'] + $frontSect['ext_perimeter'] + $divSeg['ext_perimeter'] + $bowSeg['ext_perimeter'],
			'mass'=>$noseHvy_CoG['mass'],
			),
			
		);

	foreach ($cases as $case)
		$alldata['result'] = humpDragCalc($case);

	
function humpDragCalc($case)
{

}

			
			
	
	
$cruiseWeight = $designWeight = $maxLoad_CoG['mass'];
$cushionArea = $rearCushion['area'] + $frontCushion['area'];

$perimeter = $rearSect['ext_perimeter'] + $frontSect['ext_perimeter'] + $bowSeg['ext_perimeter'];

$cruiseCushionPressure = $designCushionPressure = max($maxLoad_FrontCushion['pressure'], $maxLoad_RearCushion['pressure']);

//	$designCushionPressure = $data['designWeight'] * 9.81 / $cushionArea;
//	$cruiseCushionPressure = min(array($noseHvy_CoG['cushionPress'], $tailHvy_CoG['cushionPress']));  // use whichever is lowest

$cruiseLiftAirFlow = $designLiftAirFlow = $maxLoad_FrontCushion['airflow'] + $maxLoad_RearCushion['airflow'];

//	$designLiftAirFlow = liftAirFlow($data['skirtGap'], $perimeter, $designCushionPressure, $data['skirt']);
//	$cruiseLiftAirFlow = liftAirFlow($data['skirtGap'], $perimeter, $cruiseCushionPressure, $data['skirt']);
	
//	$feedArea = 0;
//	if (!empty ($data['feed_holes']) AND !$data['directFeed'])
//		foreach ($data['feed_holes'] as $hole)
//			$feedArea += $hole['qty'] * pi() * pow($hole['diam'],2)/4;
	
$cruiseLiftPower = $designLiftPower = $maxLoad_FrontCushion['liftPower'] + $maxLoad_RearCushion['liftPower'];
//	$designLiftPower = LiftPower($designCushionPressure, $designLiftAirFlow, $feedArea, $data['directFeed'], $data['reserve'] / 100, $data['skirt']);  
//	$cruiseLiftPower = LiftPower($cruiseCushionPressure, $cruiseLiftAirFlow, $feedArea, $data['directFeed'], $data['reserve'] / 100, $data['skirt']);  
/*
dbug($cushionArea);
dbug($cruiseWeight);
dbug($perimeter);
dbug($cruiseLiftAirFlow);
dbug($cruiseLiftPower);
*/

	// calculate power available to thrust in W
	$maxThrustPower = $data['tPower'] * 745.699872;
	$cruiseThrustPower = $data['tPower'] * 745.699872;

	// adjust thrust engine power for twin fan craft (integrated is auto adjusted by splitter height)
	if ($data['twinFan'] == 'twinFan')
		$maxThrustPower = $maxThrustPower - $designLiftPower; // single engine, two fans
	if ($maxThrustPower<0)
		$maxThrustPower = 0;  //not enough power for thrust!!!
		
	// wave drag tables
	for ($i=0.3; $i<=1; $i+=0.1) $beamL[] = $i;
	$froudeN[] = 0;
	for ($i=0.2; $i<=5; $i+=0.05) $froudeN[] = $i;

	// table is arranged as [froude-key][beaml-key]
	// note- Lawrence Doctors data!!
	$waveDrag[] = array(0,0,0,0,0,0,0,0);
	$waveDrag[] = array(0.0020435,0.0013943,0.00090993,0.00067028,0.00056598,0.00051292,0.00047796,0.00045113);
	$waveDrag[] = array(0.14791,0.183,0.2102,0.23018,0.24465,0.25537,0.26362,0.27017);
	$waveDrag[] = array(0.11048,0.13264,0.15573,0.17864,0.19919,0.21649,0.2307,0.24237);
	$waveDrag[] = array(0.48338,0.57542,0.63414,0.66929,0.69043,0.70389,0.71313,0.71994);
	$waveDrag[] = array(0.16716,0.15103,0.12432,0.099568,0.080493,0.066652,0.056661,0.049311);
	$waveDrag[] = array(0.17551,0.19456,0.21839,0.24746,0.27813,0.30759,0.33454,0.3586);
	$waveDrag[] = array(0.38618,0.48823,0.58611,0.67512,0.75277,0.81924,0.8759,0.92432);
	$waveDrag[] = array(0.56478,0.7175,0.85036,0.96142,1.0528,1.1282,1.1909,1.2437);
	$waveDrag[] = array(0.66336,0.82947,0.96338,1.0688,1.152,1.2188,1.2735,1.319);
	$waveDrag[] = array(0.70061,0.85766,0.97557,1.0634,1.1301,1.1822,1.224,1.2585);
	$waveDrag[] = array(0.7008,0.8389,0.93536,1.0032,1.0524,1.0896,1.1188,1.1425);
	$waveDrag[] = array(0.68086,0.7974,0.87272,0.92218,0.95613,0.98063,0.99914,1.0137);
	$waveDrag[] = array(0.65086,0.74679,0.80358,0.83777,0.85935,0.87374,0.88387,0.89135);
	$waveDrag[] = array(0.61652,0.6942,0.73565,0.75771,0.76971,0.77641,0.78022,0.78241);
	$waveDrag[] = array(0.58098,0.64315,0.67227,0.68495,0.68974,0.69073,0.6899,0.68823);
	$waveDrag[] = array(0.54596,0.59522,0.61463,0.62018,0.61961,0.61645,0.61229,0.60787);
	$waveDrag[] = array(0.51234,0.55102,0.56284,0.56304,0.55857,0.55244,0.54593,0.53959);
	$waveDrag[] = array(0.48058,0.51062,0.51657,0.5128,0.50553,0.49732,0.48919,0.48155);
	$waveDrag[] = array(0.45084,0.47387,0.47532,0.46862,0.45937,0.44974,0.44055,0.43206);
	$waveDrag[] = array(0.42315,0.44051,0.43851,0.4297,0.41909,0.40853,0.39866,0.38967);
	$waveDrag[] = array(0.39747,0.41024,0.40561,0.39531,0.3838,0.37267,0.36243,0.35316);
	$waveDrag[] = array(0.3737,0.38275,0.37615,0.36481,0.35275,0.34132,0.33091,0.32155);
	$waveDrag[] = array(0.35171,0.35776,0.34969,0.33767,0.32532,0.31378,0.30335,0.29402);
	$waveDrag[] = array(0.33138,0.335,0.32586,0.31343,0.30097,0.28946,0.27913,0.26992);
	$waveDrag[] = array(0.31259,0.31425,0.30434,0.2917,0.27927,0.26789,0.25773,0.2487);
	$waveDrag[] = array(0.2952,0.29528,0.28484,0.27214,0.25985,0.24868,0.23874,0.22993);
	$waveDrag[] = array(0.2791,0.27791,0.26714,0.25449,0.2424,0.23148,0.2218,0.21325);
	$waveDrag[] = array(0.26418,0.26197,0.25101,0.2385,0.22666,0.21604,0.20664,0.19835);
	$waveDrag[] = array(0.25035,0.24732,0.23628,0.22397,0.21243,0.20211,0.19301,0.185);
	$waveDrag[] = array(0.23751,0.23383,0.22279,0.21073,0.1995,0.1895,0.18071,0.17297);
	$waveDrag[] = array(0.22557,0.22138,0.21042,0.19863,0.18773,0.17806,0.16957,0.16211);
	$waveDrag[] = array(0.21447,0.20988,0.19904,0.18755,0.17698,0.16764,0.15944,0.15225);
	$waveDrag[] = array(0.20412,0.19922,0.18855,0.17737,0.16714,0.15811,0.15021,0.14329);
	$waveDrag[] = array(0.19447,0.18934,0.17886,0.16799,0.1581,0.14939,0.14178,0.13511);
	$waveDrag[] = array(0.18545,0.18016,0.16989,0.15935,0.14978,0.14138,0.13405,0.12763);
	$waveDrag[] = array(0.17703,0.17162,0.16157,0.15135,0.14211,0.134,0.12694,0.12076);
	$waveDrag[] = array(0.16914,0.16366,0.15385,0.14394,0.13501,0.1272,0.12039,0.11445);
	$waveDrag[] = array(0.16175,0.15623,0.14666,0.13706,0.12844,0.1209,0.11435,0.10862);
	$waveDrag[] = array(0.15481,0.14928,0.13996,0.13067,0.12234,0.11507,0.10875,0.10324);
	$waveDrag[] = array(0.1483,0.14278,0.13371,0.12471,0.11666,0.10965,0.10356,0.098251);
	$waveDrag[] = array(0.14218,0.13669,0.12786,0.11915,0.11138,0.10461,0.09874,0.093623);
	$waveDrag[] = array(0.13642,0.13098,0.12239,0.11395,0.10644,0.099914,0.094252,0.089319);
	$waveDrag[] = array(0.13099,0.12561,0.11726,0.10909,0.10183,0.09553,0.090067,0.08531);
	$waveDrag[] = array(0.12587,0.12056,0.11244,0.10453,0.097517,0.09143,0.086158,0.081568);
	$waveDrag[] = array(0.12103,0.1158,0.10791,0.10025,0.09347,0.08759,0.0825,0.078071);
	$waveDrag[] = array(0.11647,0.11131,0.10365,0.096233,0.089672,0.08399,0.079073,0.074797);
	$waveDrag[] = array(0.11215,0.10708,0.099636,0.092449,0.086101,0.080608,0.075858,0.071727);
	$waveDrag[] = array(0.10806,0.10308,0.095848,0.088883,0.08274,0.077428,0.072837,0.068845);
	$waveDrag[] = array(0.10419,0.099302,0.092271,0.08552,0.079573,0.074434,0.069994,0.066135);
	$waveDrag[] = array(0.10051,0.095722,0.088889,0.082345,0.076585,0.071611,0.067316,0.063584);
	$waveDrag[] = array(0.097026,0.092331,0.085689,0.079342,0.073763,0.068947,0.06479,0.061179);
	$waveDrag[] = array(0.093714,0.089115,0.082658,0.076501,0.071094,0.06643,0.062405,0.05891);
	$waveDrag[] = array(0.090566,0.086062,0.079784,0.07381,0.068568,0.064049,0.06015,0.056765);
	$waveDrag[] = array(0.087572,0.083162,0.077057,0.071258,0.066174,0.061794,0.058016,0.054737);
	$waveDrag[] = array(0.08472,0.080404,0.074467,0.068836,0.063904,0.059657,0.055995,0.052817);
	$waveDrag[] = array(0.082004,0.07778,0.072004,0.066536,0.06175,0.057629,0.054078,0.050997);
	$waveDrag[] = array(0.079414,0.075282,0.069661,0.064348,0.059702,0.055704,0.052259,0.04927);
	$waveDrag[] = array(0.076943,0.0729,0.06743,0.062267,0.057755,0.053874,0.05053,0.04763);
	$waveDrag[] = array(0.074584,0.070629,0.065304,0.060285,0.055902,0.052133,0.048886,0.046071);
	$waveDrag[] = array(0.07233,0.068462,0.063277,0.058397,0.054137,0.050475,0.047322,0.044589);
	$waveDrag[] = array(0.070175,0.066392,0.061342,0.056595,0.052454,0.048896,0.045832,0.043177);
	$waveDrag[] = array(0.068114,0.064414,0.059495,0.054876,0.050848,0.047389,0.044412,0.041832);
	$waveDrag[] = array(0.066141,0.062522,0.057729,0.053233,0.049316,0.045952,0.043057,0.040549);
	$waveDrag[] = array(0.064252,0.060712,0.056041,0.051663,0.047851,0.044579,0.041764,0.039324);
	$waveDrag[] = array(0.062442,0.058979,0.054425,0.050162,0.046451,0.043267,0.040528,0.038155);
	$waveDrag[] = array(0.060706,0.057318,0.052878,0.048725,0.045112,0.042012,0.039347,0.037037);
	$waveDrag[] = array(0.05904,0.055726,0.051395,0.047349,0.04383,0.040812,0.038216,0.035968);
	$waveDrag[] = array(0.057442,0.054199,0.049974,0.04603,0.042602,0.039662,0.037134,0.034945);
	$waveDrag[] = array(0.055907,0.052734,0.048611,0.044766,0.041425,0.03856,0.036098,0.033965);
	$waveDrag[] = array(0.054431,0.051326,0.047303,0.043553,0.040296,0.037504,0.035104,0.033026);
	$waveDrag[] = array(0.053013,0.049974,0.046046,0.042388,0.039212,0.03649,0.034151,0.032126);
	$waveDrag[] = array(0.051649,0.048675,0.044839,0.04127,0.038172,0.035518,0.033237,0.031262);
	$waveDrag[] = array(0.050337,0.047425,0.043679,0.040195,0.037173,0.034583,0.032359,0.030433);
	$waveDrag[] = array(0.049073,0.046223,0.042563,0.039162,0.036212,0.033685,0.031515,0.029636);
	$waveDrag[] = array(0.047856,0.045065,0.041489,0.038168,0.035288,0.032822,0.030704,0.028871);
	$waveDrag[] = array(0.046684,0.04395,0.040455,0.037211,0.034399,0.031992,0.029924,0.028135);
	$waveDrag[] = array(0.045553,0.042876,0.039459,0.03629,0.033544,0.031193,0.029174,0.027427);
	$waveDrag[] = array(0.044463,0.04184,0.0385,0.035403,0.032719,0.030423,0.028451,0.026745);
	$waveDrag[] = array(0.043411,0.040841,0.037574,0.034547,0.031925,0.029682,0.027755,0.026089);
	$waveDrag[] = array(0.042396,0.039878,0.036682,0.033723,0.03116,0.028967,0.027085,0.025456);
	$waveDrag[] = array(0.041416,0.038948,0.035821,0.032927,0.030422,0.028278,0.026438,0.024847);
	$waveDrag[] = array(0.040469,0.03805,0.03499,0.032159,0.029709,0.027614,0.025815,0.024259);
	$waveDrag[] = array(0.039553,0.037183,0.034188,0.031418,0.029022,0.026972,0.025213,0.023692);
	$waveDrag[] = array(0.038669,0.036344,0.033412,0.030702,0.028358,0.026353,0.024632,0.023144);
	$waveDrag[] = array(0.037813,0.035534,0.032663,0.03001,0.027716,0.025755,0.024071,0.022615);
	$waveDrag[] = array(0.036986,0.03475,0.031939,0.029342,0.027096,0.025177,0.023529,0.022105);
	$waveDrag[] = array(0.036185,0.033992,0.031238,0.028695,0.026497,0.024618,0.023006,0.021611);
	$waveDrag[] = array(0.035409,0.033259,0.03056,0.02807,0.025917,0.024078,0.022499,0.021134);
	$waveDrag[] = array(0.034658,0.032548,0.029904,0.027465,0.025356,0.023555,0.022009,0.020673);
	$waveDrag[] = array(0.033931,0.03186,0.029269,0.026879,0.024814,0.023049,0.021535,0.020226);
	$waveDrag[] = array(0.033226,0.031194,0.028653,0.026311,0.024288,0.022559,0.021077,0.019794);
	$waveDrag[] = array(0.032543,0.030548,0.028057,0.025762,0.023779,0.022085,0.020632,0.019376);
	$waveDrag[] = array(0.03188,0.029922,0.02748,0.025229,0.023286,0.021626,0.020202,0.018971);
	$waveDrag[] = array(0.031238,0.029315,0.026919,0.024713,0.022808,0.021181,0.019785,0.018578);
	$waveDrag[] = array(0.030614,0.028727,0.026376,0.024213,0.022345,0.020749,0.019381,0.018198);
	$waveDrag[] = array(0.030009,0.028155,0.025849,0.023727,0.021895,0.02033,0.018989,0.017829);
	$waveDrag[] = array(0.029421,0.027601,0.025338,0.023256,0.021459,0.019925,0.018609,0.017471);

	// get beamLength column we are using in the wave drag array
	$beamLengthRatio = $data['hullWidth'] / $data['hullLength']; // beam to length ratio
	$beamCol = closestMatch($beamLengthRatio, $beamL);
	// Interpolate in the BeamRatio (Col) axis
//echo "<br/>	beamcol = " . 	($beamLengthRatio - $beamL[$beamCol]) / ($beamL[$beamCol+1] - $beamL[$beamCol]);
//echo "<br/>	beamlen = $beamLengthRatio beamcol = {$beamL[$beamCol]} col+1 = {$beamL[$beamCol+1]} ";
	// calc the interpolation fraction
	if (($beamCol < count($beamL)-1) AND ($beamLengthRatio > $beamL[$beamCol])) // check we aren't at the end of the array!
		$beamFr = ($beamLengthRatio - $beamL[$beamCol]) / ($beamL[$beamCol+1] - $beamL[$beamCol]);
	else 
		$beamFr = 0;
	
	// construct thrust/drag array
	$drag = array();
	$drag[]['index']=0;
	$drag[]['index']=0.05;
	for ($i=0.1; $i<0.36; $i+=0.01) $drag[]['index'] = $i;
	for ($i=0.4; $i<1.2; $i+=0.05) $drag[]['index'] = $i;
	
	foreach ($drag as $key=>$v)
	{
		$drag[$key]['mph'] = $data['maxSpeed'] * $drag[$key]['index']; // mph
		$drag[$key]['m/s'] = $drag[$key]['mph'] * 0.44704;  // m/sec
		$drag[$key]['Max Mom Drag'] = momentumDrag($drag[$key]['m/s'], $designLiftAirFlow);
		$drag[$key]['Cruise Mom Drag'] = momentumDrag($drag[$key]['m/s'], $cruiseLiftAirFlow);
		$drag[$key]['Profile Drag'] = profileDrag($drag[$key]['m/s'], $data['frontalArea'], $data['dragCoeff']);
// fudge factor for wave drag is 2!!
		$drag[$key]['Max Wave Drag'] = 2 * waveDrag($froudeN, $beamL, $waveDrag, $beamCol, $beamFr, $drag[$key]['m/s'], $data['hullLength'], $beamLengthRatio, $designCushionPressure, $cushionArea);
		$drag[$key]['Cruise Wave Drag'] = 2 * waveDrag($froudeN, $beamL, $waveDrag, $beamCol, $beamFr, $drag[$key]['m/s'], $data['hullLength'], $beamLengthRatio, $cruiseCushionPressure, $cushionArea);

// estimate is that wetting drag is between 35% (perfect trim & skirts) and 55% (bad trim) of the total drag (Y&B)
// fudge factor is 45% to account for slightly rougher water and less perfect trim
// this could be a user-selected option?
//			$drag[$key]['Wetting Drag'] = 0.45 * ($drag[$key]['Max Mom Drag'] + $drag[$key]['Profile Drag'] + $drag[$key]['Max Wave Drag']);
		$drag[$key]['Wetting Drag'] = 0.55 * ($drag[$key]['Max Mom Drag'] + $drag[$key]['Max Wave Drag']);

 //echo 'Max Thrust  ', $maxThrustPower, '<br>', $data['fanDiam'], '<br>', $data['prop'], '<br>', $drag[$key]['m/s'], '<br>', '<br>', $data['twinFan'], '<br>', (isset($data['splitterHeight']) ? $data['splitterHeight'] : 0), '<br>' . thrust($maxThrustPower, $data['fanDiam'], $data['prop'], $drag[$key]['m/s'], $data['twinFan'], (isset($data['splitterHeight']) ? $data['splitterHeight'] : 0));		
		$drag[$key]['Max Thrust'] = thrust($maxThrustPower, $data['fanDiam'], $data['prop'], $drag[$key]['m/s'], $data['twinFan'], (isset($data['splitterHeight']) ? $data['splitterHeight'] : 0));
//	$drag[$key]['Cruise thrust'] = thrust($cruiseThrustPower, $data['fanDiam'], $propArray[$data['prop']], $drag[$key]['m/s'], $twinFanArray[$data['twinFan']] , $data['splitterHeight']);
	}
//dbug($drag);

	$alldata = array();
	$titles = array('Max','Cruise','Profile','Max Thrust');

	// contruct & output thrust/drag table 
	// .. fill graph data array
	// .. find hump peaks 
	// .. and max speed
	$prevDrags[] = array('drag'=>-1, 'mph'=>0, 'key'=>0);
	$prevDrags[] = array('drag'=>-1, 'mph'=>0, 'key'=>0);
	$maxPeak = array('drag'=>-1,'mph'=>0, 'key'=>0);
	$peaks = array();

	foreach ($drag as $key=>$d)
	{
		$totDrag = $d['Max Mom Drag'] + $d['Max Wave Drag'] + $d['Profile Drag'] + $d['Wetting Drag'];

		// fill graph data array
		$alldata[] = array(
			'',
			$d['mph'],
			0.224808943 * $totDrag,
			0.224808943 * ($d['Cruise Mom Drag'] + $d['Cruise Wave Drag'] + $d['Profile Drag'] + $d['Wetting Drag']),
			0.224808943 * $d['Profile Drag'],
//				0.224808943 * $d['Wetting Drag'],
			0.224808943 * $d['Max Thrust']
			);

		// hump speed detection - we look for a sequence of rising then falling values in the sequence
		if (($prevDrags[1]['drag'] > $prevDrags[0]['drag']) AND ($totDrag < $prevDrags[1]['drag'])) 
		{
			$peaks[] = array('mph'=>$prevDrags[1]['mph'],'drag'=>$prevDrags[1]['drag']); // found peak so save speed
			if ($maxPeak['drag'] < $prevDrags[1]['drag']) 
				$maxPeak = $prevDrags[1];
		}
		// most economical cruise speed (lowest drag after the last hump) we look for a sequence of falling then rising values
		if (($prevDrags[1]['drag'] < $prevDrags[0]['drag']) AND ($totDrag > $prevDrags[1]['drag'])) 
			$cruiseSpeed = $prevDrags[1]; // will save the LAST trough AFTER the hump peak
		array_shift($prevDrags); //shift array down one position and delete the first item
		$prevDrags[1] = array('drag'=>$totDrag, 'mph'=>$d['mph'], 'key'=>$key);

		// max speed detector (we look for the first point that the total drag exceeds the thrust
		if (!isset($maxSpeed))
			if ($totDrag > $d['Max Thrust'])
				$maxSpeed = $drag[$key-1]['mph']; // use slightly lower speed!
			elseif ($key == count($drag)-1) $maxSpeed = $drag[$key]['mph']; // didn't exceed the max drag so use the max displayed speed on the chart instead!
	}
	
	// work out the maximum operational wind speed
	// by calculating the hump speed totdrag MINUS prof drag (the "margin")
	// then looking along the thrust curve until we find a match to the prof drag + margin
	// check this speed and subtract from the maxPaek hump speed and that's the maxiumu wind speed!
	$maxDragDiff = $maxPeak['drag'] - $drag[$maxPeak['key']]['Profile Drag']; 
	//find the nearest thrust value for this drag
	for ($i=$maxPeak['key']; $i<count($drag); $i++)
	{
		if ($drag[$i]['Max Thrust'] <= ($drag[$i]['Profile Drag'] + $maxDragDiff))
		{
			$windSpeed = $drag[$i]['mph'] - $maxPeak['mph'];
			break;
		}
	}

	
	// --------------------------------------------------------------
	// now we have thrust values we can calc the apparent CofG and complete the CoL calcs
	
	$thrustMax = $drag[0]['Max Thrust'] * $data['thrustY'];  // max static thrust moment (speed = zero)
	$thrustCruise = $drag[$cruiseSpeed['key']]['Max Thrust'] * $data['thrustY']; // cruise speed thrust
	$maxDrag = end($drag);
	$thrustMin = $maxDrag['Max Thrust'] * $data['thrustY']; // thrust at max speed

//	$maxload_CoG['moment'] = $maxLoad_CoG['moment'] - $thrustMax; // adjust for thrust pushing nose down
				
				
				
				
// ALL DONE //////////////////


/****************************************************/


// functions based on the hovercraft design calculator excel sheet Rev 4

function liftAirFlow($airgap, $perimeter, $cushionPressure, $skirt = 'bag') 
{
	global $roAir;
//This function returns the lift air flow required
	if ($skirt == 'bag')
		$cd = 0.9;
	else
		$cd = 0.65;
// .65 = discharge coefficient for skirt
	return (0.65 * $airgap * $perimeter * sqrt(2 * $cushionPressure / $roAir));
}

function liftPower($cushionPressure, $liftFlowRate, $feedArea, $directFeed, $reserve, $skirt='bag')
{
	global $roAir;
	$cd = 0.65; // discharge coefficient
	if ($directFeed) 
		$total_dP = $cushionPressure;
	else
	{
		$plenum_dP = ($roAir / 2) * pow(($liftFlowRate / ($cd * (empty($feedArea) ? 1 : $feedArea))),2);
		$total_dP = $plenum_dP + $cushionPressure;
	}
	return (1 + $reserve) * $liftFlowRate * $total_dP / 0.5;
}

function momentumDrag($velocity, $liftFlowRate)
{
	global $roAir;
	// Finds the momentum drag. This is drag caused by accelerating the
	// lift air up to craft speed
	return $roAir * $liftFlowRate * $velocity;
}

function profileDrag($velocity, $frontalArea , $cd)
{
	global $roAir;
	// Finds the profile drag. This is drag caused by the passage of air over/round the vehicle
	// $cd is the drag coefficent
	return $frontalArea * $cd * $roAir * pow($velocity ,2) / 2;
// ProfileDrag = FrontalArea * CD * (RoAir * Velocity ^ 2) / 2
}

function waveDrag(&$froudeN, $beamL, &$waveDrag, $beamCol, $beamFr, $velocity, $craftLength, $beamLengthRatio, $cushionPressure, $cushionArea)
{
	global $roWater;
//print_r(func_get_args());
	// finds the wavedrag
	// has to lookup the value of the coefficient of drag from a table
	// then finds the drag value

	$froudeNumber = $velocity / sqrt(9.81 * $craftLength);
	// This looks up in the datafile to find the right Cw value
	// First work out which row has the best match, then which column, then get the value
	$row = closestMatch($froudeNumber, $froudeN);
			
	// read table values data around the row,col
	// checking for valid row
	$Cw1 = $waveDrag[$row][$beamCol];
	$Cw2 = (isset($waveDrag[$row + 1]) ? $waveDrag[$row + 1][$beamCol] : $Cw1);
	$Cw3 = (isset($waveDrag[$row][$beamCol + 1]) ? $waveDrag[$row][$beamCol + 1] : $waveDrag[$row][$beamCol]);
	$Cw4 = (isset($waveDrag[$row + 1]) ? $waveDrag[$row + 1][$beamCol + 1] : $Cw3);

	//First step is to interpolate in the Froude axis (rows) to find CwA and CwB
	if (($row < count($froudeN)-1) AND ($froudeNumber > $froudeN[$row])) // check we aren't at the start OR the value is already less than the first one in the array!
		$FrFraction = ($froudeNumber - $froudeN[$row]) / ($froudeN[$row + 1] - $froudeN[$row]);
	else
		$FrFraction = 1;

	$CwA = $Cw1 + ($FrFraction * ($Cw2 - $Cw1));
	$CwB = $Cw3 + ($FrFraction * ($Cw4 - $Cw3));
		
	$Cw = $CwA + ($beamFr * ($CwB - $CwA));
	return ($Cw * (pow($cushionPressure,2)) * sqrt($cushionArea)) / ($roWater * 9.81);
}

function wettingDrag($perimeterArea, $skirt, $velocity)
{ 
	global $roWater;
// a real bodge!!
// using estimated drag coefficent for finger bag skirts
// assume skirt contadct area is 1/10th of hull width

		if($skirt=='finger')
			$cd = 0.8;
		else // bag skirt
			$cd = 0.2;
		return $perimeterArea * $cd * $roWater * pow($velocity ,2) / 2;
}


function thrust($power, $diamProp, $thrusterType, $velocity, $liftType, $splitterHeight)
{
	global $roAir;

//dbug(func_get_args());

	if ($power <= 0)
		return 0; // not enough power for thrust!

	$FOM_Prop = 0.55;
	$FOM_Fan = 0.55 * 1.27; // This is a bodge. It's as accurate as anything I've found so far.

	$radProp = $diamProp / 2;
	$areaProp = pi() * pow($radProp,2);

	if ($thrusterType ) // true if prop
	{  
		if ($velocity < 0.3)
{
			// This is the static thrust
			$thrust = pow(($FOM_Prop * $power / sqrt((1 / (2 * $roAir * $areaProp)))),(2 / 3));
}
		else
		{   // This is the moving thrust
			// first iterate to find the efficiency
			$eff_iter = 1;
			$eff = 0.1;
			while (abs(($eff - $eff_iter) / $eff) > 0.001)
			{
				$a = $velocity * $FOM_Prop;
				$b = sqrt(pow($velocity, 2) + (2 / ($roAir * $areaProp)) * ($eff * $power / $velocity));
				$eff_iter = $eff;
				$eff = $a / ($velocity + ((-$velocity + $b) / 2));
			}
			//Then calculate the thrust
			$thrust = $eff * $power / $velocity;
		}   
	} 
	else // fan
	{
		$staticThrust = pow(($FOM_Fan * $power / sqrt((1 / (2 * $roAir * $areaProp)))),0.66666);
		$exitVelocity = 1.26 * sqrt($staticThrust / ($roAir * $areaProp)); //1.26 is is a bodge factor
		$alpha = 1 - $velocity / $exitVelocity;
		$thrust = $staticThrust * $alpha;

		if ($liftType=='int') // reduce thrust by splitter area ratio
		{    
			$areaSplitter = (acos(($radProp - $splitterHeight) / $radProp) * (pow($diamProp,2)) / 4) - (sqrt(pow($radProp,2) - pow($radProp - $splitterHeight,2))) * ($radProp - $splitterHeight);
			$thrustRatio = 1 - ($areaSplitter / $areaProp);
			$thrust = $thrust * $thrustRatio;
		}
	}
	return $thrust;
}

//>>>>>>>>>> subs >>>>>>>>>>

//returns the index to the nearest LOWEST match to the int
function closestMatch($int, $arr) 
{
//print_r(func_get_args());
    $diffs = array();
    foreach ($arr as $key=>$i) 
		if (($i - $int) <= 0) $diffs["'" . abs($i - $int) . "'"] = $key; // array index = difference, value = key
	ksort($diffs);
//print_r($diffs);
		if (!empty($diffs))
			foreach ($diffs as $i) return $i; // only returns first value (lowest difference)
		else
			return 0; // value is less than min in array
}




////////////////////////////////////////////////// Functions from excel CofG Calculator - Rev0

/*
Function Area_Polynomial(x1, y1, x2, y2, x3, y3, x4, y4, x5, $y5, x6, y6, x7, y7, x8, y8, x9, y9) As Double

a1 = x1 * y2 - x2 * y1
a2 = x2 * y3 - x3 * y2
a3 = x3 * y4 - x4 * y3
a4 = x4 * y5 - x5 * y4
a5 = x5 * y6 - x6 * y5
a6 = x6 * y7 - x7 * y6
a7 = x7 * y8 - x8 * y7
a8 = x8 * y9 - x9 * y8
    
Area_Polynomial = Abs((1 / 2) * (a1 + a2 + a3 + a4 + a5 + a6 + a7 + a8))

End Function

Function Area_Circular_Segment(Chord As Double, Radius As Double) As Double

Alpha = 2 * ArcSin(Chord / (2 * Radius))
Area_Circular_Segment = (Radius ^ 2 / 2) * (Alpha - Sin(Alpha))

End Function

Function Centroid_Circular_Segment(Chord, Radius, Offset)

Alpha = 2 * ArcSin(Chord / (2 * Radius))
Centroid_Circular_Segment = Offset - ((4 * Radius * (Sin(Alpha / 2)) ^ 3) / (3 * (Alpha - Sin(Alpha))) - Radius * Cos(Alpha / 2))

End Function

Function Centroid_X_Polynomial(x1, y1, x2, y2, x3, y3, x4, y4, x5, y5, x6, y6, x7, y7, x8, y8, x9, y9, X_Offset, angle)

m1 = (x1 + x2) * (x1 * y2 - x2 * y1)
m2 = (x2 + x3) * (x2 * y3 - x3 * y2)
m3 = (x3 + x4) * (x3 * y4 - x4 * y3)
m4 = (x4 + x5) * (x4 * y5 - x5 * y4)
m5 = (x5 + x6) * (x5 * y6 - x6 * y5)
m6 = (x6 + x7) * (x6 * y7 - x7 * y6)
m7 = (x7 + x8) * (x7 * y8 - x8 * y7)
m8 = (x8 + x9) * (x8 * y9 - x9 * y8)

Area = Area_Polynomial(x1, y1, x2, y2, x3, y3, x4, y4, x5, y5, x6, y6, x7, y7, x8, y8, x9, y9)

Centroid_X_Polynomial = X_Offset + Abs((1 / (6 * Area)) * (m1 + m2 + m3 + m4 + m5 + m6 + m7 + m8)) * Cos(angle * Pi / 180)

End Function

Function ArcSin(X As Double) As Double
    ArcSin = Atn(X / Sqr(-X * X + 1))
End Function
*/


// $coords = array of XY coords for each corner of the polygon
function Area_Polynomial($coords = array())
{
	if (empty($coords) OR count($coords) < 3)
		return 0;  // no enclosed shape!
	// check first and last coords are the same!
	if ($coords[0] != end($coords))
		$coords[] = $coords[0];
	$area = 0;
	for ($i = 1; $i < count($coords); $i++)
		$area += ($coords[$i-1]['x'] * $coords[$i]['y']) - ($coords[$i]['x'] * $coords[$i-1]['y']);
	return abs($area / 2);
}

function Area_Circular_Segment($chord, $radius)
{
	$alpha = 2 * asin($chord / (2 * $radius));  // angle of segment
	return (pow($radius, 2) / 2) * ($alpha - sin($alpha));
}

function Centroid_Circular_Segment($chord, $radius, $offset = 0)
{
	$alpha = 2 * asin($chord / (2 * $radius)); // angle of segment
	return $offset - ((4 * $radius * pow((sin($alpha / 2)), 3)) / (3 * ($alpha - sin($alpha))) - $radius * cos($alpha / 2));
}

function Perimeter_Circular_Segment($chord, $radius)
{
	$alpha = 2 * asin($chord / (2 * $radius));  // angle of segment
	return $alpha * $radius;
}

function Centroid_X_Polynomial($coords = array(), $offset = 0)
{
	if (empty($coords) OR count($coords) < 3)
		return 0;
	// check first and last coords are the same!
	if ($coords[0] != end($coords))
		$coords[] = $coords[0];
	$area = Area_Polynomial($coords);
	
	$m = 0;
	for ($i = 1; $i < count($coords); $i++)
			$m += ($coords[$i-1]['x'] + $coords[$i]['x']) * ($coords[$i-1]['x'] * $coords[$i]['y'] - $coords[$i]['x'] * $coords[$i-1]['y']);
	return $offset + abs((1 / (6 * $area)) * $m);
}

// $coords = array of XY coords for each corner of the polygon
function Perimeter_Polygon($coords = array())
{
	if (empty($coords))
		return 0;  

	$per = 0;
	for ($i = 1; $i < count($coords); $i++)
			$per += sqrt(pow(abs($coords[$i]['x'] - $coords[$i -1]['x']), 2) + pow(abs($coords[$i]['y'] - $coords[$i - 1]['y']), 2));
	return $per;
}

function getWeights($groups = array())
{
	global $data;
	// CofG calc - work out weights and moments for forward and rearward cases
	$result = array('mass'=>0, 'cog'=>0);
	$moment = 0;
	foreach ($groups as $group)
		foreach ($data[$group] as $w)
		{
			if (!empty($w['qty']))
			{
				$result['mass'] += $w['qty'] * $w['M'] * 9.81;
				$moment += ($w['qty'] * $w['M'] * 9.81) * $w['X'];
			}
		}
	if (!empty($result['mass']))
		$result['cog'] = $moment / $result['mass'];
	return $result;
}
