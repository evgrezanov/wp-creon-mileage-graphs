<?php
/*
Plugin Name: Creon mileage graphs
Description: --
Version: 1.1.0
Author: Evgeniy Rezanov
Author URI: https://www.upwork.com/freelancers/~01ea58721977099d53
Text Domain: mileage-graphs
Domain Path: /languages
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
PHP requires at least: 5.6
WP requires at least: 5.0
Tested up to: 5.6
Copyright: 2021
*/
namespace CreonMG;

// Exit if accessed directly
defined('ABSPATH') || exit;

class CreonMileageGraphs{

  public static function init(){
    define('CMG_PLUGIN_URL', plugins_url('', __FILE__));
    define('CMG_PLUGIN_DIR_PATH', plugin_dir_path(__FILE__));
    define('CMG_PLUGIN_VERSION', '1.1.0');

    //add_action('plugins_loaded', [__CLASS__, 'inc_components']);
    add_action( 'wp_enqueue_scripts', [__CLASS__, 'custom_scripts']);
    add_shortcode( 'total_mileage', [__CLASS__, 'render_total_mileage']);
    
  }

  public static function inc_components(){
    //require_once CMG_PLUGIN_DIR_PATH . '/inc/slide-post-type.php';
    //require_once CMG_PLUGIN_DIR_PATH . '/inc/settings.php';
    //require_once CMG_PLUGIN_DIR_PATH . '/inc/shortcodes.php';
  }

  /* Custom Scripts to load */
  //https://cdnjs.com/libraries/Chart.js
  public static function custom_scripts() {
      wp_enqueue_script( 
        'chart-js', 
        'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.6.0/Chart.min.js', 
        array(), 
        true 
      );
  }
  
  public static function render_total_mileage(){
    ob_start();
    
    
    $user_mileages = get_posts(
      array(
        'post_type'       => 'mileage',
        'post_status'     => 'publish',
        'posts_per_page'  => -1,
        'fields'          => 'ids',
        'post_author'     => get_current_user_id(),
      )
    );

    $distance = 0;
    if ($user_mileages){
      foreach($user_mileages as $p){
        // Total mileage of individual
        $distance = $distance + get_post_meta($p,"distance",true);
      }
    }

    $team_id = get_user_meta(get_current_user_id(), 'team-id', true);
    //var_dump($team_id);
    if ($team_id){
      $team_mileages = get_posts(
        array(
          'post_type'       => 'mileage',
          'post_status'     => 'publish',
          'posts_per_page'  => -1,
          'fields'          => 'ids',
          'meta_key'        => 'team-id',
          'meta_value_num'  => $team_id,
          'compare'         => '=',
        )
      );
      $t_distance = 0;
      if ($team_mileages){
        foreach($team_mileages as $t){
          // Total mileage of team
          $t_distance = $t_distance + get_post_meta($t,"distance",true);
        }
      }
    }
    ?>
    <strong>User total distance:<?=$distance;?></strong>
    <strong>Team [<?=$team_id;?>] total distance:<?=$t_distance;?></strong>
    <?php
    return ob_get_clean();
  }

}
CreonMileageGraphs::init();