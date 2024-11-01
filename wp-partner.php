<?php
/*
Plugin Name: WP-Partner
Plugin URI: http://www.angelofagony.de.vu
Description: Enhanced Partner linking
Version: 1.2.1
Author: David Brendel
Author URI: http://www.angelofagony.de.vu
*/

/*  Copyright 2008  David Brendel http://www.angelofagony.de.vu

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    For a copy of the GNU General Public License, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

global $wpdb;
define(partnerLinkTABLE,$wpdb->prefix.'wppartner_links');

class WP_Partner {
    const partnerVersion = '1.2.1';
    const PARTNER_PLUGIN_ID = "wp-partner-plugin";
    private $options = array();

    function WP_Partner() {
        add_action('admin_menu',array(&$this, 'installMenue'));
        add_action('wp_head', array(&$this,'header_scripts'));
        add_action('admin_init', array(&$this,'wp_partner_admin_init'));
        add_action('activate_'.dirname(plugin_basename(__FILE__)).'/wp-partner.php', array(&$this,'install'));
        add_action('wp_dashboard_setup',array(&$this,'dashboard_setup'));
        add_shortcode('wp-partner', array(&$this,'shortcode'));
        add_shortcode('wp-partner_rform', array(&$this,'shortcode_form'));
        //register the callback been used if options of page been submitted and needs to be processed
        add_action('admin_post_save_wp_partner_general', array(&$this, 'on_save_changes'));
        //add filter for WordPress 2.8 changed backend box system !
	add_filter('screen_layout_columns', array(&$this, 'on_screen_layout_columns'), 10, 2);

        $this->init();
        $this->progressRecievedData();
    }

    function wp_partner_admin_init() {
        /* Register our stylesheet. */
        wp_register_style('wp_partner', WP_PLUGIN_URL . '/wp-partner/stylesheet.css');
    }

    //for WordPress 2.8 we have to tell, that we support 2 columns !
    function on_screen_layout_columns($columns, $screen) {
		if ($screen == $this->pagehook) {
			$columns[$this->pagehook] = 2;
		}
		return $columns;
	}

    public function init() {
        $this->options = get_option(PARTNER_PLUGIN_ID);
        if(!is_array($this->options)) {
            $this->options = array();
            $this->options['show_names'] = true;
        }
    }

    public function progressRecievedData() {

        $this->deleteLink();
        $this->activateLink();
    }

    //executed if the post arrives initiated by pressing the submit button of form
    function on_save_changes() {
        //user permission check
        if ( !current_user_can('manage_options') )
            wp_die( __('Cheatin&#8217; uh?') );
        //cross check the given referer
        check_admin_referer('wp-partner-general');

        //process here your on $_POST validation and / or option saving
        if(isset($_POST['action'])) {
            if($_POST['action'] == 'save_wp_partner_general') {
                $this->saveCSS();
                $this->saveOptions();
                update_option(PARTNER_PLUGIN_ID,$this->options);
            }
        }

        //lets redirect the post request into get request (you may add additional params at the url, if you need to show save results
        wp_redirect(add_query_arg('success','true',$_POST['_wp_http_referer']));
    }

    private function saveCSS() {
        $this->options['bordercolor'] = $_POST['bordercolor'];
        $this->options['font-color'] = $_POST['font-color'];
    }

    private function saveOptions() {
        if(isset($_POST['show_cats'])) {
            $this->options['show_cats'] = true;
        } else {
            $this->options['show_cats'] = false;
        }
        $this->options['cat_ids'] = $_POST['cat-ids'];
        if(isset($_POST['show_images'])) {
            $this->options['show_images'] = true;
        } else {
            $this->options['show_images'] = false;
        }
        $this->options['order'] = $_POST['order'];
        if(isset($_POST['show_names'])) {
            $this->options['show_names'] = true;
        } else {
            $this->options['show_names'] = false;
        }
    }

    private function deleteLink() {
        if(isset($_GET['action']) && isset($_GET['id']) && $_GET['action'] == 'delete') {
            global $wpdb;

            $sql = "DELETE FROM ".partnerLinkTABLE." WHERE id=".$_GET['id'];
            $wpdb->query($sql);
        }
    }

    private function activateLink() {
        if(isset($_GET['action']) && isset($_GET['id']) && 'confirm' == $_GET['action']) {
            global $wpdb;
            global $current_user;
            require_once(ABSPATH . WPINC . '/pluggable.php');
            require_once(ABSPATH . 'wp-admin/includes/bookmark.php');
            get_currentuserinfo();
            $id = $_GET['id'];

            $sql = "SELECT * FROM ".partnerLinkTABLE." WHERE id=$id";
            $row = $wpdb->get_results($sql);

            $link[ 'link_url' ] 		= $row[0]->url;
            $link[ 'link_name' ]		= $row[0]->name;
            $link[ 'link_description' ]         = $row[0]->description;
            $link[ 'link_owner' ] 		= $current_user->ID;
            $link[ 'link_image']                = $row[0]->image;
            $link[ 'link_target']               = '_blank';

            wp_insert_link( $link );

            $sql = "DELETE FROM ".partnerLinkTABLE." WHERE id=$id";
            $wpdb->query($sql);
            //wp_die(print_r($link));
        }
    }

    function deinstall() {
        delete_option(PARTNER_PLUGIN_ID);
    }

    function install() {
        //Standart Settings in den Options speichern
        if(!is_array($this->options)) {
            $this->options = array();
        }
        $this->options['version'] = self::partnerVersion;
        $this->options['show_cats'] = false;
        $this->options['cat_ids'] = "";
        $this->options['show_images'] = true;
        $this->options['order'] = "name";
        $this->options['show_names'] = true;
        $this->options['bordercolor'] = '#FFFFFF';
        $this->options['bordercolor_form'] = '#000000';

        update_option(PARTNER_PLUGIN_ID,$this->options);

        $this->createDBTables();
    }

    function dashboard_setup() {
        wp_add_dashboard_widget('wppartner_dash_widget','WP-Partner',array(&$this,'dashboard_widget'));
    }

    function dashboard_widget() {
        global $wpdb;
        $partnerLinkTABLE = $wpdb->prefix.'wppartner_links';

        $sql = 'SELECT COUNT(*) FROM '.$partnerLinkTABLE;
        $count = $wpdb->get_var($sql);

        if($count == 0) {
            echo 'There are no new requests!';
        } else {
            echo 'There are <span style="color:red;">'.$count.'</span> new Partner-Requests to moderate!';
        }
    }

    function createDBTables() {
        global $wpdb;

        $partnerLinkTABLE = $wpdb->prefix.'wppartner_links';

        $sql = "
		CREATE TABLE IF NOT EXISTS ".$partnerLinkTABLE." (
                        id bigint(20) NOT NULL auto_increment,
			name varchar(50) NOT NULL,
			description text NOT NULL,
                        url varchar(100) NOT NULL,
                        image varchar(100) NOT NULL,
                        PRIMARY KEY (id)
                );";

        $ergebnis = $wpdb->query($sql);
    }

    function installMenue() {
        if(function_exists('add_menu_page')) {
            $this->pagehook = add_menu_page('WP-Partner','WP-Partner',10,__FILE__, array(&$this,'outputHTML'),'');
            //add_action( 'admin_head-'. $this->pagehook, array(&$this,'wp_partner_admin_header') );
        }
        if(function_exists('add_submenu_page')) {
            $page = add_submenu_page(__FILE__,'Partner Plugin Settings','Settings',10,'wp-partner/wp-partner.php');
            add_submenu_page(__FILE__,'Partner Requests','Requests',10,'partner_requests',array(&$this,'showRequests'));
            add_action('admin_print_styles-' . $page, array(&$this,'wp_partner_admin_header'));
        }
    }

    function wp_partner_admin_header() {
        wp_enqueue_style('wp_partner');
    }

    function shortcode() {
        //Load Options
        $this->options = get_option(PARTNER_PLUGIN_ID);

        //Should Categorynames been displayed
        if(!$this->options['show_cats']) {
            $this->list_links($this->options['cat_ids'],$this->options);
        }
        else {
            $catnames = get_categories('type=link&include='.$this->options['cat_ids']);
            foreach($catnames as $cat) {
                echo "<h2 id=\"partner-cat-header\">". $cat->cat_name ."</h2><br>";
                $this->list_links($cat->cat_ID,$this->options);
            }
        }
    }

    function shortcode_form() {
        $id = $_GET['page_id'];
        $file = get_bloginfo('url') . '/' . PLUGINDIR . '/wp-partner/save-request.php';
        echo '<p>If you like to add your site click <a href="#" onclick="partner_show_form(\''.$file.'\');">here</a>.</p>';
        echo <<< EOT
        <div id="partner_request_form_container">
            <div id="message" style="display: none;"></div>
            <div id="waiting" style="display: none;">
                Please wait<br />
                <img src="ajax-loader.gif" title="Loader" alt="Loader" />
            </div>
            <form action="" id="partner-requestForm" method="post">
                <fieldset>
                    <legend>Partner Request Form</legend>
                    <table border="0" cellpadding="0" cellspacing="0">
                    <tr>
                        <td>Name:</td>
                        <td align="left"><input type="text" name="name" id="name" /></td>
                    </tr>
                    <tr>
                        <td>Image-Link:</td>
                        <td align="left"><input type="text" name="image_link" id="image_link" /></td>
                    </tr>
                    <tr>
                        <td>URL:</td>
                        <td align="left"><input type="text" name="url" id="url" value="http://" /></td>
                    </tr>
                    <tr>
                        <td>Description:</td>
                        <td align="left"><textarea name="description" id="description"></textarea></td>
                    </tr>
                    <tr>
                        <td colspan="2"><input type="button" name="submit" id="submit" align="center" value="Submit" /></td>
                    </tr>
                </table>
                </fieldset>
            </form>
        </div>
EOT;
        echo '<script type="text/javascript" src="'. get_bloginfo('url') . '/' . PLUGINDIR . '/wp-partner/wp-partner.js"></script>';
    }

    private function list_links($cat_id,$options) {
        $partner_links = get_bookmarks('orderby='.$options['order'].'&category='.$cat_id);

        foreach($partner_links as $row) {
            echo "<div class=\"partner-plugin-container\">\n";
            //Should Link-Names been displayed?
            if($options['show_names']) {
                echo "<p class=\"partner-name\">". $row->link_name ."</p>\n";
                echo "<hr class=\"partner-split\">\n";
            }
            //Should Link-Images been displayed?
            if($options['show_images']) {
                echo "<p class=\"partner-image\"><img src=\"". $row->link_image ."\" /></p>\n";
            }
            echo "<p class=\"partner-description\">". $row->link_description ."</p>\n";
            echo "<hr class=\"partner-split\">\n";
            echo "<p><a id=\"partner-link-container\" href=\"". $row->link_url ."\" target=\"".$row->link_target."\">Seite besuchen</a></p>\n";
            echo "</div>\n<br />\n";
        }
    }

    function header_scripts() {
        $this->options = get_option(PARTNER_PLUGIN_ID);
        echo '<link rel="stylesheet" type="text/css" id="WP-Partner-Plugin" media="screen" href="'. get_bloginfo('url') . '/' . PLUGINDIR . '/wp-partner/stylesheet.css" />';
        echo '<style type="text/css">';
        echo '.partner-plugin-container {';
        echo 'border: 1px solid '.$this->options['bordercolor'].';';
        echo '}';
        echo '.partner-split {';
        echo 'border-top: 1px solid '.$this->options['bordercolor'].';';
        echo '}';
        echo '#partner-requestForm legend {';
        echo 'border: 1px solid '.$this->options['bordercolor_form'].';';
        echo 'border-bottom: none;';
        echo '}';
        echo '#partner-requestForm fieldset {';
        echo 'border: 1px solid '.$this->options['bordercolor_form'].';';
        echo '}';
        echo '</style>';
    }

    function outputHTML() {
        //ensure, that the needed javascripts been loaded to allow drag/drop, expand/collapse and hide/show of boxes
	wp_enqueue_script('common');
	wp_enqueue_script('wp-lists');
	wp_enqueue_script('postbox');

        add_meta_box('wp-partner-contentbox-1', 'Settings', array(&$this, 'on_contentbox_1_content'), $this->pagehook, 'normal', 'core');
        add_meta_box('wp-partner-contentbox-2', 'CSS', array(&$this, 'on_contentbox_2_content'), $this->pagehook, 'normal', 'core');

        $success = false;
        if($_GET['success']) {
            $success = true;
        }

        global $screen_layout_columns;
        ?>
<div class="wrap">
            <?php screen_icon('options-general'); ?>
    <h2>Partner Plugin</h2>
    <span style="font-size:8px">Version: <?php echo self::partnerVersion ?></span>
    <p><i>Thanks for using this Plugin!</i></p>
    <div id="message2"></div>
    <form action="admin-post.php" method="post">
                <?php wp_nonce_field('wp-partner-general'); ?>
                <?php wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', false ); ?>
                <?php wp_nonce_field('meta-box-order', 'meta-box-order-nonce', false ); ?>
        <input type="hidden" name="action" value="save_wp_partner_general" />
        
        <div id="poststuff" class="metabox-holder<?php echo 2 == $screen_layout_columns ? ' has-right-sidebar' : ''; ?>">

            <div id="post-body" class="has-sidebar">
                <div id="post-body-content" class="has-sidebar-content">
                            <?php do_meta_boxes($this->pagehook, 'normal', $data); ?>
                    <p>
                        <input type="submit" value="Save Changes" class="button-primary" name="Submit"/>
                    </p>
                </div>
            </div>
            <br class="clear"/>

        </div>
    </form>
    <script type="text/javascript">
        //<![CDATA[
        jQuery(document).ready( function($) {
            var success = '<?php echo $success; ?>';
            //alert(success);
            if(success) {
                $('#message2').removeClass().addClass('success').text('Options saving was successfull.').show(500);
            }
            // close postboxes that should be closed
            $('.if-js-closed').removeClass('if-js-closed').addClass('closed');
            // postboxes setup
            postboxes.add_postbox_toggles('<?php echo $this->pagehook; ?>');

        });
        //]]>
    </script>
</div>
        <?php
    }

    function on_contentbox_1_content($data) {
        ?>
<table class="widefat">
    <tr>
        <td><b>Show Category Names</b></td>
        <td>
            <input type="checkbox" name="show_cats" <?php if($this->options['show_cats']) {
            echo "checked";
        } ?> />
            <label id="partner-small-font">Sollen die Namen der Kategorien angezeigt werden</label>
        </td>
    </tr>
    <tr>
        <td><b>Category ID's</b></td>
        <td><input type="text" name="cat-ids" value="<?php echo $this->options['cat_ids'] ?>"/><br />
            <label id="partner-small-font">Categorys - Coma seperated</label></td>
    </tr>
    <tr>
        <td><b>Show Link Images</b></td>
        <td>
            <input type="checkbox" name="show_images" <?php if($this->options['show_images']) {
            echo "checked";
        } ?> />
            <label id="partner-small-font">Should Link Images been displayed</label>
        </td>
    </tr>
    <tr>
        <td><b>Show Link Names</b></td>
        <td>
            <input type="checkbox" name="show_names" <?php if($this->options['show_names']) {
            echo "checked";
        } ?> />
            <label id="partner-small-font">Should Linknames been displayed</label>
        </td>
    </tr>
    <tr>
        <td><b>Results Order</b></td>
        <td>
            <select name="order" id="order" style="width:350px;">
                <option value="name"<?php if ($this->options['order'] == 'name') {
            echo ' selected="selected"';
        } ?>>Order by Name</option>
                <option value="id"<?php if ($this->options['order'] == 'id') {
            echo ' selected="selected"';
        } ?>>Order by ID</option>
            </select>
            <br /><label id="partner-small-font">Sortorder</label>
        </td>
    </tr>
</table>
        <?php
    }

    function on_contentbox_2_content($data) {
        ?>
<table class="widefat">
    <tr>
        <td><b>BorderColor Partnerbox:</b></td>
        <td>
            <script type="text/javascript" src="<?php echo get_bloginfo('url') . '/' . PLUGINDIR . '/wp-partner'; ?>/jscolor/jscolor.js"></script>
            <input class="color {hash:true}" value="<?php echo $this->options['bordercolor']; ?>" name="bordercolor" size="8" />
        </td>
    </tr>
    <tr>
        <td><b>BorderColor Request Form:</b></td>
        <td>
            <script type="text/javascript" src="<?php echo get_bloginfo('url') . '/' . PLUGINDIR . '/wp-partner'; ?>/jscolor/jscolor.js"></script>
            <input class="color {hash:true}" value="<?php echo $this->options['bordercolor_form']; ?>" name="bordercolor_form" size="8" />
        </td>
    </tr>
</table>
        <?php
    }

    function showRequests() {
        ?>
<div class="wrap">
    <h3>Settings</h3>
    <form method="post" name="partner-requests">
        <table border="0" cellpadding="0" class="widefat">
            <thead>
            <th>Name</th>
            <th>Description</th>
            <th>URL</th>
            <th>Image-Link</th>
            <th>&nbsp;</th>
            </thead>
        <?php $this->getRequests(); ?>
        </table>
    </form>
</div>
        <?php
    }

    function getRequests() {
        global $wpdb;
        $partnerLinkTABLE = $wpdb->prefix.'wppartner_links';

        $sql = "SELECT COUNT(*) FROM ".$partnerLinkTABLE;
        $num_rows = $wpdb->get_var($sql);

        // Are there any entries?
        if($num_rows == 0) {
            echo "<tr><td></td><td></td><td style='text-align:center;'><b>No entries found.</b></td><td></td><td></td></tr>";
        } else {

            $sql = 'SELECT * FROM '.$partnerLinkTABLE;
            $rows = $wpdb->get_results($sql);

            foreach ($rows as $row) {
                $id = $row->id;
                $name = htmlentities(stripslashes($row->name));
                $description = htmlentities(stripslashes($row->description));
                $url = htmlentities(stripslashes($row->url));
                $image_link = htmlentities(stripslashes($row->image));
                echo <<< EOT
            <tr>
                <td>$name</td>
                <td>$description</td>
                <td>$url</td>
                <td>$image_link</td>
                <td>
                    <a href="../wp-admin/admin.php?page=partner_requests&action=confirm&id=$id">Confirm</a>
                     |
                    <a href="../wp-admin/admin.php?page=partner_requests&action=delete&id=$id" onclick="return window.confirm('Really delete link?');"><span style="color:red;">L&ouml;schen</span></a>
                </td>
            </tr>
EOT;
            }
        }
    }
}

$WPPartner = new WP_Partner();
?>