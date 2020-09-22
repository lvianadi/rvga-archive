<?php

require_once 'config.php';

function rvga_formatCategory($string) {
    return ucfirst(strtolower($string));
}

function rvga_formatSlug($string) {
    $string = preg_replace('/[^a-z0-9]/i', '-', $string);
    $string = preg_replace('/[\-]{2,}/i', '-', $string);
    
    // Only alphanumeric for the first character
    $string = preg_replace('/^[^a-z0-9]{1}/i', '', $string);
    
    // Only alphanumeric for the last character
    $string = preg_replace('/[^a-z0-9]{1}$/i', '', $string);
    
    return strtolower($string);
}


function rvga_createCategory($category, $parent = 0) {
   global $wpdb;
   
   $category = rvga_formatCategory($category);
   $slug = rvga_formatSlug($category);
   
   $category_id = rvga_categoryExists($category);
   
   if($category_id == null){
       $sql = $wpdb->prepare( "INSERT INTO {$wpdb->terms} (name, slug, term_group ) VALUES ( %s, %s, %d )", $category, $slug, 0 );
       $wpdb->query($sql);
       $term_id = $wpdb->insert_id;
       
       if($parent) {
           $row = $wpdb->get_results($wpdb->prepare("SELECT term_id, name FROM {$wpdb->terms} where name = %s", $parent));
           $parent = $row[0]->term_id;
       }
       
       $sql = $wpdb->prepare( "INSERT INTO {$wpdb->term_taxonomy} (term_taxonomy_id, term_id, taxonomy, description, parent, count ) VALUES ( %d, %d, 'category', '', %d, 1 )", $term_id, $term_id, $parent);
       $wpdb->query($sql);
       $term_taxonomy_id = $wpdb->insert_id;
       
       
       return $term_taxonomy_id;
   }
   
   return $category_id;
}

function rvga_categoryExists($category) {
   global $wpdb;
   $row = $wpdb->get_results($wpdb->prepare("SELECT name, t.term_id FROM {$wpdb->terms} t
                                INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
                                WHERE name = %s AND tt.taxonomy = 'category'", $category));

   return (isset($row) && isset($row[0])) ? $row[0]->term_id : null;
}

function rvga_createPost($category1_id, $category2_id, $category3_id, $title, $notes, $author, $published, $link) {
    global $wpdb;
    
    $config = rvga_config();
    
    // Updating or creating new content
    $row = $wpdb->get_results($wpdb->prepare("SELECT post_id FROM {$config['migration_table']} where link = %s", $link));
    if(isset($row[0])){
        $existingPostId = $row[0]->post_id;

        // Delete existing post / category association
        $sql = $wpdb->prepare( "DELETE FROM {$wpdb->term_relationships} tr where object_id = %d
                                AND (SELECT taxonomy FROM {$wpdb->term_taxonomy} tt where tr.term_taxonomy_id = tt.term_taxonomy_id) = 'category'", $existingPostId);
        $wpdb->query($sql);
        
        $return = [
            'success' => null,
            'action' => 'update',
        ];
    } else {
        $return = [
            'success' => null,
            'action' => 'create',
        ];
    }
    
    $file2html = rvga_file2html($link);
    if($file2html == null){
        return [
            'success' => false,
            'msg' => "File {$link} not found"
        ];
    }
    
    $content = "<p>{$notes}</p>" . $file2html;
    
    $postName = rvga_formatSlug($title);
    
    $published = rvga_col2DateTime($published);
    
    // If content is new, create it. Otherwise, update it
    if(!isset($existingPostId)){
        $sql = $wpdb->prepare( "INSERT INTO {$wpdb->posts} ( post_author, post_date, post_date_gmt, post_content, post_title, post_status, comment_status, ping_status, post_name, post_modified, post_modified_gmt, post_parent, menu_order, post_type, comment_count ) 
                                VALUES ( 1, %s, %s, %s, %s, 'publish', 'closed', 'closed', %s, %s, %s, 0, 0, 'post', 0 )", $published, $published, $content, $title, $postName, $published, $published);
        $wpdb->query($sql);
        
        // Get inserted post id
        $post_id = $wpdb->insert_id;
        
        // Add entry to rvga_migration table
        $sql = $wpdb->prepare( "INSERT INTO {$config['migration_table']} (link, extension, post_id, created_at, updated_at ) VALUES ( %s, '', %d, %s, %s )", $link, $post_id, date('Y-m-d H:i:s'), date('Y-m-d H:i:s') );
        $wpdb->query($sql);
    } else {
        $sql = $wpdb->prepare( "UPDATE {$wpdb->posts} SET post_author = 1, post_date = %s, post_date_gmt = %s, post_content = %s, post_title = %s, post_status = 'publish', comment_status = 'closed', ping_status = 'closed', 
                                post_name = %s, post_modified = %s, post_modified_gmt = %s, post_parent = 0, menu_order = 0, post_type = 'post', comment_count = 0
                                WHERE ID = %d", $published, $published, $content, $title, $postName, $published, $published, $post_id);
        $wpdb->query($sql);
        
        // Get post id
        $post_id = $existingPostId;
        
        // Update entry to rvga_migration table
        $sql = $wpdb->prepare( "UPDATE {$config['migration_table']} SET updated_at = %s WHERE post_id = %d", date('Y-m-d H:i:s'), $post_id);
        $wpdb->query($sql);
    }
    
    
    // ASSOCIATE CATEGORY WITH POST ID
    if($category1_id){
        rvga_assocPostCategory($post_id, $category1_id);
    }
    if($category2_id){
        rvga_assocPostCategory($post_id, $category2_id);
    }
    if($category3_id){
        rvga_assocPostCategory($post_id, $category3_id);
    }
    
    $return['success'] = true;
    
    return $return;
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

function rvga_fetchFile($link) {
    $config = rvga_config();
    $files = glob(__DIR__ . "/../../uploads/{$config['upload_folder']}/{$link}.*");
    if(isset($files[0])){
        // Remove directory path from the file name
        $files[0] = preg_replace('/.*\//i', '', $files[0]);

        $file = explode('.', $files[0]);
        return [
            'name' => $file[0],
            'ext' => $file[1],
        ];
    }else {
        return null;
    }
}

function rvga_file2html($link) {
    $config = rvga_config();
    $file = rvga_fetchFile($link);
    if($file){
        switch($file['ext']){
            case 'pdf':
                return "[pdf-embedder url='/wp-content/uploads/{$config['upload_folder']}/{$link}.pdf']";
            default:
                return "";
        }
    } else {
        return null;
    }
}

function rvga_assocPostCategory($post_id, $categoryId){
    global $wpdb;
    
    $sql = $wpdb->prepare( "INSERT INTO {$wpdb->term_relationships} (object_id, term_taxonomy_id, term_order ) VALUES ( %d, %d, %d )", $post_id, $categoryId, 0 );
    $wpdb->query($sql);
}

function rvga_printLine($string, $count){
    echo "<pre>" . $string . " (Line " . $count . ")</pre>";
    flush();
    ob_flush();
}

function rvga_createFirstMenuItem() {
    global $wpdb;
    
    $row = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->posts} where post_title = %s AND post_type = %s", 'archives', 'nav_menu_item'));
    if(!isset($row[0])){
        $post_id = rvga_menuPost(1, 0, 'Archive');
        
        rvga_insertPostMeta($post_id, '_menu_item_type', 'custom');
        rvga_insertPostMeta($post_id, '_menu_item_menu_item_parent', '0');
        rvga_insertPostMeta($post_id, '_menu_item_object_id', $post_id);
        rvga_insertPostMeta($post_id, '_menu_item_object', 'custom');
        rvga_insertPostMeta($post_id, '_menu_item_target', '');
        rvga_insertPostMeta($post_id, '_menu_item_classes', 'a:1:{i:0;s:0:"";}');
        rvga_insertPostMeta($post_id, '_menu_item_xfn', '');
        rvga_insertPostMeta($post_id, '_menu_item_url', '#');
    }
}

function rvga_createMenuItem($category_id, $parent, $count) {
    global $wpdb;
    
    if(!rvga_menuExists($category_id)){
        $post_id = rvga_menuPost($count, $parent);
        
        if($parent) {
            $parentPostMeta = rvga_menuExists($parent);
            $parentPostId = $parentPostMeta->post_id;
        } else {
            $row = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->posts} where post_title = %s AND post_type = %s", 'archives', 'nav_menu_item'));
            $parentPostId = $row[0]->ID;
        }
        
        rvga_insertPostMeta($post_id, '_menu_item_type', 'taxonomy');
        rvga_insertPostMeta($post_id, '_menu_item_menu_item_parent', $parentPostId);
        rvga_insertPostMeta($post_id, '_menu_item_object_id', $category_id);
        rvga_insertPostMeta($post_id, '_menu_item_object', 'category');
        rvga_insertPostMeta($post_id, '_menu_item_target', '');
        rvga_insertPostMeta($post_id, '_menu_item_classes', 'a:1:{i:0;s:0:"";}');
        rvga_insertPostMeta($post_id, '_menu_item_xfn', '');
        rvga_insertPostMeta($post_id, '_menu_item_url', '');
    }
}

function rvga_menuPost($count, $parent = 0, $title = '') {
    global $wpdb;
    
    $sql = $wpdb->prepare( "INSERT INTO {$wpdb->posts} ( post_author, post_date, post_date_gmt, post_content, post_title, post_status, comment_status, ping_status, post_name, post_modified, post_modified_gmt, post_parent, menu_order, post_type, comment_count ) 
                                VALUES ( 1, %s, %s, %s, %s, 'publish', 'closed', 'closed', %s, %s, %s, %d, %d, 'nav_menu_item', 0 )", date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), '', $title, $title, date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), $parent, $count);
    $wpdb->query($sql);
    
    // Get inserted post id
    return $wpdb->insert_id;
}

function rvga_insertPostMeta($post_id, $meta_key, $meta_value) {
    global $wpdb;
    
    $sql = $wpdb->prepare( "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value ) VALUES ( %d, %s, %s )", $post_id, $meta_key, $meta_value );
    $wpdb->query($sql);
}

function rvga_menuExists($category_id) {
    global $wpdb;
    
    $row = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->postmeta} pm WHERE (SELECT meta_value FROM {$wpdb->postmeta} pm2 WHERE pm2.post_id = pm.post_id AND meta_value = 'category') = 'category' AND meta_key = '_menu_item_object_id' AND meta_value = %d", $category_id));
    if(isset($row[0])){
        return $row[0];
    }else {
        return null;
    }
}
