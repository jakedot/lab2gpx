<?php

namespace App\Exporter;

use Location\Coordinate;

class GpxExporter extends AbstractExporter
{
    protected function gpxEncode(string $s): string
    {
        return htmlentities($s, ENT_XML1);
    }

    public function export(array $fetchedLabs, array $values, array $ownersToSkip, array $finds): string
    {
        $xml = '<?xml version="1.0" encoding="utf-8"?>
                <gpx xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" version="1.0" creator="Groundspeak Pocket Query" xsi:schemaLocation="http://www.topografix.com/GPX/1/0 http://www.topografix.com/GPX/1/0/gpx.xsd http://www.groundspeak.com/cache/1/0/1 http://www.groundspeak.com/cache/1/0/1/cache.xsd" xmlns="http://www.topografix.com/GPX/1/0">
                    <name>Adventure Labs</name>
                ';
        $id = -1;
        foreach ($fetchedLabs as $cache) {
            $cache = $this->getCache($cache['Id']);
            if (! $this->includeCache($cache, $values, $ownersToSkip)) {
                continue;
            }

            $stage = 1;
            foreach ($cache['GeocacheSummaries'] as $wpt) {
                $found = $this->isFound($finds, $cache, $wpt);
                if ($found && ! $values['includeFinds']) {
                    $stage++;
                    continue;
                }

                $lat = $wpt['Location']['Latitude'];
                $lon = $wpt['Location']['Longitude'];

                $description = '<h3>' . $cache['Title'] . '</h3>';
                $description .= '<h4>' . $wpt['Title'] . '</h4>';
                if ($cache['IsLinear']) {
                    $description .= '<p><span style="background:#990000;color:#fff;border-radius:5px;padding:3px 5px;">' . $this->locale['TAG_LINEAR'] . '</span></p>';
                }
                $description .= '<p><a href="' . $cache['DeepLink'] . '">' . $cache['DeepLink'] . '</a></p>';

                if ($values['includeQuestion']) {
                    $description .= '<p>' . $this->locale['HEADER_QUESTION'] . ':<br />' . $wpt['Question'] . '</p>';
                }

                if ($values['includeWaypointDescription']) {
                    $description .= '<hr />';
                    $description .= '<h5>' . $this->locale['HEADER_WAYPOINT_DESCRIPTION'] . '</h5>';
                    $description .= '<p><img src="' . $wpt['KeyImageUrl'] . '" /></p>';
                    $description .= '<p>' . $wpt['Description'] . '</p>';
                }

                if ($values['includeCacheDescription']) {
                    $description .= '<hr />';
                    $description .= '<h5>' . $this->locale['HEADER_LAB_DESCRIPTION'] . '</h5>';
                    $description .= '<p><img src="' . $cache['KeyImageUrl'] . '" /></p>';
                    $description .= '<p>' . $cache['Description'] . '</p>';
                }

                if ($values['includeAwardMessage']) {
                    if ($wpt['AwardImageUrl'] || $wpt['CompletionAwardMessage']) {
                        $description .= '<hr />';
                        $description .= '<h5>' . $this->locale['HEADER_AWARD'] . '</h5>';
                    }
                    if ($wpt['AwardImageUrl']) {
                        $description .= '<p><img src="' . $wpt['AwardImageUrl'] . '" /></p>';
                    }
                    if ($wpt['CompletionAwardMessage']) {
                        $description .= '<p>' . $this->locale['HEADER_AWARD_MESSAGE'] . ':<br />' . $wpt['CompletionAwardMessage'] . '</p>';
                    }
                }

                // remove non printable chars https://github.com/mirsch/lab2gpx/issues/10
                $description = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $description);

                $displayStage = $this->getStageForDisplay($stage, $cache);

                $waypointTitle = $this->getWaypointTitle($cache, $values, $wpt, $stage);
                $code = $this->getCode($cache, $values, $stage);

                $xml .= '<wpt lat="' . $lat . '" lon="' . $lon . '">
                    <time>' . $cache['PublishedUtc'] . '</time>
                    <name>' . $code . '</name>
                    <desc>' . $this->gpxEncode($wpt['Title']) . '</desc>
                    <url>' . $cache['DeepLink'] . '</url>
                    <urlname>S' . $displayStage . ' ' . $this->gpxEncode($cache['Title']) . '</urlname>
                    <sym>Geocache' . ($found ? ' Found' : '') . '</sym>
                    <type>Geocache|' . $values['cacheType'] . '</type>';
                if ($values['linear'] === 'corrected' && $cache['IsLinear']) {
                    $xml .= '<gsak:wptExtension xmlns:gsak="http://www.gsak.net/xmlv1/5">
                        <gsak:Code>' . $code . '</gsak:Code>
                        <gsak:IsPremium>false</gsak:IsPremium>
                        <gsak:FavPoints>0</gsak:FavPoints>
                        <gsak:UserFlag>false</gsak:UserFlag>
                        <gsak:DNF>false</gsak:DNF>
                        <gsak:FTF>false</gsak:FTF>
                        <gsak:LatBeforeCorrect>' . $lat . '</gsak:LatBeforeCorrect>
                        <gsak:LonBeforeCorrect>' . $lon . '</gsak:LonBeforeCorrect>
                    </gsak:wptExtension>';
                }
                $xml .= '<groundspeak:cache id="' . $id . '" available="True" archived="False" xmlns:groundspeak="http://www.groundspeak.com/cache/1/0/1">
                        <groundspeak:name>' . $this->gpxEncode($waypointTitle) . '</groundspeak:name>
                        <groundspeak:placed_by>' . $this->gpxEncode($cache['OwnerUsername']) . '</groundspeak:placed_by>
                        <groundspeak:owner>' . $this->gpxEncode($cache['OwnerUsername']) . '</groundspeak:owner>
                        <groundspeak:type>' . $values['cacheType'] . '</groundspeak:type>
                        <groundspeak:container>Virtual</groundspeak:container>
                        <groundspeak:attributes />
                        <groundspeak:difficulty>1</groundspeak:difficulty>
                        <groundspeak:terrain>1</groundspeak:terrain>
                        <groundspeak:country />
                        <groundspeak:state />
                        <groundspeak:short_description html="True" />
                        <groundspeak:long_description html="True">' . $this->gpxEncode($description) . '</groundspeak:long_description>
                        <groundspeak:encoded_hints />
                        <groundspeak:logs />
                        <groundspeak:travelbugs />
                    </groundspeak:cache>
                </wpt>';

                $stage++;
                $id--;

                if ($cache['IsLinear'] && $values['linear'] === 'first') {
                    break;
                }
            }
        }

        $xml .= '</gpx>';

        return $xml;
    }
}
