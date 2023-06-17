<?php

namespace datagutten\tidal_musicbrainz;

class Utils
{
    /**
     * Check if two tracks has similar name and artist
     * @param string $artist1
     * @param string $track1
     * @param string $artist2
     * @param string $track2
     * @param int $levenshtein_tolerance Levenshtein distance tolerance for artist name and track title
     * @return bool
     */
    public static function track_match(string $artist1, string $track1, string $artist2, string $track2, int $levenshtein_tolerance = 2): bool
    {
        $artist1 = mb_strtolower($artist1);
        $artist2 = mb_strtolower($artist2);

        $track1 = mb_strtolower($track1);
        $track2 = mb_strtolower($track2);

        $match_artist = $artist1 == $artist2;
        $match_track = $track1 == $track2;
        if ($match_track === false)
        {
            $track1 = preg_replace('#(.+)\s\(.+?\sversion\)#', '$1', $track1);
            //Remove special characters
            $check1 = preg_replace('/[^\w\s]/', '', $track1);
            $check2 = preg_replace('/[^\w\s]/', '', $track2);
            if ($check1 === $check2)
                $match_track = true;
            else
            {
                $check_distance = levenshtein($check1, $check2);
                if ($check_distance <= $levenshtein_tolerance)
                    $match_track = true;
                elseif ($check_distance <= $levenshtein_tolerance + 5)
                    printf('"%s" "%s" distance %d' . "\n", $check1, $check2, $check_distance);
            }
        }
        return $match_artist && $match_track;
    }
}