<?php
/*
Template Name: Menu page v3
*/
get_header(); ?>

<div class="main-container">
	<div class="main-grid grid-container">
      
        <div class="row grid-x grid-padding-x" data-sticky-container>
            <div class="columns cell medium-7">
                <div class="menu-page">
                   
                      <div class="" id="starters">
                          <h2>Starters &amp; Street Plates</h2>
                          <div class="" role="region" aria-label="starters" style="max-width:100%; overflow-x:hidden;">

                                <div class="figure">
                                <h3>Street Plates</h3>
                                  <?php echo do_shortcode( '[product_category category=street-plates title=true limit="16"]' ); ?>
                                </div>

                                <div class="figure"><h3>Classic Starters</h3>
                                  <?php echo do_shortcode( '[product_category category=classic-starters title=true limit="16"]' ); ?>
                                </div>

                        
                          </div>
                          
                      </div>
                      <div class="" id="delhi-mains">
                          <h2>Specialities</h2>
                          <div class="" role="region" aria-label="Delhi Main Event" style="max-width:100%; overflow-x:hidden;">

                                        
                                <div class="figure"><h3>Bangladeshi Babouchi</h3>
                                  <?php echo do_shortcode( '[product_category category=bangladeshi title=true limit="16"]' ); ?>
                                </div>

                                <div class="figure"><h3>The Indian Chef</h3>
                                  <?php echo do_shortcode( '[product_category category=indian-chef title=true limit="16"]' ); ?>
                                </div>

                                <div class="figure"><h3>Born in Birmingham</h3>
                                  <?php echo do_shortcode( '[product_category category=birmingham title=true limit="16"]' ); ?>
                                </div>

                                <div class="figure"><h3>British Indian Kitchen</h3>
                                  <?php echo do_shortcode( '[product_category category="british-indian-kitchen" title=true limit="16"]' ); ?>
                                </div>

                                <div class="figure"><h3>Tandoori</h3>
                                   <?php echo do_shortcode( '[product_category category=tandoori title=true limit="16"]' ); ?>
                                </div>

                             

                             

                            </div>
                      </div>
                     
                      <div class="" id="sides">
                          <h2>On the side</h2>
                          <div class="" role="region" aria-label="sides" style="max-width:100%; overflow-x:hidden;">
                             
                              <div class="figure"><h3>Cards</h3>
                                  <?php echo do_shortcode( '[product_category category=carbs title=true limit="16"]' ); ?>
                              </div>
                             
                              <div class="figure"><h3>Little dishes</h3>
                                  <?php echo do_shortcode( '[product_category category=little-dishes title=true limit="16"]' ); ?>
                              </div>
                              
                          </div>
                      </div>
                   
                </div>
            </div>
            <div class="cell medium-5"  data-sticky-container>
            
                <div id="Basket" class="sticky"  data-sticky data-margin-top="2"><?php echo do_shortcode( '[woocommerce_cart]' ); ?></div>
                  
            </div>
        </div>
        
    </div>
</div>
<?php get_footer();