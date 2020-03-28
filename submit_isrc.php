<?php

use datagutten\tidal_musicbrainz\TIDAL_to_musicbrainz;

require 'vendor/autoload.php';
$tidal_to_mb = new TIDAL_to_musicbrainz();

if(isset($_GET['album']) && isset($_GET['release_mbid']))
{
	$argv[2]=$_GET['album'];
	$argv[1]=$_GET['release_mbid'];
}
if(empty($argv[1]) || empty($argv[2]))
	die('Usage: submit_isrc.php [release MBID] [TIDAL Album ID or URL]'."\n");

try {
    $tidal_to_mb->submit_isrc($argv[1], $argv[2]);
}
catch (Exception $e)
{
    die($e->getMessage()."\n");
}