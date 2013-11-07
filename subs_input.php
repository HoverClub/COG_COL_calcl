<?php

/*
	General form input stuff

last update 28/10/13
added function to handl mutiple text input boxes in $data array
	
*/

// show a text input box
// $var is name of variable 
// $title is right side title
// $descr is under title
// $active is the name to be selected
// $submit is true if changing the selection should cause a form submit
// $params is an array of parameters for each radio button (same order as $sels)
// $dis is true to disable the select box

function doselinput($sels, $var, $active, $title, $descr='', $submit=false, $params=array(), $dis=false )
{
	global $err_array;
	echo '
		<dt>
			<strong><span ' . (isset($err_array[$var]) ? ' style="color:red;"' : '') . '>' . $title . '</span></strong>
			' . ($descr!='' ? '<br /><span class="smalltext">' . $descr . '</span>' : '') . '
		</dt>
		<dd>
			<select name="' . $var . '"' . ($dis ? 'disabled="disabled" ' : '') . ($submit ? ' onchange="this.form.submit()">' : '>');
		foreach ($sels as $key=>$name) 
			echo '<option value="' . htmlspecialchars($name) . '"' . ($name==$active ? ' selected ="selected"' : '') . (empty($params) ? '' : ' ' . $params[$key]) . '>' . htmlspecialchars($name) . '</option>';
		echo '
			</select>' . showerr($var) . '
		</dd>';
}

// show a text input box
// $var is variable to show from $data array
// $len is width of text input box
// $maxlen is maximum input accepted
// $title is right side title
// $descr is under title
// $unit is the unit after the input box (m, Kg, lbs, etc)
// $params controls the entire block in a <div>
function dotextinput($var, $len, $maxlen, $title, $descr, $unit='', $params='', $inputparms='')
{
	global $err_array, $data;

	if (!isset($data[$var]))
		$data[$var] = '';
	echo '
		<dt id="dt_' . $var . '" ' . $params . '>
			<strong><span ' . (isset($err_array[$var]) ? ' style="color:red;"' : '') . '>' . $title . '</span></strong>
			' . ($descr!='' ? '<br /><span class="smalltext">' . $descr . '</span>' : '') . '
		</dt>
		<dd id="dd_' . $var . '" ' . $params . '>
			<input class="input_text" size="' . $len . '" name="' . $var. '" value="' . htmlspecialchars($data[$var]) . '" maxlength="' . $maxlen . '" ' . ' ' . $inputparms . '/>' . '&nbsp;' . $unit .  showerr($var) . ' 			
		</dd>';
}

function showerr($var)
{
	global $err_array;
	if (isset($err_array["$var"])) 
		return '<br /><span class="smalltext" style="color:red;">' . $err_array[$var] . '</span>';
}

// $data[$var] is a comma-delimited string list of the 
// currentlty active (selected) check boxes (comma delimited),
// $sels is an array of text names for each checkbox 
// checkboxes are numbered using the $descr array keys (+1 = 0-nn)
// $hor true to arrnage boxes horizontally, false for vertical (stacked)
// $params is an array of paramters for each radio button (same order as $sels)
function docheckinput($var, $sels, $descr, $title, $hor=true, $params=array())
{
	global $err_array, $data;
	echo '
	<dt>
		<strong><span ' . (isset($err_array[$var]) ? ' style="color:red;"' : '') . '>' . $title . '</span></strong>
		' . ($descr!='' ? '<br /><span class="smalltext">' . $descr . '</span>' : '') . '
	</dt>
	<dd>';
	foreach ($sels as $key=>$name)
	{
		echo '
		<input type="checkbox" class="input_check" name="' . $var . '[]"' . (!empty($params) ? ' ' . $params[$key] : '') . ' value="' . ($key+1) . '" ' . (in_array((string)($key+1), explode(',',$data[$var])) ? 'checked="checked"' : '') . '/>' . $name . ($hor ? '&nbsp;' : '<br />');
	}
	echo showerr($var) . '</dd>';
}

// $data[$var] is the currently selected radio button number
// $sels is an array of text names for each radio button 
// they are numbered using the $descr array keys (+1 = 0-nn)
// $hor true to arrange buttons horizontally, false for vertical (stacked)
// $params is an array of paramters for each radio button (same order as $sels)
function doradioinput($var, $sels, $descr, $title, $hor=true, $params=array(), $buttonids=array())
{
	global $err_array, $data;
	if (!isset($data[$var]))
		return;
	else
	{
		echo '
		<dt>
			<strong><span ' . (isset($err_array[$var]) ? ' style="color:red;"' : '') . '>' . $title . '</span></strong>
			' . ($descr!='' ? '<br /><span class="smalltext">' . $descr . '</span>' : '') . '
		</dt>
		<dd>';
		foreach ($sels as $key=>$name)
		{
			echo '
			<input type="radio" name="' . $var . '" class="input_radio" value="' . (!empty($buttonids) ? $buttonids[$key] : ($key+1)) . '" ' . (((!empty($buttonids) AND $data[$var] == $buttonids[$key]) OR $data[$var] == ($key + 1)) ? 'checked="checked" ' : ' ') . (!empty($params) ? ' ' . $params[$key] : '') . ' />' . $name . ($hor ? '&nbsp;' : '<br />');
		}
		echo '</dd>' . showerr($var);
	}
}

// $var is variable array to show from $data array
// $title is right side title
// $descr is under title
// $inputparams is the style for the input text box
function doMtextinput($var, $title, $descr, $inputparms='')
{
	global $data, $err_array, $dis;
	if (!is_array($data[$var]))
		return; // not an array!
	echo '
		<dt id="dt_' . $var . '" >
			<strong><span ' . (isset($err_array[$var]) ? ' style="color:red;"' : '') . '>' . $title . '</span></strong>
			' . ($descr!='' ? '<br /><span class="smalltext">' . $descr . '</span>' : '') . '
		</dt>
		<dd>
			<table id="CGtable_' . $var . '">
			<tr>
				<th>Qty</th><th>Item Name</th><th>Weight (Kg)</th><th>Distance from bow (m)</th>
			</tr>';
	foreach ($data[$var] as $index => $gdata)
	{
		if (isset($err_array[$var][$index]))
			echo '
			<tr>
				<td colspan="4"><span class="smalltext" style="color:red;">' . reset($err_array[$var][$index]) . '</span></td>
			</tr>';
		echo '
			<tr>';
		foreach ($gdata as $key => $value)
			echo '
				<td>
					<input class="input_text" name="' . $var . '[' . $index . '][' . $key . ']" value="' . htmlspecialchars($value) . '" ' . $inputparms . (isset($err_array[$var][$index][$key]) ? ' style="color:red;"'  : '') . ' />
				</td>
			';
		echo '
			</tr>';
		}
	echo '
			</table>';
	if (!$dis)
		echo '
			<a href="javascript:void(0);" onclick="addCalcRow(\'' . $var . '\')">... add another item.</a> (to remove an item set the quantity to zero).';
	echo '
		</dd>';
}


// validate a POST numeric string - default is greater than zero and less than 1M.
function validatenumber($var, $max = '1e6',$min = '0', $err='')
{
	// check for valid numbers and saves it into $data[$var]
	// number must be within min and max if invalid adds error text ($err) into $err_array
	global $err_array, $data;
	get_post($var, '0'); // default to zero is not set
	if ($data[$var] < $min OR $data[$var] > $max OR (!is_numeric($data[$var])))
		$err_array[$var] = ($err ? $err : "Value must be greater than $min and less than $max");
}

function validateurl($var)
{
	global $data, $err_array;
	get_post($var);
	if ($data[$var] != '')
	{
		if ((strpos($data[$var], "http://")) === false) $data[$var] = "http://" . $data[$var];
		if (filter_var($data[$var],FILTER_VALIDATE_URL) === false)
			$err_array[$var] = 'This URL doesn\'t seem to be valid or the website isn\'t available';
	}
}

// get post variable $var and save in $data array
// if no Post var then set to $def
function get_post($var, $def='')
{
	global $data;
	$data[$var] = (!empty($_POST[$var]) ? trim($_POST[$var]) : $data[$var] = $def);
	return $data[$var];	
}

?>

