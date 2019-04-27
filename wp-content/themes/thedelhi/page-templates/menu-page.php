<?php
/*
Template Name: Menu page
*/
get_header(); ?>

<div class="main-container">
	<div class="main-grid grid-container">
      
        <div class="row cell grid-x">
            <div class="columns cell">
                <div class="menu-page">
                    <ul class="tabs" data-tabs id="food-menu" >
                        <li class="tabs-title is-active"><a href="#appetisers" data-tabs-target="appetisers" aria-selected="true">Appetisers</a></li>
                        <li class="tabs-title"><a data-tabs-target="speciality" href="#speciality">Speciality Dishes &amp; Vegetarian</a></li>
                        <li class="tabs-title"><a data-tabs-target="classic" href="#classic">Classic Mains</a></li>
                        <li class="tabs-title"><a data-tabs-target="sundries" href="#sundries">Bread, Rice &amp; Sundries</a></li>
                    </ul>
                    <div class="tabs-content" data-tabs-content="food-menu">
                      <div class="tabs-panel is-active" id="appetisers" style="max-width:100%">
                          <h2>Appetisers</h2>
                          <div class="" role="region" aria-label="appetisers" style="max-width:100%; overflow-x:hidden;">
<!--
                              <nav class="orbit-bullets">
                                <button class="is-active" data-slide="0">To share</button>
                                <button data-slide="1">Lamb &amp; Chicken</button>
                                <button data-slide="2">Seafood</button>
                                <button data-slide="3">Vegetarian</button>
                              </nav>

                               <div class="orbit-wrapper">-->
                                    <div class="owl-carousel owl-theme ">
<!--                                      <li class="slide">-->
                                        <div class="figure">
                                            <h3>To share</h3>
                                          <?php echo do_shortcode( '[product_category category=appetisers-to-share title=true limit="16"]' ); ?>
                                        </div>
<!--
                                      </li>
                                      <li class="slide">
-->
                                        <div class="figure"><h3>Lamb &amp; chicken</h3>
                                          <?php echo do_shortcode( '[product_category category=appetisers-lamb-chicken title=true limit="16"]' ); ?>
                                        </div>
<!--
                                      </li>
                                      <li class="slide">
-->
                                        <div class="figure"><h3>Seafood</h3>
                                          <?php echo do_shortcode( '[product_category category=appetisers-seafood title=true limit="16"]' ); ?>
                                        </div>
<!--
                                      </li>
                                      <li class="slide">
-->
                                        <div class="figure"><h3>Vegetarian</h3>
                                           <?php echo do_shortcode( '[product_category category=appetisers-vegetarian title=true limit="16"]' ); ?>
                                        </div>
<!--                                      </li>-->
<!--                                    </ul>-->
                                    </div>
                              </div>
                          
                      </div>
                      <div class="tabs-panel" id="speciality">
                          <h2>Specialities</h2>
                          <div class="" role="region" aria-label="speciality" style="max-width:100%; overflow-x:hidden;">
<!--
                              <nav class="orbit-bullets">
                                <button class="is-active" data-slide="0">House Specials</button>
                                <button data-slide="1">Chicken</button>
                                <button data-slide="2">Lamb</button>
                                <button data-slide="3">Seafood</button>
                                <button data-slide="4">Vegetarian</button>
                                <button data-slide="5">Tandoori Baked</button>
                              </nav>
-->
<!--                               <div class="orbit-wrapper">-->
                                    <div class="owl-carousel owl-theme">
                                        
                                        <div class="orbit-figure"><h3>House Specials</h3>
                                          <?php echo do_shortcode( '[product_category category=specialities-house-specials title=true limit="16"]' ); ?>
                                        </div>

                                        <div class="orbit-figure"><h3>Chicken</h3>
                                          <?php echo do_shortcode( '[product_category category=specialities-chicken title=true limit="16"]' ); ?>
                                        </div>

                                        <div class="orbit-figure"><h3>Lamb</h3>
                                          <?php echo do_shortcode( '[product_category category=specialities-lamb title=true limit="16"]' ); ?>
                                        </div>

                                        <div class="orbit-figure"><h3>Seafood</h3>
                                          <?php echo do_shortcode( '[product_category category=specialities-seafood title=true limit="16"]' ); ?>
                                        </div>

                                        <div class="orbit-figure"><h3>Vegetarian</h3>
                                           <?php echo do_shortcode( '[product_category category=specialities-vegetarian title=true limit="16"]' ); ?>
                                        </div>

                                        <div class="orbit-figure"><h3>Tandoori</h3>
                                           <?php echo do_shortcode( '[product_category category=specialities-tandoori title=true limit="16"]' ); ?>
                                        </div>

                                </div>

                            </div>
                          <?php //echo do_shortcode( '[product_category category=specialities ]' ); ?>
                      </div>
                      <div class="tabs-panel" id="classic">
                          <h2>Classic Mains</h2>
                          <div class="" role="region" aria-label="mains" style="max-width:100%; overflow-x:hidden;">
                              <div class="owl-carousel owl-theme">
                                  <div class="slide">
                                      <?php echo do_shortcode( '[product_category category=mains-traditional title=true limit="16"]' ); ?>
                                  </div>
                              </div>
                          </div>
                      </div>
                      <div class="tabs-panel" id="sundries">
                          <h2>Bread, Rice &amp; Sundries</h2>
                          <div class="" role="region" aria-label="sides" style="max-width:100%; overflow-x:hidden;">
                              <div class="owl-carousel owl-theme">
                                  <div class="slide"><h3>Breads</h3>
                                      <?php echo do_shortcode( '[product_category category=sides-breads title=true limit="16"]' ); ?>
                                  </div>
                                  <div class="slide"><h3>Rice</h3>
                                      <?php echo do_shortcode( '[product_category category=sides-rice title=true limit="16"]' ); ?>
                                  </div>
                                  <div class="slide"><h3>Sundries</h3>
                                      <?php echo do_shortcode( '[product_category category=sides-sundries title=true limit="16"]' ); ?>
                                  </div>
                                  <div class="slide"><h3>Wraps</h3>
                                      <?php echo do_shortcode( '[product_category category=sides-wraps title=true limit="16"]' ); ?>
                                  </div>
                              </div>
                          </div>
                      </div>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
</div>

<?php get_footer();