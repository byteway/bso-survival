<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://byteway.eu/contact
 * @since      1.0.0
 *
 * @package    Survival
 * @subpackage Survival/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Survival
 * @subpackage Survival/public
 * @author     Berend Otten <berend.otten@gmail.com>
 */
class Survival_Public {

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
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

        // Survival help pages
        add_shortcode( 'survival_help', array( $this, 'display_survival_help' ) );
        add_shortcode( 'survival_page', array( $this, 'display_survival_page' ) );
        add_shortcode( 'survival_01_page', array( $this, 'display_survival_01_page' ) );
        add_shortcode( 'survival_02_page', array( $this, 'display_survival_02_page' ) );
        add_shortcode( 'survival_03_page', array( $this, 'display_survival_03_page' ) );
        add_shortcode( 'survival_04_page', array( $this, 'display_survival_04_page' ) );
        add_shortcode( 'survival_05_page', array( $this, 'display_survival_05_page' ) );
        add_shortcode( 'survival_06_page', array( $this, 'display_survival_06_page' ) );
        add_shortcode( 'survival_07_page', array( $this, 'display_survival_07_page' ) );
        add_shortcode( 'survival_08_page', array( $this, 'display_survival_08_page' ) );
        add_shortcode( 'survival_09_page', array( $this, 'display_survival_09_page' ) );
        add_shortcode( 'survival_10_page', array( $this, 'display_survival_10_page' ) );
        add_shortcode( 'survival_11_page', array( $this, 'display_survival_11_page' ) );
        add_shortcode( 'survival_12_page', array( $this, 'display_survival_12_page' ) );
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/survival-public.css', array(), $this->version, 'all' );

        // 'navigation' => 'tabs',
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/survival-public.js', array( 'jquery' ), $this->version, false );
	}

    /**
	 * Display help page in user selected language.
     * Default language is EN.
     * Available languages: nl_NL, en_US
	 *
	 * @since    1.0.0
	 */
	private function getHelpLanguage($filename) {
        $ret = '';
        $fn=substr($filename, 0, strpos($filename,'.php'));
        switch (get_bloginfo("language")) {
            case 'en-US': 
                $ret = $fn . '-en_US.php';
                break;
            case 'nl-NL': 
                $ret = $fn . '-nl_NL.php';
                break;
            default: 
                $ret = $filename;
                break;
        }
        return $ret;
    }
    /**
     * All Survival help pages
     *
     * @since    1.0.0
     */
    public function display_survival_help() {
        include_once $this->getHelpLanguage('partials/survival-help.php');
    }
    /**
     * Main help page for Survival
     *
     * @since    1.0.0
     */
    public function display_survival_page() {
        include_once $this->getHelpLanguage('partials/survival.php');
    }
    /**
     * Page 01 for Survival
     *
     * @since    1.0.0
     */
    public function display_survival_01_page() {
        include_once $this->getHelpLanguage('partials/survival-01.php');
    }
    /**
     * Page 02 for Survival
     *
     * @since    1.0.0
     */
    public function display_survival_02_page() {
        include_once $this->getHelpLanguage('partials/survival-02.php');
    }
    /**
     * Page 03 for Survival
     *
     * @since    1.0.0
     */
    public function display_survival_03_page() {
        include_once $this->getHelpLanguage('partials/survival-03.php');
    }
    /**
     * Page 04 for Survival
     *
     * @since    1.0.0
     */
    public function display_survival_04_page() {
        include_once $this->getHelpLanguage('partials/survival-04.php');
    }
    /**
     * Page 05 for Survival
     *
     * @since    1.0.0
     */
    public function display_survival_05_page() {
        include_once $this->getHelpLanguage('partials/survival-05.php');
    }
    /**
     * Page 06 for Survival
     *
     * @since    1.0.0
     */
    public function display_survival_06_page() {
        include_once $this->getHelpLanguage('partials/survival-06.php');
    }
    /**
     * Page 07 for Survival
     *
     * @since    1.0.0
     */
    public function display_survival_07_page() {
        include_once $this->getHelpLanguage('partials/survival-07.php');
    }
    /**
     * Page 08 for Survival
     *
     * @since    1.0.0
     */
    public function display_survival_08_page() {
        include_once $this->getHelpLanguage('partials/survival-08.php');
    }
    /**
     * Page 09 for Survival
     *
     * @since    1.0.0
     */
    public function display_survival_09_page() {
        include_once $this->getHelpLanguage('partials/survival-09.php');
    }
    /**
     * Page 10 for Survival
     *
     * @since    1.0.0
     */
    public function display_survival_10_page() {
        include_once $this->getHelpLanguage('partials/survival-10.php');
    }
    /**
     * Page 11 for Survival
     *
     * @since    1.0.0
     */
    public function display_survival_11_page() {
        include_once $this->getHelpLanguage('partials/survival-11.php');
    }
    /**
     * Page 12 for Survival
     *
     * @since    1.0.0
     */
    public function display_survival_12_page() {
        include_once $this->getHelpLanguage('partials/survival-12.php');
    }

}
