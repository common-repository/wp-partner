<?php

require_once("../../../wp-blog-header.php");
$return['error'] = false;

while (true) {
    if (empty($_POST['image_link'])) {
        $return['error'] = true;
        $return['msg'] = 'You did not enter an image-link.';
        break;
    }

    if (empty($_POST['name'])) {
        $return['error'] = true;
        $return['msg'] = 'You did not enter your name.';
        break;
    }

    if (empty($_POST['url'])) {
        $return['error'] = true;
        $return['msg'] = 'You did not enter an url.';
        break;
    } else if(!filter_var($_POST['url'], FILTER_VALIDATE_URL)) {
        $return['error'] = true;
        $return['msg'] = 'No valid url.';
    }

    if (empty($_POST['description'])) {
        $return['error'] = true;
        $return['msg'] = 'You did not enter an description.';
        break;
    }

    break;
}

if (!$return['error']) {
   global $wpdb;
   $partnerLinkTABLE = $wpdb->prefix.'wppartner_links';

    $sql = "INSERT INTO $partnerLinkTABLE (name,description,url,image) ";
    $sql .= "VALUES ('".mysql_real_escape_string(strip_tags($_POST['name']))."','".mysql_real_escape_string(strip_tags($_POST['description']))."','".mysql_real_escape_string(strip_tags($_POST['url']))."','".mysql_real_escape_string(strip_tags($_POST['image_link']))."');";

    if($wpdb->query($sql)) {
        $return['msg'] = 'Your request has been saved! It will be reviewed by an administrator!';
    } else {
        $return['error'] = true;
        $return['msg'] = 'There was an error saving your request!';
    }
}

echo json_encode($return);
?>