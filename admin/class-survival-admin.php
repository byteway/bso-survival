<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://https://byteway.eu/contact
 * @since      1.0.0
 *
 * @package    Survival
 * @subpackage Survival/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Survival
 * @subpackage Survival/admin
 * @author     Berend Otten <berend.otten@gmail.com>
 */
class Survival_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;
    /**
	 * The options name to be used in this plugin
	 *
	 * @since  	1.0.0
	 * @access 	private
	 * @var  	string 		$option_name 	Option name of this plugin
	 */
	private $option_name = 'survival';

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
        
        // ['Shortcodes'] for BSO Survival plugin
        // List all Survival details
        add_shortcode( 'survival_list', array( $this, 'survivalListAll' ) );
        // List all Team details
        add_shortcode( 'team_list', array( $this, 'teamListAll' ) );
        // List all Referee details
        add_shortcode( 'referee_list', array( $this, 'refereeListAll' ) );
        // List all Survivals of Referee
        add_shortcode( 'referee_survival_list', array( $this, 'refereeSurvivalListAll' ) );
        // List all Scores per Team
        add_shortcode( 'team_score_survival_list', array( $this, 'teamScoreAllSurvival' ) );
        // List all Team per Survival
        add_shortcode( 'survival_team_score', array( $this, 'survivalTeamScore' ) );
        // Edit Score Form
        add_shortcode( 'team_score_update', array( $this, 'teamScoreUpdate' ) );
        // Show score result
        add_shortcode( 'display_score', array( $this, 'displayScore' ) );

        // updateSurvivalPosition
        add_shortcode( 'survival_update_position', array( $this, 'updateSurvivalPosition' ) );
        
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Survival_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Survival_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/survival-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Survival_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Survival_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/survival-admin.js', array( 'jquery' ), $this->version, false );

	}

    /**
	 * Add an options page under the Settings submenu
	 *
	 * @since  1.0.0
	 */
	public function add_options_page() {
    
        // Under Settings menu
		$this->plugin_screen_hook_suffix = add_options_page(
			__( 'BSO Survival Settings', 'survival-pages' ),
			__( 'Survival Settings', 'survival-pages' ),
			'manage_options',
			$this->plugin_name,
			array( $this, 'display_options_page' )
		);
	}

    /**
	 * Add a page under the Tools submenu
	 *
	 * @since  1.0.0
	 */
	public function add_management_page() {

        // Under Tools menu
        $this->plugin_screen_hook_suffix = add_management_page(
            __('BSO Survival Tools', 'survival-pages'),
            __('Survival Tools', 'survival-pages'),
            'manage_options',
            $this->plugin_name,
            array( $this, 'display_tools_page' )
        );
    }
	    
        
    /**
	 * Render the options page for plugin
	 *
	 * @since  1.0.0
	 */
	public function display_options_page() {
		include_once 'partials/survival-admin-display.php';
	}

    /**
	 * Render the tools page for plugin
	 *
	 * @since  1.0.0
	 */
    public function display_tools_page() {
		include_once 'partials/survival-tools-display.php';
	}

    /**
     * Pages to connect to for generating URL's
     * for usage of link generation in shortcode
     * 'team_page'      - start time at survival
     * 'survival_page'  - all team positions per survival
     * 'score_page'     - update score for team at survival
     * 
     */
    public function register_setting() {
        
        // Add a General section
        add_settings_section(
            $this->option_name . '_general',                                // string $id
            __( 'General', 'survival-pages' ),                              // string $title
            array( $this, $this->option_name . '_general_cb' ),             // callable $callback, string $page
            $this->plugin_name                                              // array $args = array()
        );
        
        // Team score page            
        add_settings_field(
            $this->option_name . '_team_score_page',
            __( 'Filter on team and show all scores page URL', 'survival-pages' ),
            array( $this, $this->option_name . '_team_score_page_cb' ),
            $this->plugin_name,
            $this->option_name . '_general',
            array( 'label_for' => $this->option_name . '_team_score_page' )
        );
    
        register_setting( 
            $this->plugin_name,
            $this->option_name . '_team_score_page',
            'string' );

        // Survival page            
        add_settings_field(
            $this->option_name . '_survival_page',
            __( 'Filter on survival and show team position page  URL', 'survival-pages' ),
            array( $this, $this->option_name . '_survival_page_cb' ),
            $this->plugin_name,
            $this->option_name . '_general',
            array( 'label_for' => $this->option_name . '_survival_page' )
        );
    
        register_setting( 
            $this->plugin_name,
            $this->option_name . '_survival_page',
            'string' );

        // Score page            
        add_settings_field(
            $this->option_name . '_score_page',
            __( 'Update score page  URL', 'survival-pages' ),
            array( $this, $this->option_name . '_score_page_cb' ),
            $this->plugin_name,
            $this->option_name . '_general',
            array( 'label_for' => $this->option_name . '_score_page' )
        );
    
        register_setting( 
            $this->plugin_name,
            $this->option_name . '_score_page',
            'string' );
                
    }
     /**
	 * Render the text for the general section
	 *
	 * @since  1.0.0
	 */
	public function survival_general_cb() {
		echo '<p>' . __( 'Please change the settings accordingly.', 'bso-survival' ) . '</p>';
	}
    
	public function survival_team_score_page_cb() {
		$teamscorepage = get_option( $this->option_name . '_team_score_page' );
		echo '<p><input type="text" name="' . $this->option_name . '_team_score_page' . '" id="' . $this->option_name . '_team_score_page' . '" value="' . $teamscorepage . '" size="75"></p>'. __( '[survival_team_score]', 'survival-pages' );
	}

	public function survival_survival_page_cb() {
		$survivalpage = get_option( $this->option_name . '_survival_page' );
		echo '<p><input type="text" name="' . $this->option_name . '_survival_page' . '" id="' . $this->option_name . '_survival_page' . '" value="' . $survivalpage . '" size="75"></p>'. __( '[team_score_survival_list]', 'survival-pages' );
	}

	public function survival_score_page_cb() {
		$scorepage = get_option( $this->option_name . '_score_page' );
		echo '<p><input type="text" name="' . $this->option_name . '_score_page' . '" id="' . $this->option_name . '_score_page' . '" value="' . $scorepage . '" size="75"></p>'. __( '[team_score_update]', 'survival-pages' );
	}

    public static function linkToolsPage(){
        // Link button to Survival Tools page
        // https://byteway.eu/wp-admin/tools.php?page=survival
        $toolsURL = get_admin_url(null, 'tools.php?page=survival');
        $tools = "<a class='button' href='$toolsURL'>Tools</a><br>";
        return $tools;
    }

 /** 
     * Calculate Team Score
     * There are twelve time slots.
     * Per survival all Teams take a position.
     * 
     * Each position earns points: 
     *   The first position earns count(Team) points
     *   The second position gets count(Team)-1 points
     *   The third position gets count(Team)-2 points, and so on until the last position
     * 
     * A team doubles there points when joker is given.
     */ 
    public function calculateScore() {
        global $wpdb;

		status_header(200); // redirect to main page

        $table_survival = $wpdb->prefix . 'survival';
        $table_team = $wpdb->prefix . 'team';
        $table_score = $wpdb->prefix . 'score';
        $table_timeslot = $wpdb->prefix . 'timeslot';

		echo "<h1>".esc_html__('Calculate Interim Team Score', 'bso-survival')."</h1><br>";
        echo Survival_Admin::linkToolsPage();

        // Determine points to give. Number one gets (Team count) points. Two one less, etc.
        // Debug: $pointArray[0]=0, $pointArray[1]=23, $pointArray[10]=14, $pointArray[20]=4
        $teamCount = $wpdb->get_results("SELECT count(id) as num_rows FROM $table_team;");
        $pointArray = array();
        $pointArray[0] = 0; //no position is no points
        for ( $i = 1; $i <= $teamCount[0]->num_rows; $i++) {
            $pointArray[$i] = $teamCount[0]->num_rows-$i;
        }

        // Clear all score table total field
        $wpdb->get_results("UPDATE $table_score SET total = 0");

        // Loop through all survivals
        $survivals = $wpdb->get_results("SELECT id, name FROM $table_survival");
        foreach( $survivals as $survivalRecord ) {

            // loop through all scores
            $scores = $wpdb->get_results("SELECT s.*
                                        FROM $table_score s
                                        WHERE s.survival_id= $survivalRecord->id
                                        AND s.position != 0
                                        ORDER BY s.position ASC");

            foreach( $scores as $scoreRecord ) {
                // If joker then double the points!
                $joker = (int)isset($scoreRecord->joker)? true: false;
                $position = (int)isset($scoreRecord->position)?$scoreRecord->position: 0;
                $pp = (int)isset($pointArray[$position])? $pointArray[$position]: 0;
                $points = $joker? $pp*2: $pp;

                // Save new points to total field in score record
                $wpdb->update(
                    $table_score,
                    array(
                        'total' => $points
                    ),
                    array( 'id' => $scoreRecord->id )
                );
            }
        }
        echo "<br><b>".esc_html__('The total score has been calculated.','bso-survival')."</b><br>"; 
        unset($pointArray);        

		exit("<br><em>".esc_html__('Done calculating score.','bso-survival')."</em>");
    }

    /**
     * Display current Team positions
     * List all Teams
     * - sum of all total points
     * - order by total DESC
     * - position is row number
     */
    public function displayScore() {
        global $wpdb;
        $table_team = $wpdb->prefix . 'team';
        $table_score = $wpdb->prefix . 'score';

        ob_start();
        echo "<br>";
        echo '<form name="display_score" id="display_score">';
		echo "<h2>".esc_html__('Survival score','bso-survival')."</h2><br>";
        //echo Survival_Admin::linkToolsPage();

        // Show result of calulation: list position of Teams
        // Sum all total scores per team
        $data = $wpdb->get_results(
            "SELECT t.id TeamId, t.name TeamName, sum(s.total) as total
            FROM $table_team t, $table_score s
            WHERE t.id = s.team_id 
            GROUP BY s.team_id
            ORDER BY s.total DESC"); 
                   
            if( ! empty( $data ) ) {
                $position = 1;
                echo '<table class="survivaltable">';
                echo '<tr>';
                echo "<th>".__( 'Position', 'bso-survival'). "</th>";
                echo "<th>".__( 'Team','bso-survival')."</th>";
                echo "<th>".__( 'Score', 'bso-survival')."</th>";
                echo '</tr>';
                foreach( $data as $record ) {
                    echo '<tr>';
                    echo '<td>'.$position.'</td>';
                    echo '<td>'.$record->TeamName.'</td>';
                    echo '<td>'.$record->total.'</td>';
                    echo '</tr>';
                    $position++;
                }
                echo '</table>';
            }
        echo '</form>';
        return ob_get_clean();
    }

    public function survivalListAll( $atts, $content = "" ) {
        global $wpdb;
        $table_survival = $wpdb->prefix . 'survival';
    
        ob_start();
		echo '<form name="survival_list" id="survival_list">';
        $data = $wpdb->get_results("SELECT id, name FROM $table_survival");
		if( ! empty( $data ) ) {
			echo '<table>';
			echo '<tr>';
			echo "<th>".__( 'Identification','bso-survival')."</th>";
			echo "<th>".__( 'Name','bso-survival')."</th>";
			echo '</tr>';
			foreach( $data as $survival ) {
				echo '<tr>';
				echo '<td>'.$survival->id.'</td>';
				echo '<td>'.$survival->name.'</td>';
				echo '</tr>';
			}
			echo '</table>';
		}
		echo '</form>';
		return ob_get_clean();
    }

    public function teamListAll( $atts, $content = "" ) {
        global $wpdb;
        $table_team = $wpdb->prefix . 'team';

        // Generate the team score URL 
        $teamscorepage = get_option( $this->option_name . '_team_score_page' );
        if ($teamscorepage == '')
        {
            Survival_Admin::showMessage(
                __( "URL not found! Make sure to provide  the URL for 'team_score_page' in the Survival Setting.",'bso-survival'), 
                    "error");
            return;
        }

        ob_start();
		echo '<form name="team_list" id="team_list">';
        $data = $wpdb->get_results("SELECT id, name FROM $table_team");
		if( ! empty( $data ) ) {
			echo '<table>';
			echo '<tr>';
			echo "<th>".__( 'Name','bso-survival')."</th>";
			echo '</tr>';
			foreach( $data as $team ) {
				echo '<tr>';
                $teamscoreURL = add_query_arg( array( 'TeamId' => $team->id, ), $teamscorepage );
				echo '<td>'."<a href='$teamscoreURL'>$team->name</a>".'</td>';
				echo '</tr>';
			}
			echo '</table>';
		}
		echo '</form>';
		return ob_get_clean();
    }

    public function refereeListAll( $atts, $content = "" ) {
        global $wpdb;
        $table_referee = $wpdb->prefix . 'referee';
    
        ob_start();
		echo '<form name="referee_list" id="referee_list">';
        $data = $wpdb->get_results("SELECT id, name FROM $table_referee");
		if( ! empty( $data ) ) {
			echo '<table class="survivaltable">';
			echo '<tr>';
			echo "<th>".__( 'Identification','bso-survival')."</th>";
			echo "<th>".__( 'Name','bso-survival')."</th>";
			echo '</tr>';
			foreach( $data as $referee ) {
				echo '<tr>';
				echo '<td>'.$referee->id.'</td>';
				echo '<td>'.$referee->name.'</td>';
				echo '</tr>';
			}
			echo '</table>';
		}
		echo '</form>';
		return ob_get_clean();
    }

    public function refereeSurvivalListAll( $atts, $content = "" ) {
        global $wpdb;
        $table_referee = $wpdb->prefix . 'referee';
        $table_survival = $wpdb->prefix . 'survival';

        $survivalTeamsPage = get_option( $this->option_name . '_survival_page' ); // _survival_team_score
        if ($survivalTeamsPage == '')
        {
            Survival_Admin::showMessage(
                __( "URL not found! Make sure to provide the 'team_score_survival_list' in the Survival Setting.",'bso-survival')
                , "error");
            return;
        }
        ob_start();
		echo '<form name="referee_survival_list" id="referee_survival_list">';
        $data = $wpdb->get_results("SELECT r.id RefereeId, r.name RefereeName, s.id SurvivalId, s.name SurvivalName FROM $table_referee r, $table_survival s where r.survival_id = s.id");
		if( ! empty( $data ) ) {
			echo '<table class="survivaltable">';
			echo '<tr>';
			echo "<th>".__( 'Referee','bso-survival')."</th>";
			echo "<th>".__( 'Survival','bso-survival')."</th>";
			echo '</tr>';
			foreach( $data as $record ) {
				echo '<tr>';
				echo '<td>'.$record->RefereeName.'</td>';
                $survivalTeamsURL = add_query_arg( array( 'SurvivalId' => $record->SurvivalId, ), $survivalTeamsPage );
                echo '<td>'."<a href='$survivalTeamsURL'>$record->SurvivalName</a>".'</td>';
				echo '</tr>';
			}
			echo '</table>';
		}
		echo '</form>';
		return ob_get_clean();
    }

    public function teamScoreAllSurvival( $atts, $content = "" ) {
        global $wpdb;
        $table_team = $wpdb->prefix . 'team';
        $table_score = $wpdb->prefix . 'score';
        $table_survival = $wpdb->prefix . 'survival';
        $table_timeslot = $wpdb->prefix . 'timeslot';

        // Link for update score page
        if ( is_user_logged_in() ){
            $teamScorePage = get_option( $this->option_name . '_score_page' ); 
            if ($teamScorePage == '')
            {
                Survival_Admin::showMessage(
                    __( "URL not found! Make sure to provide the 'score_update' in the Survival Setting.", 'bso-survival'),
                    "error");
                return;
            } 
        } else {
            $teamScorePage = "";
        }

        // Link for survival page
        $survivalTeamsPage = get_option( $this->option_name . '_survival_page' );
        if ($survivalTeamsPage == '')
        {
            Survival_Admin::showMessage(
                __( "URL not found! Make sure to provide the 'survival_page' in the Survival Setting.", 'bso-survival')
                , "error");
        }

        ob_start();
		echo '<form name="team_score_survival_list" id="team_score_survival_list">';
        echo '<h1>Team score</h1>';

        // Select a team id
        echo "<emp>".__( 'Select a team:','bso-survival')."</emp>";
		if ( isset($_REQUEST['TeamId']) && $_REQUEST['TeamId'] )
		{
			$teamid=$_REQUEST['TeamId'];
		} else { $teamid=1; } // when empty select the first team
        
        // Team choice
        $data = $wpdb->get_results("SELECT id, name FROM $table_team");
        echo '<select name="TeamId" id="TeamId">';
        foreach( $data as $record ) {
            if ($teamid==$record->id) { 
                echo "<option value='$record->id' selected='selected'>$record->name</option>";
            } 
            else { 
                echo "<option value='$record->id'>$record->name</option>";
            }
        }
        echo '</select> <input type="submit"><br>';
        
        // Team scores
        // TODO: change starttime from score to timeslot
        $data = $wpdb->get_results("
            SELECT a.id TeamId, a.name TeamName, b.id ScoreId, b.time_min_score, b.time_sec_score, b.points_score, b.error_score, b.position, b.timeslot_id, c.id SurvivalId, c.name SurvivalName, t.starttime 
            FROM $table_team a, $table_score b, $table_survival c , $table_timeslot t
            WHERE a.id = b.team_id AND c.id = b.survival_id AND b.timeslot_id=t.id AND a.id=$teamid
            ORDER BY  t.starttime;"
        );
		if( ! empty( $data ) ) {
			echo '<table class="survivaltable">';
			echo '<tr>';
            echo "<th>".__( 'Start time','bso-survival')."</th>";

            if ($teamScorePage > ""){
                echo '<th>Score</th>';
            }
            echo "<th>".__( 'Survival Name','bso-survival')."</th>";
            echo "<th>".__( 'Position','bso-survival')."</th>";
            echo "<th>".__( 'Joker','bso-survival')."</th>";
			echo '</tr>';
			foreach( $data as $record ) {
				echo '<tr>';
                $stt = (new DateTime($record->starttime))->format('H:i');
                echo '<td>'.$stt.'</td>';
                // Update score page
                if ($teamScorePage > ""){
                    $teamScoreURL = add_query_arg( array( 'ScoreId' => $record->ScoreId, ), $teamScorePage );
                    echo "<td><a href='$teamScoreURL'>"._e( 'Update','bso-survival')."</a></td>";
                } 
                // Survival page
                if ($survivalTeamsPage > '')
                {
                  $survivalTeamsURL = add_query_arg( array( 'SurvivalId' => $record->SurvivalId, ), $survivalTeamsPage );
                  echo '<td>'."<a href='$survivalTeamsURL'>$record->SurvivalName</a>".'</td>';
                } else {                
                    echo '<td>'.$record->SurvivalName.'</td>';
                }
                echo '<td>'.$record->position.'</td>';
                $jk = (int)isset($score->joker)? __( 'Yes', 'bso-survival'): __( 'No','bso-survival');
                echo '<td>'.$jk.'</td>';
				echo '</tr>';
			}
			echo '</table>';
		}
		echo '</form>';
		return ob_get_clean();
    }

    public function survivalTeamScore( $atts, $content = "" ) {
        global $wpdb;
        $table_team = $wpdb->prefix . 'team';
        $table_score = $wpdb->prefix . 'score';
        $table_survival = $wpdb->prefix . 'survival';
        $table_timeslot = $wpdb->prefix . 'timeslot';

        // Link to Team score page
        $teamScorePage = get_option( $this->option_name . '_team_score_page' );
        if ($teamScorePage == '')
        {
            Survival_Admin::showMessage(
                __( "URL not found! Make sure to provide the '_team_score_page' in the Survival Setting.", 'bso-survival'), 
                "error");
            return;
        }

        // Link to score update when user is logged in
        if ( is_user_logged_in() ){
            $scoreUpdatePage = get_option( $this->option_name . '_score_page' ); 
        } else {
            $scoreUpdatePage = "";
        }

        ob_start();
		echo '<form name="survival_team_score" id="survival_team_score">';
        echo "<h1>".esc_html__('Survival score', 'bso-survival'). "</h1>";
        echo "<emp>".__( 'Select a survival:','bso-survival')."</emp>";

		// Pass Survival Id
		if ( isset($_REQUEST['SurvivalId']) && $_REQUEST['SurvivalId'] )
		{
			$survivalid=$_REQUEST['SurvivalId'];
		} else { $survivalid=1; } // when empty select the first survival
        
        // Survival choice
        $survivals = $wpdb->get_results("SELECT id, name FROM $table_survival");
        echo '<select name="SurvivalId" id="SurvivalId">';
        foreach( $survivals as $survivalRecord ) {
            if ($survivalid==$survivalRecord->id) { 
                echo "<option value='$survivalRecord->id' selected='selected'>$survivalRecord->name</option>";
            } 
            else { 
                echo "<option value='$survivalRecord->id'>$survivalRecord->name</option>";
            }
        }
        echo '</select> <input type="submit"><br>';
        
        // Time slots
        $timeslots = $wpdb->get_results("
                SELECT t.id, t.starttime
                FROM $table_timeslot t
                ORDER BY t.starttime;");

		if( ! empty( $timeslots ) ) {
			echo '<table class="survivaltable">';
			echo '<tr>';
            echo "<th>".__( 'Start time','bso-survival')."</th>";
            echo "<th>".__( 'Team A','bso-survival')."</th>";
            echo "<th>".__( 'Team B','bso-survival')."</th>";
			echo '</tr>';

			foreach( $timeslots as $timeSlotRecord ) {
				echo '<tr>';
                echo '<td>'.$timeSlotRecord->starttime.'</td>';

                // Competing teams
                $teams = $wpdb->get_results("
                        SELECT t.id as TeamId, t.name as TeamName, s.id as ScoreId, 
                        s.time_min_score, s.time_sec_score, s.points_score, s.error_score, s.position,
                        ts.starttime
                        FROM $table_score s, $table_team t, $table_timeslot ts
                        WHERE s.team_id = t.id AND s.timeslot_id = ts.id
                        AND s.survival_id = $survivalid
                        AND ts.id = $timeSlotRecord->id
                        ORDER by s.starttime;");

                // Show both competing teams as a link
                foreach( $teams as $teamRecord ) {
                    if ($scoreUpdatePage > '')
                    { 
                        // logged in: update score
                        $scoreUpdateURL = add_query_arg( array( 'ScoreId' => $teamRecord->ScoreId, ), $scoreUpdatePage );
                        echo '<td>'."<a href='$scoreUpdateURL'>$teamRecord->TeamName</a>".'</td>';
                    } else {
                        // Anonymous: team score page
                        $teamScoreURL = add_query_arg( array( 'TeamId' => $teamRecord->TeamId, ), $teamScorePage );
                        echo '<td>'."<a href='$teamScoreURL'>$teamRecord->TeamName</a>".'</td>';
                    }
                }
                echo '</tr>';
			}
			echo '</table>';
		}
		echo '</form>';

		return ob_get_clean();
    }

    public function updateSurvivalPosition( $atts, $content = "" ) {
        global $wpdb;
        $table_team = $wpdb->prefix . 'team';
        $table_score = $wpdb->prefix . 'score';
        $table_survival = $wpdb->prefix . 'survival';
        $table_timeslot = $wpdb->prefix . 'timeslot';

        ob_start();
		echo '<form name="survival_update_position" id="survival_update_position" action="tools.php?page=survival&tab=tabupdate">';
        echo "<h2>".esc_html__('Update survival position','bso-survival')."</h2>";
        echo "<emp>".__( 'Select a survival:','bso-survival')."</emp>";

		// Pass Survival Id
		if ( isset($_REQUEST['SurvivalId']) && $_REQUEST['SurvivalId'] )
		{
			$survivalid=$_REQUEST['SurvivalId'];
		} else { $survivalid=1; } // when empty select the first survival
        
        // Survival choice
        $survivals = $wpdb->get_results("SELECT id, name FROM $table_survival");
        echo '<select name="SurvivalId" id="SurvivalId">';
        foreach( $survivals as $survivalRecord ) {
            if ($survivalid==$survivalRecord->id) { 
                echo "<option value='$survivalRecord->id' selected='selected'>$survivalRecord->name</option>";
            } 
            else { 
                echo "<option value='$survivalRecord->id'>$survivalRecord->name</option>";
            }
        }
        echo '</select> <input type="submit"><br>';

        $teamScore = $wpdb->get_results("
                        SELECT t.id as TeamId, t.name as TeamName, 
                        s.id as ScoreId, s.points_score, s.error_score, s.position, 
                        CONCAT(LPAD(s.time_min_score, 2, '0'), ':', LPAD(s.time_sec_score, 2, '0')) as time_score
                        FROM $table_score s, $table_team t
                        WHERE s.team_id = t.id 
                        AND s.survival_id = $survivalid
                        ORDER by s.position ASC;");
                        //TODO: add field s.time_score

		if( ! empty( $teamScore ) ) {
			echo '<table class="survivaltable">';
			echo '<tr>';
            echo '<th>Position</th>';
            echo "<th>".__( 'Team','bso-survival')."</th>";
            echo "<th>".__( 'Time','bso-survival')."</th>";
            echo "<th>".__( 'Error','bso-survival')."</th>";
            echo "<th>".__( 'Points','bso-survival')."</th>";
			echo '</tr>';

			foreach( $teamScore as $score ) {
				echo '<tr>';
                echo '<td>'.$score->position.'</td>';
                echo '<td>'."$score->TeamName".'</td>';
                echo '<td>'."$score->time_score".'</td>';
                echo '<td>'."$score->error_score".'</td>';
                echo '<td>'."$score->points_score".'</td>';
                echo '</tr>';
			}
			echo '</table>';
		}
		echo '</form>';

		return ob_get_clean();
    }    

public static function validUserRole($returnMessage=false) {
    $ret = true;
    // check for roles
    $correctRole= ( Survival_Admin::check_user_role('referee') ||
                    Survival_Admin::check_user_role('administrator') )? true: false;
    if (!$correctRole) {
        $ret = false;
        if ($returnMessage){
            Survival_Admin::showMessage(
                __( 'You need to be logged in as Referee!','bso-survival'), 
                "error");
        }
    } 
    return $ret;
}

    // Test https://byteway.eu/bso-survival/update-score/?ScoreId=1
    public function teamScoreUpdate( $atts, $content = "" )
	{
        global $wpdb;

        if (Survival_Admin::validUserRole(true) == true){
            $table_score = $wpdb->prefix.'score';
            $table_team = $wpdb->prefix.'team';
            $table_survival = $wpdb->prefix.'survival';
            $scoreid='';

            if ( isset($_REQUEST['ScoreId']) && $_REQUEST['ScoreId'] )
            {
                $scoreid=$_REQUEST['ScoreId'];
            } 
            if ($scoreid == '') { return; } // do nothing

            // update?
            if (isset($_POST['update'])) {
                
                if (isset($_POST['starttime'])) {
                    $formattedTime = (new DateTime($_POST['starttime']))->format('Y-m-d H:i:s'); // Time convertion fix
                    $jk = (int)isset($_POST['joker'])? 1: 0; // joker checkbox fix

                    $wpdb->update(
                        $table_score,
                        array(
                            'starttime'  => $formattedTime,
                            'joker' => $jk,
                            'time_min_score' => $_POST['time_min_score'],
                            'time_sec_score' => $_POST['time_sec_score'],
                            'points_score' => $_POST['points_score'],
                            'error_score' => $_POST['error_score'],
                            'position' => $_POST['position']
                        ),
                        array( 'id' => $scoreid )
                    );
                    echo "<b>The score has been updated.</b><br>";
                    //echo "Start time: ".$formattedTime;
                }
            }

            ob_start();
            $data = $wpdb->get_results(
                            "SELECT a.*, b.id SurvivalId, b.name SurvivalName, c.name TeamName, c.id TeamId
                                FROM $table_score a, $table_survival b, $table_team c
                                WHERE a.survival_id=b.id
                                AND a.team_id=c.id
                                AND a.id=$scoreid");
            if( ! empty( $data ) ) {
                echo '<form name="team_score_update" id="team_score_update" method="post">'; 
                echo '<table class="update-score-table">';
                echo '<tr>';
                echo "<th>".__( 'Field','bso-survival')."</th>";
                echo "<th>".__( 'Value','bso-survival')."</th>";
                echo '</tr>';

                // See if there is anything to link
                $teamPage = get_option( $this->option_name . '_team_score_page' );
                $survivalPage = get_option( $this->option_name . '_survival_page' );

                foreach( $data as $score ) {
                    echo '<tr><td>Score Id</td>';
                    echo '<td>'.$score->id."<input type='hidden' id='ScoreId' name='ScoreId' value='$score->id'>".'</td></tr>';
                    
                    echo "<tr><td>".__('Team', 'bso-survival')."</td><td>"; // start tr td
                    if ($teamPage > '')
                    {
                        $teamURL = add_query_arg( array( 'TeamId' => $score->TeamId, ), $teamPage );
                        echo "<a href='$teamURL'>$score->TeamName</a>";
                    } else {
                        echo $score->TeamName;
                    }
                    echo "<input type='hidden' id='TeamId' name='TeamId' value='$score->TeamId'>".'</td></tr>'; // end tr td

                    echo '<tr><td>Survival</td><td>'; // start tr td
                    if ($survivalPage > '')
                    {
                        $survivalURL = add_query_arg( array( 'SurvivalId' => $score->SurvivalId, ), $survivalPage );
                        echo "<a href='$survivalURL'>$score->SurvivalName</a>";
                    } else {
                        echo $score->SurvivalName;    
                    }
                    echo "<input type='hidden' id='SurvivalId' name='SurvivalId' value='$score->SurvivalId'>".'</td></tr>'; //end td tr

                    echo "<tr><td>".__( 'Start Time', 'bso-survival'). "</td>";
                    $formattedTime = (new DateTime($score->starttime))->format('H:i:s');
                    echo '<td><input type="time" name="starttime" id="starttime" value="'.$formattedTime.'"></td></tr>';

                    echo "<tr><td>".__('Joker', 'bso-survival')."</td>";
                    $checked = ($score->joker==1)? "checked": '';
                    echo "<td><input type='checkbox' name='joker' id='joker' $checked></td></tr>";
                    
                    echo "<tr><td>".__( 'Time score', 'bso-survival')."<br>[min:sec]</td>";
                    echo '<td><input type="number" name="time_min_score" id="time_min_score" min="0" max="60" style="width: 60px;" value="'.$score->time_min_score.'">';
                    echo '<input type="number" name="time_sec_score" id="time_sec_score" min="0" max="60" style="width: 60px;" value="'.$score->time_sec_score.'"></td></tr>';
                    echo "<tr><td>".__( 'Points', 'bso-survival'). "</td>";
                    echo '<td><input type="number" name="points_score" id="points_score" min="0" value="'.$score->points_score.'"></td></tr>';
                    echo "<tr><td>".__( 'Error', 'bso-survival')."</td>";
                    echo '<td><input type="number" name="error_score" id="error_score" min="0" value="'.$score->error_score.'"></td></tr>';
                    echo "<tr><td>".__( 'Position','bso-survival')."</td>";
                    echo '<td><input type="number" name="position" id="position" min="0" max="25" value="'.$score->position.'"></td></tr>';
                }
                echo '</table>';
                echo "<input type='submit' name='update' value=".__('Update', 'bso-survival')."><br>";
                echo '</form>';
            }
            return ob_get_clean();
        }
    }

    /**
     * Validate if the user has the correct role.
     */
    public static function check_user_role($role){
        $user = wp_get_current_user();
        if (isset($user))
        {
            if(in_array( $role, (array) $user->roles )){
                // echo 'User ID: ' . $user->ID;
                // echo 'User Display Name: ' . $user->display_name;
                // echo 'User Email: ' . $user->user_email;
                return true;
            }
        }
        return false;
    }

    /**
     * Displaying a Messages
     * Message types: success/error/info/warning
     */
    public static function showMessage($message="Done", $messagetype="info") {
        $class="";
        switch ($messagetype) {
            case "error": $class="error-message";
                break;
            case "warning": $class="warning-message";
                break;
            case "success": $class="success-message";
                break;
            case "info": $class="info-message";
                break;
            default : $class="info-message";
                break;
        }
        echo "<div class='$class' data-slug='$class'>";
        //wp_die("$message", "$messagetype", array('response' => 401));
        echo wpautop($message);
        echo '</div>';
    }

    // Todo: check survival count and team count
    public static function validateCount_Survival() {
        global $wpdb;
        $table_survival = $wpdb->prefix . 'survival';
        $table_team = $wpdb->prefix . 'team';

        // Max amount of time slots 
        $timeSlotCount = 12; 
        
        // Ideally there are as much survivals as there are time slots
        $survivalCount = $wpdb->get_results("SELECT count(id) FROM $table_survival");
        if ($survivalCount < $timeSlotCount)
        {
            while ($survivalCount < $timeSlotCount)
            {
                $wpdb->insert( $table_survival, array( 'name' => "pause" ) );
                $survivalCount = $wpdb->get_results("SELECT count(id) FROM $table_survival");
            }
        }
        // Each survival needs two teams, so total team count must be even
        $teamCount = $wpdb->get_results("SELECT count(id) FROM $table_team");
        if (fmod($teamCount,2) != 0){
            $wpdb->insert( $table_team, array( 'name' => "dummy" ) );
        }    
    } 
}