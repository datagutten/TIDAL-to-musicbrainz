<?Php

use datagutten\musicbrainz;
use datagutten\Tidal;
use datagutten\tidal_musicbrainz\TIDAL_to_musicbrainz;

require 'vendor/autoload.php';
$mb=new musicbrainz\musicbrainz;
$tidal_to_mb = new TIDAL_to_musicbrainz();
$tidal=$info=$tidal_to_mb->tidal;
try {
	$info->token = Tidal\Info::get_token();
}
catch (Tidal\TidalError $e) {
	die($e->getMessage());
}

if(empty($argv[1]))
	die('Usage: batch_submit_isrc.php [artist MBID]'."\n");

try {
    $releases = $mb->api_request(sprintf('/artist/%s?inc=releases', $argv[1]));
}
catch (musicbrainz\exceptions\MusicBrainzException $e)
{
    die('Error from MusicBrainz: '. $e->getMessage()."\n");
}

foreach($releases->artist->{'release-list'}->release as $release)
{
	printf("MB release: %s\n",$release->title);
	try {
		$response = $info->query(sprintf('https://api.tidal.com/v1/search?query=%s&limit=3&offset=0&types=ALBUMS&countryCode=NO', urlencode($release->title)));
		$results = $info->parse_response($response);
	}
	catch (Tidal\TidalError $e)
	{
		printf("Error from tidal: %s\n", $e->getMessage());
		continue;
	}

	foreach($results['albums']['items'] as $album)
	{
		echo "\t".$album['title']."\n";
		if(!empty($release->barcode) && !empty($album['upc']))
		{
			if((int)$release->barcode===(int)$album['upc'])
			{
				$match=$album;
				echo sprintf("\t\tUPC match, %d on musicbrainz, %d on TIDAL\n",$release->barcode,$album['upc']);
				break;
			}
			else
			{
				echo sprintf("\t\tUPC mismatch, %d on musicbrainz, %d on TIDAL\n",$release->barcode,$album['upc']);
				continue;
			}
		}
		//Missing barcode, check other fields
		if((string)$release->title!==$album['title'])
		{
			echo "\tMatching ".(string)$release->title." by name\n";
			continue;
		}
	}
	if(!empty($match))
	{
		echo "MB title: ".$release->{'title'}."\n";
		try {
            $tidal_to_mb->submit_isrc((string)$release->attributes()['id'], $album['id']);
        }
        catch (Exception $e)
        {
            echo $e->getMessage()."\n";
            continue;
        }

		$match=false;
	}

}
