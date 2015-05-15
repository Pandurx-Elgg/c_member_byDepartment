<?php

global $CONFIG;
$dbprefix = elgg_get_config("dbprefix");

// cyu - modified 01-13-2015: issue with table naming convention resolved
$query = "SELECT e.guid, ue.email, ue.username FROM ".$dbprefix."entities e, ".$dbprefix."users_entity ue WHERE e.type = 'user' AND e.guid = ue.guid AND e.enabled = 'yes'";

$connection = mysqli_connect($CONFIG->dbhost, $CONFIG->dbuser, $CONFIG->dbpass, $CONFIG->dbname);
if (mysqli_connect_errno($connection)) error_log("cyu_tag_users.php: Failed to connect to MySQL: ".mysqli_connect_errno());
$result = mysqli_query($connection,$query);
mysqli_close($connection);

$tagging_members = array();
while ($row = mysqli_fetch_array($result)) {
	$department = $row['email'];
	$department = explode('@',$department);
	$department = explode('.',$department[1]);
	$tagging_members[$row['username']] = strtoupper($department[0]);
}

$today = date("YmdHis");
$data_directory = $CONFIG->dataroot.'gc_dept'.DIRECTORY_SEPARATOR;
if (is_array($tagging_members)) {
	$write_as_backup = file_put_contents($data_directory.'tagged_members_'.$today.'.json', json_encode($tagging_members));
}

forward(REFERER);