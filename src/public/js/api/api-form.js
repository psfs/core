(function () {
    app = app || angular.module(module || 'psfs', ['ngMaterial', 'ngSanitize', 'bw.paging']);

    var formCtrl = ['$scope', '$httpSrv', '$msgSrv', '$log', '$apiSrv', '$mdDialog', '$q', '$timeout',
        function ($scope, $httpSrv, $msgSrv, $log, $apiSrv, $mdDialog, $q, $timeout) {
            $scope.method = 'POST';
            $scope.itemLoading = false;
            $scope.combos = {};
            $scope.dates = {};
            $scope.limit = globalLimit || 25;
            $scope.extraActionExecution = false;
            $apiSrv.setEntity($scope.entity);

            function getEntityFields(url, callback) {
                $httpSrv.$post(url.replace(/\/$/, ''))
                    .then(callback, function (err, status) {
                        $log.error(err);
                        $scope.loading = false;
                    });
            }

            function loadFormFields() {
                $scope.itemLoading = true;
                $log.debug('Loading entity form info');
                getEntityFields($scope.formUrl.replace(/\/$/, ''), function (response) {
                    $log.debug('Entity form loaded');
                    $scope.form = response.data.data || {};
                    $scope.itemLoading = false;
                    $timeout(function() {
                        $('.date').datepicker({
                            todayBtn: "linked",
                            clearBtn: true,
                            language: "es",
                            autoclose: true,
                            todayHighlight: true,
                            format: 'yyyy-mm-dd',
                            forceParse: false,
                            keyboardNavigation: false
                        });
                    }, 250);
                });
            }

            function isInputField(field) {
                var type = (field.type || 'text').toUpperCase();
                return (type === 'TEXT' || type === 'TEL' || type === 'URL' || type === 'NUMBER' || type === 'PASSWORD');
            }

            function isTextField(field) {
                var type = (field.type || 'text').toUpperCase();
                return (type === 'TEXTAREA');
            }

            function isDateField(field) {
                var type = (field.type || 'text').toUpperCase();
                return  (type === 'DATE' || type === 'DATETIME' || type === 'TIMESTAMP');
            }

            function isRelatedield(field) {
                var type = (field.type || 'text').toUpperCase();
                return (type === 'SELECT' || type === 'MULTIPLE') && null !== field.relatedField;
            }

            function isComboField(field) {
                var type = (field.type || 'text').toUpperCase();
                return (type === 'SELECT' || type === 'MULTIPLE') && null === field.relatedField && field.data.length;
            }

            function loadSelect(field) {
                if (field.url) {
                    var query = {
                        '__limit': -1
                    };
                    $httpSrv.$get(field.url, query)
                        .then(function (response) {
                            field.data = response.data.data || [];
                        });
                }
            }

            function showError(err) {
                $mdDialog.show(
                    $mdDialog.alert()
                        .clickOutsideToClose(true)
                        .title($scope.entity + ' Error ' + err.status)
                        .content(err.data.data)
                        .ariaLabel('Save error')
                        .ok($scope.i18N['close'])
                ).finally(function() {
                    $scope.itemLoading = false;
                    $scope.loading = false;
                });
                $log.error(err);
                $scope.loading = false;
                $scope.itemLoading = false;
            }

            function clearAutocomplete() {
                if($("md-autocomplete-wrap button").length) {
                    $timeout(function() {
                        $("md-autocomplete-wrap button").click();
                    }, 10);
                }
            }


            function clearForm() {
                $scope.itemLoading = false;
                $scope.model = {};
                $scope.modelBackup = {};
                for(var i in $scope.combos) {
                    var combo = $scope.combos[i];
                    combo.item = null;
                    combo.search = null;
                }
                $scope.entity_form.$setPristine();
                clearAutocomplete();
                for(var i in $scope.entity_form) {
                    if(!i.match(/^\$/)) {
                        $scope.cleanFormStatus($scope.entity_form[i]);
                    }
                }
            }

            function submitForm() {
                if ($scope.entity_form.$valid) {
                    $log.debug('Entity form submitted');
                    $scope.itemLoading = true;
                    var model = $scope.model, promise, identifier = null;
                    try {
                        identifier = $apiSrv.getId($scope.modelBackup, $scope.form.fields);
                    } catch(err) {}
                    if(identifier) {
                        promise = $httpSrv.$put($scope.url.replace(/\/$/, '') + '/' + identifier, model);
                    } else {
                        promise = $httpSrv.$post($scope.url.replace(/\/$/, ''), model);
                    }

                    promise.then(clearForm, showError)
                    .finally(function() {
                        $msgSrv.send('psfs.list.reload');
                        $scope.loading = false;
                        $timeout(function(){
                            $scope.itemLoading = false;
                        }, 500);
                    });
                } else {
                    $mdDialog.show(
                        $mdDialog.alert()
                            .clickOutsideToClose(true)
                            .title($scope.entity + ' form invalid')
                            .content($scope.entity + ' form invalid, please complete the form')
                            .ariaLabel('Invalid form')
                            .ok('Close')
                    );
                }

                return false;
            }

            function querySearch(search, field) {
                deferred = $q.defer();
                if(angular.isArray(field.data) && field.data.length) {
                    deferred.resolve(field.data);
                } else {
                    var query = {
                        '__limit': $scope.limit,
                        '__combo': "%" + search + "%",
                        '__fields': '__name__,' +  field.relatedField
                    };
                    $httpSrv.$get(field.url.replace(/\/$/, '').replace(/\/\{pk\}$/ig, ''), query)
                        .then(function (response) {
                            deferred.resolve(response.data.data || []);
                        }, function () {
                            deferred.resolve([]);
                        })
                        .finally(function() {
                            $scope.loading = false;
                            $timeout(function(){
                                $scope.itemLoading = false;
                            }, 500);
                        });
                }

                return deferred.promise;
            }

            function setComboField(item, field) {
                if(undefined !== item) {
                    if(field.data.length) {
                        $scope.model[field.name] = item[field.name];
                    } else {
                        $scope.model[field.name] = item[field.relatedField];
                    }
                }
            }

            function populateCombo(field) {
                clearAutocomplete();
                if(undefined !== $scope.model[field.name] && null !== $scope.model[field.name]) {
                    if(angular.isArray(field.data) && field.data.length) {
                        for(var i in field.data) {
                            var _data = field.data[i];
                            try {
                                if(_data[field.name] == $scope.model[field.name]) {
                                    $scope.combos[field.name].item = _data;
                                }
                            } catch(err) {}
                        }
                    } else {
                        var query = {
                            '__limit': 1,
                            '__fields': '__name__,' + field.relatedField
                        };
                        query[field.relatedField] = $scope.model[field.name];
                        $httpSrv.$get(field.url.replace(/\/$/, ''), query)
                            .then(function (response) {
                                $scope.combos[field.name].item = response.data.data[0];
                            })
                            .finally(function() {
                                $scope.loading = false;
                                $timeout(function(){
                                    $scope.itemLoading = false;
                                }, 500);
                            });
                    }
                }
                return null;
            }

            function initDates(fieldName) {
                if($scope.model[fieldName]) {
                    $scope.dates[fieldName] = new Date($scope.model[fieldName]);
                }
            }

            $scope.$on('populate_combos', function() {
                for(var f in $scope.form.fields) {
                    var field = $scope.form.fields[f];
                    if(field.type === 'select') {
                        populateCombo(field);
                    } else if(field.type === 'date') {
                        initDates(field.name);
                    }
                }
            });

            function getPk() {
                var pk = '', sep = '';
                if(!angular.equals({}, $scope.model)) {
                    for(var i in $scope.form.fields) {
                        var field = $scope.form.fields[i];
                        if(field.pk && field.name in $scope.model) {
                            pk += sep + $scope.model[field.name];
                            sep = '__|__';
                        }
                    }
                }
                return pk;
            }

            function isSaved() {
                var pk = getPk();
                return pk.length !== 0;
            }

            function executeAction(action) {
                if(action && action.url) {
                    var url = action.url.replace(/\/$/, '');
                    for(var i in $scope.form.fields) {
                        var field = $scope.form.fields[i];
                        url = url.replace('{' + field.name + '}', $scope.model[field.name]);
                    }
                    doAction(action.method, url, action.label);
                }
            }

            function extraActionOkFeedback(response, label) {
                $scope.loadData();
                $scope.extraActionExecution = false;
                $log.info('[EXECUTION] ' + label);
                $log.debug(response);
                var message = 'Función ejecutada correctamente. Revisa el log del navegador para más detalle de la respuesta';
                if(response.message) {
                    message += ': ' + response.message;
                }
                $mdDialog.show(
                    $mdDialog.alert()
                        .clickOutsideToClose(true)
                        .title(label)
                        .content(message)
                        .ariaLabel('Execution ok')
                        .ok('Close')
                );
            }

            function extraActionKOFeedback(response, label) {
                $scope.extraActionExecution = false;
                $log.info('[EXECUTION] ' + label);
                $log.debug(response);
                var message = 'Ha ocurrido un error ejecutando la acción, por favor revisa el log';
                if(response.message) {
                    message += ': ' + response.message;
                }
                $mdDialog.show(
                    $mdDialog.alert()
                        .clickOutsideToClose(true)
                        .title(label)
                        .content(message)
                        .ariaLabel('Execution ko')
                        .ok('Close')
                );
            }

            function doAction(method, url, label) {
                $scope.extraActionExecution = true;
                switch(method) {
                    case 'GET':
                        $httpSrv.$get(url).then(function(response) {
                            if(response.data.success) {
                                extraActionOkFeedback(response.data, label);
                            } else {
                                extraActionKOFeedback(response, label);
                            }
                        }, function(response) {
                            extraActionKOFeedback(response, label);
                        })
                        .finally(function() {
                            $scope.loading = false;
                            $timeout(function(){
                                $scope.itemLoading = false;
                            }, 500);
                        });
                        break;
                    case 'POST':
                        $httpSrv.$post(url, {}).then(function(response) {
                            if(response.data.success) {
                                extraActionOkFeedback(response.data, label);
                            } else {
                                extraActionKOFeedback(response, label);
                            }
                        }, function (response) {
                            extraActionKOFeedback(response, label);
                        })
                        .finally(function() {
                            $scope.loading = false;
                            $timeout(function(){
                                $scope.itemLoading = false;
                            }, 500);
                        });
                        break;
                    case 'DELETE':
                        $scope.loading = true;
                        var confirm = $mdDialog.confirm()
                            .title(label)
                            .content('La acción implica un borrado, ¿estás seguro?')
                            .ariaLabel('Delete Element')
                            .ok($scope.i18N['delete'])
                            .cancel($scope.i18N['cancel']);
                        $mdDialog.show(confirm).then(function() {
                            $httpSrv.$delete(url)
                                .then(function(response) {
                                    if(response.data.success) {
                                        extraActionOkFeedback(response.data, label);
                                    } else {
                                        extraActionKOFeedback(response, label);
                                    }
                                }, function(err, status){
                                    extraActionKOFeedback(err, label);
                                })
                                .finally(function() {
                                    $scope.loading = false;
                                    $timeout(function(){
                                        $scope.itemLoading = false;
                                    }, 500);
                                });
                        }, function() {
                            $scope.loading = false;
                        });
                        break;
                    default:
                        $scope.extraActionExecution = false;
                        break;
                }
            }

            function formActions() {
                var actions = [];
                for(var i in $scope.form.actions) {
                    var action = $scope.form.actions[i];
                    if((!action.url.match(/(\{|\})/) || this.isSaved()) && action.method.match(/(GET|POST|DELETE)/i)) {
                        actions.push(action);
                    }
                }
                return actions;
            }

            $scope.fieldCheckSuccess = function(entity, field) {
                var check = false, form = $scope.entity_form, name = entity + '_' + field.name;
                if(field.required && name in form && !form[name].$pristine) {
                    check = form[name].$valid;
                }
                return check;
            };
            $scope.fieldCheckError = function(entity, field) {
                var check = false, form = $scope.entity_form, name = entity + '_' + field.name;
                if(name in form && !form[name].$pristine) {
                    check = form[name].$invalid;
                }
                return check;
            };

            $scope.isInputField = isInputField;
            $scope.loadSelect = loadSelect;
            $scope.isTextField = isTextField;
            $scope.isComboField = isComboField;
            $scope.isRelatedield = isRelatedield;
            $scope.isDateField = isDateField;
            $scope.getId = $apiSrv.getId;
            $scope.getLabel = $apiSrv.getLabel;
            $scope.submitForm = submitForm;
            $scope.querySearch = querySearch;
            $scope.setComboField = setComboField;
            $scope.populateCombo = populateCombo;
            $scope.getPk = getPk;
            $scope.isSaved = isSaved;
            $scope.initDates = initDates;
            $scope.formActions = formActions;
            $scope.executeAction = executeAction;

            loadFormFields();
        }];

    app
        .directive('apiForm', function () {
            return {
                restrict: 'E',
                replace: true,
                templateUrl: '/js/api.form.html',
                controller: formCtrl
            };
        });
})();