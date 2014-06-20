function convert_to_canvas(idVideo, idCanvas)
{
    document.addEventListener('DOMContentLoaded', function(){
        var v = document.getElementById(idVideo);
        var canvas = document.getElementById(idCanvas);
        var context = canvas.getContext('2d');
        var back = document.createElement('canvas');
        var backcontext = back.getContext('2d');

        var cw,ch;

        v.addEventListener('play', function(){
            cw = v.clientWidth;
            ch = v.clientHeight;
            canvas.width = cw;
            canvas.height = ch;
            back.width = cw;
            back.height = ch;
            draw(v,context,backcontext,cw,ch);
        },false);

    },false);
    return false;
}

function draw(v,c,bc,w,h) {
    if(v.paused || v.ended) return false;
    c.drawImage(v,0,0,w,h);
    // Start over!
    setTimeout(function(){ draw(v,c,bc,w,h); }, 0);
}

