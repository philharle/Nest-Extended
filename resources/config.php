<?php
// Your Nest username and password.
define('USERNAME', 'usernameinhere');
define('PASSWORD', 'passwordinhere');

// The timezone you're in.
// See http://php.net/manual/en/timezones.php for the possible values.
$us_timezones = DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, 'US');
date_default_timezone_set('Europe/London');

//Database settings
$hostname='localhost';
$username='usernameinhere';
$password='passwordinhere';
$dbname='matth41_nestdb';

//WeatherUnderground API - Sign up at http://www.wunderground.com/weather/api/?apiref=c133a2be0b541640
$wu_api_key = 'WeatherUnderground API Key';

//Automatically set humidity target based on outside temperature?
$set_humidity=true;
$maxhumidity=45;  //What is the maximum target humidity you would like Nest to reach?
