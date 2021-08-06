<?php


use datagutten\musicbrainz\objects\Recording;
use datagutten\tidal_musicbrainz\TIDAL_to_musicbrainz;
use PHPUnit\Framework\TestCase;

class TIDAL_to_musicbrainzTest extends TestCase
{
    public function testIdentify_track()
    {
        $tidal = new datagutten\Tidal\Tidal();
        $track = $tidal->track('https://tidal.com/browse/track/41093398');
        $tidal_to_mb = new TIDAL_to_musicbrainz();
        /** @var Recording $recording */
        list($recording, $source) = $tidal_to_mb->identify_track($track);
        $this->assertEquals('Nonsens', $recording->title);
    }
}
