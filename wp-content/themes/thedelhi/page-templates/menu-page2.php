<?php
/*
Template Name: Menu page v2
*/
get_header(); ?>

<div class="main-container">
	<div class="main-grid grid-container">
      
        <div class="row grid-x grid-padding-x" data-sticky-container>
            <div class="columns cell medium-6">
                <div class="menu-page">
                   
                      <div class="" id="appetisers">
                          <h2>Appetisers</h2>
                          <div class="" role="region" aria-label="appetisers" style="max-width:100%; overflow-x:hidden;">

                                <div class="figure">
                                <h3>To share</h3>
                                  <?php echo do_shortcode( '[product_category category=appetisers-to-share title=true limit="16"]' ); ?>
                                </div>

                                <div class="figure"><h3>Lamb &amp; chicken</h3>
                                  <?php echo do_shortcode( '[product_category category=appetisers-lamb-chicken title=true limit="16"]' ); ?>
                                </div>

                                <div class="figure"><h3>Seafood</h3>
                                  <?php echo do_shortcode( '[product_category category=appetisers-seafood title=true limit="16"]' ); ?>
                                </div>

                                <div class="figure"><h3>Vegetarian</h3>
                                   <?php echo do_shortcode( '[product_category category=appetisers-vegetarian title=true limit="16"]' ); ?>
                                </div>

                          </div>
                          
                      </div>
                      <div class="" id="speciality">
                          <h2>Specialities</h2>
                          <div class="" role="region" aria-label="speciality" style="max-width:100%; overflow-x:hidden;">

                                        
                                <div class="figure"><h3>House Specials</h3>
                                  <?php echo do_shortcode( '[product_category category=specialities-house-specials title=true limit="16"]' ); ?>
                                </div>

                                <div class="figure"><h3>Chicken</h3>
                                  <?php echo do_shortcode( '[product_category category=specialities-chicken title=true limit="16"]' ); ?>
                                </div>

                                <div class="figure"><h3>Lamb</h3>
                                  <?php echo do_shortcode( '[product_category category=specialities-lamb title=true limit="16"]' ); ?>
                                </div>

                                <div class="figure"><h3>Seafood</h3>
                                  <?php echo do_shortcode( '[product_category category=specialities-seafood title=true limit="16"]' ); ?>
                                </div>

                                <div class="figure"><h3>Vegetarian</h3>
                                   <?php echo do_shortcode( '[product_category category=specialities-vegetarian title=true limit="16"]' ); ?>
                                </div>

                                <div class="figure"><h3>Tandoori</h3>
                                   <?php echo do_shortcode( '[product_category category=specialities-tandoori title=true limit="16"]' ); ?>
                                </div>

                             

                            </div>
                      </div>
                      <div class="" id="classic">
                          <h2>Classic Mains</h2>
                          <div class="" role="region" aria-label="mains" style="max-width:100%; overflow-x:hidden;">
                             
                              <div class="figure">
                                  <?php echo do_shortcode( '[product_category category=mains-traditional title=true limit="16"]' ); ?>
                              </div>
                             
                          </div>
                      </div>
                      <div class="" id="sundries">
                          <h2>Bread, Rice &amp; Sundries</h2>
                          <div class="" role="region" aria-label="sides" style="max-width:100%; overflow-x:hidden;">
                             
                              <div class="figure"><h3>Breads</h3>
                                  <?php echo do_shortcode( '[product_category category=sides-breads title=true limit="16"]' ); ?>
                              </div>
                              <div class="figure"><h3>Rice</h3>
                                  <?php echo do_shortcode( '[product_category category=sides-rice title=true limit="16"]' ); ?>
                              </div>
                              <div class="figure"><h3>Sundries</h3>
                                  <?php echo do_shortcode( '[product_category category=sides-sundries title=true limit="16"]' ); ?>
                              </div>
                              <div class="figure"><h3>Wraps</h3>
                                  <?php echo do_shortcode( '[product_category category=sides-wraps title=true limit="16"]' ); ?>
                              </div>
                              
                          </div>
                      </div>
                   
                </div>
            </div>
            <div class="cell medium-6"  data-sticky-container>
            
                <div id="Basket" class="sticky"  data-sticky data-margin-top="0"><?php echo do_shortcode( '[woocommerce_cart]' ); ?></div>
                  
            </div>
        </div>
        
    </div>
</div>
<?php get_footer();