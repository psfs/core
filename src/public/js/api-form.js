(function() {
    app = app || angular.module(module || 'psfs', ['ngMaterial', 'ngSanitize', 'bw.paging']);

    var formCtrl = ['$scope', '$http', '$msgSrv', '$log', '$apiSrv', '$mdDialog',
        function($scope, $http, $msgSrv, $log, $apiSrv, $mdDialog) {
            $scope.method = 'POST';
            $scope.loading = false;
            $scope.form = {};
            $scope.loading = false;
            $scope.model = {};

            function loadEntity($ev, data) {
                $scope.method = 'PUT';
                $scope.loading = true;
                $http.get($scope.url + '/' + $apiSrv.getId(data))
                .then(function(response){
                    $scope.model = response.data.data || {};
                    $msgSrv.send('psfs.entity.loaded');
                    $scope.loading = false;
                }, function(err, status) {
                    $log.error(err);
                    $scope.loading = false;
                });
            }

            function loadFormFields()
            {
                $scope.loading = true;
                $log.debug('Loading entity form info');
                $http.get($scope.url.replace($scope.entity, 'form/' + $scope.entity))
                    .then(function(response) {
                        $log.debug('Entity form loaded');
                        $scope.form = response.data.data || {};
                        $scope.model = {};
                        for(var i in $scope.form.fields) {
                            var field = $scope.form.fields[i];
                            $scope.model[i] = field.value;
                        }
                        $log.debug($scope.model);
                        $scope.loading = false;
                    }, function(err, status) {
                        $log.error(err);
                        $scope.loading = false;
                    });
            }

            function isInputField(field)
            {
                var type = (field.type || 'text').toUpperCase();
                return (type === 'TEXT' || type === 'PHONE' || type === 'URL');
            }

            function isComboField(field)
            {
                var type = (field.type || 'text').toUpperCase();
                return (type === 'SELECT' || type === 'MULTIPLE');
            }

            function loadSelect(field)
            {
                if (field.url) {
                    $http.get(field.url + '?__limit=-1')
                    .then(function(response) {
                        field.data = response.data.data ||[];
                    });
                }
            }

            function submitForm()
            {
                if ($scope.entity_form.$valid) {
                    $log.debug('Entity form submitted');
                    var model = {};
                    for(var i in $scope.form.fields) {
                        var field = $scope.form.fields[i];
                        model[field.name] = $scope.model[i];
                    }
                    $scope.loading = true;
                    try {
                        $http.put($scope.url + '/' + $apiSrv.getId(model), model)
                            .then(function(response){
                                $scope.loading = false;
                            }, function(err, status) {
                                $log.error(err);
                                $scope.loading = false;
                            });
                    } catch(err) {
                        $log.debug('Create new entity');
                        $http.post($scope.url, model)
                            .then(function(response){
                                $scope.loading = false;
                            }, function(err, status) {
                                $log.error(err);
                                $scope.loading = false;
                            });
                    } finally {
                        $msgSrv.send('psfs.list.reload');
                    }
                } else {
                    $log.debug($scope.entity_form);
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

            $scope.$on('psfs.load.item', loadEntity);
            loadFormFields();

            $scope.isInputField = isInputField;
            $scope.loadSelect = loadSelect;
            $scope.isComboField = isComboField;
            $scope.getId = $apiSrv.getId;
            $scope.getLabel = $apiSrv.getLabel;
            $scope.submitForm = submitForm;
        }];

    app
    .directive('apiForm', function() {
        return {
            restrict: 'E',
            replace: true,
            scope: {
                'entity': '@',
                'url': '@'
            },
            templateUrl: '/js/templates/api-form.html',
            controller: formCtrl
        };
    });
})();