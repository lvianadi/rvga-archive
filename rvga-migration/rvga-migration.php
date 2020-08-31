<?php
/**
* Plugin Name: RVGA Migration
* Plugin URI: https://rvga.masterdaweb.io/
* Description: Migrate spreadsheet .
* Version: 1.0
* Author: Lucas Carvalho
* Author URI: https://rvga.masterdaweb.io/
**/


function installer(){
    include('installer.php');
}
register_activation_hook(__file__, 'installer');



function my_admin_menu() {
    
add_menu_page(
__( 'RVGA Migration', 'my-textdomain' ),
__( 'RVGA Migration', 'my-textdomain' ),
'manage_options',
'migration-page',
'my_admin_page_contents',
'dashicons-schedule',
3
);

}


add_action( 'admin_menu', 'my_admin_menu' );


function my_admin_page_contents() {
    if($_POST['run']) {
        global $wpdb;
        
        include('functions.php');
       
       $filepath = __DIR__ . '/../../uploads/2020/08/rvga.csv';

        // The nested array to hold all the arrays
        $lines = []; 
        
        // Open the file for reading
        if (($h = fopen("{$filepath}", "r")) !== FALSE) 
        {
            
          // The items of the array are comma separated
          $count = 1;
          while (($data = fgetcsv($h, 1000, ",")) !== FALSE) 
          {
            if($count == 1){
                rvga_printLine ("Skipping header...", $count);
                continue;
            }
                
            // Each individual array is being pushed into the nested array
            $line[] = $data;	
            
            // primary category
            $category1 = $line[0];
            if(strlen($category1)){
                rvga_createCategory($category1);
            }else {
                rvga_printLine ("Skipping line (No category)...", $count);
                continue;
            }
            
            // secondary category
            $category2 = $line[1];
            if(strlen($category2)){
                rvga_createCategory($category2, $category1);
            }
            
            // tertiary category
            $category3 = $line[2];
            if(strlen($category3)){
                rvga_createCategory($category3, $category2);
            }
            
            
            $count++;   
          }
        
          // Close the file
          fclose($h);
        }
        
        
        
        
       
    }else {
?>

<h1>Welcome to the RGA Migration script</h1>
<p>In order to get the script running properly, please follow the instructions below:</p>
<ol>
  <li>Log In into the FTP account.</li>
  <li><b>Upload all the RVGA documents</b> (pdf, images, etc...) to the folder <b>"wp-content/uploads/2020/08"</b>.</li>
  <li><b>Upload the spreadsheet in format ".CSV" named "rvga.csv"</b> to the folder <b>"wp-content/uploads/2020/08"</b>.
  <li>Click on the button below to start migrating the documents into Wordpress posts.</li>
</ol> 

<form action="" method="post">
  <input type="hidden" name="run" value="1">
  <input style="background: #0073aa;color: #fff; width: 200px; height: 50px; font-size: 22px; border-radius: 15px;" type="submit" value="Start migrating">
</form>

<?php
}

}



function register_my_plugin_scripts() {
wp_register_style( 'my-plugin', plugins_url( 'ddd/css/plugin.css' ) );
wp_register_script( 'my-plugin', plugins_url( 'ddd/js/plugin.js' ) );
}

add_action( 'admin_enqueue_scripts', 'register_my_plugin_scripts' );

function load_my_plugin_scripts( $hook ) {
// Load only on ?page=sample-page
if( $hook != 'toplevel_page_sample-page' ) {
return;
}

// Load style & scripts.
wp_enqueue_style( 'my-plugin' );
wp_enqueue_script( 'my-plugin' );
}


add_action( 'admin_enqueue_scripts', 'load_my_plugin_scripts' );

