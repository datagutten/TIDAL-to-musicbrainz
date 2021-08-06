<?php


namespace datagutten\tidal_musicbrainz;


use Composer\InstalledVersions;
use datagutten\musicbrainz;
use datagutten\Tidal;
use datagutten\tools\files\files;

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
    function submit_isrc_obj(string $album_mbid, Tidal\elements\Album $tidal_album, int $distance_tolerance = 3): array
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

    /**
     * Identify track by ISRC
     * @param Tidal\elements\Track $track
     * @param bool $strict_name Only return recordings with exact name match
     * @return array MBID and what source was used to identify the track
     * @throws TIDAL_to_musicbrainzException
     * @throws musicbrainz\exceptions\MusicBrainzErrorException
     */
    public function identify_track(Tidal\elements\Track $track, bool $strict_name = false): array
    {
        if(!empty($track->isrc))
        {
            try
            {
                //$result = $this->mb->lookup_isrc_cache($track->isrc);
                $result = $this->mb->lookup_isrc($track->isrc, 'artists');
                foreach ($result['recordings'] as $recording)
                {
                    if(strcasecmp($recording['title'], $track->title)===0)
                    {
                        $recording_obj = $this->mb->recordingFromMBID($recording['id']);
                        return [$recording_obj, 'ISRC and title exact match'];
                    }
                }
                if(!$strict_name && !empty($recording))
                {
                    $recording_obj = $this->mb->recordingFromMBID($recording['id']);
                    return [$recording_obj, 'ISRC'];
                }
                else
                    throw new TIDAL_to_musicbrainzException('Unable to find matching recording for ISRC');
            }
            catch (musicbrainz\exceptions\NotFound $e)
            {
                $msg = sprintf('Unable to find recording for ISRC %s: %s', $track->isrc, $e->getMessage());
                throw new TIDAL_to_musicbrainzException($msg, 0, $e);
            }
        }
        else
            throw new TIDAL_to_musicbrainzException('Unable to identify track');
    }

}