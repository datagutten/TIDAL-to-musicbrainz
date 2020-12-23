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
	 * @param string $tidal_id Tidal ID or URL
	 * @throws Exception
	 * @return array
	 */
	function submit_isrc($album_mbid,$tidal_id)
	{
		try {
			$album_isrc=$this->tidal->album_isrc($tidal_id); //Fetch ISRCs for the album from TIDAL
			$albuminfo=$this->tidal->album($tidal_id); //Get album info for verification
		}
		catch (Tidal\TidalError $e)
		{
			throw new Exception('Error fetching information from TIDAL: '. $e->getMessage());
		}

		try {
			$release = $this->mb->getrelease($album_mbid, 'recordings');

			if (strtolower((string)$release->{'release'}->{'title'}) !== strtolower($albuminfo['title']) && (empty($argv[3]) || $argv[3] != 'ignore'))
				throw new Exception(sprintf("Titles does not match:\nTIDAL: %s\nMusicBrainz: %s\n",
					$albuminfo['title'], (string)$release->{'release'}->{'title'}));

			$isrc_list = $this->mb->build_isrc_list($album_isrc, $release);

			return $this->mb->send_isrc_list($isrc_list);
		}
		catch (musicbrainz\exceptions\MusicBrainzException $e)
		{
			throw new Exception('Error from MusicBrainz: '.$e->getMessage());
		}
		catch (Requests_Exception $e)
		{
			throw new Exception('Error sending data using requests: '. $e->getMessage());
		}
	}
}