(function() {
    app = app || angular.module(module || 'psfs', ['ngMaterial', 'ngSanitize', 'bw.paging']);

    var apiCtrl = ['$scope', '$mdDialog', '$apiSrv', '$httpSrv', '$timeout', '$log', '$msgSrv',
        function ($scope, $mdDialog, $apiSrv, $httpSrv, $timeout, $log, $msgSrv) {
            $scope.model = {};
            $scope.modelBackup = {};
            $scope.filters = {};
            $scope.list = [];
            $scope.selected = null;
            $scope.form = {};
            $scope.entity_form = null;
            $scope.itemLoading = true;
            $scope.i18N = i18N || {};

            $scope.cleanFormStatus = function(element) {
                if(typeof element === 'object' && '$setDirty' in element) {
                    element.$setDirty(false);
                    element.$setPristine(true);
                }
            };

            function checkItem(item)
            {
                var isModel = false;
                for(var i in $scope.form.fields) {
                    var field = $scope.form.fields[i];
                    if(field.pk) {
                        isModel = (item[field.name] === $scope.model[field.name]);
                    }
                }
                return isModel;
            }

            function addNewItem() {
                $scope.model = {};
                $scope.dates = {};
                for(var i in $scope.combos) {
                    var combo = $scope.combos[i];
                    combo.item = null;
                    combo.search = null;
                }
                for(var i in $scope.entity_form) {
                    if(!i.match(/^\$/)) {
                        $scope.cleanFormStatus($scope.entity_form[i]);
                    }
                }
                $msgSrv.send('populate_combos');
            }

            function loadData(clean)
            {
                if(clean) addNewItem();
                var queryParams = {
                    '__limit': $scope.limit,
                    '__page': $scope.actualPage,
                    '__fields': $scope.listLabel
                };
                if($scope.listSearch.replace(/\ /ig, '').length > 0) {
                    queryParams['__combo'] = $scope.listSearch;
                }
                $scope.loading = true;
                try {
                    $httpSrv.$get($scope.url, queryParams)
                        .then(function(result) {
                            $scope.list = result.data.data;
                            $timeout(function(){
                                $scope.loading = false;
                            }, 500);
                            $scope.count = result.data.total;
                            $msgSrv.send('admin.message', 'Hay ' + $scope.count + ' registros');
                        }, catchError)
                        .finally(function() {
                            $scope.loading = false;
                        });
                } catch(err) {
                    $log.error(err.message);
                }
            }

            function catchError(response)
            {
                $mdDialog.show(
                    $mdDialog.alert()
                        .clickOutsideToClose(true)
                        .title($scope.i18N['generic_error_label'])
                        .content(response)
                        .ariaLabel('Alert Error Dialog')
                        .ok($scope.i18N['close'])
                );
                $scope.loading = false;
            }

            function deleteItem(item)
            {
                if(item) {
                    $scope.loading = true;
                    var confirm = $mdDialog.confirm()
                        .title($scope.i18N['confirm_delete_label'].replace('%entity%', $apiSrv.getLabel(item)))
                        .content($scope.i18N['confirm_delete_message'])
                        .ariaLabel('Delete Element')
                        .ok($scope.i18N['delete'])
                        .cancel($scope.i18N['cancel']);
                    $mdDialog.show(confirm).then(function() {
                        $httpSrv.$delete($scope.url + $apiSrv.getId(item, $scope.form.fields))
                            .then(function() {
                                $timeout(function() {
                                    if(checkItem(item)) {
                                        addNewItem();
                                    }
                                    loadData();
                                }, 250);
                            }, function(err, status){
                                $mdDialog.show(
                                    $mdDialog.alert()
                                        .clickOutsideToClose(true)
                                        .title($scope.entity + ' Error ' + status)
                                        .content(err.data.data)
                                        .ariaLabel('Delete error')
                                        .ok($scope.i18N['close'])
                                );
                                $scope.loading = false;
                            })
                            .finally(function() {
                                $scope.loading = false;
                            });
                    }, function() {
                        $scope.loading = false;
                    });
                }
            }

            $scope.deleteItem = deleteItem;
            $scope.loadData = loadData;
            $scope.addNewItem = addNewItem;

        }];
    app.controller('apiCtrl', apiCtrl);
})();