<?php

foreach ($terms as $term) {
    $cate_title = !empty($term['google_places_title']) ? $term['google_places_title'] : $term['google_places_category'];
    $cate_icon = !empty($term['google_places_icon']) ? $term['google_places_icon'] : '';
    $cate_term = '';

    if (array_key_exists($term['google_places_category'], $all_cats)) {
        $cate_term = str_replace('-', '+', $term['google_places_category']);
    }
    $google_places = WP_RealEstate_Property_Google_Places::get_instance();
    $businesses_data = $google_places->search($cate_term, '-33.8670522,151.1957362', 1500);
    if ( !empty($businesses_data->results) ) {
        
        ?>
        <div class="yelp-list">
            <div class="yelp-list-cat">
                <div class="yelp-cat-title">
                    <?php if ( !empty($cate_icon) ) { ?>
                        <img src="<?php echo esc_url($cate_icon); ?>" alt="">
                    <?php } ?>
                    <?php echo esc_html($cate_title); ?>
                </div>
                <div class="yelp-cat-content">
                    <ul class="yelp-list-sub">
                        <?php
                        $i = 0;
                        foreach ($businesses_data->results as $data_business) {
                            if ( $i >= $limit ) {
                                break;
                            }
                            $business_name = isset($data_business->name) ? $data_business->name : '';
                            $business_total_reviews = isset($data_business->user_ratings_total) ? $data_business->user_ratings_total : '';
                            $business_rating = isset($data_business->rating) ? $data_business->rating : '';

                            ?>
                            <li>
                                <div class="yelp-list-inner">
                                    <div class="inner-left">
                                        <div class="yelp-item-title">
                                            <?php echo esc_html($business_name); ?>
                                            
                                        </div>
                                    </div>
                                    <div class="inner-right">
                                        <div class="rating">
                                            <div class="average-rating">
                                                <div class="average-inner" style="width: <?php echo round(($business_rating/5 * 100), 2).'%'; ?>"></div>
                                            </div>
                                        </div>
                                        <span class="rating-count"><?php echo sprintf(_n('%d Review', '%d Reviews', absint($business_total_reviews), 'wp-realestate'), absint($business_total_reviews)); ?></span>
                                    </div>
                                </div>
                            </li>
                        <?php
                            $i++;
                        }
                        ?>
                    </ul>
                </div>
            </div>
        </div>
    <?php
    }
    ?>
    
<?php
}