<?php

/**
 * Fired during plugin deactivation
 *
 * @link       https://byteway.eu/contact
 * @since      1.0.0
 *
 * @package    Survival
 * @subpackage Survival/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    Survival
 * @subpackage Survival/includes
 * @author     Berend Otten <berend.otten@gmail.com>
 */
class Survival_Deactivator {

	/**
	 * Deactivate BSO Survival
	 *
	 * Deactivate the BSO Survival plugin. 
	 * Drops survival tables and survival configs.
     * 
	 * @since    1.0.0
	 */
	public static function deactivate() {
        Survival_Deactivator::dropTable_Score();
        Survival_Deactivator::dropTable_Referee();
        Survival_Deactivator::dropTable_Team();
        Survival_Deactivator::dropTable_Survival();
        Survival_Deactivator::dropTable_TimeSlot();

        Survival_Deactivator::remove_survival_role();
	}

    public static function remove_survival_role() {
        remove_role( "referee");
    }    

    public static function dropTable_Survival() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'survival';
        $sql = "DROP TABLE IF EXISTS $table_name";
        $wpdb->query( $sql );
    }

    public static function dropTable_Referee() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'referee';
        $sql = "DROP TABLE IF EXISTS $table_name";
        $wpdb->query( $sql );
    }

    public static function dropTable_Team() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'team';
        $sql = "DROP TABLE IF EXISTS $table_name";
        $wpdb->query( $sql );
    }

    public static function dropTable_Score() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'score';
        $sql = "DROP TABLE IF EXISTS $table_name";
        $wpdb->query( $sql );
    }

    public static function dropTable_TimeSlot() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'timeslot';
        $sql = "DROP TABLE IF EXISTS $table_name";
        $wpdb->query( $sql );
    }
    
}