<?php 
/* 
 Plugin Name: Better Extended Live Archives
 Plugin URI: http://extended-live-archive.googlecode.com/
 Description: The famous ELA for WP 2.7+. It's work for WP 3.0.
 Version: 0.80beta3
 Author: Charles
 Author URI: http://sexywp.com
 */

$ela_js_version = "0.50";
if ( !defined('WP_CONTENT_URL') )
	define( 'WP_CONTENT_URL', get_option('siteurl') . '/wp-content');
if ( !defined('WP_CONTENT_DIR') )
	define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );

if ( !defined('WP_PLUGIN_URL') )
	define( 'WP_PLUGIN_URL', WP_CONTENT_URL . '/plugins' );
if ( !defined('WP_PLUGIN_DIR') )
	define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );

//the directory name of this plugin
$ela_plugin_pathname = plugin_basename(__FILE__);
$ela_plugin_basename = plugin_basename(dirname(__FILE__));

//the path name of cache directory
$ela_cache_root = WP_PLUGIN_DIR . '/' . $ela_plugin_basename . '/cache/';

//the debug flag, if true, will create a log file
$debug = false;
$utw_is_present = true;


require_once(dirname(__FILE__)."/af-extended-live-archive-include.php");

/***************************************
 * Main template function.
 **************************************/	 
function af_ela_super_archive($arguments = '') {
	global $wpdb, $ela_cache_root,$ela_plugin_basename;
	
	$settings = get_option('af_ela_options');
	$is_initialized = get_option('af_ela_is_initialized');
	if (!$settings || !$is_initialized || strstr($settings['installed_version'], $is_initialized) === false ) {
		echo '<div id="af-ela"><p class="alert">Plugin is not initialized. Admin or blog owner, <a href="' . get_settings('siteurl') . '/wp-admin/options-general.php?page=af-extended-live-archive/af-extended-live-archive-options.php">visit the ELA option panel</a> in your admin section.</p></div>';
		return false;
	}
	
	$settings['loading_content'] = urldecode($settings['loading_content']);
	$settings['idle_content'] = urldecode($settings['idle_content']);
	$settings['selected_text'] = urldecode($settings['selected_text']);
	$settings['truncate_title_text'] = urldecode($settings['truncate_title_text']);
	$settings['paged_post_next'] = urldecode($settings['paged_post_next']);
	$settings['paged_post_prev'] = urldecode($settings['paged_post_prev']);
	
	$options = get_option('af_ela_super_archive');
	
	if( $options === false ) {
		// create and store default options
		$options = array();
		$options['num_posts'] = 0;
		$options['last_post_id'] = 0;
	}
	
	$num_posts = $wpdb->get_var("
		SELECT COUNT(ID) 
		FROM $wpdb->posts 
		WHERE post_status = 'publish'");
		
	$last_post_id = $wpdb->get_var("
		SELECT ID 
		FROM $wpdb->posts 
		WHERE post_status = 'publish' 
		ORDER BY post_date DESC LIMIT 1");
	
		
	if( !is_dir($ela_cache_root) || !is_file($ela_cache_root.'years.dat')
     || $num_posts != $options['num_posts'] || $last_post_id != $options['last_post_id'] ) {
		$options['num_posts'] = $num_posts;
		$options['last_post_id'] = $last_post_id;
		update_option('af_ela_super_archive', $options);

		
		$res = af_ela_create_cache($settings);
		
		if( $res === false ) {
			// we could not create the cache, bail with error message
			echo '<div id="'.$settings['id'].'"><p class="'.$settings['error_class'].'">Could not create cache. Make sure the wp-content folder is writable by the web server. If you have doubts, set the permission on wp-content to 0777</p></div>';
			return false;
		}
	
	}
	
	$year = date('Y');
	$plugin_path = WP_PLUGIN_URL . '/' . $ela_plugin_basename;
	global $ela_js_version;
    $process_uri = $plugin_path . '/includes/af-ela.php';
    if (!settings){
        echo '<script type="text/javascript">';
        echo "document.write('<div id=\"af-ela\"><p class=\"alert\">Plugin is not initialized. Admin or blog owner, visit the ELA option panel in your admin section.</p></div>')";
        echo '</script>';
    }else{
	$text .= <<<TEXT
<script type="text/javascript">
var af_elaProcessURI = '${process_uri}';
var af_elaResultID = '${settings['id']}';
var af_elaLoadingContent = '${settings['loading_content']}';
var af_elaIdleContent = '${settings['idle_content']}';
var af_elaPageOffset = '${settings['paged_post_num']}';
</script>
<script src="$plugin_path/includes/af-extended-live-archive.js.php?v=${ela_js_version}" type="text/javascript"></script>
<div id="${settings['id']}"></div>

TEXT;

	echo $text;
    }
}

/***************************************
 * loading stuff in the header.
 **************************************/	
function af_ela_header() {
    global $ela_plugin_basename;
	// loading stuff
	$settings = get_option('af_ela_options');
	$plugin_path = WP_PLUGIN_URL . '/'. $ela_plugin_basename;
	if ($settings['use_default_style']) {
		if (file_exists(ABSPATH . 'wp-content/themes/' . get_template() . '/ela.css')) {
			$csspath = get_bloginfo('template_url')."/ela.css";
		} else {
			$csspath =$plugin_path."/includes/af-ela-style.css";
		}
	
		$text = <<<TEXT

	<link rel="stylesheet" href="$csspath" type="text/css" media="screen" />

TEXT;
	} else { 
		$text ='';
	}

	echo $text;
}


/***************************************
 * actions when a comment changes.	
 **************************************/ 
function af_ela_comment_change($id) {
	global $wpdb;
	$generator = new Better_ELA_Cache_Builder();
	
	$settings = get_option('af_ela_options');
	
	if ($id) {
        $generator->find_exclude_posts(array('excluded_categories' => $settings['excluded_categories'], 'show_page' => false));
        $generator->buildPostToGenerateTable($settings['excluded_categories'], $id, true);
        if (empty($generator->postToGenerate)) return $id;
    }
	
	$generator->build_posts_in_months_table();
		
	$generator->buildPostsInCatsTable($settings['excluded_categories'],$settings['hide_pingbacks_and_trackbacks'], $generator->postToGenerate['post_id'] );

	return $id;
}

/***************************************
 * actions when a post changes.
 **************************************/	
function af_ela_post_change($id) {
	global $wpdb;
    logthis('ID:'.$id, __FUNCTION__, __LINE__, __FILE__);
    $generator = new Better_ELA_Cache_Builder();
	
	$settings = get_option('af_ela_options');
	
	if ($id) {
        $generator->find_exclude_posts(array('excluded_categories' => $settings['excluded_categories'], 'show_page' => false));
		$generator->buildPostToGenerateTable($settings['excluded_categories'], $id);
        if (empty($generator->postToGenerate)) return $id;
	}
					
	if(!$settings['tag_soup_cut'] || empty($settings['tag_soup_X'])) { 
		$order = false;
		$idTags = $id;
	} else {
		$order = $settings['tag_soup_cut'];
		$orderparam = $settings['tag_soup_X'];
		$idTags = false;
	}
	
	$generator->build_years_table($id);
	
	$generator->build_months_table($id);
	
	$generator->build_posts_in_months_table();
		
	$generator->buildCatsTable($settings['excluded_categories'], $id);
	
	$generator->buildPostsInCatsTable($settings['excluded_categories'], $settings['hide_pingbacks_and_trackbacks']);
	
    $ret = $generator->build_tags_table($idTags, $order, $orderparam);
		
	if($ret) $generator->buildPostsInTagsTable($settings['excluded_categories'], $settings['hide_pingbacks_and_trackbacks']);
	
	return $id;
}
function af_ela_create_cache_dir(){
    return mkdir($ela_cache_root);
}
/***************************************
 * creation of the cache
 **************************************/	
function af_ela_create_cache($settings) {
	global $wpdb, $ela_cache_root;

	if( !is_dir($ela_cache_root) ) {
		if(!af_ela_create_cache_dir()) return false;
	}
	
	$generator = new Better_ELA_Cache_Builder();
	
	if(!$settings['tag_soup_cut'] || empty($settings['tag_soup_X'])) { 
		$order = false;
	} else {
		$order = $settings['tag_soup_cut'];
		$orderparam = $settings['tag_soup_X'];
	}

    $generator->find_exclude_posts(array('excluded_categories' => $settings['excluded_categories'], 'show_page' => false));
	
	$generator->build_years_table();

	$generator->build_months_table();

	$generator->build_posts_in_months_table();

	$generator->buildCatsTable($settings['excluded_categories']);

	$generator->buildPostsInCatsTable($settings['excluded_categories'], $settings['hide_pingbacks_and_trackbacks']);
	
	$ret = $generator->build_tags_table(false, $order, $orderparam);
	
	if($ret) $generator->buildPostsInTagsTable($settings['excluded_categories'], $settings['hide_pingbacks_and_trackbacks']);
	
	return true;
}


/***************************************
 * Force settings from external plugin.
 * TODO  need to do some more checks 
 **************************************/
function af_ela_set_config($config, $reset=false) {
	global $wpdb;

	$settings = get_option('af_ela_options');
	
	foreach($config as $optionKey => $optionValue) {
		switch($optionKey) {
		case 'newest_first':
		case 'num_entries' :
		case 'num_entries_tagged' :
		case 'num_comments':
		case 'fade':
		case 'hide_pingbacks_and_trackbacks':
		case 'use_default_style':
		case 'paged_posts':
		case 'truncate_title_at_space':
		case 'abbreviated_month':
			if($optionValue != 0 && $optionValue != 1) return -1;	
			break;
		case 'tag_soup_cut':
		case 'tag_soup_X':
		case 'truncate_title_length':
		case 'truncate_cat_length' :
		case 'excluded_categories' :
		case 'paged_post_num' :
			//if(!is_numeric($optionValue)) return -2;	
			break;
		case 'menu_order' : 
			$table = split(',',$optionValue);
			foreach($table as $content) {
				if ($content != 'chrono' && $content != 'cats' && $content != 'tags' && !empty($content)) return -3;
			}
			break;
		default :
			break;
		}
	}
	$config['last_modified'] = gmdate("D, d M Y H:i:s",time());
	if (!$reset) $config = array_merge($settings, $config);
	logthis($config);
	update_option('af_ela_options', $config, 'Set of Options for Extended Live Archive');
	
	return true;
}

/***************************************
 * bound admin page.
 **************************************/
include_once('af-extended-live-archive-options.php');
function af_ela_admin_pages() {
	if (function_exists('add_options_page')) add_options_page('Ext. Live Archive Options', 'Ext. Live Archive', 9, 'extended-live-archive','af_ela_admin_page');
}


function af_ela_shorcode(){
    ob_start();
    af_ela_super_archive();
    $ela = ob_get_contents();
    ob_end_clean();
    return $ela;
}

add_action('plugins_loaded', 'better_ela_init');
function better_ela_init(){
    // insert javascript in headers
    add_action('wp_head', 'af_ela_header');
    // make sure the cache is rebuilt when post changes
    add_action('publish_post', 'af_ela_post_change');
    add_action('deleted_post', 'af_ela_post_change');
    // make sure the cache is rebuilt when comments change
    add_action('comment_post', 'af_ela_comment_change');
    add_action('trackback_post', 'af_ela_comment_change');
    add_action('pingback_post', 'af_ela_comment_change');
    add_action('delete_comment', 'af_ela_comment_change');
    add_shortcode('extended-live-archive', 'af_ela_shorcode');
    
    if (is_admin()){
        add_action('admin_head', 'better_ela_js_code_in_admin_page');
        add_action('admin_menu', 'af_ela_admin_pages');
    }
}