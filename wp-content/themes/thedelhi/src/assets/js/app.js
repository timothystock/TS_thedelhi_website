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
//   $(".owl-carousel").owlCarousel({
//     loop:true,
//     margin:10,
//     nav:true,
//     responsive:{
//         0:{
//             items:1
//         },
//         600:{
//             items:1
//         },
//         1000:{
//             items:1
//         }
//     }
//   });
    if($(window).width() < 768 ) {
        $('#Basket').detach().appendTo('#basket-reveal-wrapper');
    } else {
        $('#Basket').detach().appendTo('#basket-sticky-wrapper');
    }
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
$('#mobile-menu-toggle').on('click', function() {
    $('a.site-mobile-title').toggleClass('expanded');
    $('#mobile-menu').slideToggle();
    //    alert('clicked');

});
if($(window).width() < 1024 ) {
   
    $('ul#menu-main-menu').detach().appendTo('#mobile-menu');
    
} else {
//    $('ul#menu-main-menu').detach().appendTo('.off-canvas-content');
}


$('.td-quantity-button').on('click', function () {
    var $this = $(this);
    var $input = $this.parent().find('input');
    var $quantity = $input.val();
    var $new_quantity = 0;
    if ($this.hasClass('plus')) {
        var $new_quantity = parseFloat($quantity) + 1;
    } else {
        if ($quantity > 0) {
            var $new_quantity = parseFloat($quantity) - 1;
        }
    }
    $input.val($new_quantity);
    $input.trigger( 'change' );
});

//$('.region-wrapper .region').hide();
//$('.region-wrapper > h2').on('click', function() {
//    $(this).toggleClass('expanded');
//    $(this).next('.region').slideToggle();
//    var regions = $(this).parent('.region-wrapper').siblings('.region-wrapper');
//    $(regions).find('h2').removeClass('expanded');
//    $(regions).find('.region').hide();
//});
 

// (function($) {
    //expand and collapse menu page sections
    $('.figure .woocommerce').hide();
    $('.figure > h3').on('click', function() {
        $(this).toggleClass('expanded');
        $(this).parent('.figure').find('.woocommerce').slideToggle();
        var figures = $(this).parent('.figure').siblings('.figure');
        $(figures).find('h3.expanded').removeClass('expanded').siblings('.woocommerce').slideToggle();
    //    $(figures).find('h3').removeClass('expanded');
    //    $(figures).find('.woocommerce').hide();
    });
    //expand and collapse checkout page sections
    

    // $('.checkout_section .checkout_section_content').hide();
   
    // $('.checkout_section > h2').on('click', function() {
    //     $(this).toggleClass('expanded');
    //     $(this).parent('.checkout_section').find('.checkout_section_content').slideToggle();
    //     var sections = $(this).parent('.checkout_section').siblings('.checkout_section');
    //     $(sections).find('h2.expanded').removeClass('expanded').siblings('.checkout_section').slideToggle();
    // });
// })(jQuery);

function equalheightowl() { 
   
        if( $("#home_features").children('div').length > 1) {
            $("#home_features").owlCarousel({
                dots: true,
                nav:false,
                items:1,
                autoHeight: true,
                autoplay: 3600    
            }); 
        } else {
              $("#home_features").owlCarousel({
                 items: 1,
                  nav:false,
                  dots: false,
                  loop: false,
                  mouseDrag: false,
                  touchDrag: false
                  // autoplay: 3600
            });	
        }
        if( $("#quotes_carousel").children('div').length > 1) {
            $("#quotes_carousel").owlCarousel({
                dots: true,
                nav:false,
                items:1,
                autoHeight: true,
                autoplay: 3600,
                autoplaySpeed: 1200,
                loop: true,    
            }); 
        } else {
              $("#quotes_carousel").owlCarousel({
                 items: 1,
                  nav:false,
                  dots: false,
                  loop: false,
                  mouseDrag: false,
                  touchDrag: false
                  // autoplay: 3600
            });	
        }

  // $("#homeslider").owlCarousel({
  //     dots: true,
  //     nav:false,
  //     items:1,
  //     autoHeight: true     
  // });  
  $('.owl-stage').each(function(index) {
        var maxHeight = 0;
        $(this).children().each(function(index) {
            if($(this).height() > maxHeight) 
                maxHeight = $(this).height();
        });
        $(this).children().height(maxHeight);
    });
};
// });



$(window).load(function() {
//   mobileBreakpoint();
//   equalheight();
  equalheightowl();
  // $('.button').addClass('btn')

});

//  $(window).resize(function() {equalheight();equalheightowl();});


$(window).resize( function() {
  if ($("input").is(":focus")) {
      // input is in focus, don't do nothing or input loses focus and keyboard dissapears
  } else {
      // do whatever you should do if resize happens
    //   mobileBreakpoint();
    //   equalheight();
      equalheightowl();
  }
});
