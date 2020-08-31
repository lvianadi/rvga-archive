<?php

function rvga_formatCategory($string) {
    return ucfirst(strtolower($string));
}

function rvga_formatSlug($string) {
    $string = preg_replace('/[^a-z0-9]/i', '-', $string);
    $string = preg_replace('/[\-]{2,}/i', '-', $string);
    return strtolower($string);
}


function rvga_createCategory($category, $parent = 0) {
   global $wpdb;
   
   $category = rvga_formatCategory($category);
   $slug = rvga_formatSlug($category);
   
   if(rvga_categoryExists($category) == false){
       $sql = $wpdb->prepare( "INSERT INTO $wpdb->terms (name, slug, term_group ) VALUES ( %d, %s, %d )", $category, $slug, 0 );
       $wpdb->query($sql);
       $term_id = $wpdb->insert_id;
       
       if($parent) {
           $row = $wpdb->get_results($wpdb->prepare("SELECT name FROM $wpdb->terms where name = %s", $parent));
           $parent = $row[0]->term_id;
       }
       
       $sql = $wpdb->prepare( "INSERT INTO $wpdb->term_taxonomy (term_taxonomy_id, term_id, taxonomy ) VALUES ( %d, %d, 'category', '', %d, 1 )", $term_id, $term_id, $parent);
       $wpdb->query($sql);
       $term_taxonomy_id = $wpdb->insert_id;
       
       
       return true;
   }
}

function rvga_categoryExists($category) {
   global $wpdb;
   $row = $wpdb->get_results($wpdb->prepare("SELECT name FROM $wpdb->terms t
                                INNER JOIN $wpdb->term_taxonomy tt ON tt.term_id = t.term_id
                                WHERE name = %s AND tt.taxonomy = 'category'", $category));

   return (isset($row) && isset($row[0]));
}

function rvga_createPost($title, $notes, $published, $link) {
   global $wpdb;
   
   $content = '';
   $postName = rvga_formatSlug($title);
   
   $published = rvga_col2DateTime($published):
   
   $sql = $wpdb->prepare( "INSERT INTO $wpdb->posts ( post_author, post_date, post_date_gmt, post_content, post_title, post_status, comment_status, ping_status, post_name, post_modified, post_modified_gmt, post_parent, guid, menu_order, post_type, comment_count ) VALUES ( 1, %s, %s, %s, %s, 'publish', 'closed', 'closed', %s )", $published, $published, $content, $title, $postName);
   $wpdb->query($sql);
}

function rvga_col2DateTime ($published) {
    $date = explode('.', $published);
    $year = $date[0];
    $month = $date[1];
    
    if($year > date('Y')) {
        $date = date('Y-m-d H:i:s');
    }else {
        $date = "{$year}-{$month}-01 00:00:00";
    }
    return $date;
}

function rvga_fetchFile($filename) {
    $files = glob(__DIR__ . "/" . $filename . ".*");
    if(isset($files[0])){
        $file = explode('.', $files[0]);
        return [
            'name' => $file[0],
            'ext' => $file[1],
        ];
    }else {
        return null;
    }
}

function rvga_printLine($string, $count){
    echo "<pre>" . $string . " (Line " . $count . ")</pre>";
    flush();
    ob_flush();
}