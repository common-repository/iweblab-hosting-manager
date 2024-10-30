<?php
namespace NMOD\IweblabHostingManager;

if ( ! defined( 'ABSPATH' ) ) exit;




add_action( 'IHM_UpdateSpiderList', __NAMESPACE__ . '\updateSpiderList' );




function getSpidersList() {
  static $spiders;

  if ( is_null( $spiders ) ) {    
    /*
    $spiders = array(
      'bot_ref' => array( 'name' => 'Name', 'force' => true ),
    );
    */

    $spiders = array(
      'SemrushBot'      => array( 'name' => 'Semrush'          ),
      'Yandex'          => array( 'name' => 'Yandex'           ),
      'AhrefsBot'       => array( 'name' => 'Ahrefs'           ),
    );

    $spiders = array_merge( $spiders, get_option( 'IHM-SpidersList', array() ) );
  }

  return $spiders;
}


function getSettings() {
  static $settings;

  if ( is_null( $settings ) )
    $settings = sanitizeSettings();

  return $settings;
}


function sanitizeSettings( $settings = null ) {
  if ( is_null( $settings ) ) $settings = array();
  $settings = array_merge( get_option( 'IHM-Settings', array() ), $settings );


  $defaults = array(
    'plugin_version'   => 0,
    'whitelist'        => array(),
    'recaptcha'        => 0,
    'wordpress'        => 0,
  );


  $updated_version = $defaults['plugin_version'];
  if ( isset( $settings['plugin_version'] ) ) {
    $updated_version = $settings['plugin_version'];
  } else {
    $settings['plugin_version'] = $defaults['plugin_version'];
  }

  $testing_version = '0.1.1';
  if ( \version_compare( $updated_version, $testing_version, '<' ) ) {
    if ( isset( $settings['recaptcha'] ) ) {
      if ( is_numeric( $settings['recaptcha'] ) ) {
        if ( 1 == $settings['recaptcha'] ) {
          $settings['recaptcha'] = 100;
        }
      }
    }    

    $updated_version = $testing_version;
  }



  if ( isset( $settings['whitelist'] ) && is_array( $settings['whitelist'] ) ) {
    $clean_list = array();
    $known_spiders = getSpidersList();
    foreach ( $settings['whitelist'] as $spider ) {
      if ( array_key_exists( $spider, $known_spiders ) )
        $clean_list[] = $spider;
    }
    $settings['whitelist'] = array_merge( $defaults['whitelist'], $clean_list );
  } else {
    $settings['whitelist'] = $defaults['whitelist'];
  }


  if ( isset( $settings['recaptcha'] ) ) {
    if ( ! is_numeric( $settings['recaptcha'] ) ) {
      $settings['recaptcha'] = $defaults['recaptcha'];
    }
  } else {
    $settings['recaptcha'] = $defaults['recaptcha'];
  }


  if ( isset( $settings['wordpress'] ) ) {
    if ( ! is_numeric( $settings['wordpress'] ) ) {
      $settings['wordpress'] = $defaults['wordpress'];
    }
  } else {
    $settings['wordpress'] = $defaults['wordpress'];
  }
  

  if ( ! function_exists( 'get_plugin_data' ) )
    require_once( \ABSPATH . 'wp-admin/includes/plugin.php' );
  $plugin_data = get_plugin_data(  plugin_dir_path( __FILE__ ) . 'plugin.php' );

  if ( \version_compare( $settings['plugin_version'], $plugin_data['Version'], '<' ) ) {
    $settings['plugin_version'] = $plugin_data['Version'];
    
    update_option( 'IHM-Settings', $settings );
  }


  return $settings;
}



function updateSpiderList() {
  $csv = wp_safe_remote_get( namespace\IWEBLAB_SPIDER_LIST );
  if ( ! is_wp_error( $csv ) ) {
    if ( 200 == $csv['response']['code'] ) {

      $csv_rows = explode( PHP_EOL, $csv['body'] );
      if ( empty( $csv_rows ) ) return;

      $spiders = array();
      foreach ( $csv_rows as $spider ) {
        if ( 0 === strpos( $spider, '#' ) ) continue;
        if ( '' == trim( $spider ) ) continue;

        $spider_parts = explode( ',', $spider );

        $spider_details = array();
        $spider_details['name'] = $spider_parts[1];
        if ( isset( $spider_parts[2] ) && boolval( $spider_parts[2] ) )
          $spider_details['force'] = true;
        $spiders[ $spider_parts[0] ] = $spider_details;
      }
      
      update_option( 'IHM-SpidersList', $spiders, false );

      updateHtaccessFile();
    } else {
      error_log( sprintf( 'IWEBLAB Hosting Manager is unable to find the supplementary spiders list at %s with error: %s - %s', namespace\IWEBLAB_SPIDER_LIST, $csv['response']['code'], $csv['response']['message'] ) );
      return $csv['response']['code'] . ' - ' . $csv['response']['message'];
    }
  } else {
    error_log( sprintf( 'IWEBLAB Hosting Manager - Error getting the supplementary spiders list at %s with error: %s.', namespace\IWEBLAB_SPIDER_LIST, $csv->get_error_message() ) );
    return $csv->get_error_message();
  }
}


function updateHtaccessFile( $old_values = null, $new_values = null, $option_name = '' ) {
  if ( is_null( $new_values ) )
    $new_values = getSettings();

  require_once ABSPATH . '/wp-admin/includes/misc.php';

  $data = array();

  if ( isset( $new_values['whitelist'] ) && is_array( $new_values['whitelist'] ) ) {
    foreach ( $new_values['whitelist'] as $spider )
      $data[] = sprintf( 'BrowserMatchNoCase "%s" botsenable', $spider );
  }

  if ( isset( $new_values['recaptcha'] ) && 0 != $new_values['recaptcha'] ) {
    $data[] = '<IfModule LiteSpeed>';
    $data[] = sprintf( 'LsRecaptcha %s', $new_values['recaptcha'] );
    $data[] = '</IfModule>';
  }

  if ( isset( $new_values['wordpress'] ) && 1 == $new_values['wordpress'] ) {
    $data[] = '<IfModule LiteSpeed>';
    $data[] = 'WordPressProtect full_captcha';
    $data[] = '</IfModule>';
  }

  // FOR TEST!!
  //$res = insert_with_markers( WP_PLUGIN_DIR . '/iweblab-hosting-manager/.htaccess', 'IWEBLAB Hosting Manager', $data );
  $res = insert_with_markers( ABSPATH . '.htaccess', 'IWEBLAB Hosting Manager', $data );

  if ( ! $res ) {
    error_log( 'IWEBLAB Hosting Manager is unable to write to .htaccess file.' );

    wp_mail(
      get_option( 'admin_email' ),
      sprintf( '%s - Errore da IWEBLAB Hosting Manager', get_option( 'blogname' ) ),
      sprintf( 'Durante l\'esecuzione di un controllo periodico, IWEBLAB Hosting Manager non è riuscito ad aggiornare il file htaccess del sito "%s". Si prega di notificare l\'accaduto al supporto di IWEBLAB, grazie.', get_option( 'blogname' ) ),
      array()
    );
  }
}