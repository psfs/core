function autocomplete(obj, params) {
    $(obj).typeahead('destroy').typeahead({
            minLength: 3,
            highlight: true
        },
        {
            name: 'routing',
            displayKey: 'value',
            source: substringMatcher(params || [])
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
        $.each(strs, function (i, str) {
            if (typeof str === 'string' && substrRegex.test(str)) {
                // the typeahead jQuery plugin expects suggestions to a
                // JavaScript object, refer to typeahead docs for more info
                matches.push({value: str});
            } else if (typeof str === 'object') {
                var tmpStr = '';
                try {
                    tmpStr = str.name;
                } catch(err) {
                    try {
                        tmpStr = str.title;
                    } catch(err) {
                        if(console) {
                            console.debug(err);
                        }
                    }

                }
            }
        });

        cb(matches);
    };
}

function reloadDataFromInput(input) {
    var $input = $(input);
    if(null !== $input && $input.val().length >=3) {
        $.ajax({
            url: $input.attr('src'),
            dataType: 'JSON',
            data: {
                "filters": $input.val()
            },
            success: function(data) {
                autocomplete($input, data);
                $input.focus();
            }
        });
    }
}
(function () {

})();