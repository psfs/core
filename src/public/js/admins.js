function backToTop()
{
    $(window).scroll(function () {
        if ($(this).scrollTop() > 50) {
            $('#back-to-top').fadeIn();
        } else {
            $('#back-to-top').fadeOut();
        }
    });
    // scroll body to 0px on click
    $('#back-to-top').click(function () {
        $('#back-to-top').tooltip('hide');
        $('body,html').animate({
            scrollTop: 0
        }, 800);
        return false;
    });

    $('#back-to-top').tooltip('show');
}

/**
 * Guardado de JS en LocalStorage
 */
function __storage(){
    var self = this;
    this.addData = function(name, code, expire){
        if(undefined !== Storage)
        {
            if(undefined === expire) expire = 3600000;
            else expire *= 1000;
            localStorage.setItem(name, JSON.stringify({data:code, time: new Date().getTime() + expire}));
        }
    };
    this.getData = function(name) {
        var data = null;
        if(undefined !== Storage)
        {
            var _data = JSON.parse(localStorage.getItem(name)),
                now = new Date().getTime();
            if(_data && (_data.time > now))
            {
                data = _data.data;
            }else this.dropData(name);
        }
        return data;
    };
    this.dropData = function(name)
    {
        if(undefined !== Storage)
        {
            localStorage.removeItem(name);
        }
    };
};
jsStorage = new __storage();

/**
 * Método que serializa la información de un formulario en un objeto JSON
 * @returns {}
 */
$.fn.serializeObject = function()
{
    var o = {};
    var a = this.serializeArray();
    $.each(a, function() {
        if (o[this.name] !== undefined) {
            if (!o[this.name].push) {
                o[this.name] = [o[this.name]];
            }
            o[this.name].push(this.value || '');
        } else {
            o[this.name] = this.value || '';
        }
    });
    return o;
};

/**
 * Función que guarda el estado de un formulario
 * @param form
 */
function backupForm(form)
{
    var data = $(form).serializeObject(),
        name = $(form).attr("name");
    jsStorage.addData(name, data, 3600);
}

/**
 * Función que restaura los valores de un formulario
 * @param form
 */
function restoreForm(form)
{
    var $form = $(form),
        name = $form.attr("name"),
        data = jsStorage.getData(name);
    if(data)
    {
        for(var i in data)
        {
            $form.find(":input[name='" + i + "']").val(data[i]);
        }
        jsStorage.dropData(name);
    }
}

/**
 * Función que revisa la existencia de generadores de datos
 */
function checkCreationFields()
{
    var $forms = $("form");
    $forms.each(function(){
        var $form = $(this);
        if($form.find("a[data-add]").length)
        {
            $form.find("a[data-add]").on("click", function(){
                _w = window.open($(this).attr("data-add"), '_blank', 'height=300,width=300');
                backupForm($form);
                $(_w).on("beforeunload", function(){
                    location.reload();
                });
            });
        }
    });
}

(function(){
    backToTop();
    checkCreationFields();
    $("form").each(function(){
        restoreForm(this);
    });
})();