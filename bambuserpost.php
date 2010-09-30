<?php
/*
Plugin Name: Bambuser Auto-Poster
Plugin URI: http://www.tv4.se/
Description: Publish Bambuser videocasts on a blog
Author: David Hall
Version: 0.1
Author URI: http://www.tv4.se/
License: GPL2
*/


add_filter('cron_schedules', 'tv4se_bambuser_cron',10); // Priority 11 to avoid being overwritten by SharedItems2WP

function tv4se_bambuser_cron($schedules)
{
    $options = get_option('tv4se_bambuser_options');
    $interval = intval($options['interval'])*60;
    if($interval==0) { $interval=1800; }

    return array_merge($schedules, array(
        'tv4se_bambuser_update' => array(
            'interval' => $interval,
            'display' => sprintf('Bambuser update(every %d seconds)', $interval)
        )));
}

function install_tv4se_bambuser(){
    if (!wp_next_scheduled('tv4se_bambuser_event')) {
        wp_schedule_event(time()+30, 'tv4se_bambuser_update', 'tv4se_bambuser_event');
        print wp_next_scheduled( 'tv4se_bambuser_event');
    }
}
register_activation_hook(__FILE__, 'install_tv4se_bambuser');

function uninstall_tv4se_bambuser(){
    $timestamp = wp_next_scheduled( 'tv4se_bambuser_event');
    wp_unschedule_event($timestamp, 'tv4se_bambuser_event');
}
register_deactivation_hook(__FILE__, 'uninstall_tv4se_bambuser');

add_filter( 'wp_feed_cache_transient_lifetime', 'tv4se_bambuser_cachetime', 10, 2 );

function tv4se_bambuser_cachetime($lifetime, $url) {
    $options = get_option('tv4se_bambuser_options');
    $interval = intval($options['interval'])*60;
    if($interval==0) { $interval=1800; }
    if ( false !== strpos( $url, 'feed.bambuser.com' ) ) {
        $lifetime = $interval-5;
    }
    return $lifetime;
}

add_action('admin_menu', 'tv4se_bambuser_settings_menu');

function tv4se_bambuser_settings_menu(){
    add_options_page('Bambuser Autoposter Settings', 'Bambuser Autoposter', 'manage_options', 'bambuser', 'tv4se_bambuser_options_page');
}

function tv4se_bambuser_options_page(){
    //wp_clear_scheduled_hook('tv4se_bambuser_event');
    //uninstall_tv4se_bambuser();
    //install_tv4se_bambuser();
    echo "<div>";
    echo "<h2>Bambuser Autoposter Settings</h2>";
    $timestamp = wp_next_scheduled( 'tv4se_bambuser_event');
    $last_save = intval(get_option('tv4se_bambuser_lastpub'));
    print "<p>Next update at ".date("Y-m-d H:i:s",$timestamp).'</p>';
    print '<p>Last clip from '.date("Y-m-d H:i:s",$last_save).'</p>';
    echo '<form action="options.php" method="post">';
    settings_fields('tv4se_bambuser_options');
    do_settings_sections('bambuser');
    echo '<input name="Submit" type="submit" value="'. esc_attr('Save Changes') .'" />
</form></div>';
}


function tv4se_bambuser_init(){
    register_setting( 'tv4se_bambuser_options', 'tv4se_bambuser_options', 'tv4se_bambuser_options_validate' );
    add_settings_section('tv4se_bambuser_autoposter', '', 'tv4se_bambuser_details_text', 'bambuser');
    add_settings_field('tv4se_bambuser_field_1', 'User name', 'tv4se_bambuser_field_display', 'bambuser', 'tv4se_bambuser_autoposter','url');
    add_settings_field('tv4se_bambuser_field_2', 'Post as user', 'tv4se_bambuser_field_display', 'bambuser', 'tv4se_bambuser_autoposter','postuser');
    add_settings_field('tv4se_bambuser_field_3', 'Post in category', 'tv4se_bambuser_field_display', 'bambuser', 'tv4se_bambuser_autoposter','category');
    add_settings_field('tv4se_bambuser_field_4', 'Maximum posts to publish', 'tv4se_bambuser_field_display', 'bambuser', 'tv4se_bambuser_autoposter','maxposts');
    add_settings_field('tv4se_bambuser_field_5', 'Update interval', 'tv4se_bambuser_field_display', 'bambuser', 'tv4se_bambuser_autoposter','interval');

}
add_action('admin_init', 'tv4se_bambuser_init');


function tv4se_bambuser_field_display($field){
    $options = get_option('tv4se_bambuser_options');
    switch ($field) {
        case "url":
            echo "<input id='tv4se_bambuser_field' name='tv4se_bambuser_options[url]' size='20' type='text' value='{$options['url']}' />";
            break;
        case "postuser":
            $blogusers = get_users_of_blog();
            echo '<select name="tv4se_bambuser_options[postuser]">';
            foreach ($blogusers as $usr) {
                echo "<option value=\"{$usr->ID}\"";
                if($options['postuser']==$usr->ID) {
                    echo ' selected="selected"';
                }
                echo ">{$usr->user_login} ({$usr->display_name})</option>";
            }
            echo '</select>';
            break;

        case "category":
            echo '<select name="tv4se_bambuser_options[category]">';
            $categories=  get_categories();
            foreach ($categories as $category) {
                $option = '<option value="'.$category->term_id.'"';
                if($options['category']==$category->term_id) {
                    $option .= ' selected="selected"';
                }
                $option .= '>'.$category->cat_name;
                $option .= ' ('.$category->category_count.')';
                $option .= '</option>';
                echo $option;
            }
            echo '</select>';
            break;
        case "maxposts":
            echo "<input id='tv4se_bambuser_field' name='tv4se_bambuser_options[maxposts]' size='5' type='text' value='{$options['maxposts']}' />";
            break;
        case "interval":
            echo "<input id='tv4se_bambuser_field' name='tv4se_bambuser_options[interval]' size='5' type='text' value='{$options['interval']}' /> minutes";
            break;
    }

}

function tv4se_bambuser_details_text(){
    echo "<p>Bambuser id information and post settings</p>";
}

function tv4se_bambuser_options_validate($input){
    preg_match("/[A-Za-z0-9\-_\.\ ]*/", $input['url'], $matches);
    $newinput['url'] = $matches[0];
    $newinput['postuser'] = intval($input['postuser']);
    $newinput['category'] = intval($input['category']);
    $newinput['maxposts'] = intval($input['maxposts']);
    $newinput['interval'] = abs(intval($input['interval']));
    return $newinput;
}

function tv4se_bambuser(){
    $options = get_option('tv4se_bambuser_options');
    $last_save = intval(get_option('tv4se_bambuser_lastpub'));
    $url = $options['url'];
    $feed = fetch_feed("http://feed.bambuser.com/channel/$url.rss");
    if($feed && $feed->get_items()) {
        $maxitems = $options['maxposts'];
        $items = array_slice($feed->get_items(), 0, $maxitems);
        foreach ( $items as $item ) :
            if(intval($item->get_date('U')) > $last_save) {
                $my_post = array(
                    'post_title' => $item->get_title(),
                    'post_content' => $item->get_enclosure()->native_embed(),
                    'post_date' => $item->get_date('Y-m-d H:i:s'),
                    'post_status' => 'publish',
                    'post_author' => $options['postuser'],
                    'post_category' => array($options['category'])
                );
                update_option('tv4se_bambuser_lastpub', $item->get_date('U'));
                $post_id = wp_insert_post( $my_post );

            }

        endforeach;
    }
}
add_action('tv4se_bambuser_event', 'tv4se_bambuser');


?>
    
 
