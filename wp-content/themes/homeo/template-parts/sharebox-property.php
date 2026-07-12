<?php
global $post;

if ( !homeo_get_config('show_property_social_share', true) ) {
	return;
}
?>
<div class="social-property">
	<a href="javascript:void(0);" class="btn-add-social">
		<i class="flaticon-share"></i>
		<span><?php esc_html_e('Share', 'homeo'); ?></span>
	</a>
	<div class="bo-social-icons">
		<?php if ( homeo_get_config('facebook_share', 1) ): ?>

 			<a class="bo-social-facebook" data-toggle="tooltip" data-original-title="Facebook" href="http://www.facebook.com/sharer.php?s=100&u=<?php the_permalink(); ?>" target="_blank" title="<?php echo esc_html__('Share on facebook', 'homeo'); ?>">
				<i class="fab fa-facebook-f"></i>
			</a>

		<?php endif; ?>
		<?php if ( homeo_get_config('twitter_share', 1) ): ?>
 			<a class="bo-social-twitter" data-toggle="tooltip" data-original-title="Twitter" href="https://twitter.com/intent/tweet?url=<?php the_permalink(); ?>" target="_blank" title="<?php echo esc_html__('Share on Twitter', 'homeo'); ?>">
				<i class="fab fa-x-twitter"></i>
			</a>
		<?php endif; ?>
		<?php if ( homeo_get_config('linkedin_share', 1) ): ?>
 			<a class="bo-social-linkedin"  data-toggle="tooltip" data-original-title="LinkedIn" href="http://linkedin.com/shareArticle?mini=true&amp;url=<?php the_permalink(); ?>&amp;title=<?php the_title(); ?>" target="_blank" title="<?php echo esc_html__('Share on LinkedIn', 'homeo'); ?>">
				<i class="fab fa-linkedin-in"></i>
			</a>

		<?php endif; ?>
		
		<?php if ( homeo_get_config('pinterest_share', 1) ): ?>
 			<a class="bo-social-pinterest" data-toggle="tooltip" data-original-title="Pinterest" href="http://pinterest.com/pin/create/button/?url=<?php echo urlencode(get_permalink()); ?>&amp;media=<?php echo urlencode($img); ?>" target="_blank" title="<?php echo esc_html__('Share on Pinterest', 'homeo'); ?>">
				<i class="fab fa-pinterest-p"></i>
			</a>

		<?php endif; ?>

	</div>
</div>