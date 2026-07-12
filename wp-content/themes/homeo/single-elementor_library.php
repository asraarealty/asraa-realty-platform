<?php
get_header();
?>

<section id="main-container" class="main-content-detail inner">
	<div class="row">

		<div id="main-content" class="col-xs-12">
			<div id="primary" class="content-area">
				<div id="content" class="site-content detail-post" role="main">
					<?php
						// Start the Loop.
						while ( have_posts() ) : the_post();

							the_content();

						// End the loop.
						endwhile;
					?>
				</div><!-- #content -->
			</div><!-- #primary -->
		</div>	
		
	</div>	
</section>
<?php get_footer(); ?>