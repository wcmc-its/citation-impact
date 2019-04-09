<?php
/************************************************************************************************
 * // Name:    medline_fetch.php
 * // Author:    Prakash Adekkanattu
 * // Date:    08/05/16
 * // Description:    Fetch publications from medline using delayed query.
 ************************************************************************************************/

include_once(__DIR__ . "/library/main.php");
include_once(__DIR__ . "/library/portable_utf8.php");
$mysqli = getDatabaseConnection();
require_file('data.php');
$objData = new Data($mysqli);

$limit = 200;

$pub_years = array(
    '2003',
);

$pub_types = array(
    'Academic Article',
    'Review'
);

$categories = $objData->get_all_categories();

// include pubmed api
require_file('MedlineAPI.php');
$objAPI = new MedlineAPI();
$count = 1;
foreach ($pub_years as $year) {
    $term = "";
    foreach ($pub_types as $type) {
        if (count($categories) > 0) {
            foreach ($categories as $key => $val) {

                // construct queries
                $ids = array();
                $ids = $objData->get_category_journal_ids($key);

                $pubmed_efetch_grand_results = array();

                if(!empty($ids)){

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

}

?>