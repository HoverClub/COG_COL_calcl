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

		$plot->SetTitle($craftName);
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

	dotextinput('craftName', 40, 50, 'Craft Name and/or model', (isset($data['date']) ? ($data['date']<>'' ? 'Last modified ' . date('r',$data['date']) : '') : ''), '', '',$dis);
	echo '
		<dt>';
		if ($dis == '') // not disabled so save is allowed
		{
			echo '
			<div align="center">
				<input value="Calculate &amp; save Data" type="submit" name="docalc" class="button_submit"/>';
			if (!empty($err_array)) 
				echo '<br /><p><div style="font-family:Verdana; font-size:12px; color:red;">There are errors in the data you have entered - they are marked in red</div></p>'; 
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
		require_once('subs_hcalc.php');
		echo '<br/>
	<fieldset><legend style="font-family:Verdana; font-size:16px;"><b>PERFORMANCE DATA</b></legend>';
			
		if ($showtable)
		{
			echo '<br/><table border="1" align="center"  style="font-family:Verdana; font-size:10px;"><theader><tr align="middle"  style="background-color:#ffffcc;font-weight:bold;">';

			foreach (array_keys($drag[0]) as $d) 
				if ($d<>'index' AND $d<>'m/s') echo '<td>' . $d . '</td>';
			echo '</tr></theader"><tbody>';
		}
		
		foreach ($drag as $key=>$d)
		{
			$totDrag = $d['Max Mom Drag'] + $d['Max Wave Drag'] + $d['Profile Drag'] + $d['Wetting Drag'];
			if ($showtable)
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
		}
		
		if ($showtable)
		{
			echo	'</tbody>
			</table>';
		}

		// output result table first
		echo '	<br /><table border="1" align="center" >
			<tbody style="font-family:Verdana; font-size:10px;">
				<tr align="middle">
				  <td bgcolor="#99CCFF">Maximum possible speed</td>
				  <td bgcolor="#e0e0e0" style="font-size:16px; text-align:center;">' . round($maxSpeed,2) . 'mph (' . round($maxSpeed * 1.609344,2) . 'Km/h)</td>
				</tr>

				<tr align="middle">
				  <td bgcolor="#99CCFF">Optimum cruise speed</td>
				  <td bgcolor="#e0e0e0" style="font-size:16px; text-align:center;">'. (isset($cruiseSpeed) ? $cruiseSpeed['mph'] . 'mph' : 'n/a') . '</td>
				</tr>

				<tr align="middle">
				  <td bgcolor="#99CCFF">Max. operating wind speed.</td>
				  <td bgcolor="#e0e0e0" style="font-size:16px; text-align:center;">'. (isset($windSpeed) ? $windSpeed . 'mph' : 'n/a') . '</td>
				</tr>

				<tr align="middle">
				  <td bgcolor="#99CCFF">Hump drag @ speed</td>
				  <td bgcolor="#e0e0e0" style="font-size:16px; text-align:center;">';
		foreach ($peaks as $pk) 
			echo 
						'<span' . ($pk['mph']==$maxPeak['mph'] ? ' style="color:red;">' : '>') . round(0.224808943 * $pk['drag'],1) . 'lbs @ ' . round($pk['mph'],1) . 'mph</span><br/>';
		echo '
				</td>
				</tr>

				<tr align="middle">
				  <td bgcolor="#99CCFF">Total cushion area</td>
				  <td bgcolor="#e0e0e0" style="font-size:16px; text-align:center;">' . round($cushionArea,2) . 'm^2 (' . round($cushionArea * 10.7639104,2) . 'ft^2)</td>
				</tr>
			
				<tr align="middle">
				  <td bgcolor="#99CCFF">Skirt perimeter length</td>
				  <td bgcolor="#e0e0e0" style="font-size:16px; text-align:center;">' . round($perimeter,2) . 'm (' . round($perimeter * 3.2808399,1) .'ft)</td>
				</tr>
			
				<tr align="middle">
				  <td bgcolor="#99CCFF">Design cushion pressure</td>
				  <td bgcolor="#e0e0e0" style="font-size:16px; text-align:center;">' . round($designCushionPressure,2) . 'Pa (' . round($designCushionPressure * 0.0208854342,1) .'lbf/ft^2)</td>
				</tr>

				<tr align="middle">
				  <td bgcolor="#99CCFF">Cruise cushion pressure</td>
				  <td bgcolor="#e0e0e0" style="font-size:16px; text-align:center;">' . round($cruiseCushionPressure,2) . 'Pa (' . round($cruiseCushionPressure * 0.0208854342,1) .'lbf/ft^2)</td>
				</tr>'

				. (($directFeedArray[$data['directFeed']]) ? '' : '<tr align="middle">
				  <td bgcolor="#99CCFF">Indirect cushion feed area</td>
				  <td bgcolor="#e0e0e0" style="font-size:16px; text-align:center;">' . round($feedArea,2) . 'm^2 (' . round($feedArea * 10.7639104,2) . 'ft^2)</td>
				</tr>') . '

				<tr align="middle">
				  <td bgcolor="#99CCFF">Design lift air flow</td>
				  <td bgcolor="#e0e0e0" style="font-size:16px; text-align:center;">' . round($designLiftAirFlow,2) . 'm^3/s (' . round($designLiftAirFlow * 35.3146667,1) .'ft^3/s)</td>
				</tr>

				<tr align="middle">
				  <td bgcolor="#99CCFF">Cruise lift air flow</td>
				  <td bgcolor="#e0e0e0" style="font-size:16px; text-align:center;">' . round($cruiseLiftAirFlow,2) . 'm^3/s (' . round($cruiseLiftAirFlow * 35.3146667,1) .'ft^3/s)</td>
				</tr>
				
				<tr align="middle">
				  <td bgcolor="#99CCFF">Design Engine Lift Power required</td>
				  <td bgcolor="#e0e0e0" style="font-size:16px; text-align:center;">' . round($designLiftPower/1000,2) . 'kW (' . round($designLiftPower * 0.00134102209,1) .'HP)</td>
				</tr>

				<tr align="middle">
				  <td bgcolor="#99CCFF">Cruise Engine Lift Power required</td>
				  <td bgcolor="#e0e0e0" style="font-size:16px; text-align:center;">' . round($cruiseLiftPower/1000,2) . 'kW (' . round($cruiseLiftPower * 0.00134102209,1) .'HP)</td>
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
			<dd><strong>CRAFT & HULL</strong></dd>
			<dt></dt>
		</dl>
		<dl>';
		
	doradioinput('rectShape', array('Rectangular','Rounded'),'The shape of your craft is assumed to be either rectangular or with semi-circular front and rear. If your craft is a different shape, then enter length and width dimensions that will give you the same cushion area as your craft (see calculator results).','Craft shape',false, array($dis,$dis));
	dotextinput('hullLength', 10, 10, 'Craft length', 'Hard structure length', 'metres', '', $dis);
	dotextinput('hullWidth', 10, 10, 'Craft width', 'Hard structure width (off-cushion)', 'metres', '', $dis);
	dotextinput('designWeight', 10, 10, 'Maximum weight','Total all-up weight of craft when fully loaded with the maximum permitted payload (craft empty weight plus max. no. of passengers, full fuel load, gear, etc.)',  'Kg', '', $dis);
	dotextinput('cruiseWeight', 10, 10, 'Cruise weight', 'Normal all-up cruise weight (craft empty weight plus one occupant, fuel, gear, etc.?)', 'Kg', '', $dis);
	dotextinput('maxSpeed', 5, 5, 'Maximum operating speed', 'Available from manufacturer or designer (usually on ice or other smooth surfaces)', 'MPH', '', $dis);
	dotextinput('frontalArea', 5, 5, 'Frontal Area', 'The area of the front profile of the craft whilst on hover - don\'t include the fan/prop duct area', 'Square Metres', '', $dis);
	dotextinput('dragCoeff', 5, 5, 'Drag coefficient', 'Most craft are around 0.4, smooth & pointy = 0.2, boxy = 0.8', '', '', $dis);	
	echo '
		</dl>
		<hr class=!"hrcolor" size="1" />
		
		<dl>
			<dd><strong>LIFT SYSTEM</strong></dd>
			<dt></dt>
		</dl>
		<dl>';
	dotextinput('skirtGap', 4, 4, 'Skirt gap', 'If your craft will fly mostly over smooth surfaces then 0.012m to 0.018m is usually OK.  If you operate over rough water or other rough surfaces (long grass, etc) then 0.020 to .025m hover gap may be better. If in doubt use a higher value.','metre','',$dis);	
	dotextinput('reserve', 4, 4, 'Lift reserve','The Lift System reserve should be at least 50% to allow for operation on rough surfaces - higher values may be needed for very rough water.', 'percent','',$dis);	
	doradioinput('twinFan', array('Twin engine','Single engine, twin fan', 'Single engine, single fan (integrated)'),'<i>Twin engine</i>: one engine for thrust and another for lift.<br /><i>Single engine twin fan</i>: separate lift and thrust fans/propeller.<br /><i>Single engine, single fan</i>: one engine and one fan supplies BOTH lift and thrust (integrated)','Lift engine',false, array($dis . ' onclick="getElementById(\'dt_splitterHeight\').style.display =\'none\';getElementById(\'dd_splitterHeight\').style.display =\'none\';"',$dis . ' onclick="getElementById(\'dt_splitterHeight\').style.display =\'none\';getElementById(\'dd_splitterHeight\').style.display =\'none\';"',$dis . ' onclick="getElementById(\'dt_splitterHeight\').style.display =\'block\';getElementById(\'dd_splitterHeight\').style.display =\'block\';"'));

	dotextinput('splitterHeight', 5, 10, 'Splitter Height','Integrated lift fan splitter plate height.', 'metre','style="display:' .(($data['twinFan']=='3' ? 'block;"' : 'none;"')), $dis);	

	doradioinput('skirt', array('Finger/segment or loop/segment','Bag skirt'),'','Skirt type',false,array($dis,$dis));
	doradioinput('directFeed', array('Direct','Indirect'),'Direct means the lift air goes directly into the cushion (no duct or plenum), <strong>indirect</strong> that it passes through a plenum, loop or bag before reaching the main cushion','Skirt feed type',false, array($dis . ' onclick="getElementById(\'dt_feedHoles\').style.display =\'none\';getElementById(\'dd_feedHoles\').style.display =\'none\';"',$dis . ' onclick="getElementById(\'dt_feedHoles\').style.display =\'block\';getElementById(\'dd_feedHoles\').style.display =\'block\';"'));
	echo '
			<dt id="dt_feedHoles" style="display:' . ($data['directFeed']=='2' ? 'block;"' : 'none;"') . '">
				<strong><span ' . (isset($err_array['feedholes']) ? ' style="color:red;"' : '') . '>Air feed holes</span></strong>
				<br /><span class="smalltext">Please enter the quantity and size of the air feed holes in the plenum or bag skirt that feed the lift air into the main cushion under the craft (on a segmented skirt craft these are normally the holes in the hull near the top of each skirt segment)</span>
			</dt>
			<dd id="dd_feedHoles" style="display:' . ($data['directFeed']=='2' ? 'block;"' : 'none;"') . '">
				<table align="center"  border="1">
					<theader>
						<tr>
							<td>&nbsp;</td><td>Quantity</td><td>Diameter</td>
						</tr>
					</theader>
					<tbody>';
		for($i=1; $i<=3; $i++)
			echo '		<tr>
							<td>Size ' . $i . ' holes</td>
							<td>					
								<input name="hole' . $i . 'qty"' . $dis . ' class="input_text" value="' . (isset($data['hole' . $i . 'qty']) ? $data['hole' . $i . 'qty'] : '') . '" size="10" maxlength="10">' . showerr('hole' . $i . 'qty') . '
							</td>
							<td>					
								<input name="hole' . $i . 'size"' . $dis . ' class="input_text" value="' . (isset($data['hole' . $i . 'size']) ? $data['hole' . $i . 'size'] : '') . '" size="10" maxlength="10"> metre' . showerr('hole' . $i . 'size') . '
							</td>
						</tr>';
		echo '		</tbody>
				</table>
			</dd>
		</dl>
		<hr class=!"hrcolor" size="1" />
		
		<dl>
			<dd><strong>THRUST SYSTEM</strong></dd>
			<dt></dt>
		</dl>
		<dl>';
	doradioinput('prop', array('Propeller','Fan'),'','Thrust Device', true, array($dis,$dis));
	dotextinput('fanDiam', 5, 10, 'Prop/Fan diameter','', 'metre','' ,$dis);	
	dotextinput('tPower', 5, 10, 'Engine power','If it\'s a twin engine craft then only enter the THRUST engine power.  For single engine craft, the program will automatically subtract the amount of power needed (calculated) for lift using the remainder for thrust.  In all cases, you should reduce the engine manufacturer\'s power figure by at least 10% to allow for real-world variations and conditions.', 'HP', '', $dis);	
	echo '	</dl>
		</div>
</form>';
			
?>