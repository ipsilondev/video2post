<?php
/**
 * Video2Post
 *
 * @package     Video2Post
 * @author      Ipsilon Developments Inc.
 * @copyright   2020 Ipsilon Developments Inc.
 * @license     MIT License
 *
 * @wordpress-plugin
 * Plugin Name: Video2Post
 * Plugin URI:  https://video2post.com/wordpress
 * Description: Import Video2Post project into a wordpress post
 * Version:     1.0.0
 * Author:      Ipsilon Developments Inc.
 * Author URI:  https://ipsilondev.com
 * Text Domain: video2post
 * License:     GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

function cleanupFiles () {
  $filesInDir = array_diff(scandir(WP_PLUGIN_DIR . "/video2post/files/"), array('.', '..'));
          foreach($filesInDir as $val) {
                unlink(WP_PLUGIN_DIR . "/video2post/files/" . $val);
                }
}
function display_admin_page() {
	global $_GET, $_POST;
	if($_POST[process] == 1 && $_SESSION[v2p_status] == 0 && $_POST[url] != '') {
		if (substr($_POST[url], 0, strlen('https://video2post.com')) == 'https://video2post.com' || substr($_POST[url], 0, strlen('https://www.video2post.com')) == 'https://www.video2post.com') {
		$_SESSION[v2p_status] = 1;
		$_SESSION[v2p_url] = $_POST[url];
                cleanupFiles();
		} else {
		$_SESSION[v2p_error] = 1;	
		}
	}
	if ($_GET['cancel'] == 1) {
	  $_SESSION[v2p_status] = 0;
          $_SESSION[v2p_url] =  '';
          $_SESSION[v2p_error] = 0;
	  cleanupFiles();
		echo "<script>window.location.href = '/wp-admin/admin.php?page=video2post&time=".time()."';</script>";
		exit();
	}
	if($_SESSION[v2p_status] > 0) {
		echo "<a href='/wp-admin/admin.php?page=video2post&time=".time()."&cancel=1'>Cancel</a><br> ";
		echo "<h3>IMPORTING PROJECT. DO NOT CLOSE THE BROWSER.</h3>";
		echo "<h3>Downloading file</h3>";
		if($_SESSION[v2p_status] == 1) {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $_SESSION[v2p_url]);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			$data = curl_exec ($ch);
			$error = curl_error($ch); 
			curl_close ($ch);
			if($error !='' || strrpos($data, 'failed request') != false) {
			$_SESSION[v2p_error] = 1;
                        $_SESSION[v2p_status] = 0; 
			} else {			
			$destination = WP_PLUGIN_DIR . "/video2post/files/zip.zip";
			$file = fopen($destination, "w+");			
			fwrite($file, $data);
			fclose($file);
			$_SESSION[v2p_status] = 2;
			}
			echo "<script>setTimeout(() => { window.location.href = '/wp-admin/admin.php?page=video2post&time=".time()."'; }, 3000);</script>";
			exit();
		}
	if($_SESSION[v2p_status] > 1) {
		echo "<h3>Extracting file</h3>";		
		if ($_SESSION[v2p_status] == 2) {
		$zip = new ZipArchive;
                $resultZip = $zip->open(WP_PLUGIN_DIR . '/video2post/files/zip.zip');
		if ($resultZip === TRUE) {
			$zip->extractTo(WP_PLUGIN_DIR . '/video2post/files/');
			$zip->close();
			$_SESSION[v2p_status] = 3;
		} else {
			print_r($resultZip);	
			$_SESSION[v2p_error] = 2;
			$_SESSION[v2p_status] = 0; 
		}			
			echo "<script>setTimeout(() => { window.location.href = '/wp-admin/admin.php?page=video2post&time=".time()."'; }, 3000);</script>";
			exit();
		}
	}
	if($_SESSION[v2p_status] > 2) {
		echo "<h3>Importing data</h3>";		
		$destination = WP_PLUGIN_DIR . "/video2post/files/index.html";
		$file = fopen($destination, "r");
		$doc = fread($file, filesize($destination));
		$filesInDir = array_diff(scandir(WP_PLUGIN_DIR . "/video2post/files/"), array('.', '..'));
		$uploadDir = wp_upload_dir(date('Y/m'));		
		if($uploadDir['error'] == false || $uploadDir['error'] == "") {
			foreach($filesInDir as $val) {
			if(strrpos($val, '.zip') == false && strrpos($val, '.html') == false){
			rename(WP_PLUGIN_DIR . "/video2post/files/" . $val , $uploadDir['path'] . '/' . $val);				
			$doc = str_replace($val, $uploadDir['url'] . '/' . $val, $doc);
				}
			}
		$arrayPost = array();
		$arrayPost['post_content'] = $doc;
                $re = '/\<title>(.*?)<\/title>/m';
                preg_match_all($re, $doc, $matches, PREG_SET_ORDER, 0);
		$arrayPost['post_title'] = $matches[0][1];
		$postid = wp_insert_post($arrayPost);
		if ($postid == 0) {
		$_SESSION[v2p_error] = 3;
		$_SESSION[v2p_status] = 0; 			
		} else {
		$_SESSION[v2p_status] = 0; 
                echo "<script>window.location.href = '/wp-admin/post.php?post=".$postid."&action=edit&time=".time()."';</script>";
	        exit();              
		}
		}
	}
	} else {
		if($_SESSION[v2p_error] == 1) {
			echo "<h3 style='padding:10px;border: 1px solid red;color:red;'>The URL to import is not valid</h3>";
		} else if($_SESSION[v2p_error] == 2) {
			echo "<h3 style='padding:10px;border: 1px solid red;color:red;'>We couldn't extract the zip file, please check your hosting file permissions or your network connection.</h3>";		
		} else if($_SESSION[v2p_error] == 3) {
			echo "<h3 style='padding:10px;border: 1px solid red;color:red;'>Couldn't insert the HTML document as a post.</h3>";						
		}
		$_SESSION[v2p_error] = 0;
		echo '
		<h1>Import project from Video2Post.com</h1>
		<form method="post">
		Project URL: <input type="text" name="url" /> <br>
		<input type="hidden" name="process" value="1" />
		<input type="submit" name="submit" value="Submit" />		
		</form>
		';
		
	}
}
function video2post_admin_menu() {
  add_menu_page(
        'Video2Post',// page title
        'Video2Post',// menu title
        'manage_options',// capability
        'video2post',// menu slug
        'display_admin_page' // callback function
    );
}
function register_session () {
	if (!session_id()) {
        session_start();
	}
	if ($_SESSION[v2p_status] == '') {
		$_SESSION[v2p_status] = 0;
	}
}
add_action('admin_menu', 'video2post_admin_menu');
add_action('init', 'register_session', 1);
