<?php

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://https://byteway.eu/contact
 * @since      1.0.0
 *
 * @package    Survival
 * @subpackage Survival/admin/partials
 */

// Used in the HTML title tag.
$title = __( 'BSO Survival Settings' );

//Get the active tab from the $_GET param
$default_tab = null;
$tab = isset($_GET['tab']) ? $_GET['tab'] : $default_tab;

// Get the plugin name
$plugin_name = $this->plugin_name;

// check user capabilities
if ( ! current_user_can( 'manage_options' ) ) {
    return;
  }

?>

<!-- This file should primarily consist of HTML with a little bit of PHP. -->
<div class="wrap">
    <h2><?php echo esc_html( $title ); ?></h2>

    <nav class="nav-tab-wrapper">
    <a href="?page=<?php echo $plugin_name; ?>" class="nav-tab <?php if($tab===null):?>nav-tab-active<?php endif; ?>">General Setings</a>
    <a href="?page=<?php echo $plugin_name; ?>&tab=shortcode" class="nav-tab <?php if($tab==='shortcode'):?>nav-tab-active<?php endif; ?>">Shortcodes</a>
    <a href="?page=<?php echo $plugin_name; ?>&tab=help" class="nav-tab <?php if($tab==='help'):?>nav-tab-active<?php endif; ?>">Survival Help</a>
    </nav>
  
    <div class="tab-content">
    <?php switch($tab) :
      case 'shortcode':
        echo "<h3>Shortcodes for Survival Data</h3>";
        echo "<ul>"; 
	    echo "<li>".__( "List all Survivals",'bso-survival').": ['survival_list']"."</li>";
        echo "<li>".__( "List all Team details",'bso-survival').": ['team_list']"."</li>";
        echo "<li>".__( "List all Referee details",'bso-survival').": ['referee_list']"."</li>";
        echo "<li>".__( "List all Survivals of Referee",'bso-survival').": ['referee_survival_list']"."</li>";
        echo "<li>".__( "List all Scores per Team",'bso-survival').": ['team_score_survival_list']"."</li>";
        echo "<li>".__( "List all Team per Survival",'bso-survival').": ['survival_team_score']"."</li>";
        echo "<li>".__( "Edit Score Form",'bso-survival').": ['team_score_update']"."</li>";
        echo "<li>".__( "Show score result",'bso-survival').": ['display_score']"."</li>";
        echo "<li>".__( "Update Survival Position",'bso-survival').": ['survival_update_position']"."</li>";
        echo "</ul>";
        break;
      case 'help':
        echo "<h3>Shortcodes for Survival Help</h3>";
        echo "<ul>";
        echo "<li>".__( "Survival main help page",'bso-survival').": ['survival_page']"."</li>";
        echo "<li>".__( "Survival solution description",'bso-survival').": ['survival_help']"."</li>";
        echo "<li>".__( "Kano bunchy jump",'bso-survival').": ['survival_01_page']"."</li>";
        echo "<li>".__( "Rope Track",'bso-survival').": ['survival_02_page']"."</li>";
        echo "<li>".__( "Step run",'bso-survival').": ['survival_03_page']"."</li>";
        echo "<li>".__( "Obstacle course",'bso-survival').": ['survival_04_page']"."</li>";
        echo "<li>".__( "Truck / Zip line",'bso-survival').": ['survival_05_page']"."</li>";
        echo "<li>".__( "Survival Track",'bso-survival').": ['survival_06_page']"."</li>";
        echo "<li>".__( "Scooping water",'bso-survival').": ['survival_07_page']"."</li>";
        echo "<li>".__( "Cary water",'bso-survival').": ['survival_08_page']"."</li>";
        echo "<li>".__( "Building rafts",'bso-survival').": ['survival_09_page']"."</li>";
        echo "<li>".__( "Kano sprint",'bso-survival').": ['survival_10_page']"."</li>";
        echo "<li>".__( "Kano bunchy jump",'bso-survival').": ['survival_11_page']"."</li>";
        echo "</ul>";
        break;
      default:
        echo '<form action="options.php" method="post">';
            settings_fields( $this->plugin_name );
            do_settings_sections( $this->plugin_name );
            submit_button();
        echo '</form>';

        break;
        endswitch; ?>
    </div>
</div>