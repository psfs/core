/**
 * EFFECTOS
 */
(function($) {

    $.fn.scaleVideo = function() {
        return this.each(function(){
            var $video = $(this),
                videoTag = $video.get(0),
                videoRatio = videoTag.videoWidth / videoTag.videoHeight,
                tagRatio = $video.width() / $video.height(),
                margin = $video.parent().attr("data-margin") || 50,
                canvas = $("<canvas>"),
                id = $video.attr("id"),
                _h = $video.height(),
                _w = $video.width();

            canvas.attr("id", id + "_canvas").attr("height", _h * tagRatio);
            $video.after(canvas);
            convert_to_canvas(id, id + "_canvas");
            $video.css("visibility", "hidden");

//            if (videoRatio < tagRatio) {
//                $video.css('-webkit-transform','scaleX(' + tagRatio / videoRatio  + ')')
//            } else if (tagRatio < videoRatio) {
//                $video.css('-webkit-transform','scaleY(' + videoRatio / tagRatio  + ')')
//            }
            $video.parent().find(".video-text").css("margin-top", "-" + ($video.height() * margin / 100) + "px");
        });
    };

    $.fn.showDelay = function(options){
        var settings = $.extend({
            delay: 1000,
            offset: 0
        }, options);

        return this.each(function(){
            var $this = $(this),
                easing = $this.attr("data-effect") || $this.find("[data-effect]").atrt("data-effect") || "slide",
                scroll = $(window).scrollTop() + $(window).height();

            if(scroll >= $this.offset().top && undefined === $this.attr("data-loaded"))
            {
                $this.attr("data-loaded", true).children().show({
                    effect: easing,
                    duration: settings.delay,
                    easing: "easeInQuart",
                    queue: false
                });
            }
        });
    };

    $.fn.slideLateral = function(options){
        var settings = $.extend({
                delay: 2000
            }, options);

        return this.each(function(){
            var $this = $(this),
                scroll = $(window).scrollTop() + ($(window).height() * 2 / 3);

            if(scroll >= $this.offset().top)
            {
                $this.show("slide", settings.delay);
            }
        });
    };

    $.fn.sameHeight = function(){
        return this.each(function(){
            var max_height = 0;
            $(this).children().filter(function(i){
                return !$(this).hasClass("clearfix");
            }).each(function(){
                if($(this).height() > max_height) max_height = $(this).height();
            }).css("height", (max_height + 20) + "px");

        });
    };

    $.fn.counter = function(options){
        var settting = $.extend({
            delay: 50,
            increment: 1
        }, options);
        return this.each(function(){
            var $this = $(this),
                max = parseInt($this.attr("data-counter")),
                actual = parseInt($this.text()),
                increment = parseInt($this.attr("data-increment")) || settting.increment,
                delay = parseInt($this.attr("data-delay")) || settting.delay,
                scroll = $(window).scrollTop() + $(window).height();

            if(scroll >= $this.offset().top || actual > 0)
            {
                if(max > actual)
                {
                    actual += increment;
                    if(actual > max) actual = max;
                    $this.text(actual);
                    setTimeout(function(){
                        $this.counter();
                    }, delay);
                }
            }
        });
    };

    $.fn.autoBar = function(options){
        var setting = $.extend({
            max: 100,
            min: 0,
            inc: 1,
            delay: 500
        }, options);
        return this.each(function(){
            var $this = $(this),
                goto = $this.attr("aria-valuenow") || min,
                scroll = $(window).scrollTop() + $(window).height(),
                end = $this.attr("data-progress-success") || false;

            if(scroll >= $this.offset().top && !end)
            {
                $this.attr("data-progress-success", true).animate({
                    width: goto + "%"
                }, setting.delay);
            }
        });
    };

    // Apply the parallax effect to elements with the attribute
    $("[data-parallax]").parallax();

    // Normalize element heights
//    $("[data-same-height]").sameHeight();

    // Apply scroll-driven effects
    $(document).on("scroll", function(ev){
        $("[data-effect]").showDelay();
//        $("[data-effect=lateral]").slideLateral();
        $("[data-counter]").counter();
        $(".progress-bar").autoBar();
    });

    // Fix top margin offsets
    $("[data-position]").each(function(){
        if($(document).width() >= 1000)
        {
            var height = ($(this).height() * -1) + 20;
            $(this).css("margin-top", height + "px");
        }
        return true;
    });

    // Normalize video sizes
    $("video").scaleVideo();

    $(".carousel .active .carousel-caption").show({
        effect: "fade",
        duration: 1000,
        easing: "easeInQuart"
    });
    // Apply caption reveal effect in the carousel
    $(".carousel").on("slid.bs.carousel", function(ev){
        if(undefined === $(this).find(".active").attr("data-loaded"))
        {
            var effect = $(this).find(".active").find(".carousel-slide").attr("data-effect") || "slide";
            $(this).find(".active").attr("data-loaded", true).find(".carousel-caption").show({
                effect: effect,
                duration: 1000,
                easing: "easeInQuart"
            });
        }
    });

}(jQuery));
