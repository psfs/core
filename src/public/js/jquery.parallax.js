/* =================================
 * jQuery parallax v1.0 / Copyright © 2014 Spab Rice
 * All rights reserved.
 ================================= */
function moveParallax(e) {
    var t = jQuery(e).visible(true);
    if (t) {
        var n = parseInt(jQuery(e).offset().top);
        var r = n - jQuery(window).scrollTop();
        var i = -(r / 1.7);
        var s = "50% " + i + "px";
        jQuery(e).css({backgroundPosition: s})
    }
}
(function(e) {
    e.fn.extend({parallax: function(e) {
        return this.each(function() {
            var e = jQuery(this),
                $this = jQuery(this);
            moveParallax(e);
            jQuery(window).scroll(function() {
                if($this.position().top <= jQuery(window).scrollTop()) moveParallax(e)
            })
        })
    }})
})(jQuery)
