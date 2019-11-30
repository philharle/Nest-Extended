<?php
// The Nest-Extended Configuration
require_once(__DIR__ . '/../config.php');

// The Nest API Class file
require_once(__DIR__ . '/../libs/nest/nest.class.php');

if (defined('STDIN')) {
  $datatype = $argv[1];
} else { 
  $datatype = $_GET['datatype'];
}


//Connect to the Database
$con = new mysqli($hostname, $username, $password, $dbname);
if ($con->connect_error) {
	trigger_error('Database connection failed: ' . $con->connect_error, E_USER_ERROR);
}

//Create a new Nest Object
$nest = new Nest();

//Used to return current location, away status, and outside weather
$locations = $nest->getUserLocations();

//Postal Code formatting
if (in_array(date_default_timezone_get(), $us_timezones)) {
	$postal_code = $locations[0]->postal_code; 
} else {
	$postal_code = substr($locations[0]->postal_code, 0, -3) . " " . substr($locations[0]->postal_code, -3);
}

//Get current Nest data (5 minute cron)
if ($datatype === 'current'){
	//Used to return current inside temperature, current inside humidity, current mode, target temperature, time to target temperature, current heat state, current ac state
	$infos = $nest->getDeviceInfo();

	//DEBUG
	//echo json_encode($infos);

	//If the target temperature is an array, we need to deal with that.
	if (strpos($infos->current_state->mode,'heat') !== false) {
		if (is_array($infos->target->temperature)) {
			$low_target_temp = $infos->target->temperature[0];
			$high_target_temp = null;
		} else {
			$low_target_temp = $infos->target->temperature;
			$high_target_temp = null;
		}
	} elseif(strpos($infos->current_state->mode,'cool') !== false) {
		if (is_array($infos->target->temperature)) {
			$low_target_temp = null;
			$high_target_temp = $infos->target->temperature[1];
		} else {
			$low_target_temp = null;
			$high_target_temp = $infos->target->temperature;
		}
	} elseif(strpos($infos->current_state->mode,'range') !== false) {
		$low_target_temp = $infos->target->temperature[0];
		$high_target_temp = $infos->target->temperature[1];
	}
	
	//Insert Current Values into Nest Database Table
	$sql = 'INSERT INTO nest (log_datetime, location, outside_temp, outside_humidity, away_status, leaf_status, current_temp, current_humidity, temp_mode, low_target_temp, high_target_temp, time_to_target, target_humidity, heat_on, humidifier_on, ac_on, fan_on, battery_level, is_online, hotwater_on) VALUES (NOW(), "'.$postal_code.'", "'.$locations[0]->outside_temperature.'", "'.$locations[0]->outside_humidity.'", "'.$locations[0]->away.'", "'.$infos->current_state->leaf.'", "'.$infos->current_state->temperature.'", "'.$infos->current_state->humidity.'", "'.$infos->current_state->mode.'", "'.$low_target_temp.'", "'.$high_target_temp.'", "'.$infos->target->time_to_target.'","0","'.$infos->current_state->heat.'","0","'.$infos->current_state->ac.'","'.$infos->current_state->fan.'","'.$infos->current_state->battery_level.'","'.$infos->network->online.'", "'.$infos->current_state->hot_water.'")';
	$result = $con->query($sql) or trigger_error('SQL: ' . $sql . ' Error: ' . $con->error, E_USER_ERROR);

	//Set the humidity level if enabled.
	if ($set_humidity) {
		$exttemp = $nest->temperatureInCelsius($locations[0]->outside_temperature);
		// Drop target humidity 5% for every 5degree C drop below 0
		$target = max(0, $maxhumidity + $exttemp);
		$target = round( min($target, $maxhumidity, 60) );
		if (abs($target - $infos->target->humidity) >= 1) {
			// Target humidity has changed
			$nest->setHumidity(intval($target));
		}
	}
} 
//Get data from Nest energy reports (daily)
elseif ($datatype === 'daily') {
	//Used to get Nest energy reports
	$energy = $nest->getEnergyLatest();

	//Used to get yesterday's weather
	$weather_json = file_get_contents('http://api.wunderground.com/api/'.$wu_api_key.'/yesterday/q/'.rawurlencode($postal_code).'.json');
	$weather=json_decode($weather_json);
	$yesterday_date = date("Y-m-d", time() - 60 * 60 * 24);

	//Loop through the array of days and get the data
	$days = $energy->objects[0]->value->days; 
	foreach ($days as $day) {

		//We can only get degree days for yesterday. If this isn't yesterday, we'll have to skip it.
		if($yesterday_date === $day->day){
			$heating_degree_days = $weather->history->dailysummary[0]->heatingdegreedays;
			$cooling_degree_days = $weather->history->dailysummary[0]->coolingdegreedays;
		} else {
			$heating_degree_days = null;
			$cooling_degree_days = null;
		}
		
		//Check to make sure we didn't already record this day.
		$result = $con->query("SELECT date FROM energy_reports WHERE date = '".$day->day."'");
		if($result->num_rows == 0) {
			//Insert Current Values into Nest Database Table
			$sql = 'INSERT INTO energy_reports (date, total_heating_time, heating_degree_days, total_cooling_time, cooling_degree_days, total_fan_time, total_humidifier_time, total_dehumidifier_time, leafs, recent_avg_used, usage_over_avg) VALUES ("'.$day->day.'", "'.$day->total_heating_time.'", "'.$heating_degree_days.'", "'.$day->total_cooling_time.'", "'.$cooling_degree_days.'", "'.$day->total_fan_cooling_time.'", "'.$day->total_humidifier_time.'", "'.$day->total_dehumidifier_time.'", "'.$day->leafs.'", "'.$day->recent_avg_used.'", "'.$day->usage_over_avg.'")';
            $result = $con->query($sql) or trigger_error('SQL: ' . $sql . ' Error: ' . $con->error, E_USER_ERROR);
		}
	}
}

//Close mySQL DB connection
$con->close();

