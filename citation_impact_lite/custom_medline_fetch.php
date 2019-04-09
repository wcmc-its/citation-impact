<?php
include_once(__DIR__ . "/library/main.php");
include_once(__DIR__ . "/library/portable_utf8.php");
$mysqli = getDatabaseConnection();
require_file('data.php');
$objData = new Data($mysqli);

$year = '';
if (count($argv) == 4 ) {
    if (isset($argv[1])) {
        $year = (int)$argv[1];
        if( empty($year) || !is_numeric($year)){
            exit;
        }
    }
    if (isset($argv[2])) {
        $pub_cat = (int)$argv[2];
        if( empty($pub_cat) || !is_numeric($pub_cat)){
            exit;
        }
    }
    if (isset($argv[3])) {
        $pub_type = $argv[3];
        if ( !(($pub_type == 'R') || ($pub_type == 'A')) ){
            exit;
        }
    }
}else {
    exit;
}
$limit = 200;

$pub_types = array();

switch($pub_type){
    case 'R': $pub_types[] = 'Review';
        break;
    case 'A': $pub_types[] = 'Academic Article';
        break;
}


// Get custom category
$custom_cat_ids = array($pub_cat);
$categories = $objData->get_custom_categories($custom_cat_ids);

// include pubmed api
require_file('MedlineAPI.php');
$objAPI = new MedlineAPI();
$count = 1;

$term = "";
foreach ($pub_types as $type) {
    if (count($categories) > 0) {
        foreach ($categories as $key => $val) {
            // construct queries
            $ids = array();
            $ids = $objData->get_category_journal_ids($key);

            $pubmed_efetch_grand_results = array();

            if (!empty($ids)) {
                $chunks = array_chunk($ids, 100, true);

                foreach ($chunks as $chunk) {
                    $start = microtime();

                    $previous_efetch_results = array();
                    foreach($pubmed_efetch_grand_results as $k=>$v){
                        $previous_efetch_results[$k] = $v;
                    }

                    $term = $objData->construct_query($year, $type, $chunk);

                    $pmids = array();
                    $pmids = $objAPI->query($term);

                    $result_count = count($pmids);

                    // Get return count
                    $random_count = ($result_count >= $limit)? $limit : $result_count;

                    // get xml for all articles with  a random 200 pmids from this list
                    $chunk_efetch_results = array();
                    $chunk_efetch_results = $objAPI->pubmed_random_efetch($pmids, $random_count);

                    // merge with previous results
                    $merged_results = array();
                    if (count($chunk_efetch_results) > 0) {
                        if (count($previous_efetch_results) > 0){
                            $merged_results = array_merge($previous_efetch_results, $chunk_efetch_results);
                        }else {
                            $merged_results = $chunk_efetch_results;
                        }
                    }else {
                        if (count($previous_efetch_results) > 0){
                            $merged_results = $previous_efetch_results;
                        }
                    }

                    $pubmed_efetch_grand_results = array();
                    foreach($merged_results as $k=>$v){
                        $pubmed_efetch_grand_results[$k] = $v;
                    }

                    $end = microtime();
                    if(($end - $start) < 333000 ) {
                        usleep(333000);
                    }

                }
                if (!empty($pubmed_efetch_grand_results)) {

                    $pubmed_efetch_200_rand_results = array();
                    if(count($pubmed_efetch_grand_results) > $limit) {
                        shuffle($pubmed_efetch_grand_results); // randomize order of array items
                        $pubmed_efetch_200_rand_results = array_slice($pubmed_efetch_grand_results, 0, $limit);
                    }else {
                        $pubmed_efetch_200_rand_results = $pubmed_efetch_grand_results;
                    }
                    // echo "Count:" . count($pubmed_efetch_200_rand_results)."\n";

                    // populate local database with the result
                    // $objData->populate_data( $year, $type, $key, $pubmed_efetch_results );
                    $objData->populate_multi_data($year, $type, $key, $pubmed_efetch_200_rand_results);

                    echo "Processed, Year:" . $year . ", Type:" . $type . ", Category:" . $key . "\n";
                }
            }
        }
    }
}

$count++;


?>