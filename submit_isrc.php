<?php	
require '../musicbrainz/musicbrainz.class.php';
$mb=new musicbrainz;
require_once '../TIDALtools/tidalinfo.class.php';
$tidal=new tidalinfo;

if(empty($argv[1]) || empty($argv[2]))
	die('Usage: batch_submit_isrc.php [release MBID] [TIDAL Album ID or URL]'."\n");

$album_isrc=$tidal->album_isrc($argv[2]); //Fetch ISRCs for the album from TIDAL
if($album_isrc===false)
	die($tidal->error."\n");
$release=$mb->getrelease($argv[1],'recordings');
if($release===false)
	die($mb->error."\n");
$albuminfo=$tidal->album($argv[2]); //Get album info for verification
if(strtolower((string)$release->release->title)!==strtolower($albuminfo['title']) && (empty($argv[3]) || $argv[3]!='ignore'))
	die(sprintf("Titles does not match:\nTIDAL: %s\nMusicBrainz: %s\n",$albuminfo['title'],(string)$release->release->title));

$isrc_list=$mb->build_isrc_list($album_isrc,$release);
if($isrc_list===false)
	die($mb->error."\n");

$result=$mb->send_isrc_list($isrc_list);
		
echo $mb->error."\n";
