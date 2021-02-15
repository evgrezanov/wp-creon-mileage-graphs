<?php
/*
Plugin Name: Creon mileage graphs
Description: --
Version: 1.2.0
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

class CreonMileageGraphs
{

  public static function init()
  {
    define('CMG_PLUGIN_URL', plugins_url('', __FILE__));
    define('CMG_PLUGIN_DIR_PATH', plugin_dir_path(__FILE__));
    define('CMG_PLUGIN_VERSION', '1.2.0');

    add_shortcode('user_total_distance', [__CLASS__, 'render_distance_by_user_id']);
    add_shortcode('team_total_distance', [__CLASS__, 'render_team_distance_by_user_id']);
    add_shortcode('project_total_distance', [__CLASS__, 'render_total_distance_by_project']);
    add_shortcode('project_teams_titles', [__CLASS__, 'render_project_teams_titles']);
    add_shortcode('project_teams_distance', [__CLASS__, 'render_project_teams_distance']);
    add_shortcode('teams_distance_by_id', [__CLASS__, 'render_teams_distance_by_id']);

    add_shortcode('mapping_users_teams', [__CLASS__, 'mapping_users_teams']);

    add_action('wp_enqueue_scripts', array(__CLASS__, 'assets'));

    add_shortcode('team_result_dt', [__CLASS__, 'team_result_dt']);

    add_action('save_post', [__CLASS__, 'save_post_mileage_callback'], 20, 3);
  }

  public static function render_team_distance_by_user_id($atts)
  {
    $user_id = get_current_user_id();
    $team_id = get_user_meta($user_id, 'team-id', true);
    $distance = self::get_distance_by_team_id($team_id);
    $distance = round($distance, 2);
    ob_start();
?>
    <span><?= $distance ?></span>
  <?php
    return ob_get_clean();
  }

  public static function render_distance_by_user_id($atts)
  {
    $distance = self::get_distance_by_user_id(get_current_user_id());
    $distance = round($distance, 2);
    ob_start(); ?>
    <span><?= $distance ?></span>
    <?php
    return ob_get_clean();
  }

  public static function render_total_distance_by_project()
  {
    global $wpdb;
    $args = array(
      'post_type'       => 'mileage',
      'post_status'     => 'publish',
      'posts_per_page'  => -1,
      'fields'          => 'ids',
    );
    $mileages_ids = get_posts($args);
    if (!empty($mileages_ids)) :
      // escape the status values
      $mileages_ids = array_map('esc_sql', (array) $mileages_ids);
      $mileages_ids_string = "'" . implode("', '", $mileages_ids) . "'";
      $query = "
        SELECT SUM(`meta_value`)
        FROM $wpdb->postmeta
        WHERE `post_id` IN (" . $mileages_ids_string . ") 
        AND `meta_key` = 'distance'";
      $total_project_distance = $wpdb->get_var($query);
      $total_project_distance = round($total_project_distance, 2);
      ob_start(); ?>
      <span><?= $total_project_distance ?></span>
      <?php
      return ob_get_clean();
    endif;
  }

  public static function render_project_teams_titles()
  {
    global $wpdb;

    $args = array(
      'post_type'       => 'team',
      'post_status'     => 'publish',
      'posts_per_page'  => -1,
      'orderby'         => 'ID',
      'order'           => 'ASC'
    );
    $teams = get_posts($args);
    if (!empty($teams)) :
      $items = array();

      foreach ($teams as $team) :
        $items[] = $team->post_title;
      endforeach;

      $items = implode(",", $items);

      return $items;
    endif;
  }

  public static function render_project_teams_distance()
  {
    global $wpdb;

    $args = array(
      'post_type'       => 'team',
      'post_status'     => 'publish',
      'posts_per_page'  => -1,
      'orderby'         => 'ID',
      'order'           => 'ASC'
    );
    $teams = get_posts($args);
    if (!empty($teams)) :
      $items = array();
      foreach ($teams as $team) :
        $items[] = self::get_distance_by_team_id($team->ID);
      endforeach;

      return $items = implode(",", $items);
    endif;
  }

  public static function render_teams_distance_by_id($atts)
  {
    $atts = shortcode_atts(array(
      'team_id' => 'set correct team id'
    ), $atts);
    $team_id = $atts['team_id'];
    if (!is_numeric($team_id)) :
      return $team_id;
    else :
      return self::get_distance_by_team_id($team_id);
    endif;
  }

  // return total distance by user
  public static function get_distance_by_user_id($user_id)
  {
    global $wpdb;
    empty($user_id) ? get_current_user_id() : $user_id;
    $mileages_ids = $wpdb->get_col("SELECT DISTINCT ID FROM $wpdb->posts WHERE post_type = 'mileage' AND post_status = 'publish' AND post_author =" . $user_id);

    if (!empty($mileages_ids)) :
      // escape the status values
      $mileages_ids = array_map('esc_sql', (array) $mileages_ids);
      $mileages_ids_string = "'" . implode("', '", $mileages_ids) . "'";
      $query = "
        SELECT SUM(`meta_value`)
        FROM $wpdb->postmeta
        WHERE `post_id` IN (" . $mileages_ids_string . ") 
        AND `meta_key` = 'distance'";

      $total_user_distance = $wpdb->get_var($query);
      $total_user_distance = round($total_user_distance, 2);
      return $total_user_distance;
    endif;
  }

  // return total distance by team
  public static function get_distance_by_team_id($team_id)
  {
    global $wpdb;
    $team_users = self::get_all_team_users($team_id);

    $team_mileages = self::get_users_mileages($team_users);
    $query = "
        SELECT SUM(`meta_value`)
        FROM $wpdb->postmeta
        WHERE `post_id` IN (" . $team_mileages . ") AND `meta_key` = 'distance'
    ";
    $team_distance = $wpdb->get_var($query);
    $team_distance = round($team_distance, 2);
    return $team_distance;
  }

  // return all users ids (string with delimeter) by team 
  public static function get_all_team_users($team_id)
  {
    global $wpdb;
    $query = "
        SELECT DISTINCT
          `user_id`
        FROM 
          $wpdb->usermeta
        WHERE 
          `meta_key` = 'team-id' 
        AND 
          `meta_value` = " . $team_id;
    $users_ids = $wpdb->get_col($query);

    if (empty($users_ids))
      return;

    $users_ids = array_map('esc_sql', (array) $users_ids);
    $users_ids_string = "'" . implode("', '", $users_ids) . "'";
    return $users_ids_string;
  }

  // return all ids of mileages (string with delimeter) by users_ids
  public static function get_users_mileages($users_id)
  {
    global $wpdb;
    $query = "
          SELECT DISTINCT
            `ID`
          FROM 
            $wpdb->posts
          WHERE 
            `post_type` = 'mileage'
          AND
            `post_status` = 'publish'
          AND 
            `post_author` IN (" . $users_id . ")";

    $mileages_ids = $wpdb->get_col($query);
    if (empty($mileages_ids))
      return;

    $mileages_ids = array_map('esc_sql', (array) $mileages_ids);
    $mileages_ids_string = "'" . implode("', '", $mileages_ids) . "'";
    return $mileages_ids_string;
  }

  public static function mapping_users_teams()
  {
    ob_start();
    $users = get_users();
    foreach ($users as $user) {
      $team_name = get_user_meta($user->ID, 'team-name', true);
      $team = get_page_by_title(trim($team_name), OBJECT, 'team');
      if ($team) :
        $res = update_user_meta($user->ID, 'team-id', $team->ID);
        if ($res) :
          print_r('<p>' . $user->display_name . ' [' . $user->user_login . '] - [' . $user->ID . '] - update team-id to-->>' . $team->ID . '</p>');
        else :
          print_r('<strong><p>' . $user->display_name . ' [' . $user->user_login . '] - [' . $user->ID . ']- PROBLEM>>>>>' . $team->ID . '</p></strong>');
        endif;
      else :
        print_r('<p><strong>ALERT: ' . $user->display_name . ' [' . $user->user_login . ' - ' . $user->ID . ' - ' . $team->ID . ' - ' . $team->post_title . '</strong></p>');
      endif;
    }
    return ob_get_clean();
  }

  public static function assets()
  {
    wp_enqueue_script(
      'datatables',
      CMG_PLUGIN_URL . ('/lib/dt/datatables.min.js'),
      ['jquery'],
      CMG_PLUGIN_VERSION,
      false
    );

    wp_enqueue_script(
      'script',
      CMG_PLUGIN_URL . ('/lib/script.js'),
      ['datatables', 'jquery'],
      CMG_PLUGIN_VERSION,
      false
    );

    wp_enqueue_style(
      'datatable-styles',
      CMG_PLUGIN_URL . ('/lib/dt/datatables.min.css'),
      [],
      CMG_PLUGIN_VERSION
    );
  }

  public static function team_result_dt()
  {
    ob_start();
    $args = array(
      'post_type'       => 'team',
      'post_status'     => 'publish',
      'posts_per_page'  => -1,
      'orderby'         => 'ID',
      'order'           => 'ASC'
    );
    $teams = get_posts($args);
    if (!empty($teams)) : ?>
      <table id="teams_result" class="display" style="width:100%">
        <thead>
          <tr class="header">
            <th><span class="nobr">Team</span></th>
            <th><span class="nobr">Distance</span></th>
          </tr>
        </thead>
        <tbody>
          <?php
          foreach ($teams as $team) {
            $t_distance = self::get_distance_by_team_id($team->ID);
          ?>
            <tr>
              <td><?= $team->post_title ?></td>
              <td><?= $t_distance ?></td>
            </tr>
          <?php
          }
          ?>
        </tbody>
      </table>
    <?php
    endif;
    return ob_get_clean();
  }


  public static function save_post_mileage_callback($post_ID, $post, $update){
    global $post; 
    if ($post->post_type != 'mileage'){
      return;
    } else {
      $team_id = get_post_meta($post_ID, 'team-id', true);
      if ( empty($team_id) ){
        return;
      } else{
        $total_distance = self::get_distance_by_team_id($team_id);
        update_post_meta($team_id, 'total-distance', $total_distance);
      }
    }
  }

}

CreonMileageGraphs::init();
