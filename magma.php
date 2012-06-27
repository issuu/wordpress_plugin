<?php
  /*
  Plugin Name: Magma
  Plugin URI: http://www.magmahq.com
  Description: Access your Magma publications directly from WordPress
  Author: Magma
  Version: 0.1
  Author URI: http://www.magmahq.com
  */

// API

add_filter('template_redirect', 'magma_api_redirect' );
function magma_api_redirect() {
  if($_SERVER['REQUEST_METHOD'] == 'POST' && preg_match("/magma_api/", $_SERVER['REQUEST_URI'])){
    status_header( 200 );
    $user_id = basic_auth_user();
    if($user_id != 0){
      save_articles($user_id);
    }
    exit;
  }
}

function save_articles($user_id){
  // Read RAW POST
  $raw_xml = file_get_contents('php://input');
  
  // Read form data
  // $raw_xml = stripcslashes($_POST['articles']);         

  $xml = simplexml_load_string($raw_xml);

  $articles = $xml->article;
  for($i = 1; $i <= count($articles); $i++){

    $article = $articles[$i-1];

    $title   = trim((string)$article->title);
    $content = (string)$article->text;

    $content .= format_assets_for_content($article);

    $my_post = array();
    $my_post['post_title']    = $title;
    $my_post['post_content']  = $content;
    $my_post['post_status']   = 'draft';
    $my_post['post_author']   = $user_id;
    $my_post['post_type']     = 'post';
    
    // Insert the post into the database
    $successful_post = wp_insert_post($my_post);
    if($successful_post != 0){
      echo '<h1>'.$title.'</h1><p>'.$content.'</p>';
    }else{
      status_header( 400 );
      echo 'Error importing post';
    }
  // return $successful_post;
  }
}

function format_assets_for_content($article){
  $content = '';
  $assets = $article->assets->asset;
  for($j = 1; $j <= count($assets); $j++){
    $asset = (string)$assets[i-1]['src'];
    $fileinfo = mb_pathinfo($asset);
    if($fileinfo['extension'] == 'jpg' || $fileinfo['extension'] == 'jpeg' || $fileinfo['extension'] == 'png' || $fileinfo['extension'] == 'gif'){
      $title = preg_replace('/\.[^.]+$/', '', basename($asset));
      $content .= '<img src="'.$asset.'" alt="'.$title.'" title="'.$title.'"/>';
    }
  }
  return $content;
}

function mb_pathinfo($filepath) {
  preg_match('%^(.*?)[\\\\/]*(([^/\\\\]*?)(\.([^\.\\\\/]+?)|))[\\\\/\.]*$%im',$filepath,$m);
  if($m[1]) $ret['dirname']   = $m[1];
  if($m[2]) $ret['basename']  = $m[2];
  if($m[5]) $ret['extension'] = strtolower(preg_replace('/\?.*/', '', $m[5]));
  if($m[3]) $ret['filename']  = $m[3];
  return $ret;
}

function basic_auth_user(){
  $email    = $_SERVER["PHP_AUTH_USER"];
  $password = $_SERVER['PHP_AUTH_PW'];
  
  $creds = array();
  $creds['user_login']    = $email;
  $creds['user_password'] = $password;
  $creds['remember']      = false;
  $user = wp_signon( $creds, false );
  if (is_wp_error($user))
    status_header( 401 );
    echo $user->get_error_message();
  return $user->ID;
}

?>