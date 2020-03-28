<?Php
require 'vendor/autoload.php';
$mb=new musicbrainz;
require_once 'TIDALtools/tidalinfo.class.php';
$tidal=$info=new tidalinfo;

if(empty($argv[1]))
	die('Usage: batch_submit_isrc.php [artist MBID]'."\n");

$releases=$mb->api_request(sprintf('/artist/%s?inc=releases',$argv[1]));
if($releases===false)
	die($mb->error."\n");

foreach($releases->artist->{'release-list'}->release as $release)
{
	$response=$info->query(sprintf('https://api.tidal.com/v1/search?query=%s&limit=3&offset=0&types=ALBUMS&countryCode=NO',urlencode($release->title)));
	if($response===false)
		die($info->error."\n");
	$results=$info->parse_response($response);

	foreach($results['albums']['items'] as $album)
	{
		if(!empty($release->barcode) && !empty($album['upc']))
		{
			if((int)$release->barcode===(int)$album['upc'])
			{
				$match=$album;
				echo sprintf("UPC match, %d on musicbrainz, %d on TIDAL\n",$release->barcode,$album['upc']);
				break;
			}
			else
			{
				echo sprintf("UPC mismatch, %d on musicbrainz, %d on TIDAL\n",$release->barcode,$album['upc']);
				continue;
			}
		}
		//Missing barcode, check other fields
		if((string)$release->title!==$album['title'])
		{
			echo "Matching ".(string)$release->title." by name\n";
			continue;
		}
	}
	if(!empty($match))
	{
		echo "MB title: ".$release->title."\n";
		$album_isrc=$tidal->album_isrc($album['id']); //Fetch ISRCs for the album from TIDAL
		$release=$mb->getrelease((string)$release->attributes()['id'],'recordings');
		if($release===false)
		{
			echo $mb->error."\n";
			continue;
		}
		$isrc_list=$mb->build_isrc_list($album_isrc,$release);
		if($isrc_list===false)
		{
			echo $mb->error."\n";
			continue;
		}

		$result=$mb->send_isrc_list($isrc_list);
		
		echo $mb->error."\n";
		if($result===false)
			break;

		$match=false;
	}

}
