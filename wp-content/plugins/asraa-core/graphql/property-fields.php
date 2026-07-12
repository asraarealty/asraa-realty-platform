<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
|--------------------------------------------------------------------------
| Enable Property Post Type in GraphQL
|--------------------------------------------------------------------------
*/

add_filter('register_post_type_args', function ($args, $post_type) {

    if ($post_type === 'property') {
        $args['show_in_graphql'] = true;
        $args['graphql_single_name'] = 'property';
        $args['graphql_plural_name'] = 'properties';
    }

    return $args;

}, 10, 2);

/*
|--------------------------------------------------------------------------
| Register Property GraphQL Fields
|--------------------------------------------------------------------------
*/

add_action('graphql_register_types', function () {

    /*
    |--------------------------------------------------------------------------
    | Basic Property Fields (UPDATED)
    |--------------------------------------------------------------------------
    */

    $basic_fields = [
        'customPropertyId' => '_property_property_id',
        'price' => '_property_price',
        'rooms' => '_property_rooms',
        'beds' => '_property_beds',
        'baths' => '_property_baths',
        'garages' => '_property_garages',
        'yearBuilt' => '_property_year_built',
        'homeArea' => '_property_home_area',
    ];

    foreach ($basic_fields as $field => $meta_key) {
        register_graphql_field('Property', $field, [
            'type' => 'String',
            'resolve' => function ($post) use ($meta_key) {
                return get_post_meta(
                    $post->databaseId,
                    $meta_key,
                    true
                );
            }
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | RERA
    |--------------------------------------------------------------------------
    */

    register_graphql_field('Property', 'reraNumber', [
        'type' => 'String',
        'resolve' => function ($post) {
            return get_post_meta(
                $post->databaseId,
                'custom-text-44',
                true
            );
        }
    ]);

    /*
    |--------------------------------------------------------------------------
    | Location Fields
    |--------------------------------------------------------------------------
    */

    register_graphql_field('Property', 'address', [
        'type' => 'String',
        'resolve' => function ($post) {
            $location = get_post_meta(
                $post->databaseId,
                '_property_map_location',
                true
            );

            if (!is_array($location)) {
                $location = maybe_unserialize($location);
            }

            return $location['address'] ?? '';
        }
    ]);

    register_graphql_field('Property', 'latitude', [
        'type' => 'String',
        'resolve' => function ($post) {
            $location = get_post_meta(
                $post->databaseId,
                '_property_map_location',
                true
            );

            if (!is_array($location)) {
                $location = maybe_unserialize($location);
            }

            return $location['latitude'] ?? '';
        }
    ]);

    register_graphql_field('Property', 'longitude', [
        'type' => 'String',
        'resolve' => function ($post) {
            $location = get_post_meta(
                $post->databaseId,
                '_property_map_location',
                true
            );

            if (!is_array($location)) {
                $location = maybe_unserialize($location);
            }

            return $location['longitude'] ?? '';
        }
    ]);

    /*
    |--------------------------------------------------------------------------
    | Featured Image
    |--------------------------------------------------------------------------
    */

    register_graphql_field('Property', 'featuredImageUrl', [
        'type' => 'String',
        'resolve' => function ($post) {
            $thumbnail_id = get_post_thumbnail_id($post->databaseId);

            return $thumbnail_id
                ? wp_get_attachment_url($thumbnail_id)
                : null;
        }
    ]);

    /*
    |--------------------------------------------------------------------------
    | Gallery
    |--------------------------------------------------------------------------
    */

    register_graphql_field('Property', 'gallery', [
        'type' => ['list_of' => 'String'],
        'resolve' => function ($post) {

            $gallery_key = get_option(
                'asraa_gallery_key',
                '_property_gallery'
            );

            $gallery = get_post_meta(
                $post->databaseId,
                $gallery_key,
                true
            );

            if (empty($gallery)) {
                return [];
            }

            $gallery = maybe_unserialize($gallery);

            if (!is_array($gallery)) {
                return [];
            }

            $images = [];

            foreach ($gallery as $id => $url) {
                if (!empty($url)) {
                    $images[] = $url;
                }
            }

            return $images;
        }
    ]);

    /*
    |--------------------------------------------------------------------------
    | Core Media Fields
    |--------------------------------------------------------------------------
    */

    $media_fields = [
        'brochure' => 'asraa_brochure',
        'model3d' => 'asraa_model_3d',
        'heroVideo' => 'asraa_hero_video',
    ];

    foreach ($media_fields as $field => $meta_key) {
        register_graphql_field('Property', $field, [
            'type' => 'String',
            'resolve' => function ($post) use ($meta_key) {
                return get_post_meta(
                    $post->databaseId,
                    $meta_key,
                    true
                );
            }
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Offer Fields
    |--------------------------------------------------------------------------
    */

    $offer_fields = [
        'developerName' => '_developer_name',
        'monthlyScheme' => '_monthly_scheme',
        'discountOffer' => '_discount_offer',
        'inventoryStatus' => '_inventory_status',
        'offerPopupText' => '_offer_popup_text',
    ];

    foreach ($offer_fields as $field => $meta_key) {
        register_graphql_field('Property', $field, [
            'type' => 'String',
            'resolve' => function ($post) use ($meta_key) {
                return get_post_meta(
                    $post->databaseId,
                    $meta_key,
                    true
                );
            }
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Dynamic Fields
    |--------------------------------------------------------------------------
    */

    $dynamic_fields = get_option('asraa_property_fields', []);

    if (!empty($dynamic_fields)) {

        foreach ($dynamic_fields as $field) {

            if (empty($field['key']) || empty($field['graphql'])) {
                continue;
            }

            $field_key = sanitize_key($field['key']);
            $field_type = $field['type'] ?? 'text';

            register_graphql_field('Property', $field_key, [
                'type' => $field_type === 'gallery'
                    ? ['list_of' => 'String']
                    : 'String',

                'resolve' => function ($post) use ($field_key, $field_type) {

                    $value = get_post_meta(
                        $post->databaseId,
                        $field_key,
                        true
                    );

                    if ($field_type === 'gallery') {

                        if (empty($value)) {
                            return [];
                        }

                        $value = maybe_unserialize($value);

                        if (!is_array($value)) {
                            return [];
                        }

                        $images = [];

                        foreach ($value as $id => $url) {
                            if (!empty($url)) {
                                $images[] = $url;
                            }
                        }

                        return $images;
                    }

                    return $value;
                }
            ]);
        }
    }

});