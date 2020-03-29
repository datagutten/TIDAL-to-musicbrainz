<?php
use datagutten\musicbrainz;
require 'vendor/autoload.php';
$config = require 'config.php';
$acoustid=new musicbrainz\AcoustId($config['AcoustId_key']);

$results = $acoustid->lookup_file($argv[1], false);

echo lookup_output($results);

function lookup_output($results)
{
    $output = '';
    if(empty($results['results']))
        return "No matches\n";
    foreach ($results['results'] as $result) {
        $output .= sprintf("AcoustID %s score %s:\n", $result['id'], $result['score']);
        if(!isset($result['recordings']))
        {
            $output.="\tNo recordings\n";
            continue;
        }
        $output .= "\tRecordings:\n";

        foreach ($result['recordings'] as $recording)
        {
            $output .= sprintf("\t%s\n", $recording['id']);
        }
    }
    return $output;
}