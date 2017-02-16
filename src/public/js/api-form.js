(function () {
    app = app || angular.module(module || 'psfs', ['ngMaterial', 'ngSanitize', 'bw.paging']);

    var formCtrl = ['$scope', '$http', '$msgSrv', '$log', '$apiSrv', '$mdDialog', '$q', '$timeout',
        function ($scope, $http, $msgSrv, $log, $apiSrv, $mdDialog, $q, $timeout) {
            $scope.method = 'POST';
            $scope.itemLoading = false;
            $scope.combos = {};
            $scope.dates = {};
            $scope.limit = globalLimit || 25;
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
                        .title($scope.entity + '<br> Error ' + err.status)
                        .content(err.data.data)
                        .ariaLabel('Save error')
                        .ok($scope.i18N['close'])
                );
                $log.error(err);
                $scope.loading = false;
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
            $scope.$on('populate_combos', function() {
                for(var f in $scope.form.fields) {
                    var field = $scope.form.fields[f];
                    if(field.type == 'select') {
                        populateCombo(field);
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
                $log.debug(newValue);
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