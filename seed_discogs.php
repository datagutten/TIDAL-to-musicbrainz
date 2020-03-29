<?php
/**
 * Seed MusicBrainz release editor with data from a discogs release, using acoustid of local files to find recordings
 */
use datagutten\musicbrainz;

require 'vendor/autoload.php';
$config = require 'config.php';
$discogs_id = preg_replace('#.+discogs\.com/.+/release/([0-9]+)#', '$1', $_GET['url']);
if(!file_exists($_GET['folder']))
    die('Invalid folder');

$response = Requests::get('https://api.discogs.com/releases/'.$discogs_id);
$response->throw_for_status();
$seed = new musicbrainz\seed();
$acoustid=new musicbrainz\AcoustId($config['AcoustId_key']);
$seed->build_page();
$mb = new musicbrainz\musicbrainz();

$release = json_decode($response->body, true);

$seed->field('script', 'Latn');
$seed->field('status', 'Official');
$seed->field('name',$release['title']);
foreach ($release['artists'] as $artist_key=>$artist)
{
	if($artist['id']==194)
	{
		$seed->field('artist_credit.names.0.name', 'Various Artists');
		$seed->field('artist_credit.names.0.mbid', '89ad4ac3-39f7-470e-963a-56509c546377');
		$seed->field('type', 'album');
		$seed->field('type', 'compilation');
		break;
	}
	$seed->field(sprintf('artist_credit.names.%d.name', $artist_key), $artist['name']);
}
foreach ($release['identifiers'] as $identifier)
{
	if($identifier['type']=='Barcode')
		$seed->field('barcode', $identifier['value']);
}

foreach ($release['labels'] as $key=>$label)
{
    $seed->field(sprintf('labels.%d.name', $key), $label['name']);
    $seed->field(sprintf('labels.%d.catalog_number', $key), $label['catno']);
}
$seed->field('events.0.date.year', $release['released']);
$seed->field('urls.0.url', $release['uri']);
$seed->field('edit_note', sprintf('Imported from discogs %s', $release['uri']));
//print_r($release);
//die();

$files = glob($_GET['folder'].'/*.mp3');
//print_r($files);
sort($files);

if(count($files) != count($release['tracklist']))
	die('Track count does not match'."\n");

$disc_key = 0;
//$release['formats'][$disc_key]
foreach ($release['tracklist'] as $track_key=>$track)
{
	$file = $files[$track_key];
	preg_match('/^[0-9]+/', basename($file), $matches);
	$seed->fieldset(basename($file));
	$track_number = $matches[0];
	if($track_number!=$track['position'])
		die('Track position does not match');
	if(isset($track['title']))
		$seed->field(sprintf('mediums.%d.track.%d.name',$disc_key,$track_key),$track['title']);
	if(isset($track['position']))
		$seed->field(sprintf('mediums.%d.track.%d.number',$disc_key,$track_key),$track['position']);
	if(!empty($track['duration']))
		$seed->field(sprintf('mediums.%d.track.%d.length',$disc_key,$track_key),$track['duration']*1000);

	if(isset($track['artists']))
	{
		foreach ($track['artists'] as $artist_key => $artist) {
			$seed->field(sprintf('mediums.%d.track.%d.artist_credit.names.%d.name', $disc_key, $track_key, $artist_key), musicbrainz\seed::artistNoNum($artist['name']));
		}
	}

	$fingerprint = $acoustid->lookup_file($file);
	if (!empty($fingerprint['recordings'])) {
		$recording_mbid = $fingerprint['recordings'][0]['id'];
		$seed->dom->createElement_simple('p', $seed->fieldset, false, $track['title'] . ' match by fingerprint');
	}
	if(isset($recording_mbid)) {
		try {
			$recording = $mb->api_request('/recording/' . $recording_mbid . '?inc=artists');
			$seed->field(sprintf('mediums.%d.track.%d.recording', $disc_key, $track_key), $recording_mbid);
			$artist_key = 0;
			foreach ($recording->{'recording'}->{'artist-credit'}->{'name-credit'} as $artist) {
				//var_dump($artist);
				$artist_mbid = $artist->artist->attributes()['id'];
				$join_phrase = $artist->attributes()['joinphrase'];
				$artist_name = (string)$artist->artist->name;

				$seed->field(sprintf('mediums.%d.track.%d.artist_credit.names.%d.mbid', $disc_key, $track_key, $artist_key), $artist_mbid);
				//field(sprintf('mediums.%d.track.%d.artist_credit.names.%d.name',$disckey,$trackkey,$artistkey),$artist_name);
				//field(sprintf('mediums.%d.track.%d.artist_credit.names.%d.join_phrase',$disckey,$trackkey,$artistkey),$join_phrase);
				$artist_key++;
			}
			//print_r($recording);
		}
		catch (musicbrainz\exceptions\MusicBrainzException $e)
		{
			$seed->message(sprintf("Error looking up recording %s: %s\n", $recording_mbid, $e->getMessage()));
		}
	}
}

echo $seed->show_page();
