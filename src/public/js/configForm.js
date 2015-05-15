var params, routing;
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
        div_input = $("<div>");
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
            minLength: 1,
            highlight: true
        },
        {
            name: 'routing',
            displayKey: 'value',
            source: substringMatcher(params  || [])
        });
    return false;
}

function substringMatcher(strs) {
    return function findMatches(q, cb) {
        var matches, substrRegex;

        // an array that will be populated with substring matches
        matches = [];

        // regex used to determine if a string contains the substring `q`
        substrRegex = new RegExp(q, 'i');

        // iterate through the pool of strings and for any string that
        // contains the substring `q`, add it to the `matches` array
        $.each(strs, function(i, str) {
            if (substrRegex.test(str)) {
                // the typeahead jQuery plugin expects suggestions to a
                // JavaScript object, refer to typeahead docs for more info
                matches.push({ value: str });
            }
        });

        cb(matches);
    };
}

(function(){
    $("[data-toggle=tooltip]").tooltip();
    //Hidratamos las opciones de configuración
    $.ajax({
        url: "/admin/config/params",
        dataType: "JSON",
        success: function(json){
            params = json || [];
        }
    });
    //Hidratamos las rutas de acceso
    $.ajax({
        url: "/admin/routes/show",
        dataType: "JSON",
        success: function(json){
            $("input[name*=action]").typeahead({
                    minLength: 1,
                    highlight: true
                },
                {
                    name: 'routing',
                    displayKey: 'value',
                    source: substringMatcher(json  || [])
                });
        }
    });
})();