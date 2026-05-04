<?php
/**
 * Google Places API Wrapper for TripSync
 * Enhanced to fetch all hotel details (photos, reviews, description)
 */

class GooglePlacesAPI
{
    private $apiKey;

    public function __construct()
    {
        $this->apiKey = defined('GOOGLE_MAPS_API_KEY') ? GOOGLE_MAPS_API_KEY : '';
    }

    /**
     * Deterministic price based on place_id + rating (no random — consistent across pages)
     * Google's price_level: 1=budget, 2=moderate, 3=expensive, 4=very expensive
     */
    public static function calcPrice($place_id, $rating = 4.0, $price_level = null)
    {
        // Use numeric hash of place_id so same hotel always gives same price
        $hash = abs(crc32($place_id));

        if ($price_level !== null) {
            $ranges = [
                1 => [3000, 7000], // Budget
                2 => [7500, 14000], // Moderate
                3 => [14500, 25000], // Expensive
                4 => [25500, 55000], // Very expensive
            ];
            [$min, $max] = $ranges[(int)$price_level] ?? [8000, 18000];
        }
        else {
            // Fall back to rating-based tiers
            if ($rating >= 4.7)
                [$min, $max] = [28000, 55000];
            elseif ($rating >= 4.4)
                [$min, $max] = [16000, 28000];
            elseif ($rating >= 4.0)
                [$min, $max] = [8500, 16000];
            else
                [$min, $max] = [4000, 8500];
        }
        return $min + ($hash % ($max - $min));
    }

    /**
     * Search for hotels in a specific city/location
     */
    public function searchHotels($location)
    {
        if (empty($this->apiKey))
            return [];

        $url = "https://maps.googleapis.com/maps/api/place/textsearch/json?" . http_build_query([
            'query' => "hotels in " . $location,
            'type' => 'lodging',
            'key' => $this->apiKey
        ]);

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $res = curl_exec($ch);
            curl_close($ch);
            $data = json_decode($res, true);

            $hotels = [];
            if (!empty($data['results'])) {
                foreach (array_slice($data['results'], 0, 12) as $h) {
                    $photo_ref = $h['photos'][0]['photo_reference'] ?? '';
                    $place_id = $h['place_id'];
                    $rating = $h['rating'] ?? 4.0;
                    $price_level = $h['price_level'] ?? null;
                    $price = self::calcPrice($place_id, $rating, $price_level);
                    $hotels[] = [
                        'id' => 'google_' . $place_id,
                        'name' => $h['name'],
                        'location' => $h['formatted_address'],
                        'stars' => $rating,
                        'reviews_count' => $h['user_ratings_total'] ?? 0,
                        'price_per_night' => $price,
                        'price_level' => $price_level,
                        'image_path' => $this->getPhotoUrlFromReference($photo_ref),
                        'photo_reference' => $photo_ref,
                        'category' => $rating >= 4.5 ? 'Luxury' : 'Premium Stay',
                        'amenities' => 'WiFi, AC, Pool, Restaurant',
                        'is_live' => true
                    ];
                }
            }
            // Sort by rating desc to ensure "top hotels" appear first
            usort($hotels, fn($a, $b) => $b['stars'] <=> $a['stars']);
            return $hotels;
        }
        catch (Exception $e) {
            error_log("Google Search Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get autocomplete suggestions for hotels
     */
    public function getHotelSuggestions($input)
    {
        if (empty($input) || empty($this->apiKey))
            return [];

        $url = "https://maps.googleapis.com/maps/api/place/autocomplete/json?" . http_build_query([
            'input' => $input,
            'types' => 'lodging',
            'location' => '7.8731,80.7718', // Center of Sri Lanka
            'radius' => '250000', // 250km radius to cover most of SL
            'strictbounds' => 'false',
            'key' => $this->apiKey
        ]);

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $res = curl_exec($ch);
            curl_close($ch);
            $data = json_decode($res, true);

            $suggestions = [];
            if (!empty($data['predictions'])) {
                foreach (array_slice($data['predictions'], 0, 5) as $p) {
                    $suggestions[] = [
                        'description' => $p['description'],
                        'main_text' => $p['structured_formatting']['main_text'],
                        'secondary_text' => $p['structured_formatting']['secondary_text'] ?? '',
                        'place_id' => $p['place_id']
                    ];
                }
            }
            return $suggestions;
        }
        catch (Exception $e) {
            error_log("Google Autocomplete Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get a direct photo URL from a reference (much faster than search)
     */
    public function getPhotoUrlFromReference($reference)
    {
        if (empty($reference) || empty($this->apiKey)) {
            return "../assets/images/hotel_details/main.jpg";
        }
        return "https://maps.googleapis.com/maps/api/place/photo?" . http_build_query([
            'maxwidth' => 800,
            'photoreference' => $reference,
            'key' => $this->apiKey
        ]);
    }

    /**
     * Get a single high-quality photo URL for a given place name
     */
    public function getPlacePhoto($query)
    {
        $details = $this->getAdvancedDetails($query);
        return $details['photos'][0] ?? "../assets/images/hotel_details/main.jpg";
    }

    /**
     * Get all details for a hotel/place to fill the detail page
     */
    public function getAdvancedDetails($query)
    {
        if (empty($this->apiKey))
            return $this->getFallbackData();

        // Step 1: Find Place ID
        $searchUrl = "https://maps.googleapis.com/maps/api/place/findplacefromtext/json?" . http_build_query([
            'input' => $query,
            'inputtype' => 'textquery',
            'fields' => 'place_id',
            'key' => $this->apiKey
        ]);

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $searchUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $res = curl_exec($ch);
            curl_close($ch);
            $searchData = json_decode($res, true);

            if (empty($searchData['candidates'][0]['place_id']))
                return $this->getFallbackData();

            return $this->getDetailsByPlaceId($searchData['candidates'][0]['place_id']);

        }
        catch (Exception $e) {
            error_log("Google API Error: " . $e->getMessage());
            return $this->getFallbackData();
        }
    }

    /**
     * Get details directly by Place ID
     */
    public function getDetailsByPlaceId($placeId)
    {
        if (empty($this->apiKey) || empty($placeId))
            return $this->getFallbackData();

        try {
            $detailsUrl = "https://maps.googleapis.com/maps/api/place/details/json?" . http_build_query([
                'place_id' => $placeId,
                'fields' => 'name,formatted_address,rating,user_ratings_total,price_level,editorial_summary,photos,reviews,types,url,website,formatted_phone_number',
                'key' => $this->apiKey
            ]);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $detailsUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $res = curl_exec($ch);
            curl_close($ch);
            $d = json_decode($res, true)['result'] ?? [];

            if (empty($d))
                return $this->getFallbackData();

            // Format photos
            $photos = [];
            if (!empty($d['photos'])) {
                foreach (array_slice($d['photos'], 0, 10) as $p) {
                    $photos[] = "https://maps.googleapis.com/maps/api/place/photo?" . http_build_query([
                        'maxwidth' => 1200,
                        'photoreference' => $p['photo_reference'],
                        'key' => $this->apiKey
                    ]);
                }
            }

            $rating = $d['rating'] ?? 4.0;
            $price_level = $d['price_level'] ?? null;
            $base_price = self::calcPrice($placeId, $rating, $price_level);

            return [
                'name' => $d['name'] ?? 'Luxury Hotel',
                'address' => $d['formatted_address'] ?? 'Sri Lanka',
                'rating' => $rating,
                'reviews_count' => $d['user_ratings_total'] ?? 100,
                'description' => $d['editorial_summary']['overview'] ?? "Welcome to " . ($d['name'] ?? 'this hotel') . ", a premium hospitality destination located in the heart of Sri Lanka. Experience unmatched service and luxury.",
                'photos' => $photos,
                'reviews' => $d['reviews'] ?? [],
                'map_url' => $d['url'] ?? '',
                'website' => $d['website'] ?? '',
                'phone' => $d['formatted_phone_number'] ?? '',
                'amenities' => $this->mapTypesToAmenities($d['types'] ?? []),
                'place_id' => $placeId,
                'price_level' => $price_level,
                // Consistent room types derived from the same base price
                'room_types' => $this->generateRoomTypes($rating, $price_level, $placeId),
                'policies' => $this->generatePolicies($d['name'] ?? 'this hotel')
            ];
        }
        catch (Exception $e) {
            error_log("Google Details Error: " . $e->getMessage());
            return $this->getFallbackData();
        }
    }

    private function generateRoomTypes($rating, $price_level = null, $place_id = '')
    {
        // Use same deterministic price calc so details page matches marketplace card
        $base = self::calcPrice($place_id ?: 'default', $rating, $price_level);
        // Round base to nearest 100 for clean display
        $base = round($base / 100) * 100;

        $types = [
            ['name' => 'Standard Room', 'description' => 'Comfortable room with essential amenities', 'price' => $base],
            ['name' => 'Deluxe Room', 'description' => 'Spacious room with better views and decor', 'price' => round($base * 1.55 / 100) * 100],
        ];

        if ($rating >= 4.5) {
            $types[] = ['name' => 'Luxury Suite', 'description' => 'Premium suite with private lounge access', 'price' => round($base * 2.4 / 100) * 100];
            $types[] = ['name' => 'Presidential Suite', 'description' => 'The ultimate luxury experience', 'price' => round($base * 4.2 / 100) * 100];
        }
        elseif ($rating >= 4.0) {
            $types[] = ['name' => 'Junior Suite', 'description' => 'Elegant suite with separate seating area', 'price' => round($base * 2.0 / 100) * 100];
        }
        return $types;
    }

    private function generatePolicies($name)
    {
        return [
            'check_in' => '14:00',
            'check_out' => '11:00',
            'cancellation' => 'Free cancellation within 24 hours of booking',
            'children' => 'Children of all ages are welcome',
            'pets' => 'Pets are not allowed',
            'payment' => 'Visa, MasterCard, American Express and Cash accepted at ' . $name
        ];
    }

    private function mapTypesToAmenities($types)
    {
        $map = [
            'restaurant' => 'Restaurant',
            'bar' => 'Bar',
            'spa' => 'Spa',
            'gym' => 'Fitness Center',
            'swimming_pool' => 'Pool',
            'parking' => 'Parking',
            'wifi' => 'WiFi'
        ];
        $found = ['WiFi', 'AC', 'Parking']; // Default
        foreach ($types as $t) {
            if (isset($map[$t]))
                $found[] = $map[$t];
        }
        return array_unique($found);
    }

    private function getFallbackData()
    {
        return [
            'name' => 'Luxury Hotel',
            'address' => 'Colombo, Sri Lanka',
            'rating' => 4.2,
            'reviews_count' => 120,
            'description' => 'A beautiful property with modern amenities.',
            'photos' => ["../assets/images/hotel_details/main.jpg"],
            'reviews' => [],
            'amenities' => ['WiFi', 'Pool', 'AC', 'Restaurant']
        ];
    }
}
