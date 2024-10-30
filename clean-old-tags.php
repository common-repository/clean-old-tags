<?php
/*
Plugin Name: Clean old tags
Plugin URI: http://www.herewithme.fr/wordpress-plugins/clean-old-tags
Description: Clean old tags and options of these plugins: Simple Tagging, Ultimate Tag Warrior, Bunny&#8217;s Technorati Tags, Jerome&#8217;s Keywords 1.9 and 2.0 beta
Version: 1.1.1
Author: Amaury Balmer
Author URI: http://www.herewithme.fr

© Copyright 2008 Amaury BALMER (balmer.amaury AT gmail DOT com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
*/

class CleanOldTags {
	var $counter_table = 0;
	var $counter_options = 0;
	var $counter_meta = 0;
	var $clean = false;
	
	// Plugins to be cleaned
	var $stp = false;
	var $utw = false;
	var $jk = false;
	var $btt = false;

	function CleanOldTags() {
		// Actions
		add_action('admin_menu', array (&$this, 'addMenu'));
		add_action('init', array (&$this, 'checkCleaner'));

		// Load localisation
		$dirurl_tmp = basename(dirname(__FILE__));
		$pluginpath = 'wp-content/plugins';
		if ( $dirurl_tmp != 'plugins' ) {
			$pluginpath .=  '/' . $dirurl_tmp . '/';
		}
		load_plugin_textdomain('cleanoldtags', $pluginpath);
		
		return true;
	}

	function addMenu() {
		add_management_page(__('Clean old tags', 'cleanoldtags'), __('Clean old tags', 'cleanoldtags'), 'manage_options', __FILE__, array(&$this, 'pageClean'));
		return true;
	}

	function pageClean() {
		?>
		<div class="wrap">
			<style type="text/css">
				.plugins, .plugins li { list-style: none; }
			</style>
			<h2><?php _e('Clean old tags', 'cleanoldtags'); ?></h2>
			<p><?php _e('<strong>Avertissement</strong>:<br />Use this cleaner after have import olds tags in new native tagging structure. (<strong>Manage - Import</strong>)', 'cleanoldtags'); ?></p>
			<p><strong><?php _e('Don&#8217;t be stupid - backup your database before proceeding!', 'cleanoldtags'); ?></strong></p>
			<p><?php _e('Check plugins to be cleaned:', 'cleanoldtags'); ?></p>
			<form method="post">
				<ul class="plugins">
					<li><label><input type="checkbox" name="stp" value="ok" /> <?php _e('Simple Tagging Plugin', 'cleanoldtags'); ?></label></li>
					<li><label><input type="checkbox" name="utw" value="ok" /> <?php _e('Ultimate Tag Warrior', 'cleanoldtags'); ?></label></li>
					<li><label><input type="checkbox" name="btt" value="ok" /> <?php _e('Bunny&#8217;s Technorati Tags', 'cleanoldtags'); ?></label></li>
					<li><label><input type="checkbox" name="jk" value="ok" /> <?php _e("Jerome's Keywords 1.9 and 2.0 Beta", 'cleanoldtags'); ?></label></li>
				</ul>
				<h3><?php _e('Ready ?', 'cleanoldtags'); ?></h3>			
			
				<p><input type="hidden" name="cleaner_tags_nonce" value="<?php echo wp_create_nonce('cleanoldtags_tags'); ?>" />
					<input name="cleaner_tags" type="submit" value="<?php _e('Start cleaner now !', 'cleanoldtags'); ?>" /></p>
			</form>
			<?php if ( $this->clean == true ) : ?>
				<h3><?php _e('Finish !', 'cleanoldtags'); ?></h3>
				<p><?php _e('Cleaner results:', 'cleanoldtags'); ?></p>
				<ul>
					<li><?php printf(__('<strong>%s</strong> deleted tag tables', 'cleanoldtags'), $this->counter_table); ?></li>
					<li><?php printf(__('<strong>%s</strong> deleted post meta', 'cleanoldtags'), $this->counter_meta); ?></li>
					<li><?php printf(__('<strong>%s</strong> deleted options', 'cleanoldtags'), $this->counter_options); ?></li>
				</ul>
			<?php endif; ?>
		</div>		
		<?php
		return true;
	}

	function checkCleaner() {
		// Check origin and intention
		if ( current_user_can('manage_options') && isset($_POST['cleaner_tags']) && wp_verify_nonce($_POST['cleaner_tags_nonce'], 'cleanoldtags_tags') ) {
			if ( $_POST['stp'] == 'ok' ) $this->stp = true;
			if ( $_POST['utw'] == 'ok' ) $this->utw = true;
			if ( $_POST['jk'] == 'ok' )  $this->jk = true;
			if ( $_POST['btt'] == 'ok' ) $this->btt = true;
			
			$this->runCleaner();
			$this->clean = true;
		}
		return true;
	}

	function runCleaner() {
		// Start cleaners
		$this->cleanTables();
		$this->cleanPostMeta();
		$this->cleanOptions();
		
		// Optimize DB
		$this->optimizeDB();
		return true;
	}

	function cleanTables() {
		global $wpdb;
		$tables[] = array();

		// STP tables
		if ( $this->stp === true ) {
			$options = get_option('stp_options');			
			if ( $options ) {
				$tables[] = $wpdb->prefix . $options['tags_table'];
			}
			
			// Perhaps options are deleted, drop STP default table
			$tables[] = $wpdb->prefix . 'stp_tags';	
		}
	
		// UTW tables
		if ( $this->utw === true ) {
			$tables[] = $wpdb->prefix . 'tags';
			$tables[] = $wpdb->prefix . 'post2tag';
			$tables[] = $wpdb->prefix . 'tag_synonyms';
		}
		
		// JK tables
		if ( $this->jk === true ) {
			$tables[] = $wpdb->prefix . 'jkeywords';
		}

		// Delete eventual doublons
		$tables = array_unique ($tables);
		foreach ( $wpdb->get_results("SHOW TABLES;", ARRAY_N) as $row ) {
			if ( in_array( $row[0], $tables) ) {
				$wpdb->query("DROP TABLE `{$row[0]}`");
				$this->counter_table++;
			}
		}
		return true;
	}

	function cleanPostMeta() {
		global $wpdb;
		// Delete JK post meta
		if ( $this->jk === true ) {
			$this->counter_meta += $wpdb->query("DELETE FROM $wpdb->postmeta WHERE meta_key = 'keywords'");
		}
		// Delete UTW post meta
		if ( $this->utw === true ) {
			$this->counter_meta += $wpdb->query("DELETE FROM $wpdb->postmeta WHERE LOWER(LEFT( meta_key, 5)) = '_utw_'");
		}
		// Delete BTT post meta
		if ( $this->btt === true ) {
			$this->counter_meta += $wpdb->query("DELETE FROM $wpdb->postmeta WHERE meta_key = 'tags'");
			$this->counter_meta += $wpdb->query("DELETE FROM $wpdb->postmeta WHERE meta_key = 'keywords'");		
		}
		return true;
	}

	function cleanOptions() {
		global $wpdb;
		if ( $this->stp === true ) {
			// Delete STP options
			if ( delete_option('stp_options') ) $this->counter_options++;
			// Delete eventual STP Mu options
			if ( delete_option('mu_stp_options') ) $this->counter_options++;
		}
		// Delete UTW options
		if ( $this->utw === true ) {
			$this->counter_options += $wpdb->query("DELETE FROM $wpdb->options WHERE LOWER(LEFT( option_name, 4)) = 'utw_'");
		}
		// Delete JK options
		if ( $this->jk === true ) {
			$this->counter_options += $wpdb->query("DELETE FROM $wpdb->options WHERE LOWER(LEFT( option_name, 10)) = 'jkeywords_'");
		}
		return true;
	}

	function optimizeDB() {
		global $wpdb;
		foreach ( $wpdb->get_results("SHOW TABLES;", ARRAY_N) as $row ) {
			$wpdb->query("OPTIMIZE TABLE `{$row[0]}`");
		}
		return true;
	}
}

if ( is_admin() ) {
	new CleanOldTags();
}