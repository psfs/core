var params = [], routing = [];
/**
 * Función que añade un nuevo campo al formulario de configuración
 * @returns {boolean}
 */
function addNewField(form)
{
    if(undefined === form) throw new Error('Se necesita un formulario de origen');

    var prelabel = $("<label>"),
        div_label = $("<div>"),
        label = $("<input>"),
        div_input = $("<div>")
        input = $("<input>"),
        container = $("<div>"),
        ts = new Date().getTime(),
        label_field_count = $(form).find("input[name^=label]").filter(function(){
            return $(this).val() === '';
        }).length,
        input_field_count = $(form).find("input[name^=value]").filter(function(){
            return $(this).val() === '';
        }).length;
    //Controlamos que existan ya unos sin rellenar
    if(label_field_count + input_field_count > 0) return false;

    //Añadimos el label
//    prelabel.addClass('control-label col-md-2')
//    .text('Nuevo parámetro de configuración')
//    .appendTo(container);

    //Añadimos le campo del nombre del campo
    div_label.addClass("control-label col-md-2")
    .css("padding", 0)
    .appendTo(container);
    label.attr({
        "type": "text",
        "name": "label[]",
        "id": ts,
        "placeholder": "Parámetro"
    }).css({
        "text-align": "right",
        "font-weight": "bolder"
    }).addClass("form-control")
    .appendTo(div_label);

    //Añadimos el campo del valor
    div_input.addClass("col-md-6")
    .appendTo(container);
    input.attr({
        "type": "text",
        "name": "value[]",
        "for": ts,
        "placeholder": "Introduce el valor del nuevo campo"
    }).addClass("form-control")
    .appendTo(div_input);

    //Añadimos el contenedor
    container.addClass('form-group row').appendTo($(form).find("fieldset"));
    return autocomplete(label);
}

function autocomplete(obj)
{
    $(obj).typeahead({
        local: params
    });
    return false;
}

(function(){
    $("[data-toggle=tooltip]").tooltip();
    //Hidratamos las opciones de configuración
    $.ajax({
        url: "/admin/config/params",
        dataType: "JSON",
        success: function(json){
            params = json;
        }
    });
    //Hidratamos las rutas de acceso
    $.ajax({
        url: "/admin/routes/show",
        dataType: "JSON",
        success: function(json){
            routing = json;
            $("input[name*=action]").typeahead({local:json});
        }
    });
})();