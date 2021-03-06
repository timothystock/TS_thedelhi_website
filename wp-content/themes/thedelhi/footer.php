<?php
/**
 * The template for displaying the footer
 *
 * Contains the closing of the "off-canvas-wrap" div and all content after.
 *
 * @package FoundationPress
 * @since FoundationPress 1.0.0
 */
?>

    <footer class="footer">
        <!-- <div class="footer-container"> -->
            <div class="footer-grid">
                <?php dynamic_sidebar( 'footer-widgets' ); ?>
                    <!-- <div class="cell medium-4">
						Trip advisor
					</div>
					<div class="cell medium-4">
						Trip Advisor quotes
					</div>
					<div class="cell medium-4">
						Best of Solihull
					</div> -->
            </div>
        <!-- </div> -->
    </footer>
	<!-- </div>  -->
	</div><!-- Close off-canvas content -->
</div>
<script type='text/javascript' src="https://cdnjs.cloudflare.com/ajax/libs/foundation/6.4.3/js/foundation.min.js"></script>
<?php wp_footer(); ?>

</body>
</html>