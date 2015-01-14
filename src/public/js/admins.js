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
}
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
    var w = 600;
    var h = 450;
    var left = Number((screen.width/2)-(w/2));
    var tops = Number((screen.height/2)-(h/2));
    $forms.each(function(){
        var $form = $(this);
        if($form.find("a[data-add]").length)
        {

            $form.find("a[data-add]").on("click", function(){
                _w = window.open($(this).attr("data-add"), '_blank', 'height='+h+',width='+w+',top='+tops+',left='+left);
                backupForm($form);
                $(_w).on("beforeunload", function(){
                    location.reload();
                });
            });
        }
    });
}

/**
 * Función auxilar para el funcionamiento del select multiple
 * @param name
 * @param id
 * @param select
 * @returns {boolean}
 */
function changeMultiple(name, id, select, all)
{
    var $left = $("#src_" + id),
        $right = $("#dest_" + id),
        selected = (all) ? "" : ":selected";
    if(select)
    {
        $left.find("option" + selected).each(function(){
            var hidden = $("<input>");
            hidden.attr({
                "type": "hidden",
                "value": $(this).attr("value"),
                "name": name + "[]"
            });
            $right.append($(this).clone()).after(hidden);
            $(this).remove();
        });
    }else{
        $right.find("option" + selected).each(function(){
            var value = $(this).attr("value");
            $("input[type=hidden][name='"+name+"[]'][value="+value+"]").remove();
            $left.append($(this).clone());
            $(this).remove();
        });
    }
    return false;
}

function uploadImg(file)
{
    var url = $(file).attr("data-upload") || '';
    if(url.length > 0)
    {
        var formData = new FormData();
        //http://stackoverflow.com/questions/6974684/how-to-send-formdata-objects-with-ajax-requests-in-jquery
        formData.append('file', file.files[0]);

        $.ajax({
            url: url,
            data: formData,
            processData: false,
            contentType: false,
            type: 'POST',
            success: function(response){
                if(response.success)
                {
                    backupForm($(file).parents("form").get(0));
                    location.reload();
                }else{
                    bootbox.alert(response.error || 'Upload failed!');
                }
            }
        });

    }
}

function changeFocus(select, id)
{
    $("select[id*='"+id+"']").attr("data-focus", false);
    $(select).attr("data-focus", true);
}

function showDetails(id)
{
    var select = $("select[id*='"+id+"'][data-focus=true]"),
        text = '', sep = '', s = '';
    select.find("option").each(function(){
       if($(this).is(":selected"))
       {
           if(sep.length) s = "s";
           text += sep + $(this).text();
           sep = ", ";
       }
    });
    if(text.length)
    {
        bootbox.alert({
            title: "Selected option" + s,
            message: text
        });
    }
}

function toggleLogs(logGroup)
{
    $(".logs:not(.hide)").addClass("hide");
    $("." + logGroup).removeClass("hide");
}

(function(){
    backToTop();
    checkCreationFields();
    $("form").each(function(){
        restoreForm(this);
    });

    $("[data-tooltip]").tooltip();
})();