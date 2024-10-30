<?php
namespace NMOD\IweblabHostingManager;

if ( ! defined( 'ABSPATH' ) ) exit;


register_activation_hook  ( dirname( __FILE__ ) . '/plugin.php', __NAMESPACE__ . '\addCronJobs'    );
register_deactivation_hook( dirname( __FILE__ ) . '/plugin.php', __NAMESPACE__ . '\removeCronJobs' );
register_deactivation_hook( dirname( __FILE__ ) . '/plugin.php', __NAMESPACE__ . '\clearDatabase'  );

add_action( 'admin_init'                            , __NAMESPACE__ . '\registerSettings'                  );
add_action( 'admin_menu'                            , __NAMESPACE__ . '\setBackendMenu'                    );
add_action( 'admin_enqueue_scripts'                 , __NAMESPACE__ . '\enqueueStylesAndScripts'  , 10,  1 );
add_action( 'update_option_IHM-Settings'            , __NAMESPACE__ . '\updateHtaccessFile'       , 10,  3 );
add_action( 'add_option_IHM-Settings'               , __NAMESPACE__ . '\updateHtaccessFile'       , 10,  3 );

add_action( 'wp_ajax_update_spider_list'            , __NAMESPACE__ . '\forceSpiderListUpdate'             );



function addCronJobs() {
  if ( ! wp_next_scheduled( 'IHM_UpdateSpiderList' ) ) {
    $date = new \DateTime( '23:59:59' );
    wp_schedule_event( strtotime( $date->format( 'Y/m/d H:i:s' ) ), 'daily', 'IHM_UpdateSpiderList' );

    updateSpiderList();
  }
}


function removeCronJobs() {
  $timestamp = wp_next_scheduled( 'IHM_UpdateSpiderList' );
  wp_unschedule_event( $timestamp, 'IHM_UpdateSpiderList' );
}


function clearDatabase() {
  delete_option( 'IHM-Settings'    );
  delete_option( 'IHM-SpidersList' );

  updateHtaccessFile( array(), array() );
}


function registerSettings() {
  $option_settings = array(
    'sanitize_callback'   => __NAMESPACE__ . '\sanitizeSettings',
    'show_in_rest'        => false
  );
  register_setting( 'iweblab-hosting-manager', 'IHM-Settings', $option_settings );

  add_settings_section( 'hc-spiders-list-section', 'WHITELIST degli spider'  , __NAMESPACE__ . '\renderDescriptionForSpidersListSettings', 'iweblab-manager' );
  add_settings_section( 'hc-protection-section'  , 'Moduli di protezione'    , __NAMESPACE__ . '\renderDescriptionForProtectionSettings' , 'iweblab-manager' );

  add_settings_field( 'whitelist'  , 'Spider abilitati'                        , __NAMESPACE__ . '\renderField_SpidersList'   , 'iweblab-manager', 'hc-spiders-list-section' , array( 'ver' => 1, 'field' => 'whitelist'   ) );
  add_settings_field( 'recaptcha'  , 'Abilita protezione reCaptcha nascosto'   , __NAMESPACE__ . '\renderField_reCaptcha'     , 'iweblab-manager', 'hc-protection-section'   , array( 'ver' => 1, 'field' => 'recaptcha'   ) );
  add_settings_field( 'wordpress'  , 'Abilita protezione per WordPress'        , __NAMESPACE__ . '\renderField_WordPress'     , 'iweblab-manager', 'hc-protection-section'   , array( 'ver' => 1, 'field' => 'wordpress'   ) );
}


function renderDescriptionForSpidersListSettings() {
?>
  <p>In questa sezione puoi abilitare degli spider che blocchiamo per default sul nostro hosting.<p>
<?php
}
function renderDescriptionForProtectionSettings() {
?>
  <p>In questa sezione puoi gestire i moduli di protezione che configuriamo sul nostro hosting.<p>
<?php
}
  

function renderField_SpidersList( $args ) {
  $spiders_list = getSpidersList();
  $settings = getSettings();

  printf( '<p>Abilita nella lista in basso gli spider da inserire in whitelist.</p>' );
  printf( '<input type="hidden" name="IHM-Settings[%1$s]" value="0" />', $args['field'] );
  echo '<div class="checkbox-switch">';
  foreach ( $spiders_list as $key => $details ) {
    printf(
      '<div><input type="checkbox" id="HC-%2$s" name="IHM-Settings[%1$s][]" value="%2$s" %4$s/><label for="HC-%2$s"></label>%3$s</div>',
      $args['field'],
      $key,
      $details['name'],
      ( 
        isset( $details['force'] )
        ? 'disabled="disabled" checked="checked"'
        : ( 
            in_array( $key, $settings['whitelist'] )
            ? 'checked="checked"'
            : ''
          )
      )
    );
  }
  echo '<p><a href="#" id="updateList" class="button-secondary">Aggiorna elenco</a></p></div>';
}


function renderField_reCaptcha( $args ) {
  $settings = getSettings();

  printf( '<p>Imposta il livello di protezione reCaptcha per il tuo sito.</p>' );
  printf( '<input type="range" id="HC-%1$s" name="IHM-Settings[%1$s]" min="0" max="100" step="1" value="%2$s" style="max-width:340px;" />', $args['field'], $settings['recaptcha'] );
  printf( '<p>Livello protezione: <span id="%1$s_protection_level">%2$s</span></p>', $args['field'], 0 == $settings['recaptcha'] ? 'Disabilitato' : $settings['recaptcha'] . '%' );
}



function renderField_WordPress( $args ) {
  $settings = getSettings();

  printf( '<p>Abilita la protezione avanzata per il tuo sito WordPress.</p>' );
  printf( '<input type="hidden" name="IHM-Settings[%1$s]" value="0" />', $args['field'] );
  echo '<div class="checkbox-switch">';
  printf(
    '<div><input type="checkbox" id="HC-%1$s" name="IHM-Settings[%1$s]" value="%2$s" %4$s/><label for="HC-%1$s"></label>%3$s</div>',
    $args['field'],
    1,
    'Modulo WordPress',
    (
      isset( $settings['wordpress'] ) && 1 == $settings['wordpress']
      ? 'checked="checked"'
      : ''
    )
  );
  echo '</div>';
}



function setBackendMenu() {
  add_options_page( 'IWEBLAB Hosting Manager', 'IWEBLAB Hosting Manager', 'administrator', 'iweblab-manager', __NAMESPACE__ . '\renderSettingsPage' );
}



function enqueueStylesAndScripts( $page ) {
  if ( 'settings_page_iweblab-manager' != $page ) return;
  
  wp_register_style( 'ihm-be-style', plugins_url( 'res/backend.css', __FILE__ ), array(), null );
  wp_enqueue_style( 'ihm-be-style' );
}



function renderSettingsPage() {
?>
<div class="wrap">  
  <h1 class="wp-heading-inline">IWEBLAB Hosting Manager</h1>
  <hr class="wp-header-end">
  <form method="post" action="options.php">
<?php
    settings_fields( 'iweblab-hosting-manager' );
    do_settings_sections( 'iweblab-manager' );
    submit_button();
?>
  </form>
  <p>Per informazioni e supporto aprire un ticket in area clienti (<a href="https://hosting.iweblab.it/submitticket.php?step=2&deptid=3" target="_blank">facendo click qui</a>) o scrivere a supporto@iweblab.it .</p>
</div>
<script>
(function() {
'use strict';

  jQuery( '#updateList' ).on( 'click', function( e ) {
    e.preventDefault();

    jQuery.post(
      '<?php echo admin_url('admin-ajax.php') ?>',
      {
        action: 'update_spider_list',
        refchk: '<?php echo wp_create_nonce( 'klj238hdqw dliqw,i23y 89d23qgi32qgw,DQ3FLUWD, GLIU2 GEFQ2,UD FL  FDFQ' ) ?>'
      },
      function( response ) {
        window.location.reload();
      },
      'json'
    );    

  } );

  jQuery( '#HC-recaptcha' ).on( 'change', function( e ) {
    var val = jQuery( this ).val();
    jQuery( '#recaptcha_protection_level' ).text( 0 == val ? 'Disabilitato' : val + '%' );
  } );
} )();
</script>
<?php
}




function forceSpiderListUpdate() {
  check_ajax_referer( 'klj238hdqw dliqw,i23y 89d23qgi32qgw,DQ3FLUWD, GLIU2 GEFQ2,UD FL  FDFQ', 'refchk' );

  updateSpiderList();
}