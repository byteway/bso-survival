<?php

/**
 * Fired during plugin activation
 *
 * @link       https://byteway.eu/contact
 * @since      1.0.0
 *
 * @package    Survival
 * @subpackage Survival/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Survival
 * @subpackage Survival/includes
 * @author     Berend Otten <berend.otten@gmail.com>
 */
class Survival_Activator {

	/**
	 * Activate BSO Survival
	 *
	 * Activate the BSO Survival plugin. 
	 * Creates survival tables and initial data.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {

        global $survival_db_version;
        $survival_db_version = '1.0';

        Survival_Activator::createTable_Team();
        Survival_Activator::initValue_Team();

        Survival_Activator::createTable_Survival();
        Survival_Activator::initValue_Survival();

        Survival_Activator::createTable_Referee();
        Survival_Activator::initValue_Referee();

        Survival_Activator::createTable_TimeSlot();
        Survival_Activator::initValue_TimeSlot();

        Survival_Activator::createTable_Score();
        Survival_Activator::initValue_Score();

        Survival_Activator::add_survival_role();

        add_option( 'survival_db_version', $survival_db_version );

        // tips: https://learn.wordpress.org/tutorial/custom-database-tables/
	}

    // public static function survival_update_db_check() {
    //     global $survival_db_version;
    //     if ( get_site_option( 'survival_db_version' ) != $survival_db_version ) {
    //         survival_install_update();
    //     }
    // }
    // add_action( 'plugins_loaded', 'survival_update_db_check' );


public static function add_survival_role() {
    //Create a Referee role
    add_role( "referee", "referee", array(
        'read' => true,
        'create_posts' => false,
        'edit_posts' => false,
        'edit_others_posts' => false,
        'publish_posts' => false,
        'manage_categories' => false,
        'read_private_pages' => true,
        'edit_private_pages' => true,
        ));
}

public static function createTable_Survival() {

    global $wpdb;
    $table_name = $wpdb->prefix . 'survival';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name tinytext NOT NULL,
        notes text,
        PRIMARY KEY (id)
    )$charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}

/**
 * Each survival represents an actual match.
 * A survival is associated with a name and instructions on how to play the match.
 * Additionally, each survival has a GPS location, pinpointing the exact place of the match.
 *
 */
public static function initValue_Survival() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'survival';
    
    $sql="INSERT INTO $table_name(id, name) VALUES 
        (1, 'Kanovaren'),
        (2, 'Touwbaan'),
        (3, 'Kasteelspel'),
        (4, 'Kano Bungee'),
        (5, 'Survivalbaan'),
        (6, 'Vrachtauto / tokkelbaan'),
        (7, 'Kano touwtrekken'),
        (8, 'Water scheppen'),
        (9, 'Water dragen'),
        (10,'Vlotten bouw'),
        (11,'Step-run'),
        (12,'Labyrint')";
    dbDelta( $sql );
}

    
public static function createTable_Referee() {
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'referee';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name tinytext NOT NULL,
        email varchar(55),
        survival_id mediumint(9) NOT NULL,
        notes text,
        PRIMARY KEY (id)
    )$charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}

/**
* Each referee is assigned a single survival.
* Initially, referees are randomly assigned to a survival.
* Referees have the flexibility to switch between survival teams and provide assistance at other survival locations.
*
 */
public static function initValue_Referee() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'referee';

    // Random assignment to survival
    $i=1;    
    for ($x = 1; $x <= 20; $x++) {

        if(fmod($x, 2)==0) { $i = $x; }
        if ($x > 13) { $i = $x-13;}
      
        $welcome_name = "Referee $x";

        $wpdb->insert( 
            $table_name, 
            array( 
                'name' => $welcome_name, 
                'survival_id'=> $i,
            ) 
        );
    }
}

public static function createTable_Team() {
   
    global $wpdb;
    $table_name = $wpdb->prefix . 'team';
	$charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		name tinytext NOT NULL,
		notes text,
		PRIMARY KEY  (id)
	) $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
}

/**
 * A team must have a name.
 * Each team must have a contact person.
 * There is a maximum of team members per team.
 * A team has an average age for all team members.
 * 
 */
public static function initValue_Team() {
	
    global $wpdb;
    $table_name = $wpdb->prefix . 'team';
	
    for ($x = 1; $x <= 20; $x++) {
        $welcome_name = "Team $x";
        $welcome_notes = "Welcome team $x";
        $wpdb->insert( 
            $table_name, 
            array( 
                'name' => $welcome_name, 
                'notes' => $welcome_notes, 
            ) 
        );
    }
}

public static function createTable_TimeSlot(){
    global $wpdb;
    $table_timeslot = $wpdb->prefix . 'timeslot';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_timeslot (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        start_time_hour tinyint(2) DEFAULT '00',
        start_time_minute tinyint(2) DEFAULT '00',
        starttime varchar(5) GENERATED ALWAYS AS 
            (CONCAT(LPAD(start_time_hour, 2, '0'), ':', LPAD(start_time_minute, 2, '0'))),
        PRIMARY KEY (id)
    )$charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );

}
public static function initValue_TimeSlot(){
    global $wpdb;
    $table_timeslot = $wpdb->prefix . 'timeslot';
    
    $sql = "INSERT INTO $table_timeslot(start_time_hour, start_time_minute) 
    VALUES
        ('09', '00'),
        ('09', '30'),
        ('10', '00'),
        ('10', '30'),
        ('11', '00'),
        ('11', '30'),
        ('13', '00'),
        ('13', '30'),
        ('14', '00'),
        ('14', '30'),
        ('15', '00'),
        ('15', '30');";
    dbDelta( $sql );
}


public static function createTable_Score() {

    global $wpdb;
    $table_score = $wpdb->prefix . 'score';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_score (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        team_id mediumint(9) NOT NULL,
        survival_id mediumint(9) NOT NULL,
        timeslot_id mediumint(9) NOT NULL,
        starttime TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        joker tinyint(1) DEFAULT '0',
        time_min_score tinyint(2) DEFAULT '00',
        time_sec_score tinyint(2) DEFAULT '00',
        points_score int(11) DEFAULT '0',
        error_score int(11) DEFAULT '0',
        position smallint(3) DEFAULT '0',
        total int(11) DEFAULT '0',
        notes text,
        time_score varchar(5) GENERATED ALWAYS AS 
            (CONCAT(LPAD(time_min_score, 2, '0'), ':', LPAD(time_sec_score, 2, '0'))),
        PRIMARY KEY (id)
    )$charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}

/**
 * Every team must participate in all survival matches.
 * Each survival match involves two competing teams.
 * Throughout the event, teams should strive to compete against as many different opponents as possible.
 * When a Joker is played, it doubles the scoring points for that specific survival for that specific team.
 * Only a referee assigned to a survival has the authority to modify the score for the competing teams.
 * 
*/
public static function initValue_Score() {
    global $wpdb;
    $table_score = $wpdb->prefix . 'score';
    $table_team = $wpdb->prefix . 'team';
    $table_survival = $wpdb->prefix . 'survival';
    $table_timeslot = $wpdb->prefix . 'timeslot';
    
    $index1=0;
    $index2=0;
    $team1Array=array(); 
    $team2Array=array(); 

    // Fetch all teams, but split them evenly in array's
    $teams = $wpdb->get_results("SELECT id FROM $table_team");
    foreach ($teams as $teamRow)
    {
        if ($index1 <= 11)
        {
            $team1Array[$index1] = $teamRow->id;
            $index1++;
        }
        else
        {
            $team2Array[$index2] = $teamRow->id;
            $index2++;
        }
    }

    // Fetch all survivals
    $index=0;
    $survivalArray = array();
    $survivals = $wpdb->get_results("SELECT id FROM $table_survival");
    foreach ($survivals as $survivalRow)
    {
        $survivalArray[$index] = $survivalRow->id;
        $index++;
    }
    
    // Generate all scores for each timeslot and survival
    $timeslots = $wpdb->get_results("SELECT id, starttime FROM $table_timeslot ORDER BY starttime");
    foreach ($timeslots as $timeslotRow)
    {
        // Convert time to valid database time format
        $starttime = (new DateTime($timeslotRow->starttime))->format('Y-m-d H:i:s');

        // Prepare for battle: team 1 against team 2
        $teamCounter = 0;
        foreach($survivalArray as $survivalid)
        {
            // Insert score for team 1
            $teamid1 = $team1Array[$teamCounter];
            $sql = "INSERT INTO $table_score(team_id, survival_id, timeslot_id, starttime) VALUES($teamid1, $survivalid, $timeslotRow->id, '$starttime');";
            dbDelta( $sql );

            // Insert score for team 2
            $teamid2 = $team2Array[$teamCounter];
            $sql = "INSERT INTO $table_score(team_id, survival_id, timeslot_id, starttime) VALUES($teamid2, $survivalid, $timeslotRow->id, '$starttime');";
            dbDelta( $sql );
            $teamCounter++;
        }

        // Rotate Teams
        $team1Array = Survival_Activator::array_rotate_right($team1Array);
        $team2Array = Survival_Activator::array_rotate_left($team2Array);
    }

    // Clean variables
    unset($team1Array, $team2Array, $survivalArray);
    //unset($teams, $survivals, $timeslots);
}

public static function array_rotate_left(&$array)
{
    // first becomes last
    $n = count($array);
    for($i=0;$i<$n-1;$i++)
    {
        $temp = $array[$i];
        $array[$i] = $array[$i+1];
        $array[$i+1] = $temp;
    }
    return $array;
}

public static function array_rotate_right(&$array)
{
    // last becomes first
    $n = count($array);
    for($i=$n-1;$i>0;$i--)
    {
        $temp = $array[$i];
        $array[$i] = $array[$i-1];
        $array[$i-1] = $temp;
    }
    return $array;
}

}