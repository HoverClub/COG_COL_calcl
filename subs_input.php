<?php

/*
	General form input stuff

last update 28/10/13
added function to handle mutiple text input slider boxes in $data array

NOTE - this version of this file is ONLY for the CG calc - it 
does NOT WORK  with the older performance calculator
	
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
// $sels is an array of text names for each radio button. the keys are the button values
// they are numbered using the $sels array keys (+1 = 0-nn)
// $hor true to arrange buttons horizontally, false for vertical (stacked)
// $params is an array of paramters for each radio button (same index values as $sels)
function doradioinput($var, $sels, $descr, $title, $hor=true, $params=array())
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
			<input type="radio" name="' . $var . '" class="input_radio" value="' . $key . '" ' . ($data[$var] == $key  ? 'checked="checked" ' : ' ') . (!empty($params[$key]) ? ' ' . $params[$key] : '') . ' />' . $name . ($hor ? '&nbsp;' : '<br />');

//			echo '
//			<input type="radio" name="' . $var . '" class="input_radio" value="' . (!empty($buttonids) ? $buttonids[$key] : ($key+1)) . '" ' . (((!empty($buttonids) AND $data[$var] == $buttonids[$key]) OR $data[$var] == ($key + 1)) ? 'checked="checked" ' : ' ') . (!empty($params) ? ' ' . $params[$key] : '') . ' />' . $name . ($hor ? '&nbsp;' : '<br />');
		}
		echo '</dd>' . showerr($var);
	}
}

/*
 $var is variable array to show from the $data array
 $title is right side title
 $descr is under title
 $inputparams is the style for the input text box
 $cols is array of 
	'input_indexname'	=> array( 
		'title'=>"column header name", 
		'type'=>"input type (text (default), range, etc.), 
		'max'=>nn, 
		'min'=>nn (both optional and only for for range type)
		'step'=> range step size
		'size'=> width for text input
		)
*/
function doMtextinput($var, $title, $descr, $cols = array(), $inputparms='')
{
	global $data, $err_array, $dis;
	echo '
		<dt id="dt_' . $var . '">
			<strong><span ' . (isset($err_array[$var]) ? ' style="color:red;"' : '') . '>' . $title . '</span></strong>
			' . ($descr!='' ? '<br /><span class="smalltext">' . $descr . '</span>' : '') . '
		</dt>
		<dd id="dd_' . $var . '">
			<table id="CGtable_' . $var . '">
			<tr>';
	// construct html input tag parameters for each column
	$params = array();
	foreach ($cols as $index=>$col)
	{
		echo '
				<th>' . $col['title'] . '</th>';
		$params[$index] = $inputparms . (isset($col['size']) ? ' size="' . $col['size'] . '"' : '') . 
			(isset($col['type']) ? ' type="' . $col['type'] . '"' .  
				($col['type'] == 'range' ? 
					(isset($col['max']) ? ' max="' . $col['max'] . '"' : '') . 
					(isset($col['max']) ? ' min="' . $col['min'] . '"' : '') . 
					(isset($col['step']) ? ' step="' . $col['step'] . '"' : '')
				: '')
			: '');
	}

	echo '
			</tr>';
	// if no data value then add at least one empty row!
	if (empty($data[$var]))
		$data[$var][] = array_fill_keys(array_keys($cols), 0); // empty line
	foreach ($data[$var] as $index => $gdata)
	{
		if (isset($err_array[$var][$index]))
			echo '
			<tr>
				<td colspan="4"><span class="smalltext" style="color:red;">' . reset($err_array[$var][$index]) . '</span></td>
			</tr>';
		echo '
			<tr>';
		foreach ($gdata as $col => $value)
		{
			echo '
			<td> 
				<input ' . $params[$col] . ' class="input_text" name="' . $var . '[' . $index . '][' . $col . ']" value="' . htmlspecialchars($value) . '" ' . (isset($err_array[$var][$index][$col]) ? ' style="color:red;"'  : '');
			if (isset($cols[$col]['type']) AND $cols[$col]['type'] == 'range')
				echo ' id="range_' . $var . '_' .$index . '_' . $col . '" onchange="document.getElementById(\'input_' . $var . '_' .$index . '_' . $col . '\').value=this.value" /><input id="input_' . $var . '_' . $index . '_' . $col . '" value="' . htmlspecialchars($value) . '" onchange="document.getElementById(\'range_' . $var . '_' .$index . '_' . $col . '\').value=this.value" size="4"/>'; 
			else
				echo '
					/>';
			echo '
			</td>';
		}
		echo '
			</tr>';
	}
	echo '
			</table>';
	if (!$dis)
		echo '
			<a href="javascript:void(0);" onclick="addCalcRow(\'' . $var . '\', [\'' . htmlspecialchars(implode('\',\'', array_keys($cols))) . '\'], [\'' . htmlspecialchars(implode('\',\'', $params)) . '\'])">... add another item.</a>.';
	echo '
		</dd>';
}


// validate a POST numeric string - default is greater than zero and less than 1M.
//if $min is set then a value must exist, otherwise if value isn't set then it's not checked
function validatenumber($var, $max = '1e6',$min = '0', $err='')
{
	// number must be within min and max if invalid adds error text ($err) into $err_array
	global $err_array, $data;
	get_post($var); // get value into $data array first
	if (isset($data[$var]) AND ($data[$var]!='' OR $min != '0'))
	{	
		if ($data[$var] < $min OR $data[$var] > $max OR (!is_numeric($data[$var])))
			$err_array[$var] = ($err ? $err : "Value must be" . ($min == 0 ? '' : " greater than $min and") . " less than $max");
	}
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

