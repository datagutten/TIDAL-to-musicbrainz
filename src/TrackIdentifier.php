<?php

namespace datagutten\tidal_musicbrainz;

use datagutten\Tidal;
use datagutten\musicbrainz;

/**
 * Identify a TIDAL track to a MusicBrainz recording
 */
abstract class TrackIdentifier
{
    protected musicbrainz\musicbrainz $mb;
    /**
     * @var string Identification source string
     */
    public static string $source;

    function __construct(musicbrainz\musicbrainz $mb)
    {
        $this->mb = $mb;
    }

    /**
     * Identify track
     * @param Tidal\elements\Track $track
     * @param bool $strict_name Only return recordings with exact name match
     * @return musicbrainz\seed\Track Recording object
     * @throws TIDAL_to_musicbrainzException Unable to identify track
     * @throws musicbrainz\exceptions\MusicBrainzErrorException Error from MusicBrainz
     */
    abstract public function identify(Tidal\elements\Track $track, bool $strict_name = false): musicbrainz\seed\Track;
}