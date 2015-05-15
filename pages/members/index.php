<?php
// note: need to record load time for page (performance)
// note: initial load on the page will take longer because it checks and renders files that are needed for this
// 1. initial startup to set up files (.json files with departments and members) & tagging members
// 2. post startup: check if database and .json files are in sync (check if num members are same)

global $CONFIG;
$data_directory = $CONFIG->dataroot.'gc_dept'.DIRECTORY_SEPARATOR;	// /www/elggdata/gc_dept
$start_timer = microtime(true);										// record the initial time

$num_members = get_number_users();
$title = elgg_echo('members');

// determine how to render the page depending on which tab the user selects
$options = array('type' => 'user', 'full_view' => false);
switch ($vars['page'])
{
	case 'popular':
		$options['relationship'] = 'friend';
		$options['inverse_relationship'] = false;
		$content = elgg_list_entities_from_relationship_count($options);
		break;
	case 'online':
		$content = get_online_users();
		break;
	case 'department':
		$content = '<p>'.elgg_echo('c_bin:sort_by').'<a href="'.elgg_get_site_url().'members/department?sort=alpha">'.elgg_echo('c_bin:sort_alpha').'</a> | <a href="'.elgg_get_site_url().'members/department?sort=total">'.elgg_echo('c_bin:sort_totalUsers').'</a></p>'; 
		$content .= render_department_tab($data_directory);
		break;
	case 'newest':
	default:
		$content = elgg_list_entities($options);
		break;
}

$params = array(
	'content' => $content,
	'sidebar' => elgg_view('members/sidebar'),
	'title' => $title . " ($num_members)",
	'filter_override' => elgg_view('members/nav', array('selected' => $vars['page'])),
);


$body = elgg_view_layout('content', $params);

$end_timer = microtime(true);										// record the end time
$time = number_format($end_timer - $start_timer, 2);				// difference the two recorded time
$body .= '<br/>'.elgg_echo('c_bin:estimated_loaded',array($time));
echo elgg_view_page($title, $body);


/* *************** PAGE FUNCTIONS *************** */

// use this function for only when department tab is selected (reduce performance load)
function render_department_tab($data_directory) {
	gatekeeper();
	// check necessary files in elggdata-prod2/gc_dept: department-listing.csv, department_listing.json, department_directory.json, all_departments.json
	
	$dbprefix = elgg_get_config("dbprefix");
	
	if (!file_exists($data_directory.'department_listingEN.json') || !file_exists($data_directory.'department_listingFR.json'))
	{
		if (!file_exists($data_directory.'department-listing.csv'))
		{
			//error_log('cyu - missing department-listing.csv');
			// cyu - TODO::05/14/2015: please put notice here regarding error
		}

		$csvData = file_get_contents($data_directory.'department-listing.csv');
		$lines = explode(PHP_EOL, $csvData);
		$department_listing = array();
		foreach ($lines as $line) {
			$dept_info = explode(';', $line);
			$department_listing[] = $dept_info;
		}

		$department_name = array();

		$department_nameEN[] = array();
		$department_nameFR[] = array();

		foreach ($department_listing as $department) {
			//$department_name[$department[0]] = $department[2].'|'.$department[1];

			// cyu - 05/14/2015: modified for bilingual departments
			$dept_name = explode('/',$department[2]);
			$dept_nameEN = $dept_name[0];
			$dept_nameFR = $dept_name[1];

			$department_nameEN[$department[0]] = $dept_nameEN.'|'.$department[1];
			$department_nameFR[$department[0]] = $dept_nameFR.'|'.$department[1];
		}

		foreach ($department_listing as $abbrev)
		{
			if (!is_array($department_nameEN[$abbrev[2]]))
				$department_name[$abbrev[2]] = array('abbreviation' => $abbrev[1]);

			$tmp_arr = array_merge($department_nameEN[$abbrev[2]], array($abbrev[0] => $abbrev[2].'|'.$abbrev[1]));
			$department_nameEN[$abbrev[2]] = $tmp_arr;

			if (!is_array($department_nameFR[$abbrev[2]]))
				$department_nameFR[$abbrev[2]] = array('abbreviation' => $abbrev[1]);

			$tmp_arr = array_merge($department_nameFR[$abbrev[2]], array($abbrev[0] => $abbrev[2].'|'.$abbrev[1]));
			$department_nameFR[$abbrev[2]] = $tmp_arr;
		}

		// cyu - 05/24/2015: puts all the departments into this file
		file_put_contents($data_directory.'department_listingEN.json', json_encode($department_nameEN));
		file_put_contents($data_directory.'department_listingFR.json', json_encode($department_nameFR));

		// cyu - TODO::05/14/2015: Invalid argument supplied error...
		foreach ($information_array as $key => $single_information)
		{
			if ($key !== 'members')
			{
				for ($i = 0; $i < count($single_information); $i++) {
					$department_name[$key.'.gc.ca'] .= '|'.$single_information[$i];
				}
			}
		}
		unset($information_array);
		unset($department_listing);
	}

	// cyu - 05/14/2015: there will be two files that will distinguish the EN and FR
	if (!file_exists($data_directory.'all_departmentsEN.json'))
	{
		foreach ($department_nameEN as $dept => $abbreviate) {
			$name_list[] = $abbreviate;
		}
		$name_list = array_unique($name_list);
		file_put_contents($data_directory.'all_departmentsEN.json',json_encode($name_list));
	}

	if (!file_exists($data_directory.'all_departmentsFR.json'))
	{
		foreach ($department_nameFR as $dept => $abbreviate) {
			$name_list[] = $abbreviate;
		}
		$name_list = array_unique($name_list);
		file_put_contents($data_directory.'all_departmentsFR.json',json_encode($name_list));
	}


	if (!file_exists($data_directory.'department_directory.json'))
	{	
		global $CONFIG;
		$query = "SELECT ue.email, ue.name, e.time_created, e.guid FROM ".$dbprefix."entities e, ".$dbprefix."users_entity ue WHERE e.type = 'user' AND e.guid = ue.guid AND e.enabled = 'yes'";

		$connection = mysqli_connect($CONFIG->dbhost, $CONFIG->dbuser, $CONFIG->dbpass, $CONFIG->dbname);
		if (mysqli_connect_errno($connection)) error_log("cyu_index.php (members by department) : Failed to connect to MySQL: ".mysqli_connect_errno());
		$result = mysqli_query($connection,$query);

		$array_of_users = array();
		while ($row = mysqli_fetch_array($result))
		{
			$domain = explode('@', strtolower($row['email']));
			$filter_domain = explode('.', $domain[1]);
			if ($filter_domain[count($filter_domain) - 2].'.'.$filter_domain[count($filter_domain) - 1] === 'gc.ca')
				$array_of_users[$row['guid']] = $filter_domain[0];
			else
				$array_of_users[$row['guid']] = $domain[1];
		}
	
		$main_json_file = array();
		$count_members = array();
		$main_json_file['members'] = max(array_keys($array_of_users));
		$count_members = array_count_values($array_of_users);
		$main_json_file = array_merge($main_json_file, $count_members);
		
		foreach ($array_of_users as $key => $value)
		{
			if (!is_array($main_json_file[$value]))
			{
				$num_members = $main_json_file[$value];
				$main_json_file[$value] = array('members' => $main_json_file[$value]);
			}
			
			$query_user = "SELECT ue.email, ue.username, ue.guid, e.time_created, ue.name FROM ".$dbprefix."entities e, ".$dbprefix."users_entity ue WHERE e.type = 'user' AND e.guid = ue.guid AND e.enabled = 'yes' AND e.guid = ".$key;
			$result_user = mysqli_query($connection,$query_user);
			
			$query_cache = "SELECT time_created FROM ".$dbprefix."metadata WHERE name_id = 73 AND enabled = 'yes' AND entity_guid = ".$key;
			$result_cache = mysqli_query($connection,$query_cache);
			
			$row_cache = mysqli_fetch_assoc($result_cache);
			$row_user = mysqli_fetch_assoc($result_user);

			if (!$row_cache)
				$icon_url = elgg_get_site_url()."_graphics/icons/user/defaultmedium.gif";
			else
				$icon_url = elgg_get_site_url()."mod/profile/icondirect.php?lastcache=".$row_cache['time_created']."&joindate=".$row_user['time_created']."&guid=".$key."&size=medium";
			
			$tmp_arr = array_merge($main_json_file[$value], array($key => utf8_encode(strtolower($row_user['email'])).'|'.utf8_encode(strtolower($row_user['username'])).'|'.$key.'|'.$row_user['time_created'].'|'.utf8_encode($row_user['name']).'|'.$icon_url));
			$main_json_file[$value] = $tmp_arr;
			
			mysqli_free_result($result_cache);
			mysqli_free_result($result_user);
		}
		$write_to_json = file_put_contents($data_directory.'department_directory.json', json_encode($main_json_file));

		mysqli_close($connection);
	}
	
	unset($main_json_file);
	unset($count_members);

	// cyu - 05/15/2015: made it bilingual between french and english, there are now two files that distinguish the two
	if (!isset($_COOKIE["connex_lang"])) {
		$department_name = json_decode(file_get_contents($data_directory.'department_listingEN.json'), true);
		$information_array = json_decode(file_get_contents($data_directory.'department_directory.json'), true);
		$name_list = json_decode(file_get_contents($data_directory.'all_departmentsEN.json'), true);
	} else {
		if ($_COOKIE["connex_lang"] === 'en') {
			$department_name = json_decode(file_get_contents($data_directory.'department_listingEN.json'), true);
			$information_array = json_decode(file_get_contents($data_directory.'department_directory.json'), true);
			$name_list = json_decode(file_get_contents($data_directory.'all_departmentsEN.json'), true);
		} else {
			$department_name = json_decode(file_get_contents($data_directory.'department_listingFR.json'), true);
			$information_array = json_decode(file_get_contents($data_directory.'department_directory.json'), true);
			$name_list = json_decode(file_get_contents($data_directory.'all_departmentsFR.json'), true);
		}
	}


	// $department_name = json_decode(file_get_contents($data_directory.'department_listingEN.json'), true);
	// $information_array = json_decode(file_get_contents($data_directory.'department_directory.json'), true);
	// $name_list = json_decode(file_get_contents($data_directory.'all_departmentsEN.json'), true);

	//  pack the necessary data into an array 
	$main_arr = array();
	$name_list = array();
	foreach ($department_name as $dept => $abbreviate) {
		$name_list[] = $abbreviate;
	}
	$name_list = array_unique($name_list);
	$some_array = array();

	foreach ($name_list as $each_dept)
	{
		$domain_names = array_keys($department_name, $each_dept);

		$used_domains = array();
		if ($domain_names) {
			$membercount = 0;
			foreach ($domain_names as $domain_keys)
			{
				// some keys are stored like this: tbs-sct and tbs-sct.gc.ca
				if (count($information_array[$domain_keys]) > 0)
				{
					if (!in_array($domain_keys,$used_domains))
					{
						if (!$information_array[$domain_keys]['members'])
							$membercount = $membercount + 0;
						else {
							$membercount = $membercount + $information_array[$domain_keys]['members'];
							$used_domains[] = $domain_keys;
						}
					}
				} else {
					$domain_keys = explode('.', $domain_keys);
					if (!in_array($domain_keys[0],$used_domains))
					{
						if (!$information_array[$domain_keys[0]]['members'])
							$membercount = $membercount + 0;
						else {
							$membercount = $membercount + $information_array[$domain_keys[0]]['members'];
							$used_domains[] = $domain_keys[0];
						}
					}
				}
			}
			$some_array[(string)$each_dept] = $membercount;
		}
	}


	// sort the array depending on the option the user chose then display in the table
	$some_array = array_filter($some_array);

	if (get_input('sort') === 'alpha')
	{
		ksort($some_array);
	} else {
		arsort($some_array);
	}

	$display = '';
	$display .= "<table width='100%' cellpadding='0' cellspacing='0' style='border-right:1px solid #ccc; border-bottom:1px solid #ccc;'>";
	$display .= '<tr> <th style="padding:5px; border-left:1px solid #ccc; border-top:1px solid #ccc;">'.elgg_echo('c_bin:department_name').'</th> <th style="padding:5px; border-left:1px solid #ccc; border-top:1px solid #ccc;">'.elgg_echo('c_bin:department_abbreviations').'</th> <th style="padding:5px; border-left:1px solid #ccc; border-top:1px solid #ccc;">'.elgg_echo('c_bin:total_user').'</th></tr>';
	foreach ($some_array as $theKey => $theElement)
	{
		$dpt_nfo = explode('|', $theKey);
		$dpt_nfoENFR = explode('/',$dpt_nfo[0]);
		$dpt_abbENFR = explode('/',$dpt_nfo[1]);
		$display .= '<tr><td style="padding:5px; border-left:1px solid #ccc; border-top:1px solid #ccc;"><a href="'.elgg_get_site_url().'members/gc_dept?dept='.$dpt_nfo[1].'">'.$dpt_nfo[0].'</a></td>';
		$display .= '<td style="padding:5px; border-left:1px solid #ccc; border-top:1px solid #ccc;"> <a href="'.elgg_get_site_url().'members/gc_dept?dept='.$dpt_nfo[1].'">'.$dpt_nfo[1].'</a></td>';
		$display .= '<td style="padding:5px; border-left:1px solid #ccc; border-top:1px solid #ccc;"> '.$theElement.' </td></tr>';
	}
	$display .= '</table>';
	
	return $display;
}


