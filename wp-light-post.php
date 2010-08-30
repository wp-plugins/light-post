<?php
/*
Plugin Name: Light Post
Plugin URI: http://blog.bokhorst.biz/4146/computers-en-internet/wordpress-plugin-light-post/
Description: Resource light alternate post management screen for use on slow connections and/or slow computers
Version: 0.4
Author: Marcel Bokhorst
Author URI: http://blog.bokhorst.biz/about/
*/

/*
	Copyright 2010 by Marcel Bokhorst

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

#error_reporting(E_ALL);

// Check PHP version
if (version_compare(PHP_VERSION, '5', '<'))
	die('Light Post requires at least PHP 5, installed version is ' . PHP_VERSION);

// Include Light Post class
if (!class_exists('WPLightPost'))
	require_once('wp-light-post-class.php');

$lp_request_uri = $_SERVER['REQUEST_URI'];
$lp_query_string = $_SERVER['QUERY_STRING'];
if ($lp_query_string)
	$lp_request_uri = substr($lp_request_uri, 0, strpos($lp_request_uri, $lp_query_string) - 1);

if (basename($lp_request_uri) == basename(__FILE__) && isset($_REQUEST['abspath'])) {
	// Create WordPress environment
	require_once($_REQUEST['abspath'] . 'wp-load.php');
	require_once($_REQUEST['abspath'] . 'wp-admin/includes/admin.php');

	// Handle request
	WPLightPost::Handle_request();
}
else {
	// Check pre-requisites
	WPLightPost::Check_prerequisites();

	// Start plugin
	global $wp_light_post;
	$wp_light_post = new WPLightPost();
}

// That's it!

?>
