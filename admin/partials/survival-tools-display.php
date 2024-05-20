<?php
/**
 * Provide a tools area view for the plugin
 *
 * @link       https://https://byteway.eu/contact
 * @since      1.0.0
 *
 * @package    Survival
 * @subpackage Survival/admin/partials
 */

// Used in the HTML title tag.
$title = __( 'BSO Survival Tools' );

// Link to calculate interim score
$calculateScore = add_query_arg( array(
  'action' => 'calculateScore',)
  , admin_url('admin-post.php'));

//$cssRef = plugin_dir_url( __FILE__ ) . 'css/survival-admin.css?ver=1.0.0';
// TODO: link correct css  - survival-public.css

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

<div class="wrap">
    <h2><?php echo esc_html( $title ); ?></h2>
    <!-- Here are our tabs -->
    <nav class="nav-tab-wrapper">
    <a href="?page=<?php echo $plugin_name; ?>" class="nav-tab <?php if($tab===null):?>nav-tab-active<?php endif; ?>">General</a>
    <a href="?page=<?php echo $plugin_name; ?>&tab=tabupdate" class="nav-tab <?php if($tab==='tabupdate'):?>nav-tab-active<?php endif; ?>">Update</a>
    <a href="?page=<?php echo $plugin_name; ?>&tab=tabposition" class="nav-tab <?php if($tab==='tabposition'):?>nav-tab-active<?php endif; ?>">Team Position</a>
    </nav>
  
    <div class="tab-content">
    <?php switch($tab) :
      case 'tabupdate':
        echo do_shortcode("[survival_update_position]");
        break;
      case 'tabposition':
        echo do_shortcode("[display_score]");
        break;
      default:
        echo "<h3>Calculate Score</h3>";
        echo "<a class='button' href='$calculateScore'>Calculate Score</a>";
        break;
    endswitch; ?>
    </div>
</div>