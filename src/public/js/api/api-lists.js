(function(){
    app = app || angular.module(module || 'psfs', ['ngMaterial', 'ngSanitize', 'bw.paging']);

    var listCtrl = ['$scope', '$log', '$httpSrv', '$mdDialog', '$msgSrv', '$apiSrv', '$timeout', '$managerLocationSrv',
    function($scope, $log, $httpSrv, $mdDialog, $msgSrv, $apiSrv, $timeout, $managerLocationSrv){
        $scope.loading = false;
        $scope.limit = globalLimit || 25;
        $scope.actualPage = 1;
        $scope.count = 0;
        $scope.listSearch = '';
        $scope.selectedItem = null;
        var initialSelectionResolved = false;

        function syncManagerUrl(id) {
            if($scope.managerUrl) {
                $managerLocationSrv.replace($scope.managerUrl, id);
            }
        }

        function paginate(page)
        {
            $scope.actualPage = page || 1;
            $scope.loadData();
        }

        function loadItemById(id, syncUrl)
        {
            if(!id) {
                return;
            }
            $scope.itemLoading = true;
            $httpSrv.$get($scope.url.replace(/\/$/, '') + "/" + encodeURIComponent(id))
                .then(function(response) {
                    $scope.model = response.data.data;
                    $scope.modelBackup = angular.copy($scope.model);
                    $scope.selectedItem = angular.copy($scope.model);
                    if(syncUrl !== false) {
                        syncManagerUrl($apiSrv.getId($scope.model, $scope.form.fields));
                    }
                    $msgSrv.send('populate_combos');
                }, function(err, status) {
                    $mdDialog.show(
                        $mdDialog.alert()
                            .clickOutsideToClose(true)
                            .title($scope.entity + ' Error ' + status)
                            .htmlContent(err)
                            .ariaLabel('Loading error')
                            .ok($scope.i18N['close'])
                    );
                })
                .finally(function() {
                    $scope.loading = false;
                    $timeout(function(){
                        $scope.itemLoading = false;
                    }, 500);
                });
        }

        function loadItem(item, syncUrl)
        {
            loadItemById($apiSrv.getId(item, $scope.form.fields), syncUrl);
        }

        function isModelSelected() {
            return !angular.equals({}, $scope.model);
        }

        function resolveInitialSelection() {
            if(initialSelectionResolved) {
                return;
            }
            initialSelectionResolved = true;
            var initialItemId = $managerLocationSrv.resolveInitialId($scope.managerUrl, $scope.initialItemId);
            if(initialItemId) {
                loadItemById(initialItemId, false);
            }
        }

        var parentLoadData = $scope.loadData;
        $scope.loadData = function() {
            var response = parentLoadData.apply(this, arguments);
            if(response && angular.isFunction(response.finally)) {
                response.finally(resolveInitialSelection);
            } else {
                $timeout(resolveInitialSelection, 0, false);
            }
            return response;
        };

        var searcher = null;
        function search() {
            $timeout.cancel(searcher);
            searcher = $timeout($scope.loadData, 250);
        }

        $scope.$watch('listSearch', function(_new, _old) {
            if(_old === _new) return;
            if(_new.replace(/\ /g, '').length === 0) {
                $scope.actualPage = 1;
            }
            search();
        });

        $scope.getLabel = $apiSrv.getLabel;
        $scope.loadItem = loadItem;
        $scope.paginate = paginate;
        $scope.getId = $apiSrv.getId;
        $scope.isModelSelected = isModelSelected;

        $scope.$on('psfs.list.reload', $scope.loadData);
        $scope.$on('psfs.model.reload', () => {
            if(null !== $scope.selectedItem) {
                loadItemById($apiSrv.getId($scope.selectedItem, $scope.form.fields), false);
            }
        });
        $scope.$on('psfs.model.select', function(event, item) {
            if(item) {
                initialSelectionResolved = true;
                loadItemById($apiSrv.getId(item, $scope.form.fields));
            }
        });
        $scope.$on('psfs.model.clean', () => {
            $scope.selectedItem = null;
            syncManagerUrl(null);
        });

        $scope.loadData();
    }];

    app
    .directive('apiLists', function() {
        return {
            restrict: 'E',
            replace: true,
            templateUrl: '/js/api.list.html',
            controller: listCtrl
        };
    });
})();
