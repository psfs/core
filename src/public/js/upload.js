/**
 * UploadManager Object
 * @type {{create: }}
 */
var UploadManager = {
    create: (function() { // BEGIN iife
        var _instance;
        return function() {
            if (window.File && window.FileReader && window.FileList && window.Blob) {
                // Great success! All the File APIs are supported.
                if (!_instance) {
                    _instance = {
                        ts: new Date().getTime(),
                        bind: function() {
                            var self = this;
                            $("input[type=file]:not([data-upload])").each(function(){
                                $(this).attr('data-upload', true).on('change', self.upload);
                                console.log(this);
                                console.log("Binded!!");
                            });
                            return this;
                        },
                        upload: function(ev){
                            var files = ev.target.files;
                            for (var i = 0, f; f = files[i]; i++)
                            {
                                // Only process image files.
//                                if (!f.type.match('image.*')) {
//                                    continue;
//                                }

                                var reader = new FileReader();
                                // Closure to capture the file information.
                                reader.onload = (function(theFile) {
                                    return function(e) {
                                        console.log(e);
                                        // Render thumbnail.
//                                        var span = document.createElement('span');
//                                        span.innerHTML = ['<img class="thumb" src="', e.target.result, '" title="', escape(theFile.name), '"/>'].join('');
//                                        $(".container").append(span);
                                    };
                                })(f);

                                // Read in the image file as a data URL.
                                reader.readAsDataURL(f);
                            }
                            return this;
                        }
                    }
                }
            } else {
                alert('The File APIs are not fully supported in this browser.');
            }
            return _instance;
        };
    }()) // END iife
};

(function(){
    var um = UploadManager.create();
    if(undefined !== um.bind) um.bind();
})();