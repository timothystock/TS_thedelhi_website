<?php
/**
 * The template for displaying the header
 *
 * Displays all of the head element and everything up until the "container" div.
 *
 * @package FoundationPress
 * @since FoundationPress 1.0.0
 */

?>
<!doctype html>
<html class="no-js" <?php language_attributes(); ?> >
	<head>
		<meta charset="<?php bloginfo( 'charset' ); ?>" />
		<meta name="viewport" content="width=device-width, initial-scale=1.0" />
		<?php wp_head(); ?>
        
	</head>
	<body <?php body_class(); ?>>

	<div class="off-canvas-wrapper">
    <nav class="mobile-off-canvas-menu off-canvas position-left reveal-for-medium" data-auto-focus="false" role="navigation">
        <div class="off-canvas-content">
            <div class="reveal-for-medium">
                
                <?php if(is_front_page()){ ?>
                <h1 class="site-title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"></a></h1>
                <?php } else { ?>
                    <a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home" class="h1 site-title"><span class="show-for-sr">The Delhi</span></a>
                <?php } ?>
                
            </div>
            <?php foundationpress_mobile_nav(); ?> 
        </div>
    </nav>

    <div class="off-canvas-content" data-off-canvas-content>


        <header class="site-header" role="banner">
            <div class="svg-header"></div>
            <div class="site-title-bar title-bar">
                <div class="title-bar-left">
                    <a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home" class="site-mobile-title title-bar-title">
                        <span class="show-for-sr"><?php bloginfo( 'name' ); ?></span>
                        <?php get_template_part( 'template-parts/svg-logo' ); ?>
                    </a>
                    
                    <button aria-label="<?php _e( 'Main Menu', 'foundationpress' ); ?>" class="text-center menu-icon" type="button" id="mobile-menu-toggle"><span class="show-for-sr">Options</span></button>
                    
                </div>
            </div>
            <div class="top-right">
            
            </div>
            <nav class="site-navigation top-bar hide-for-medium" role="navigation">
                <div class="top-bar-left">
                    <div class="site-desktop-title top-bar-title">
                        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a>
                    </div>
                </div>
                <div class="top-bar-right">
                    <?php foundationpress_top_bar_r(); ?>

                    <?php //if ( ! get_theme_mod( 'wpt_mobile_menu_layout' ) || get_theme_mod( 'wpt_mobile_menu_layout' ) === 'topbar' ) : ?>
                        <?php get_template_part( 'template-parts/mobile-top-bar' ); ?>
                    <?php // endif; ?>
                </div>
            </nav>

        </header>
