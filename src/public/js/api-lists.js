(function(){
    app = app || angular.module(module || 'psfs', ['ngMaterial', 'ngSanitize', 'bw.paging']);

    var listCtrl = ['$scope', '$log', '$http', '$mdDialog', '$msgSrv', '$apiSrv', '$timeout',
    function($scope, $log, $http, $mdDialog, $msgSrv, $apiSrv, $timeout){
        $scope.loading = false;
        $scope.limit = globalLimit || 25;
        $scope.actualPage = 1;
        $scope.count = 0;
        $scope.listSearch = '';

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

        function paginate(page)
        {
            $scope.actualPage = page || 1;
            loadData();
        }

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
                $http.get($scope.url, {params: queryParams})
                    .then(function(result) {
                        $log.info(result);
                        $scope.list = result.data.data;
                        $timeout(function(){
                            $scope.loading = false;
                        }, 500);
                        $scope.count = result.data.total;
                    }, catchError);
            } catch(err) {
                $scope.loading = false;
                $log.error(err.message);
            }
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
                    $http.delete($scope.url + "/" + item[$scope.modelId])
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
                        });
                }, function() {
                    $scope.loading = false;
                });
            }
        }

        function loadItem(item)
        {
            $scope.itemLoading = true;
            $http.get($scope.url + "/" + item[$scope.modelId])
                .then(function(response) {
                    $scope.model = response.data.data;
                    $msgSrv.send('populate_combos');
                    $timeout(function(){
                        $scope.itemLoading = false;
                    }, 500);
                }, function(err, status) {
                    $mdDialog.show(
                        $mdDialog.alert()
                            .clickOutsideToClose(true)
                            .title($scope.entity + ' Error ' + status)
                            .content(err)
                            .ariaLabel('Delete error')
                            .ok($scope.i18N['close'])
                    );
                    $scope.loading = false;
                });
        }

        function isModelSelected() {
            return !angular.equals({}, $scope.model);
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

        var searcher = null;
        function search() {
            $timeout.cancel(searcher);
            searcher = $timeout(loadData, 250);
        }

        $scope.$watch('listSearch', function(_new, _old) {
            if(_old === _new) return;
            if(_new.replace(/\ /g, '').length == 0) {
                $scope.actualPage = 1;
            }
            search();
        });

        $scope.getLabel = $apiSrv.getLabel;
        $scope.loadData = loadData;
        $scope.loadItem = loadItem;
        $scope.deleteItem = deleteItem;
        $scope.paginate = paginate;
        $scope.getId = $apiSrv.getId;
        $scope.isModelSelected = isModelSelected;
        $scope.addNewItem = addNewItem;

        $scope.$on('psfs.list.reload', function(){
            loadData();
        });

        loadData();
    }];

    app
    .directive('apiLists', function() {
        return {
            restrict: 'E',
            replace: true,
            templateUrl: '/js/templates/api-list.html',
            controller: listCtrl
        };
    });
})();