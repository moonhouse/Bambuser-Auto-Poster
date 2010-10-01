<?php
/*
Plugin Name: Bambuser Auto-Poster
Plugin URI: http://github.com/moonhouse/Bambuser-Auto-Poster
Description: Publish Bambuser videocasts on a blog
Author: David Hall
Version: 0.14
Author URI: http://www.tv4.se/
License: GPL2
*/

if (!class_exists('BambuserAutoposter')) {

    class BambuserAutoposter
    {
        var $opt_key = 'tv4se_bambuser_options';

        var $default_options = array(
            'username' => 'bambuser',
            'postuser' => 1,
            'category'	=> 1,
            'maxposts' => 1,
            'interval' => 1800,
            'revision' => 1);

        var $o = array();

        function BambuserAutoposter() {
            $this->read_options();
	        $this->actions_filters();
        }

        function read_options() {
            $this->o = get_option($this->opt_key);
        }

        function actions_filters() {
            add_filter('cron_schedules', array ( &$this, 'cron' ));
            add_action('tv4se_bambuser_event', array ( &$this, 'fetch_and_insert' ));
            register_activation_hook(__FILE__, array ( &$this, 'install_plugin' ));
            register_deactivation_hook(__FILE__,array ( &$this,  'uninstall_plugin'));
            add_action('admin_init', array ( &$this, 'admin_init' ));
            add_filter( 'wp_feed_cache_transient_lifetime', array(&$this, 'cachetime' ), 10, 2 );
            add_action('admin_menu', array ( &$this, 'settings_menu' ));
        }

        function cron($schedules)
        {
            $interval = intval($this->o['interval'])*60;
            if($interval==0) { $interval=1800; }

            return array_merge($schedules, array(
                'tv4se_bambuser_update' => array(
                    'interval' => $interval,
                    'display' => sprintf('Bambuser update(every %d seconds)', $interval)
                )));
        }


        function install_plugin(){
              $this->o = get_option($this->opt_key);

                  if (!is_array($this->o) || empty($this->o) ) {
                      update_option($this->opt_key, $this->default_options);
                      $this->o = get_option($this->opt_key);
                  }
                  else {
                      $this->o = $this->o + $this->default_options;
                      $this->o["revision"] = $this->default_options["revision"];
                      update_option( $this->opt_key, $this->o);
                  }

            if (!wp_next_scheduled('tv4se_bambuser_event')) {
                wp_schedule_event(time()+30, 'tv4se_bambuser_update', 'tv4se_bambuser_event');
            }
        }


        function uninstall_plugin(){
            $timestamp = wp_next_scheduled( 'tv4se_bambuser_event');
            wp_unschedule_event($timestamp, 'tv4se_bambuser_event');
        }




        function cachetime($lifetime, $url) {
            $interval = intval($this->o['interval'])*60;
            if($interval==0) { $interval=1800; }
            if ( false !== strpos( $url, 'feed.bambuser.com' ) ) {
                $lifetime = $interval-5;
            }
            return $lifetime;
        }



        function settings_menu(){
            add_options_page('Bambuser Autoposter Settings', 'Bambuser Autoposter', 'manage_options', 'bambuser',
                array ( &$this, 'options_page' ));
        }

        function options_page(){
            echo "<div>";
            echo "<h2>Bambuser Autoposter Settings</h2>";
            $timestamp = wp_next_scheduled( 'tv4se_bambuser_event');
            $last_save = intval(get_option('tv4se_bambuser_lastpub'));
            print "<p>Next update at ".date("Y-m-d H:i:s",$timestamp+get_option( 'gmt_offset' ) * 3600).'</p>';
            print '<p>Last clip from '.date("Y-m-d H:i:s",$last_save+get_option( 'gmt_offset' ) * 3600).'</p>';
            echo '<form action="options.php" method="post">';
            settings_fields('tv4se_bambuser_options');
            do_settings_sections('bambuser');
            echo '<input name="Submit" type="submit" value="'. esc_attr('Save Changes') .'" />
</form></div>';
        }


        function admin_init(){
            register_setting( 'tv4se_bambuser_options', 'tv4se_bambuser_options', array ( &$this, 'options_validate' ));
            add_settings_section('tv4se_bambuser_autoposter', '',  array ( &$this, 'details_text' ), 'bambuser');
            add_settings_field('tv4se_bambuser_field_1', 'User name', array ( &$this, 'field_display'), 'bambuser',
                'tv4se_bambuser_autoposter','username');
            add_settings_field('tv4se_bambuser_field_2', 'Post as user', array ( &$this, 'field_display'), 'bambuser',
                'tv4se_bambuser_autoposter','postuser');
            add_settings_field('tv4se_bambuser_field_3', 'Post in category', array ( &$this, 'field_display'), 'bambuser',
                'tv4se_bambuser_autoposter','category');
            add_settings_field('tv4se_bambuser_field_4', 'Maximum posts to publish', array ( &$this, 'field_display'),
                'bambuser', 'tv4se_bambuser_autoposter','maxposts');
            add_settings_field('tv4se_bambuser_field_5', 'Update interval', array ( &$this, 'field_display'), 'bambuser',
                'tv4se_bambuser_autoposter','interval');

        }



        function field_display($field){
            switch ($field) {
                case "username":
                    echo "<input id='tv4se_bambuser_field' name='tv4se_bambuser_options[username]' size='20' type='text'";
                    echo "value='{$this->o['username']}' />";
                    break;
                case "postuser":
                    $blogusers = get_users_of_blog();
                    echo '<select name="tv4se_bambuser_options[postuser]">';
                    foreach ($blogusers as $usr) {
                        echo "<option value=\"{$usr->ID}\"";
                        if($this->o['postuser']==$usr->ID) {
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
                        if($this->o['category']==$category->term_id) {
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
                    echo "<input id='tv4se_bambuser_field' name='tv4se_bambuser_options[maxposts]' size='5' type='text'";
                    echo " value='{$this->o['maxposts']}' />";
                    break;
                case "interval":
                    echo "<input id='tv4se_bambuser_field' name='tv4se_bambuser_options[interval]' size='5' type='text'";
                    echo " value='{$this->o['interval']}' /> minutes";
                    break;
            }

        }

        function details_text(){
            echo "<p>Bambuser id information and post settings</p>";
        }

        function options_validate($input){
            preg_match("/[A-Za-z0-9\-_\.\ ]*/", $input['username'], $matches);
            $newinput['username'] = $matches[0];
            $newinput['postuser'] = intval($input['postuser']);
            $newinput['category'] = intval($input['category']);
            $newinput['maxposts'] = intval($input['maxposts']);
            $newinput['interval'] = abs(intval($input['interval']));
            return $newinput;
        }

        function get_embed_code($link) {
        	return '<div><object id="bplayer" classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" width="176"'
        	+ 'height="180"><embed name="bplayer" src="'.$link.'" type="application/x-shockwave-flash" width="176"'
        	+ 'height="180" allowfullscreen="true" allowscriptaccess="always" wmode="opaque"></embed><param name="movie"'
        	+ 'value="'.$link.'"></param><param name="allowfullscreen" value="true"></param><param '
        	+ 'name="allowscriptaccess" value="always"></param><param name="wmode" value="opaque"></param></object>'
        	+ '</div>';
        }

        function fetch_and_insert(){
            $last_save = intval(get_option('tv4se_bambuser_lastpub'));
            $username = $this->o['username'];
            $feed = fetch_feed("http://feed.bambuser.com/channel/$username.rss");
            if($feed && $feed->get_items()) {
                $maxitems = $this->o['maxposts'];
                $items = array_slice($feed->get_items(), 0, $maxitems);
                foreach ( $items as $item ) :
                    if(intval($item->get_date('U')) > $last_save) {
                        $my_post = array(
                            'post_title' => $item->get_title(),
                            'post_content' => $this->get_embed_code($item->get_enclosure()->get_link()),
                            'post_date' => date('Y-m-d H:i:s',$item->get_date('Y-m-d H:i:s'+get_option( 'gmt_offset' ) * 3600)),
                            'post_status' => 'publish',
                            'post_author' => $this->o['postuser'],
                            'post_category' => array($this->o['category'])
                        );
                        update_option('tv4se_bambuser_lastpub', $item->get_date('U'));
                        $post_id = wp_insert_post( $my_post );

                    }

                endforeach;
            }
        }


    }

    $bambuser_autoposter = new BambuserAutoposter();

}
?>