<?php


namespace datagutten\tidal_musicbrainz;


use Composer\InstalledVersions;
use datagutten\musicbrainz;
use datagutten\Tidal;

class TIDAL_to_musicbrainz
{
    /**
     * @var Tidal\Tidal
     */
    public Tidal\Tidal $tidal;
    /**
     * @var musicbrainz\musicbrainz
     */
    public musicbrainz\musicbrainz $mb;
    /**
     * @var string Project version
     */
    public string $version;

    /**
     * Track identifier class instances
     * Extension classes could add more identifiers
     * @var TrackIdentifier[]
     */
    public array $identifiers;

    function __construct($config = [])
    {
        $this->tidal = new Tidal\Tidal();
        $this->mb = new musicbrainz\musicbrainz($config);
        $this->version = InstalledVersions::getVersion('datagutten/tidal-to-musicbrainz');
        $this->identifiers[] = new TrackIdentifierISRC($this->mb);
    }

    /**
     * Fetch ISRC from TIDAL and submit to MusicBrainz
     * @param string $album_mbid Album MBID
     * @param Tidal\elements\Album $tidal_album Tidal album object
     * @param int $distance_tolerance Levenshtein distance tolerance for track titles
     * @return array Response from MusicBrainz
     * @throws musicbrainz\exceptions\MusicBrainzException
     */
    function submit_isrc_obj(string $album_mbid, Tidal\elements\Album $tidal_album, int $distance_tolerance = 3): array
    {
        $release = $this->mb->releaseFromMBID($album_mbid, ['recordings']);
        $isrc = [];
        foreach ($release->mediums as $medium)
        {
            foreach ($medium->tracks as $track)
            {
                $tidal_track = $tidal_album->get_track($track->number, $medium->position);
                if (empty($tidal_track->isrc))
                    continue;

                $match = Utils::track_match('', $tidal_track->title, '', $track->title, $distance_tolerance);
                if ($match)
                {
                    echo "Match {$track->id}<br />\n";
                    $isrc[$track->id] = $tidal_track->isrc;
                }
                else
                    printf("Titles does not match:\nTIDAL: %s\nMusicBrainz: %s\n\n",
                        $tidal_track->title, $track->title);
            }
        }

        $isrc_list = musicbrainz\musicbrainz::build_isrc_list_array($isrc);
        return $this->mb->send_isrc_list($isrc_list, 'ISRC from TIDAL');
    }

    /**
     * Identify track using available identifier classes
     * @param Tidal\elements\Track $track
     * @param bool $strict_name Only return recordings with exact name match
     * @return array<musicbrainz\seed\Track,string> Recording object and what source was used to identify the track
     * @throws TIDAL_to_musicbrainzException
     * @throws musicbrainz\exceptions\MusicBrainzErrorException
     */
    public function identify_track(Tidal\elements\Track $track, bool $strict_name = false): array
    {
        foreach ($this->identifiers as $identifier)
        {
            try
            {
                return [$identifier->identify($track), $identifier::$source];
            }
            catch (TIDAL_to_musicbrainzException $e)
            {
            }
        }
        throw new TIDAL_to_musicbrainzException('Unable to identify track');
    }

}