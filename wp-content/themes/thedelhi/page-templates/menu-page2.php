<?php
/*
Template Name: Menu page v2
*/
get_header(); ?>
<script type="text/javascript">
(function($) {
    $('#call-us-now-button').hide();

    // $('.figure .woocommerce').hide();
    // $('.figure > h3').on('click', function() {
    //     $(this).toggleClass('expanded');
    //     $(this).parent('.figure').find('.woocommerce').slideToggle();
    //     var figures = $(this).parent('.figure').siblings('.figure');
    //     $(figures).find('h3.expanded').removeClass('expanded').siblings('.woocommerce').slideToggle();
    //    $(figures).find('h3').removeClass('expanded');
    //    $(figures).find('.woocommerce').hide();
    // });
})(jQuery);
</script>
<div class="main-container">
	<div class="main-grid grid-container">
      
        <div class="row grid-x grid-padding-x" data-sticky-container>
            <div class="columns cell medium-7">
                <div id="menu-page" class="menu-page">
                    <?php do_action( 'woocommerce_before_cart' ); ?>
                      <div class="region-wrapper" id="deals">
                          <h2>Meal Deals</h2>
                          <div class="region" role="region" aria-label="deals" style="max-width:100%; overflow-x:hidden;">
                             
                              <div class="figure"><h3>Meal Deals</h3>
                                  <?php echo do_shortcode( '[product_category category=meal-deals title=true limit="160"]' ); ?>
                              </div>
                             
                          </div>
                      </div>
                      <div class="region-wrapper" id="appetisers">
                          <h2>Appetisers</h2>
                          <div class="region" role="region" aria-label="appetisers" style="max-width:100%; overflow-x:hidden;">

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
                      <div class="region-wrapper" id="speciality">
                          <h2>Specialities</h2>
                          <div class="region" role="region" aria-label="speciality" style="max-width:100%; overflow-x:hidden;">

                                        
                                <!-- <div class="figure"><h3>House Specials</h3>
                                  <?php echo do_shortcode( '[product_category category=specialities-house-specials title=true limit="16"]' ); ?>
                                </div> -->

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
                      <div class="region-wrapper" id="classic">
                          <h2>Classic Mains</h2>
                          <div class="region" role="region" aria-label="mains" style="max-width:100%; overflow-x:hidden;">
                             
                              <div class="figure"><h3>Traditional</h3>
                                  <?php echo do_shortcode( '[product_category category=mains title=true limit="16"]' ); ?>
                              </div>
                             
                          </div>
                      </div>
                      <div class="region-wrapper" id="sundries">
                          <h2>Bread, Rice &amp; Sundries</h2>
                          <div class="region" role="region" aria-label="sides" style="max-width:100%; overflow-x:hidden;">
                             
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
            <div class="cell medium-5"  data-sticky-container>
            <a href="#" id="basket-reveal-button" data-open="basket-reveal-wrapper" class="button hide-for-medium mobile-footer-button">View Basket</a>
                <div id="basket-sticky-wrapper" class="sticky" data-sticky data-options="marginTop:4rem;marginBtm:11rem;" data-sticky-on="medium" data-top-anchor="menu-page:top" data-update-history="true">
                    <div id="Basket" ><?php echo do_shortcode( '[woocommerce_cart]' ); ?></div>
                </div>
            </div>
        </div>
        
    </div>
</div>
<div id="basket-reveal-wrapper" class="reveal" data-reveal aria-labelledby="basket-reveal-wrapper" aria-hidden="true" role="dialog">
    
</div>

<?php get_footer();