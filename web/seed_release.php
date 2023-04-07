<?Php
$start = microtime(true);
ini_set('display_errors', true);

require __DIR__ . '/../vendor/autoload.php';


use datagutten\musicbrainz;
use datagutten\Tidal;
use datagutten\tidal_musicbrainz;
use datagutten\tidal_musicbrainz\TIDAL_to_musicbrainz;
use datagutten\tidal_musicbrainz\TIDALSeed;


$web = new tidal_musicbrainz\web();
try
{
    $utils = new TIDAL_to_musicbrainz();
}
catch (Exception $e)
{
    die($web->render('exception.twig', ['e' => $e]));
}

$info = [];

$mb = new musicbrainz\musicbrainz;

if (isset($argv[1]))
    $_GET['url'] = $argv[1];
elseif (empty($_GET['url']))
    die($web->render('url_form.twig'));

try
{
    $album_obj = $utils->tidal->album($_GET['url']);
}
catch (Tidal\TidalError $e)
{
    die($web->render('exception.twig', ['e' => $e]));
}

if (isset($_GET['mbid'])) //Edit existing release
{
    $mbid = preg_replace('#.*([a-z0-9\-]{36}).*#U', '$1', $_GET['mbid']);
    $release = new musicbrainz\seed\Release();
    $release->action = sprintf('https://musicbrainz.org/release/%s/edit', $mbid);
    $mb_release = $mb->releaseFromMBID($mbid, ['labels']);
} else //Add new release
{
    $release = TIDALSeed::seed_album($album_obj);
}

foreach ($album_obj->tracks as $track)
{
    if (!isset($medium) || $medium->position != $track['volumeNumber'])
        $medium = $release->medium(['position' => $track['volumeNumber']]);

    try
    {
        /** @var musicbrainz\seed\Track $recording */
        list($recording, $source) = $utils->identify_track($track);
        $info[] = sprintf('%s identified by %s as MBID %s', $track->title, $source, $recording->id);
        $recording = $mb->recordingFromMBID($recording->id);
    }
    catch (musicbrainz\exceptions\MusicBrainzException|tidal_musicbrainz\TIDAL_to_musicbrainzException $e)
    {
        $info[] = sprintf('Error identifying %s: %s', $track['title'], $e->getMessage());
    }
    catch (FileNotFoundException|Tidal\TidalError $e)
    {
        die($web->render('exception.twig', ['e' => $e]));
    }

    $track_obj = TIDALSeed::seed_track($track, $recording ?? null);
    $medium->tracks[] = $track_obj;

    unset($recording);
}

$release->edit_note = 'Information from TIDAL';

if (!empty($redirect_url))
    $release->redirect_uri = $redirect_url;

$end = microtime(true);
$info[] = sprintf('Runtime: %.3f', $end - $start);

$info = implode("\n", $info);


echo $web->render('form.twig', [
    'fields' => $release->save(),
    'action' => $release->action,
    'info' => $info,
    'redirect_uri' => $release->redirect_uri ?? '',
]);