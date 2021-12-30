<?php


namespace datagutten\tidal_musicbrainz;


use datagutten\musicbrainz\objects\Recording;
use datagutten\musicbrainz\seed;
use datagutten\musicbrainz\seed\URL;
use datagutten\Tidal;
use DateTime;

/**
 * Class to create MusicBrainz seed objects from TIDAL objects
 */
class TIDALSeed
{
    /**
     * Create a seed object from a Tidal object
     * @param Tidal\elements\Album $album
     * @return seed\Release
     */
    public static function seed_album(Tidal\elements\Album $album): seed\Release
    {
        $seed = new seed\Release([
            'name' => $album['title'],
            'type' => strtolower($album['type']),
            'status' => 'Official',
            'barcode' => (int)$album['upc'],
            'script' => 'Latn'
        ]);

        $seed->label(['name' => self::parse_label($album['copyright'])]);
        $seed->url(['url' => $album['url'], 'link_type' => 980]);

        $seed->artists = self::artists($album->artists);

        $release_date = DateTime::createFromFormat('Y-m-d', $album['releaseDate']);
        $seed->event($release_date);

        return $seed;
    }

    /**
     * @param Tidal\elements\Track $track
     * @param ?Recording $mb_recording
     * @return seed\Track
     */
    public static function seed_track(Tidal\elements\Track $track, Recording $mb_recording = null): seed\Track
    {
        $seed_track = new seed\Track([
            'name' => $track->title,
            'number' => $track->trackNumber,
            'length' => $track->duration * 1000,
            'recording' => $mb_recording->id ?? null]);
        $seed_track->artists = self::artists($track->artists, $mb_recording->artists ?? null);

        return $seed_track;
    }

    public static function parse_label(string $label): string
    {
        return preg_replace('/.+[0-9]{4} (.+)/', '$1', $label);
    }

    /**
     * Create an array of seed artist object from an array of TIDAL artist objects
     * @param Tidal\elements\Artist[] $artists Tidal artists
     * @param seed\Artist[] $mb_artists MusicBrainz artists to get MBID from
     * @return seed\Artist[] Array of MB artist objects
     */
    public static function artists(array $artists, array $mb_artists = null): array
    {
        $artists_args = [];
        foreach ($artists as $key => $artist)
        {
            if (isset($artists[$key + 1]))
            {
                if ($artists[$key + 1]['type'] == 'FEATURED')
                    $args = ['name' => $artist['name'], 'join_phrase' => ' feat. '];
                elseif ($artists[$key + 1]['type'] == 'MAIN')
                    $args = ['name' => $artist['name'], 'join_phrase' => ', '];
                else
                    $args = ['name' => $artist['name']];
            }
            else
                $args = ['name' => $artist['name']];

            if ($artist['name'] == 'Various Artists')
                $args['mbid'] = '89ad4ac3-39f7-470e-963a-56509c546377';
            else
                $args['mbid'] = $mb_artists[$key]->id ?? null;

            $artists_args[] = new seed\Artist($args);
        }
        return $artists_args;
    }
}