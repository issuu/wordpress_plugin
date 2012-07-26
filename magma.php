<?php
  /*
  Plugin Name: Magma
  Plugin URI: https://github.com/MagmaHQ/wordpress_plugin
  Description: Lets you publish your articles to Wordpress directly from Magma
  Author: MagmaHQ
  Version: 1.0.0
  Author URI: http://www.magmahq.com
  */

// API

add_filter( 'template_redirect', 'magma_api_redirect' );
function magma_api_redirect() {
  if ( $_SERVER['REQUEST_METHOD'] == 'POST' && preg_match( '/magma_api\/?$/', $_SERVER['REQUEST_URI']) ) {
    status_header( 200 );
    $user_id = magma_basic_auth_user();
    if ( $user_id != 0 ) {
      magma_create_articles( $user_id );
    }
    exit;
  }
}

function magma_create_articles( $user_id ){
  // Read RAW POST
  $raw_xml = file_get_contents( 'php://input' );
  
  // Read form data
  // $raw_xml = stripcslashes($_POST['articles']);         

  $xml = simplexml_load_string( $raw_xml );

  $articles = $xml->article;
  for ( $i = 1; $i <= count( $articles ); $i++ ) {

    $article = $articles[$i-1];

    $content    = (string)$article->text;
    $title      = magma_define_title( $article, $content );
    $publish_at = trim( (string) $article->start_publishing );

    $content .= magma_format_assets_for_content( $article );

    $my_post = array();
    $my_post['post_date_gmt'] = $publish_at;
    $my_post['post_title']    = $title;
    $my_post['post_content']  = $content;
    $my_post['post_status']   = magma_set_article_status( $article, $publish_at );
    $my_post['post_author']   = $user_id;
    $my_post['post_type']     = 'post';
    
    // Insert the post into the database
    $successful_post = wp_insert_post( $my_post );
    if ( $successful_post != 0 ) {
      echo '<h1>'.$title.'</h1><p>'.$content.'</p>';
    }else{
      status_header( 400 );
      echo 'Error importing post';
    }
  }
}

// We pass the content as a reference as we might modify it.
function magma_define_title( $article, &$content ){
  $title = trim( (string) $article->title );
  if( preg_match( '#<h1.*?>(.*?)</h1>#is', $content, $matches )){
    $title = $matches[1];
    $content = preg_replace( '#<h1.*?>.*?</h1>#is', '' , $content, 1 );
  }
  return $title;
}

function magma_set_article_status( $article, $publish_at ){
  $visibility = trim( (string) $article->visibility );
  $status = 'draft';

  if( $visibility == 'private' ) {
    $status = 'private';
  }else{
    $todays_date = date("Y-m-d H:i:s");
    $publish_date = strtotime( $publish_at );
    if ( $todays_date < $publish_date ) {
      $status = 'future';
    }else{
      $status = 'publish';
    }
  }
  return $status;
}

function magma_format_assets_for_content( $article ){
  $content = '';
  $assets = $article->assets->asset;
  for ( $j = 1; $j <= count( $assets ); $j++ ) {
    $asset = (string) $assets[i-1]['src'];
    if ( magma_is_accepted_asset_format( $asset ) ) {
      $title = preg_replace('/\.[^.]+$/', '', basename( $asset ));
      $content .= '<img src="'.$asset.'" alt="'.$title.'" title="'.$title.'"/>';
    }
  }
  return $content;
}

function magma_is_accepted_asset_format( $asset ){
  $fileinfo = magma_pathinfo( $asset );
  $extensions = array( 'jpg', 'jpeg', 'png', 'gif' );
  return in_array( $fileinfo['extension'], $extensions );
}

function magma_pathinfo( $filepath ) {
  preg_match( '%^(.*?)[\\\\/]*(([^/\\\\]*?)(\.([^\.\\\\/]+?)|))[\\\\/\.]*$%im', $filepath, $m );
  if($m[1]) $ret['dirname']   = $m[1];
  if($m[2]) $ret['basename']  = $m[2];
  if($m[5]) $ret['extension'] = strtolower( preg_replace( '/\?.*/', '', $m[5] ) );
  if($m[3]) $ret['filename']  = $m[3];
  return $ret;
}

function magma_basic_auth_user(){
  $email    = $_SERVER["PHP_AUTH_USER"];
  $password = $_SERVER['PHP_AUTH_PW'];
  
  $creds = array();
  $creds['user_login']    = $email;
  $creds['user_password'] = $password;
  $creds['remember']      = false;
  $user = wp_signon( $creds, false );
  if ( is_wp_error( $user ) ) {
    echo $user->get_error_message();
    status_header( 401 );
  }
  return $user->ID;
}
?>