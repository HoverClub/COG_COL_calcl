<?php	
/*
	Centre of gravity/lift Calculator
	----------------------------------

		output the data and plot result
		
*/

	if (isset($showresult) OR isset($REQUEST['print']))
	{
		require_once($boarddir . '/modules/subs_cg.php'); // do the calculations first!
		require_once($boarddir . '/modules/phplot.php'); 

		if (isset($_REQUEST['print']))
		{
			$plot = new PHPlot(1200,800); 
			$plot->SetFont('x_label',4);
			$plot->SetFont('y_label',4);
		}
		else
		{
			$plot = new PHPlot(600,400); 
			$plot->SetFont('x_label',2);
			$plot->SetFont('y_label',2);
		}
		
		$plot->SetPrintImage(False); // No automatic output
		$plot->SetDataType('data-data');
		$plot->SetDataValues($alldata);

		$plot->SetTitle($selName);
		$plot->SetXTitle('MPH');
		$plot->SetYTitle('pound force');
		$plot->SetPlotType('lines');
		$plot->SetLineWidths(3);
		$plot->SetDrawXGrid(True);
		$plot->SetLegend($titles);
		$plot->SetXLabelAngle('90');
		$plot->SetXTickIncrement('2'); // 5mph increment
		$plot->SetYTickIncrement('20'); // 5mph increment

		$plot->DrawGraph(); // will output graph in print set
		if (isset($_REQUEST['print']))
		{
			ob_end_clean(); // dump anything that's been echo'ed already
			echo '
				<p><img src="' . $plot->EncodeImage() . '" title="' . $data['craftName'] . '" /></p>';
			exit;
		}
	}

	echo '
	<script>
	 function addCalcRow(name, keynames, params)
	 {
		var table = document.getElementById(\'CGtable_\' + name);
		var index = table.rows.length - 1;  // don\'t count the table header!
		var row = table.insertRow(-1); //add row at end of table

		for(i=0; i< keynames.length; i++)
		{
			var cell=row.insertCell(i);
			var input = \'<input class="input_text" name="\' + name + \'[\' + index + \'][\' + keynames[i] + \']"\' + params[i];
			if(params[i].indexOf(\'type="range"\') != -1)
				input = input + \'id="range_\' + name + \'_\' + index + \'_\' + i + \'" onchange="document.getElementById(\\\'input_\' + name + \'_\' + index + \'_\' + i + \'\\\').value=this.value" /><input class="input_text" id="input_\' + name + \'_\' + index + \'_\' + i + \'" onchange="document.getElementById(\\\'range_\' + name + \'_\' + index + \'_\' + i + \'\\\').value=this.value" size="4"\';
			input = input + \' />\';
			cell.innerHTML = input;
		}
	 }
	 </script>

<form name="form" action="" method="post" id="creator">
	<div class="content">
		<dl>';
	if ($dis == '')
	{
		array_unshift($craftList,'New'); // add new onto start of craft list array!
		doselinput($craftList, 'selName', $selName, 'Select craft design', 'Enter the required data into the boxes below, note that ALL units are METRES and KILOGRAMS unless otherwise specified.  Click the "Calculate/Save" button to calculate and save your data (the data won\'t be saved unless it is error free)',true);
		array_pop($craftList); // remove newtype again!
	}
	else
		doselinput(array_keys($craftList), 'selName', $selName, 'Select craft design', 'Select the craft data you wish to view', true);

	dotextinput('craftName', 40, 50, 'Craft Name and/or model', (isset($data['date']) ? ($data['date']<>'' ? 'Last modified ' . date('j M Y H:i:s',$data['date']) : '') : ''), '', '',$dis);
	echo '
		<dt>';
		if ($dis == '') // not disabled so save is allowed
		{
			echo '
			<div align="center">
				<input value="Calculate &amp; save Data" type="submit" name="docalc" class="button_submit"/>';
			if (!empty($err_array)) 
				echo '<br /><p><div style="font-family:Verdana; font-size:12px; color:red;">Sorry but there are errors in the data you have entered (shown in red).</div></p>'; 
			elseif (isset($saved)) 
				echo '<p><div style="font-family:Verdana; font-size:12px; color:green;">The data for ' . ucfirst($data['craftName']) . ' has been saved.</div></p>';
			echo '
			</div>';
		}
	echo '
		</dt>
		<dd>';
	if ($selName != '' AND $selName!='New')
		echo '
			<input type="submit" class="button_submit" style="color:red;" name="delete" value="Delete this craft" onclick="return (confirm(\'Are you sure you want to delete the entry for ' . $selName . '?\'));"/>';
		echo '
		</dd>';
	echo '</dl>
		<hr class=!"hrcolor" size="1" />';
	
	if (isset($showresult)) 
	{
		require_once('subs_cg.php');
		echo '<br/>
	<fieldset><legend style="font-family:Verdana; font-size:16px;"><b>PERFORMANCE DATA</b></legend>';
			
		if ($showtable)
		{
			echo '<br/><table align="center"  style="font-family:Verdana; font-size:9px;"><theader><tr align="middle"  style="background-color:#ffffcc;font-weight:bold;">';

			foreach (array_keys($drag[0]) as $d) 
				if ($d<>'index' AND $d<>'m/s') echo '<td>' . $d . '</td>';
			echo '</tr></theader"><tbody>';
		
			foreach ($drag as $key=>$d)
			{
				echo '<tr align="middle">';
				foreach ($d as $ky=>$k) 
				{
					switch ($ky)
					{
						case 'index':
						case 'm/s':
							break;
						case 'mph':
							echo '<td>' . round($k,2);
							break;
						default:
							echo '<td>' . round($k,0) . ' (' . round(0.224808943 * $k,1) . 'lbs)';
							break;
					}
					echo '</td>';
				}
				echo '</tr>';
			}
			echo	'</tbody>
			</table>';
		}

		// output result table first
		echo '	<br />
		<table class="table_grid" 	
		align="center" >
			<tbody>
				<tr align="middle">
				  <td class="highlight2">Maximum possible speed</td>
				  <td class="approvebg2">' . round($maxSpeed,2) . 'mph (' . round($maxSpeed * 1.609344,2) . 'Km/h)</td>
				</tr>

				<tr align="middle">
				  <td class="highlight2">Optimum cruise speed</td>
				  <td class="approvebg2">'. (isset($cruiseSpeed) ? $cruiseSpeed['mph'] . 'mph' : 'n/a') . '</td>
				</tr>

				<tr align="middle">
				  <td class="highlight2">Max. operating wind speed.</td>
				  <td class="approvebg2">'. (isset($windSpeed) ? $windSpeed . 'mph' : 'n/a') . '</td>
				</tr>

				<tr align="middle">
				  <td class="highlight2">Hump drag @ speed</td>
				  <td class="approvebg2">';
		foreach ($peaks as $pk) 
			echo 
						'<span' . ($pk['mph']==$maxPeak['mph'] ? ' style="color:red;">' : '>') . round(0.224808943 * $pk['drag'],1) . 'lbs @ ' . round($pk['mph'],1) . 'mph</span><br/>';
		echo '
				</td>
				</tr>

				<tr align="middle">
				  <td class="highlight2">Total cushion area</td>
				  <td class="approvebg2">' . round($cushionArea,2) . 'm^2 (' . round($cushionArea * 10.7639104,2) . 'ft^2)</td>
				</tr>
			
				<tr align="middle">
				  <td class="highlight2">Skirt perimeter length</td>
				  <td class="approvebg2">' . round($perimeter,2) . 'm (' . round($perimeter * 3.2808399,1) .'ft)</td>
				</tr>
			
				<tr align="middle">
				  <td class="highlight2">Design cushion pressure @ weight</td>
				  <td class="approvebg2">' . round($designCushionPressure,2) . 'Pa (' . round($designCushionPressure * 0.0208854342,1) .'lbf/ft^2)' . ' @ ' . round($designWeight /  9.81,2) . 'Kg</td>
				</tr>

				<tr align="middle">
				  <td class="highlight2">Cruise cushion pressure @ weight</td>
				  <td class="approvebg2">' . round($cruiseCushionPressure,2) . 'Pa (' . round($cruiseCushionPressure * 0.0208854342,1) .'lbf/ft^2)' . ' @ ' . round($cruiseWeight /  9.81,2) . 'Kg</td>
				</tr>'

				. (($data['directFeed']) ? '' : '<tr align="middle">
				  <td class="highlight2">Indirect cushion feed area</td>
				  <td class="approvebg2">' . round($feedArea,2) . 'm^2 (' . round($feedArea * 10.7639104,2) . 'ft^2)</td>
				</tr>') . '

				<tr align="middle">
				  <td class="highlight2">Design lift air flow</td>
				  <td class="approvebg2">' . round($designLiftAirFlow,2) . 'm^3/s (' . round($designLiftAirFlow * 35.3146667,1) .'ft^3/s)</td>
				</tr>

				<tr align="middle">
				  <td class="highlight2">Cruise lift air flow</td>
				  <td class="approvebg2">' . round($cruiseLiftAirFlow,2) . 'm^3/s (' . round($cruiseLiftAirFlow * 35.3146667,1) .'ft^3/s)</td>
				</tr>
				
				<tr align="middle">
				  <td class="highlight2">Design Engine Lift Power required</td>
				  <td class="approvebg2">' . round($designLiftPower/1000,2) . 'kW (' . round($designLiftPower * 0.00134102209,1) .'HP)</td>
				</tr>

				<tr align="middle">
				  <td class="highlight2">Cruise Engine Lift Power required</td>
				  <td class="approvebg2">' . round($cruiseLiftPower/1000,2) . 'kW (' . round($cruiseLiftPower * 0.00134102209,1) .'HP)</td>
				</tr>

			</tbody>
		</table>
		';

		// can craft get over hump speed?
		if ($maxSpeed<=$maxPeak['mph'])
			echo '<br/><span style="color:red;text-align:center;">WARNING<br/>This craft design will NOT be able to exceed hump speed!  Try increasing the craft size/engine power or reducing the weight.</span>';
		elseif ($maxSpeed > $data['maxSpeed']) echo '<br/><span style="color:red;text-align:center;">WARNING<br/>This craft can exceed the maximum operating speed that you have specified - you could use a smaller engine/fan/prop OR increase the payload.</span>';

		// show graph on the page
		echo '
			<div align="center" style="color:red;font-weight:bold;">
				<p><img src="' . $plot->EncodeImage() . '" title="' . $data['craftName'] . '" /></p>
				<p><input class="button_submit" type="submit" name="print" value="Large Size Graph" style="padding:4px;"></p>
			</div>
		</fieldset>';
	}
	
	echo '
		<dl>
			<dd><strong>GENERAL</strong></dd>
			<dt></dt>
		</dl>
		<dl>';
	dotextinput('maxSpeed', 5, 5, 'Maximum operating speed', 'Available from manufacturer or designer (usually on ice or other smooth surfaces)', 'MPH', '', $dis);
	
	echo '
		</dl>
		<hr class=!"hrcolor" size="1" />
		<dl>
			<dd><strong>CRAFT HULL</strong></dd>
			<dt></dt>
		</dl>
		<dl>';
	dotextinput('hullLength', 10, 10, 'Craft length', 'Hard structure length', 'metres', '', $dis);
	dotextinput('hullWidth', 10, 10, 'Craft hull width.', 'Hard structure width (off-cushion)', 'metres', '', $dis);
	dotextinput('sternChamf', 10, 10, ' Length of rear corner angles', 'The length of any corner chamfers at rear of hull (set to 0 if rear has square corners)', 'metres', '', $dis);
	dotextinput('frontalArea', 5, 5, 'Frontal Area', 'The area of the front profile of the craft whilst on hover - don\'t include the fan/prop duct area', 'Square Metres', '', $dis);
	dotextinput('dragCoeff', 5, 5, 'Drag coefficient', 'Most craft are around 0.4, smooth & pointy = 0.2, boxy = 0.8', '', '', $dis);	
	echo '
		</dl>
		<hr class=!"hrcolor" size="1" />
		
		<dl>
			<dd><strong>SKIRT & LIFT SYSTEM</strong></dd>
			<dt></dt>
		</dl>
		<dl>';
	dotextinput('contactoffset', 5, 5, 'Skirt Contact Line Offset', 'How far in the skirt contact point (where the tips touch the surface) is from the hard hull edge at the sides and rear.', 'metres', '', $dis);	
	dotextinput('bowskirtfront', 5, 5, 'Bow Skirt Contact Line Offset', 'How far the front skirt contact point is (where the tips touch the surface) from the very front of the hull.', 'metres', '', $dis);	
	dotextinput('bowradius', 5, 5, 'Bow skirt radius', 'The approximate radius of the bow part of the skirt the shape it takes up when the craft is on-cushion).', 'metres', '', $dis);	
	dotextinput('skirtGap', 4, 4, 'Skirt gap', 'If your craft will fly mostly over smooth surfaces then 0.012m to 0.018m is usually OK.  If you operate over rough water or other rough surfaces (long grass, etc) then 0.020 to .025m hover gap may be better. If in doubt use a higher value.','metre','',$dis);	
	dotextinput('reserve', 4, 4, 'Lift reserve','The Lift System reserve should be at least 50% to allow for operation on rough surfaces - higher values may be needed for very rough water.', 'percent','',$dis);	

	doradioinput(
		'twinFan', 
		array('twinEng'=>'Twin engine','twinFan'=>'Single engine, twin fan', 'int'=>'Single engine, single fan (integrated)'),
		'<i>Twin engine</i>: one engine for thrust and another for lift.<br /><i>Single engine twin fan</i>: separate lift and thrust fans/propeller.<br /><i>Single engine, single fan</i>: one engine and one fan supplies BOTH lift and thrust (integrated)','Lift engine',
		false, 
		array(
			'twinEng'=>$dis . ' onclick="getElementById(\'dt_splitterHeight\').style.display =\'none\';;"',
			'twinFan'=> $dis . ' onclick="getElementById(\'dt_splitterHeight\').style.display =\'none\';"',
			'int'=>$dis . ' onclick="getElementById(\'dt_splitterHeight\').style.display =\'block\';"')
		); 

	echo '
		</dl>
		<div id="dt_splitterheight">
			<dl>';
	dotextinput('splitterHeight', 5, 10, 'Splitter Height','Integrated lift fan splitter plate height.', 'metre','style="display:' .(($data['twinFan']=='int' ? 'block;"' : 'none;"')), $dis);	
	echo '
			</dl>
		</div>
		<dl>';
	
	doradioinput(
		'skirt', 
		array('finger'=>'Finger/segment or loop/segment','bag'=>'Bag skirt'),
		'',
		'Skirt type',
		false,
		array('finger'=>$dis, 'bag'=>$dis)
		);
		
	doradioinput(
		'directFeed',
		array('0'=>'Indirect', '1'=>'Direct'),
		'Direct means the lift air goes directly into the cushion (no duct or plenum), <strong>indirect</strong> that it passes through a plenum, loop or bag before reaching the main cushion',
		'Skirt feed type',
		false,
		array(
			'0'=>$dis . ' onclick="getElementById(\'feedholes\').style.display =\'block\';getElementById(\'feed_type\').style.display =\'block\';"',
			'1'=>$dis . ' onclick="getElementById(\'feedholes\').style.display =\'none\';getElementById(\'feed_type\').style.display =\'none\';"')
		);
	echo '
		</dl>
		<div id="feedholes" style="display:' . ($data['directFeed']=='0' ? 'block' : 'none') . ';">
			<dl>';
		doMtextinput('feed_holes','Plenum Feed Holes','Please enter the quantity and size of the air feed holes in the plenum or bag skirt that feed the lift air into the main cushion under the craft (on a segmented skirt craft these are normally the holes in the hull near the top of each skirt segment).  To remove a hole type set the quantity to zero.', 
			array(	'qty'=>array('title'=>'Qty', 'size'=>3),
					'diam'=>array('title'=>'Diameter (m)', 'size'=>8),
				), 
		$dis);
		
		echo '
			</dl>
		</div>
		<hr class=!"hrcolor" size="1" />
		
		<dl>
			<dd><strong>THRUST SYSTEM</strong></dd>
			<dt></dt>
		</dl>
		<dl>';
	doradioinput(
		'prop', 
		array('1'=>'Propeller','0'=>'Fan'),
		'',
		'Thrust Device', 
		true, 
		array('1'=>$dis, '0'=>$dis)
		);
	dotextinput('fanDiam', 5, 10, 'Prop/Fan diameter','', 'metre','' ,$dis);	
	dotextinput('thrustY', 5, 10, 'Prop/Fan Centre Height','The distance the center of the thrust fan/pop is from the surface when the craft is on full hover.', 'metre', '', $dis);	
	dotextinput('tPower', 5, 10, 'Engine power','If it\'s a twin engine craft then only enter the THRUST engine power.  For single engine craft, the program will automatically subtract the amount of power needed (calculated) for lift using the remainder for thrust.  In all cases, you should reduce the engine manufacturer\'s power figure by at least 10% to allow for real-world variations and conditions.', 'HP', '', $dis);	
	echo '	</dl>';

	echo '
		<hr class=!"hrcolor" size="1" />
		<div><strong>COMPONENT WEIGHTS & POSITIONS</strong></div>
		<div class="smalltext">The position, weight and quantity of all components that make up the hovercraft.  "Distance from Bow" is how far the centre of gravity of the object is from the very front of the craft hard structure.  You can measure the CofG by balancing the object on a round pole.  For seated occupants, the front-to-back CofG is level with the navel (belly-button).<br><br></div>
		<dl>';

	// multi column fields - columns idex name => column description
	$cols = array(	'qty'=>array('title'=> 'Qty', 'size'=>3),
					'title'=>array('title'=> 'Item Name','size'=>23),
					'M'=>array('title'=> 'Weight','size'=>4),
					'X'=>array('title'=> 'Distance from bow(m)', 'type'=>'range', 'min'=>0, 'max'=> $data['hullLength'] + 1, 'step'=>0.1,),
					);
	doMtextinput('hull','Hull Components','Items that are contained in the craft hull. Initial items shown are just examples - you can edit or remove them (set the quantity to zero) if you wish (to remove an item set the quantity to zero).', $cols, $dis);
	doMtextinput('engines','Engines & Transmission Components','Engines, fans, frames, etc. used to lift or propel the craft. Initial items shown are just examples - you can edit or remove them if you wish (to remove an item set the quantity to zero).', $cols, $dis);
	doMtextinput('misc','Miscellaneous Components','Other items in the craft (skirt, windscreen. tools, seats, batteries, etc. Initial items shown are examples - you can edit or remove them if you wish (to remove an item set the quantity to zero).', $cols, $dis);
	
	doMtextinput('max_load','Passengers, Fuel and Equipment - maximum.','Combined with the hull, engines and misc., the is the maximum load the craft is designed to carry (to remove an item set the quantity to zero).', $cols, $dis);
	echo '
		</dl>
		<hr class=!"hrcolor" size="1" />
		<dl>
			<dd><strong>CUSHION TYPE</strong></dd>
			<dt></dt>
		</dl>
		<dl>';
	doradioinput(
		'dual_comp', 
		array('single'=>'Single compartment cushion','dual'=>'Dual compartment cushion'),
		'With a single compartment cushion there is no divider skirt, dual compartment has a partition skirt or flap.',
		'Cushion Type',
		false, 
		array(
			'single' => $dis . ' onclick="getElementById(\'div_divider\').style.display =\'none\';"',
			'dual' => $dis . ' onclick="getElementById(\'div_divider\').style.display =\'block\';"')
		);
	echo '
			</dl>
			<div id="div_divider" style="display:' . ($data['dual_comp']=='dual' ? 'block' : 'none') . ';">
				<dl>';
	dotextinput('dividerfront', 5, 5, 'Cushion Partition (divider) Offset', 'How far from the front of the hull the front of the divider or partition skirt is.', 'metres', '', $dis);	
	dotextinput('divradius', 5, 5, 'Divider/partition skirt radius', 'The approximate radius of the divider/partition skirt when the the craft is on-cushion).', 'metres', '', $dis);
	echo '
				</dl>
				<div id="feed_type" style="display:' . ($data['directFeed'] ? 'none' : 'block') . ';">
					<dl>';
	doradioinput(
		'feed_type', 
		array('underskirt'=>'Feed is from under divider skirt','plenum'=>'Feed is from plenum.'),
		'If your craft has an air feed plenum around the outer edge, then feed air for the front compartment can be supplied either from the main compartment (under the divider skirt lower edge - i.e. there are no feed holes in front compartment section of the plenum) OR fed from plenum feed holes in the front compartment.',
		'Front Compartment Feed',
		false, 
		array($dis, $dis)
		);
	echo '
					</dl>
				</div>
				<dl>';
	
	doMtextinput('pass_forward','Passengers, Fuel and Equipment - in NOSE heavy load postion.','The is the worst case FORWARD loading position, usually Driver and any passengers sitting at his side, with any extra equipment all mounted in the most forward position possible. The idea is to cover the most nose-down load case that might be envisaged. Initial items shown are examples - you can edit or remove them if you wish.', $cols, $dis);
	doMtextinput('pass_rearward','Passengers, Fuel and Equipment - in the TAIL heavy load postion.','This is the worst case REARWARD load setup- usually with only the driver up front with all passengers in their rear seats with any equipment,fual,etc. also all at the rear. The idea is to cover the most rear-heavy case that can be envisaged. Initial items shown are examples - you can edit or remove them if you wish (to remove an item set the quantity to zero).', $cols, $dis);
	echo '
				</dl>
			</div>';

	if ($dis == '') // not disabled so save is allowed
		echo '
			<div align="center">
				<input value="Calculate &amp; save Data" type="submit" name="docalc" class="button_submit"/>
			</div>';
	echo '
	</div>
</form>';
			
?>