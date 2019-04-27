<?php
/*
Template Name: Homepage
*/
get_header(); ?>
<style>
	@import url('https://fonts.googleapis.com/css?family=Caveat');
</style>



<?php get_template_part( 'template-parts/featured-image' ); ?>
<!-- <div class="main-container expanded"> -->
	<div class="main-grid ">
        <div class="cell auto">
			<main class="homepage">
				<div class="grid-x grid-padding-x ">
					<div class="cell medium-8">
						
						<?php if (have_rows('features')) : ?>
							<div id="home_features" class="owl-carousel">
								<?php while (have_rows('features')): the_row(); 
									$image = get_sub_field('feature_image');
								?>

									<div class="image-wrapper"><img src="<?php echo $image['url']; ?>"/></div>

								<?php endwhile; ?>
							</div>
						<?php endif; ?>
					
					</div>
					<div class="cell medium-4">
						<div class="grid-y  medium-grid-frame">
							<div class="cell medium-4 text-center bg_shape1_line_pink align-middle">	
								<a href="/reservations" class="bg_shape_content_wrapper">
								<div class="bg_shape_content">
									<h3>Book your table now!</h3>
								</div>
								</a>
							</div>
							<div class="cell medium-4">	
								<?php if(have_rows('quotes','options')): ?>
									<div id="quotes_carousel" class="owl-carousel">
										<?php while(have_rows('quotes','options')): the_row(); ?>
											<div class="quote-text">
												<?php the_sub_field('quote');?>
											</div>
										<?php endwhile; ?>
									</div>
								<?php endif; ?>
							</div>
							<div class="cell medium-4">	
								<?php 
									$image = get_field('small_feature_image'); 
									$link = get_field('small_feature_link');
								?>
								<a class="feature_image_link" href="<?php echo $link['url']; ?>">
									<div class="feature_image_wrapper">
										<img src="<?php echo $image['url']; ?>" alt="<?php echo $image['alt']; ?>" />
									</div>
								</a>
							</div>
						</div>
					</div>
					<!-- <div class="cell">
						<?php while ( have_posts() ) : the_post(); ?>
							<?php the_content(); ?>
							<?php // get_template_part( 'template-parts/content', 'page' ); ?>
						<?php endwhile; ?>
					</div> -->
					
			</main>
        
		</div>
	</div>
<!-- </div> -->
<?php
get_footer();