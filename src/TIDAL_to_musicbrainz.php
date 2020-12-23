<?php


namespace datagutten\tidal_musicbrainz;


use Composer\InstalledVersions;
use datagutten\Tidal;
use Exception;
use datagutten\musicbrainz;
use Requests_Exception;

class TIDAL_to_musicbrainz
{
	/**
	 * @var Tidal\Info
	 */
	public $tidal;
	/**
	 * @var musicbrainz\musicbrainz
	 */
	public $mb;
    /**
     * @var string Project version
     */
    public $version;

    function __construct()
	{
		$this->tidal=new Tidal\Info();
		$this->mb=new musicbrainz\musicbrainz;
        $this->version = InstalledVersions::getVersion('datagutten/tidal-to-musicbrainz');
	}

    /**
     * Fetch ISRC from TIDAL and submit to MusicBrainz
     * @param string $album_mbid Album MBID
     * @param Tidal\elements\Album $tidal_album Tidal album object
     * @param int $distance_tolerance Levenshtein distance tolerance for track titles
     * @return array Response from MusicBrainz
     * @throws TIDAL_to_musicbrainzException
     * @throws musicbrainz\exceptions\MusicBrainzException
     */
    function submit_isrc_obj(string $album_mbid, Tidal\elements\Album $tidal_album, $distance_tolerance = 3)
    {
        $release = $this->mb->getrelease($album_mbid, 'recordings', true);
        $isrc = [];
        foreach ($release['media'] as $medium)
        {
            foreach ($medium['tracks'] as $track)
            {
                $tidal_track = $tidal_album->get_track($track['position'], $medium['position']);
                if(empty($tidal_track->isrc))
                    continue;
                $check_tidal = mb_strtolower($tidal_track->title);
                $check_mb = mb_strtolower($track['title']);
                $distance = levenshtein($check_mb, $check_tidal);

                if ($check_tidal !== $check_mb && $distance>$distance_tolerance)
                {
                    $msg = sprintf("Titles does not match:\nTIDAL: %s\nMusicBrainz: %s (Levenshtein distance %d)\n",
                        $check_tidal, $check_mb, $distance);
                    throw new TIDAL_to_musicbrainzException($msg);
                }
                $isrc[$track['recording']['id']] = $tidal_track->isrc;
            }
        }

        $isrc_list = musicbrainz\musicbrainz::build_isrc_list_array($isrc);
        return $this->mb->send_isrc_list($isrc_list, 'datagutten/tidal-to-musicbrainz-'.$this->version);
    }

	/**
	 * Fetch ISRC from TIDAL and submit to MusicBrainz
	 * @param string $album_mbid Album MBID
	 * @param string $tidal_id Tidal ID or URL
	 * @throws Exception
     * @deprecated Use submit_isrc_obj
	 * @return array
	 */
	function submit_isrc(string $album_mbid,string $tidal_id)
	{
        $album_obj = Album::from_tidal($tidal_id);
        return $this->submit_isrc_obj($album_mbid, $album_obj);
	}
}