<?php

// toggle between english and french departments
function department_language($master_array, $is_english) {
	$temp_array = array();

	foreach ($master_array as $key => $value)
	{
		$name = explode('/',$key);
		
		// english is selected
		if ($is_english) {
			$temp_array[$name[0]] = $value;

		// french is selected
		} else {
			// department vs users array (due to french/english toggle)
			if (is_array($value))
				$temp_array[$name[1]] = $value;
			else
				$temp_array[$name[0]] = $value;
		}
	}
	unset($master_array);	// de-allocate memory
	$master_array = $temp_array;
	//print_r($master_array);
	return $master_array;
}


// creates the necessary files (json)
function create_files($data_directory) {
	$display = '';
	$dbprefix = elgg_get_config("dbprefix");

	// this is an important file, updates require this file
	if (!file_exists($data_directory.'department-listing.csv'))
	{
		if (elgg_is_admin_logged_in())
			$display .= "department-listing.csv cannot be found in the data folder";
		return $display;
	} // end if department-listing.csv
	//error_log('cyu - checked the csv file');


	// CREATE DEPARTMENT_LISTING.JSON IF FILE DOES NOT EXIST
	if (!file_exists($data_directory.'department_listing.json'))
	{
		$csvData = explode(PHP_EOL,file_get_contents($data_directory.'department-listing.csv'));	// get the contents of the csv file
		$department_listing = array();	// allocate memory for department_listing

		foreach ($csvData as $csvRow) {
			$dept_info = explode(';',$csvRow);
			$department_listing[] = $dept_info;
			//error_log('[error_log c_members_byDepartment] dept_info:'.$dept_info[0].' /// '.$dept_info[1]); // domain | abbreviation
		}
	
		$department_name = array();
		foreach ($department_listing as $department) {
			$department_name[$department[0]] = $department[2].'|'.$department[1];
			//error_log('[error_log c_members_byDepartment] department_name:'.$department[2].'|'.$department[1]);	// department_name | department abbrev
		}

		foreach ($department_listing as $dept_abbrev) {
			if (!is_array($department_name[$dept_abbrev[2]])) {
				$department_name[$dept_abbrev[2]] = array('abbreviation' => $dept_abbrev[1]);	// cyu - they're actually domains..
				//error_log('[error_log c_members_byDepartment] abbrev:'.$dept_abbrev[1]);
			}
			$temp_array = array_merge($department_name[$dept_abbrev[2]],array($dept_abbrev[0] => $dept_abbrev[2].'|'.$dept_abbrev[1]));
			//error_log('[error_log c_members_byDepartment] department abbrev:'.$dept_abbrev[2].'|'.$dept_abbrev[1]);
			//error_log('[error_log c_members_byDepartment] temp_array:'.print_r($temp_array,true));
			$department_name[$dept_abbrev[2]] = $temp_array;
		}
		$result = file_put_contents($data_directory.'department_listing.json', json_encode($department_name));
		//if (!$result) return FALSE;
		// ALL DEPARTMENT INFORMATION SAVED IN DEPARTMENT_LISTING.JSON
	} // end if department_listing.json (modified)
	//error_log('cyu - generated department listing json file');
	unset($department_listing);	// deallocate memory
	unset($department_name);


	// CREATE DEPARTMENT_DIRECTORY.JSON IF FILE DOES NOT EXIST
	if (!file_exists($data_directory.'department_directory.json'))
	{
		$query = "SELECT ue.email, ue.name, e.time_created, e.guid FROM ".$dbprefix."entities e, ".$dbprefix."users_entity ue WHERE e.type = 'user' AND e.guid = ue.guid AND e.enabled = 'yes'";
		$users = get_data($query);
		//error_log('cyu - queried agains the database and returned with results..');

		// binning users to their respective departments
		$binned_users = array();
		foreach ($users as $user) {
			$domain = explode('@',strtolower($user->email));
			$binned_users[$user->guid] = $domain[1];
		}
		//error_log('cyu - binned all users to respective departments');

		//error_log('[error_log c_members_byDepartment] binned users:'.print_r($binned_users,true));
		$main_file = array();
		$main_file['members'] = max(array_keys($binned_users));
		$usercount = array();
		$usercount = array_count_values($binned_users);
		$main_file = array_merge($main_file,$usercount);
		//error_log('cyu - tallying of users completed and big arrays have been merged');

		unset($usercount);	// deallocate memory

		// user look up makes the whole thing time out, try another way...
		foreach ($binned_users as $key => $value) {
			if (!is_array($main_file[$value]))	// key: user guid | value: user email domain
			{
				//error_log('[error_log c_members_byDepartment] value:'.$value.' /// key:'.$key);
				$usercount = $main_file[$value];
				$main_file[$value] = array('members' => $main_file[$value]);
			}
			//$user = get_user($key);
			$query = "SELECT ue.email, ue.username, e.time_created, ue.name FROM ".$dbprefix."entities e, ".$dbprefix."users_entity ue WHERE ue.guid = e.guid AND ue.guid = ".$key;
			$user = get_data($query);
			//error_log(print_r($user,true));
			//error_log('cyu - entry:'.$user[0]->email);
			$temp_array = array_merge($main_file[$value], array($key => strtolower($user[0]->email).'|'.strtolower($user[0]->username).'|'.$key.'|'.$user[0]->time_created.'|'.$user[0]->name));//'placeholder'));//array($key => strtolower($user->email).'|'.strtolower($user->username).'|'.$key.'|'.$user->time_created.'|'.$user->name.'|'.$user->getIconURL()));
			$main_file[$value] = $temp_array;
		}
		//error_log('cyu - generated department directory json file');
		file_put_contents($data_directory.'department_directory.json', json_encode($main_file));

		//error_log('cyu - file put contents:'.$put_contents_result);
		//if (!$put_contents_result) exit('something went wrong while trying to write to file..');
		
	}
	unset($main_file);	// deallocate memory
	// DEPARTMENT DIRECTORY THAT CONTAINS ALL USERS ARE SAVED IN DEPARTMENT_DIRECTORY.JSON
	return TRUE;
}