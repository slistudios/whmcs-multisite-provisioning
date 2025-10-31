<?php
/*
WHMCS Multisite Provisioning Addon Module
Plugin Name: WHMCS Multisite Remote Provisioning
Plugin URI: http://premium.wpmudev.org/project/whmcs-multisite-provisioning/
Description: This plugin allows remote control of Multisite provisioning from WHMCS. Includes provisioning for Subdomain, Subdirectory or Domain Mapping Wordpress Multisite installs.
Author: WPMU DEV
Author Uri: http://premium.wpmudev.org/
Text Domain: mrp
Domain Path: languages
Version: 2.0
Network: true
WDP ID: 264
Requires PHP: 8.1
WHMCS: 8.0+
*/

/*  Copyright 2012-2025  Incsub  (http://incsub.com)

Author - Arnold Bailey

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if (!defined("WHMCS"))
die("This file cannot be accessed directly");

function whmcs_multisite_config() {
	$configarray = array(
	"name" => "WHMCS Multisite Provisioning",
	"version" => "2.0",
	"author" => "wpmudev.org",
	"language" => "english",
	"description" => "WordPress Multisite Provisioning addon for WHMCS 8.x - PHP 8.1+ compatible",

	"fields" => array(

	/*
	"option1" => array ("FriendlyName" => "Multisite Administrator", "Type" => "text", "Size" => "25", "Description" => "Name of an administrator with rights to the Multisite Addon."),
	"option2" => array ("FriendlyName" => "Option2", "Type" => "password", "Size" => "25", "Description" => "Password"),
	"option3" => array ("FriendlyName" => "Option3", "Type" => "yesno", "Size" => "25", "Description" => "Sample Check Box"),
	"option4" => array ("FriendlyName" => "Option4", "Type" => "textarea", "Size" => "25", "Description" => "Textarea"),
	"option5" => array ("FriendlyName" => "Option5", "Type" => "dropdown", "Options" => "1,2,3,4,5", "Description" => "Sample Dropdown"),
	*/
	));
	return $configarray;
}

function whmcs_multisite_activate() {

	// Updated for PHP 8.1+ and WHMCS 8.x - use Capsule (Laravel's query builder)
	try {
		// Check if table exists using WHMCS database functions
		if (!function_exists('full_query')) {
			// Fallback to Capsule for WHMCS 8.x
			$pdo = \WHMCS\Database\Capsule::connection()->getPdo();

			// Create Custom DB Table if it doesn't exist
			$pdo->exec("CREATE TABLE IF NOT EXISTS `mod_whmcs_multisite` (
				`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
				`blog_id` INT NOT NULL,
				`service_id` INT NOT NULL,
				`domain` TEXT NOT NULL,
				`path` TEXT NOT NULL,
				`level` INT NULL DEFAULT 0,
				KEY `service_id` (`service_id`, `blog_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
		} else {
			// Use legacy WHMCS functions if available
			full_query("CREATE TABLE IF NOT EXISTS `mod_whmcs_multisite` (
				`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
				`blog_id` INT NOT NULL,
				`service_id` INT NOT NULL,
				`domain` TEXT NOT NULL,
				`path` TEXT NOT NULL,
				`level` INT NULL DEFAULT 0,
				KEY `service_id` (`service_id`, `blog_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
		}
	} catch (\Exception $e) {
		logModuleCall('whmcs_multisite', 'activate', 'Database Error', $e->getMessage(), '', []);
	}
}

function whmcs_multisite_deactivate() {

	# Remove Custom DB Table
	//$query = "DROP TABLE `mod_addonexample`";
	//$result = mysql_query($query);

}


function whmcs_multisite_upgrade($vars) {
	$version = $vars['version'];

	/*
	# Run SQL Updates for V1.0 to V1.1
	if ($version < 1.1) {
	$query = "ALTER `mod_addonexample` ADD `demo2` TEXT NOT NULL ";
	$result = mysql_query($query);
	}

	# Run SQL Updates for V1.1 to V1.2
	if ($version < 1.2) {
	$query = "ALTER `mod_addonexample` ADD `demo3` TEXT NOT NULL ";
	$result = mysql_query($query);
	}
	*/
}

function whmcs_multisite_output($vars) {
	$modulelink = $vars['modulelink'];
	$version = $vars['version'];
	$option1 = $vars['option1'] ?? '';
	$option2 = $vars['option2'] ?? '';
	$option3 = $vars['option3'] ?? '';
	$option4 = $vars['option4'] ?? '';
	$option5 = $vars['option5'] ?? '';
	$LANG = $vars['_lang'] ?? [];
	/*
	echo 'output';
	echo '<p>'.$LANG['intro'].'</p>
	<p>'.$LANG['description'].'</p>
	<p>'.$LANG['documentation'].'</p>';
	*/
	echo "<div class='infobox'>";
	echo "<h3>WHMCS Multisite Provisioning</h3>";
	echo "<p><strong>Version:</strong> " . htmlspecialchars($version) . "</p>";
	echo "<p><strong>Compatible with:</strong> WHMCS 8.x, WordPress 6.8.3+, PHP 8.1+</p>";
	echo "<p><strong>Features:</strong> Pro-Sites support, Subdomain/Subdirectory/Domain Mapping</p>";
	echo "</div>";

}

function whmcs_multisite_sidebar($vars) {
	/*
	$modulelink = $vars['modulelink'];
	$version = $vars['version'];
	$option1 = $vars['option1'];
	$option2 = $vars['option2'];
	$option3 = $vars['option3'];
	$option4 = $vars['option4'];
	$option5 = $vars['option5'];
	$LANG = $vars['_lang'];

	$sidebar = '<span class="header"><img src="images/icons/addonmodules.png" class="absmiddle" width="16" height="16" /> Example</span>
	<ul class="menu">
	<li><a href="#">Demo Sidebar Content</a></li>
	<li><a href="#">Version: '.$version.'</a></li>
	</ul>';
	return $sidebar;
	*/
	return '';
}

?>