import $ from 'jquery';
import whatInput from 'what-input';

window.$ = $;

import Foundation from 'foundation-sites';
// If you want to pick and choose which modules to include, comment out the above and uncomment
// the line below
//import './lib/foundation-explicit-pieces';

$(document).foundation();

//$(document).foundation({
//    tab: {
//      callback : function (tab) {
//        $(document).foundation('orbit', 'reflow');
//      }
//    }
//  });

//$('[data-tabs]').on('change.zf.tabs', function() {
//  $('.orbit').foundation();
//});
$(document).ready(function(){
  $(".owl-carousel").owlCarousel({
    loop:true,
    margin:10,
    nav:true,
    responsive:{
        0:{
            items:1
        },
        600:{
            items:1
        },
        1000:{
            items:1
        }
    }
  });
});

/* when product quantity changes, update quantity attribute on add-to-cart button */
$("form.cart").on("change", "input.qty", function() {
    if (this.value === "0")
        this.value = "1";
 
    $(this.form).find("button[data-quantity]").data("quantity", this.value);
});

/* remove old "view cart" text, only need latest one thanks! */
$(document.body).on("adding_to_cart", function() {
    $("a.added_to_cart").remove();
});

if($(window).width() < 769 ) {
   
    $('ul#menu-main-menu').detach().appendTo('#mobile-menu');
    $('#mobile-menu-toggle').on('click', function() {
        $('a.site-mobile-title').toggleClass('expanded');
        $('#mobile-menu').slideToggle();
        //    alert('clicked');
        
    });
} else {
//    $('ul#menu-main-menu').detach().appendTo('.off-canvas-content');
}
