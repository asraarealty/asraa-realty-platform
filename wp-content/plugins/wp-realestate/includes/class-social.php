<?php
/**
 * Social: Social
 *
 * @package    wp-realestate
 * @author     Habq 
 * @license    GNU General Public License, version 3
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WP_RealEstate_Social {
    /**
     * Initialize social
     *
     * @access public
     * @return void
     */
    public static function init() {
        add_action( 'wp_head', array( __CLASS__, 'open_graph_meta' ), 1 );
    }

    /**
     * The Open Graph protocol meta
     * http://ogp.me/
     *
     * @access public
     * @return string
     */
    public static function open_graph_meta() {
        // Rank Math already outputs a complete, correct Open Graph block —
        // this fallback was firing unconditionally alongside it, producing
        // a duplicate/conflicting og:title ahead of Rank Math's own.
        if ( defined( 'RANK_MATH_VERSION' ) ) {
            return;
        }

        if ( is_singular() ) {
            echo '<meta property="og:title" content="' . esc_attr( get_the_title() ) . '" />';
            $thumbnail_id = get_post_thumbnail_id();
            if ( ! empty( $thumbnail_id ) ) {
                $image = wp_get_attachment_image_src( $thumbnail_id, 'full' );
                if ( !empty($image[0]) ) {
                    echo '<meta property="og:image" content="' . esc_attr( $image[0] ) . '" />';
                }
            }
        }
    }
}

WP_RealEstate_Social::init();