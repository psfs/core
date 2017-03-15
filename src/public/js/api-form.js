(function () {
    app = app || angular.module(module || 'psfs', ['ngMaterial', 'ngSanitize', 'bw.paging']);

    var formCtrl = ['$scope', '$http', '$msgSrv', '$log', '$apiSrv', '$mdDialog', '$q', '$timeout', '$mdMenu',
        function ($scope, $http, $msgSrv, $log, $apiSrv, $mdDialog, $q, $timeout, $mdMenu) {
            $scope.method = 'POST';
            $scope.itemLoading = false;
            $scope.combos = {};
            $scope.dates = {};
            $scope.limit = globalLimit || 25;
            $scope.extraActionExecution = false;
            $apiSrv.setEntity($scope.entity);

            function getEntityFields(url, callback) {
                $http.post(url)
                    .then(callback, function (err, status) {
                        $log.error(err);
                        $scope.loading = false;
                    });
            }

            function loadFormFields() {
                $scope.itemLoading = true;
                $log.debug('Loading entity form info');
                getEntityFields($scope.formUrl, function (response) {
                    $log.debug('Entity form loaded');
                    $scope.form = response.data.data || {};
                    $scope.itemLoading = false;
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
                return  (type === 'DATE' || type === 'DATETIME');
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
                    $http.get(field.url + '?__limit=-1')
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


            function clearForm() {
                $scope.itemLoading = false;
                $scope.model = {};
                for(var i in $scope.combos) {
                    var combo = $scope.combos[i];
                    combo.item = null;
                    combo.search = null;
                }
                $scope.entity_form.$setPristine(true);
                $scope.entity_form.$setDirty(false);
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
                    var model = $scope.model;
                    try {
                        $http.put($scope.url + '/' + $apiSrv.getId(model, $scope.form.fields), model)
                            .then(clearForm, showError);
                    } catch (err) {
                        $log.debug('Create new entity');
                        $http.post($scope.url, model)
                            .then(clearForm, showError);
                    } finally {
                        $timeout(function () {
                            $msgSrv.send('psfs.list.reload');
                        }, 250);
                    }
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
                    $http.get(field.url.replace(/\/\{pk\}$/ig, '') + '?__limit='+$scope.limit+'&__combo=' + encodeURIComponent("%" + search + "%") + '&__fields=__name__,' + field.relatedField)
                        .then(function (response) {
                            deferred.resolve(response.data.data || []);
                        }, function () {
                            deferred.resolve([]);
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
                        $http.get(field.url + '?' + field.relatedField + '=' + $scope.model[field.name] + '&__limit=1' + '&__fields=__name__,' + field.relatedField)
                            .then(function (response) {
                                $scope.combos[field.name].item = response.data.data[0];
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
                    if(field.type == 'select') {
                        populateCombo(field);
                    } else if(field.type == 'date') {
                        initDates(field.name);
                    }
                }
            });

            function getPk() {
                var pk = '';
                if(!angular.equals({}, $scope.model)) {
                    for(var i in $scope.form.fields) {
                        var field = $scope.form.fields[i];
                        if(field.pk && field.name in $scope.model) {
                            pk = $scope.model[field.name];
                        }
                    }
                }
                return pk;
            }

            function isSaved() {
                var pk = getPk();
                return pk.length !== 0;
            }

            function watchDates(newValue, oldValue) {
                for(var d in newValue) {
                    var _date = newValue[d];
                    $scope.model[d] = _date.toISOString().slice(0, 10);
                }
            }

            function executeAction(action) {
                if(action && action.url) {
                    var url = action.url;
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
                $mdDialog.show(
                    $mdDialog.alert()
                        .clickOutsideToClose(true)
                        .title(label)
                        .content('Función ejecutada correctamente. Revisa el log del navegador para más detalle de la respuesta')
                        .ariaLabel('Execution ok')
                        .ok('Close')
                );
            }

            function extraActionKOFeedback(response, label) {
                $scope.extraActionExecution = false;
                $log.info('[EXECUTION] ' + label);
                $log.debug(response);
                $mdDialog.show(
                    $mdDialog.alert()
                        .clickOutsideToClose(true)
                        .title(label)
                        .content('Ha ocurrido un error ejecutando la acción, por favor revisa el log')
                        .ariaLabel('Execution ko')
                        .ok('Close')
                );
            }

            function doAction(method, url, label) {
                $scope.extraActionExecution = true;
                switch(method) {
                    case 'GET':
                        $http.get(url).then(function(response) {
                            if(response.data.success) {
                                extraActionOkFeedback(response.data, label);
                            } else {
                                extraActionKOFeedback(response, label);
                            }
                        }, function() {

                        });
                        break;
                    case 'POST':
                        $http.post(url, {}).then(function() {
                            if(response.data.success) {
                                extraActionOkFeedback(response.data, label);
                            } else {
                                extraActionKOFeedback(response, label);
                            }
                        }, function (response) {
                            extraActionKOFeedback(response, label);
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
                            $http.delete(url)
                                .then(function(response) {
                                    if(response.data.success) {
                                        extraActionOkFeedback(response.data, label);
                                    } else {
                                        extraActionKOFeedback(response, label);
                                    }
                                }, function(err, status){
                                    extraActionKOFeedback(err, label);
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

            $scope.$watch('dates', watchDates, true);

            loadFormFields();
        }];

    app
        .directive('apiForm', function () {
            return {
                restrict: 'E',
                replace: true,
                templateUrl: '/js/templates/api-form.html',
                controller: formCtrl
            };
        });
})();