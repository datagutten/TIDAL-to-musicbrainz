<?php

namespace datagutten\tidal_musicbrainz;

use datagutten\musicbrainz;
use datagutten\musicbrainz\seed;
use datagutten\Tidal\elements\Track;

class TrackIdentifierISRC extends TrackIdentifier
{
    public static string $source = 'ISRC';

    public function identify(Track $track, $strict_name = false): seed\Track
    {
        if (empty($track->isrc))
            throw new TIDAL_to_musicbrainzException('Track has no ISRC');
        try
        {
            $recordings = $this->mb->recordingsFromISRC($track->isrc, ['artists']);
            foreach ($recordings as $recording) // Check title if we got multiple hits
            {
                if (strcasecmp($recording['title'], $track->title) === 0)
                {
                    //Re-fetch recording to get more data
                    return $this->mb->recordingFromMBID($recording['id']);
                }
            }
            if (!$strict_name && !empty($recording))
                return $this->mb->recordingFromMBID($recording['id']);
            else
                throw new TIDAL_to_musicbrainzException('Unable to find matching recording for ISRC');
        }
        catch (musicbrainz\exceptions\NotFound $e)
        {
            $msg = sprintf('No recording with ISRC %s', $track->isrc);
            throw new TIDAL_to_musicbrainzException($msg, 0, $e);
        }
    }
}
