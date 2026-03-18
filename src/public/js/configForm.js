var parameters, routing;
/**
 * Add a new field to the configuration form
 * @returns {boolean}
 */
function addNewField(form)
{
    if(undefined === form) throw new Error('A source form is required');

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
    // Ensure there are no pending empty fields first
    if(label_field_count + input_field_count > 0) return false;

    // Add the field label input
    div_label.addClass("control-label col-md-2")
    .css("padding", 0)
    .appendTo(container);
    label.attr({
        "type": "text",
        "name": "label[]",
        "id": ts,
        "placeholder": "Parameter"
    }).css({
        "text-align": "right",
        "font-weight": "bolder"
    }).addClass("form-control")
    .appendTo(div_label);

    // Add the field value input
    div_input.addClass("col-md-4")
    .appendTo(container);
    input.attr({
        "type": "text",
        "name": "value[]",
        "for": ts,
        "placeholder": "Enter the value for the new field"
    }).addClass("form-control")
    .appendTo(div_input);

    // Add the field container
    container.addClass('form-group row').appendTo($(form).find("fieldset"));
    return autocomplete(label, parameters);
}

(function(){
    $("[data-toggle=tooltip]").tooltip();
    // Hydrate configuration options
    $.ajax({
        url: "/admin/config/params",
        dataType: "JSON",
        success: function(json){
            parameters = json || [];
        }
    });
    // Hydrate route options
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
