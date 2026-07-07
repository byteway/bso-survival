<?php

/**
 * Survival plugin project
 *
 * Main Help Page
 *
 * @link       https://byteway.eu/contact
 * @since      1.0.0
 *
 * @package    Survival
 * @subpackage Survival/public/partials
 */
get_header();

// Page title
$title = __( 'Survival Help' );
//Get the active tab from the $_GET param
$default_tab = null;
$tab = isset($_GET['tab']) ? $_GET['tab'] : $default_tab;
?>
<h2><?php echo esc_html( $title ); ?></h2>
<div class="topnav">
    <a href="?tab=survival"         <?php if($tab==='survival'):?>class="active"<?php endif; ?>>Survival Help</a></li>
    <a href="?tab=survival_01_page" <?php if($tab==='survival_01_page'):?>class="active"<?php endif; ?>>Kano bunchy jump</a>
    <a href="?tab=survival_02_page" <?php if($tab==='survival_02_page'):?>class="active"<?php endif; ?>>Touwbaan</a>
    <a href="?tab=survival_03_page" <?php if($tab==='survival_03_page'):?>class="active"<?php endif; ?>>Step run</a>
    <a href="?tab=survival_04_page" <?php if($tab==='survival_04_page'):?>class="active"<?php endif; ?>>Stormbaan</a>
    <a href="?tab=survival_05_page" <?php if($tab==='survival_05_page'):?>class="active"<?php endif; ?>>Vrachtauto / Tokkelbaan</a>
    <a href="?tab=survival_06_page" <?php if($tab==='survival_06_page'):?>class="active"<?php endif; ?>>Survivalbaan</a>
    <a href="?tab=survival_07_page" <?php if($tab==='survival_07_page'):?>class="active"<?php endif; ?>>Waterscheppen</a>
    <a href="?tab=survival_08_page" <?php if($tab==='survival_08_page'):?>class="active"<?php endif; ?>>Water dragen</a>
    <a href="?tab=survival_09_page" <?php if($tab==='survival_09_page'):?>class="active"<?php endif; ?>>Vlotten bouwen</a>
    <a href="?tab=survival_10_page" <?php if($tab==='survival_10_page'):?>class="active"<?php endif; ?>>Kano sprint</a>
    <a href="?tab=survival_11_page" <?php if($tab==='survival_11_page'):?>class="active"<?php endif; ?>>Kano bunchy jump</a>
</div>

<?php switch($tab) :
case 'survival_01_page':
        echo "<div id='survival_01_page'>";
        echo do_shortcode("[survival_01_page]");
        echo "</div>";
        break;
case 'survival_02_page':
    echo "<div id='survival_02_page'>";
    echo do_shortcode("[survival_02_page]");
    echo "</div>";
    break;
case 'survival_03_page':
    echo "<div id='survival_03_page'>";
    echo do_shortcode("[survival_03_page]");
    echo "</div>";
    break;
case 'survival_04_page':
    echo "<div id='survival_04_page'>";
    echo do_shortcode("[survival_04_page]");
    echo "</div>";
    break;
case 'survival_05_page':
    echo "<div id='survival_05_page'>";
    echo do_shortcode("[survival_05_page]");
    echo "</div>";
    break;
case 'survival_06_page':
    echo "<div id='survival_06_page'>";
    echo do_shortcode("[survival_06_page]");
    echo "</div>";
    break;
case 'survival_07_page':
    echo "<div id='survival_07_page'>";
    echo do_shortcode("[survival_07_page]");
    echo "</div>";
    break;
case 'survival_08_page':
    echo "<div id='survival_08_page'>";
    echo do_shortcode("[survival_08_page]");
    echo "</div>";
    break;
case 'survival_09_page':
    echo "<div id='survival_09_page'>";
    echo do_shortcode("[survival_09_page]");
    echo "</div>";
    break;
case 'survival_10_page':
    echo "<div id='survival_10_page'>";
    echo do_shortcode("[survival_10_page]");
    echo "</div>";
    break;
case 'survival_11_page':
    echo "<div id='survival_11_page'>";
    echo do_shortcode("[survival_11_page]");
    echo "</div>";
    break;
default:
    echo "<div id='survival_page'>";
    echo do_shortcode("[survival_page]");
    echo "</div>";
    break;
endswitch; ?>

<?php get_footer(); ?>