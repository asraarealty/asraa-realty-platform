<?php
/**
 * Property Yelp
 *
 * @package    wp-realestate
 * @author     Habq 
 * @license    GNU General Public License, version 3
 */

if ( ! defined( 'ABSPATH' ) ) {
  	exit;
}

class WP_RealEstate_Property_Google_Places {

	private static $_instance = null;
	private $API_KEY = null;
	private $API_HOST = "https://maps.googleapis.com";
	private $SEARCH_PATH = "/maps/api/place/nearbysearch/json";

	private $SEARCH_LIMIT = 3;

	public static function get_instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	public function __construct() {
		$this->API_KEY = wp_realestate_get_option('api_settings_google_places_api');
	}

	public function request($host, $path, $url_params = array()) {
	    // Send Yelp API Call
	    try {
	        $curl = curl_init();
	        if (FALSE === $curl)
	            throw new Exception('Failed to initialize');

	        $url = $host . $path . "?" . http_build_query($url_params);

	        curl_setopt_array($curl, array(
	            CURLOPT_URL => $url,
	            CURLOPT_RETURNTRANSFER => true,  // Capture response.
	            CURLOPT_ENCODING => "",  // Accept gzip/deflate/whatever.
	            CURLOPT_MAXREDIRS => 10,
	            CURLOPT_TIMEOUT => 30,
	            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	            CURLOPT_CUSTOMREQUEST => "GET",
	            CURLOPT_HTTPHEADER => array(
	                "authorization: Bearer " . $this->API_KEY ,
	                "cache-control: no-cache",
	            ),
	        ));

	        $response = curl_exec($curl);

	        if (FALSE === $response)
	            throw new Exception(curl_error($curl), curl_errno($curl));
	        $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
	        if (200 != $http_status)
	            throw new Exception($response, $http_status);

	        curl_close($curl);
	    } catch(Exception $e) {
	        trigger_error(sprintf(
	            'Curl failed with error #%d: %s',
	            $e->getCode(), $e->getMessage()),
	            E_USER_ERROR);
	    }

	    return $response;
	}

	public function search($term, $location = '', $radius = '') {
	    $url_params = array();
	    
	    $url_params['type'] = $term;
	    if ( $location ) {
		    $url_params['location'] = $location;
		}
		if ( $radius ) {
		    $url_params['radius'] = $radius;
		}
	    $url_params['key'] = $this->API_KEY;
	    
	    return $this->request($this->API_HOST, $this->SEARCH_PATH, $url_params);
	}


	public static function get_categories() {
        return apply_filters( 'wp-realestate-get-google-places-categories', array(
            'accounting' => esc_html__('Accounting', 'wp-realestate'),
            'airport' => esc_html__('Airport', 'wp-realestate'),
            'amusement_park' => esc_html__('Amusement park', 'wp-realestate'),
            'aquarium' => esc_html__('Aquarium', 'wp-realestate'),
            'art_gallery' => esc_html__('Art Gallery', 'wp-realestate'),
            'atm' => esc_html__('Atm', 'wp-realestate'),
            'bakery' => esc_html__('Bakery', 'wp-realestate'),
            'bank' => esc_html__('Bank', 'wp-realestate'),
            'bar' => esc_html__('Bar', 'wp-realestate'),
            'beauty_salon' => esc_html__('Beauty Salon', 'wp-realestate'),
            'bicycle_store' => esc_html__('Bicycle Store', 'wp-realestate'),
            'book_store' => esc_html__('Book Store', 'wp-realestate'),
            'bowling_alley' => esc_html__('Bowling alley', 'wp-realestate'),
            'bus_station' => esc_html__('Bus Station', 'wp-realestate'),
            'cafe' => esc_html__('Cafe', 'wp-realestate'),
            'campground' => esc_html__('Campground', 'wp-realestate'),
            'car_dealer' => esc_html__('Car dealer', 'wp-realestate'),
            'car_rental' => esc_html__('Car rental', 'wp-realestate'),
            'car_repair' => esc_html__('Car repair', 'wp-realestate'),
            'car_wash' => esc_html__('Car wash', 'wp-realestate'),
            'casino' => esc_html__('Casino', 'wp-realestate'),
            'cemetery' => esc_html__('Cemetery', 'wp-realestate'),
            'church' => esc_html__('Church', 'wp-realestate'),
            'city_hall' => esc_html__('City hall', 'wp-realestate'),
            'clothing_store' => esc_html__('Clothing store', 'wp-realestate'),
            'convenience_store' => esc_html__('Convenience store', 'wp-realestate'),
            'courthouse' => esc_html__('Courthouse', 'wp-realestate'),
            'doctor' => esc_html__('Doctor', 'wp-realestate'),
            'drugstore' => esc_html__('Drugstore', 'wp-realestate'),
            'electrician' => esc_html__('Electrician', 'wp-realestate'),
            'electronics_store' => esc_html__('Electronics store', 'wp-realestate'),
            'embassy' => esc_html__('Embassy', 'wp-realestate'),
            'fire_station' => esc_html__('Fire station', 'wp-realestate'),
            'florist' => esc_html__('Florist', 'wp-realestate'),
            'funeral_home' => esc_html__('Funeral home', 'wp-realestate'),
            'furniture_store' => esc_html__('Furniture store', 'wp-realestate'),
            'gas_station' => esc_html__('Gas station', 'wp-realestate'),
            'gym' => esc_html__('Gym', 'wp-realestate'),
            'hair_care' => esc_html__('Hair care', 'wp-realestate'),
            'hardware_store' => esc_html__('Hardware store', 'wp-realestate'),
            'hindu_temple' => esc_html__('Hindu temple', 'wp-realestate'),
            'home_goods_store' => esc_html__('Home goods store', 'wp-realestate'),
            'hospital' => esc_html__('Hospital', 'wp-realestate'),
            'insurance_agency' => esc_html__('Insurance agency', 'wp-realestate'),
            'jewelry_store' => esc_html__('Jewelry store', 'wp-realestate'),
            'laundry' => esc_html__('Laundry', 'wp-realestate'),
            'lawyer' => esc_html__('Lawyer', 'wp-realestate'),
            'library' => esc_html__('Library', 'wp-realestate'),
            'light_rail_station' => esc_html__('Light rail station', 'wp-realestate'),
            'liquor_store' => esc_html__('Liquor store', 'wp-realestate'),
            'local_government_office' => esc_html__('Local government office', 'wp-realestate'),
            'locksmith' => esc_html__('Locksmith', 'wp-realestate'),
            'lodging' => esc_html__('Lodging', 'wp-realestate'),
            'meal_delivery' => esc_html__('Meal delivery', 'wp-realestate'),
            'meal_takeaway' => esc_html__('Meal takeaway', 'wp-realestate'),
            'mosque' => esc_html__('Mosque', 'wp-realestate'),
            'movie_rental' => esc_html__('Movie rental', 'wp-realestate'),
            'movie_theater' => esc_html__('Movie theater', 'wp-realestate'),
            'moving_company' => esc_html__('Movie company', 'wp-realestate'),
            'museum' => esc_html__('Museum', 'wp-realestate'),
            'night_club' => esc_html__('Night club', 'wp-realestate'),
            'painter' => esc_html__('Painter', 'wp-realestate'),
            'park' => esc_html__('Park', 'wp-realestate'),
            'parking' => esc_html__('Parking', 'wp-realestate'),
            'pet_store' => esc_html__('Pet store', 'wp-realestate'),
            'pharmacy' => esc_html__('Pharmacy', 'wp-realestate'),
            'physiotherapist' => esc_html__('Physiotherapist', 'wp-realestate'),
            'plumber' => esc_html__('Plumber', 'wp-realestate'),
            'police' => esc_html__('Police', 'wp-realestate'),
            'post_office' => esc_html__('Post office', 'wp-realestate'),
            'primary_school' => esc_html__('Primary school', 'wp-realestate'),
            'real_estate_agency' => esc_html__('Real estate agency', 'wp-realestate'),
            'restaurant' => esc_html__('Restaurant', 'wp-realestate'),
            'roofing_contractor' => esc_html__('Roofing contractor', 'wp-realestate'),
            'rv_park' => esc_html__('Rv park', 'wp-realestate'),
            'school' => esc_html__('School', 'wp-realestate'),
            'secondary_school' => esc_html__('Secondary school', 'wp-realestate'),
            'shoe_store' => esc_html__('Shoe store', 'wp-realestate'),
            'shopping_mall' => esc_html__('Shopping mall', 'wp-realestate'),
            'spa' => esc_html__('Spa', 'wp-realestate'),
            'stadium' => esc_html__('Stadium', 'wp-realestate'),
            'storage' => esc_html__('Storage', 'wp-realestate'),
            'store' => esc_html__('Store', 'wp-realestate'),
            'subway_station' => esc_html__('Subway station', 'wp-realestate'),
            'supermarket' => esc_html__('Supermarket', 'wp-realestate'),
            'synagogue' => esc_html__('Synagogue', 'wp-realestate'),
            'taxi_stand' => esc_html__('Taxi stand', 'wp-realestate'),
            'tourist_attraction' => esc_html__('Tourist attraction', 'wp-realestate'),
            'train_station' => esc_html__('Train station', 'wp-realestate'),
            'transit_station' => esc_html__('Transit station', 'wp-realestate'),
            'travel_agency' => esc_html__('Travel agency', 'wp-realestate'),
            'university' => esc_html__('University', 'wp-realestate'),
            'veterinary_care' => esc_html__('Veterinary care', 'wp-realestate'),
            'zoo' => esc_html__('Zoo', 'wp-realestate'),
        ));
    }

    public static function get_yelp_star_img($star) {
		switch ($star) {
			case '1':
			case '2':
			case '3':
			case '4':
			case '5':
				$class = 'regular_'.$star.'.png';
				break;
			case '1.5':
				$class = 'regular_1_half.png';
				break;
			case '2.5':
				$class = 'regular_2_half.png';
				break;
			case '3.5':
				$class = 'regular_3_half.png';
				break;
			case '4.5':
				$class = 'regular_4_half.png';
				break;
			default:
				$class = 'regular_0.png';
				break;
		}
		return apply_filters( 'homesweet_get_yelp_star_img', $class, $star );
	}
}
