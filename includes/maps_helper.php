<?php
/**
 * Maps Helper for TripSync
 * Handles Google Maps Distance Matrix and Directions logic
 */

class MapsHelper
{
    private $apiKey;

    public function __construct()
    {
        $this->apiKey = defined('GOOGLE_MAPS_API_KEY') ? GOOGLE_MAPS_API_KEY : '';
    }

    /**
     * Get real-time distance and duration between two points
     * @param string $origin Lat,Lng or Address
     * @param string $destination Lat,Lng or Address
     * @return array [distance, duration, success]
     */
    public function getDistanceMatrix($origin, $destination)
    {
        if (empty($this->apiKey) || empty($origin) || empty($destination)) {
            return ['success' => false, 'message' => 'Missing parameters or API key'];
        }

        $url = "https://maps.googleapis.com/maps/api/distancematrix/json?" . http_build_query([
            'origins' => $origin,
            'destinations' => $destination,
            'mode' => 'driving',
            'departure_time' => 'now', // Critical for live traffic
            'key' => $this->apiKey
        ]);

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $res = curl_exec($ch);
            curl_close($ch);
            $data = json_decode($res, true);

            if ($data['status'] === 'OK' && $data['rows'][0]['elements'][0]['status'] === 'OK') {
                $element = $data['rows'][0]['elements'][0];
                return [
                    'success' => true,
                    'distance' => $element['distance']['text'],
                    'distance_m' => $element['distance']['value'],
                    'duration' => $element['duration_in_traffic']['text'] ?? $element['duration']['text'],
                    'duration_s' => $element['duration_in_traffic']['value'] ?? $element['duration']['value']
                ];
            }
            return ['success' => false, 'message' => $data['status'] ?? 'API Error'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
