<?php


use datagutten\tidal_musicbrainz\TrackIdentifierISRC;
use datagutten\tidal_musicbrainz\TIDAL_to_musicbrainzException;
use PHPUnit\Framework\TestCase;

class TrackIdentifierISRCTest extends TestCase
{
    public function testIdentify()
    {
        $mb = new datagutten\musicbrainz\musicbrainz();
        $identifier = new TrackIdentifierISRC($mb);
        $tidal = new datagutten\Tidal\Tidal();
        $track = $tidal->track('https://tidal.com/browse/track/96564755');
        $recording = $identifier->identify($track);
        $this->assertEquals('Frekk', $recording->title);
    }

    public function testIdentifyNoMatch()
    {
        $mb = new datagutten\musicbrainz\musicbrainz();
        $identifier = new TrackIdentifierISRC($mb);
        $tidal = new datagutten\Tidal\Tidal();
        $track = $tidal->track('https://tidal.com/browse/track/19226925');
        $this->expectException(TIDAL_to_musicbrainzException::class);
        $this->expectExceptionMessage('No recording with ISRC NOWAT1301020');
        $identifier->identify($track);
    }
}
