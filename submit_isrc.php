<?php

use datagutten\musicbrainz\exceptions\MusicBrainzException;
use datagutten\musicbrainz\musicbrainz;
use datagutten\Tidal\TidalError;
use datagutten\tidal_musicbrainz;
use datagutten\tidal_musicbrainz\TIDAL_to_musicbrainz;

require 'vendor/autoload.php';
$config = require __DIR__ . '/config.php';
$tidal_to_mb = new TIDAL_to_musicbrainz($config);

if (php_sapi_name() == 'cli')
{
    if (empty($argv[1]) || empty($argv[2]))
        die('Usage: submit_isrc.php [release MBID] [TIDAL Album ID or URL]' . "\n");
    try
    {
        $release = $tidal_to_mb->mb->releaseFromMBID(musicbrainz::mbidFromURL($argv[1]));
        $album = $tidal_to_mb->tidal->album($argv[2]);
        $tidal_to_mb->submit_isrc_obj($release->id, $album);
    }
    catch (MusicBrainzException | TidalError | tidal_musicbrainz\TIDAL_to_musicbrainzException $e)
    {
        die($e->getMessage() . "\n");
    }
}
else
{
    $web = new tidal_musicbrainz\web();
    if (empty($_GET['album']) || empty($_GET['release']))
        die($web->render('release_and_album.twig', []));

    try
    {
        $release = $tidal_to_mb->mb->releaseFromMBID(musicbrainz::mbidFromURL($_GET['release']));
        $album = $tidal_to_mb->tidal->album($_GET['album']);
        $tidal_to_mb->submit_isrc_obj($release->id, $album);
        header('Location: https://musicbrainz.org/release/' . $release->id);
    }
    catch (MusicBrainzException | TidalError | tidal_musicbrainz\TIDAL_to_musicbrainzException $e)
    {
        die($web->render('exception.twig', ['e' => $e]));
    }
}
