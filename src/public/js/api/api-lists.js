(function(){
    app = app || angular.module(module || 'psfs', ['ngMaterial', 'ngSanitize', 'bw.paging']);

    var listCtrl = ['$scope', '$log', '$http', '$mdDialog', '$msgSrv', '$apiSrv', '$timeout',
    function($scope, $log, $http, $mdDialog, $msgSrv, $apiSrv, $timeout){
        $scope.loading = false;
        $scope.limit = globalLimit || 25;
        $scope.actualPage = 1;
        $scope.count = 0;
        $scope.listSearch = '';
        $scope.selectedItem = null;

        function paginate(page)
        {
            $scope.actualPage = page || 1;
            $scope.loadData();
        }

        function loadItem(item)
        {
            $scope.itemLoading = true;
            $scope.selectedItem = item;
            $httpSrv.$get($scope.url.replace(/\/$/, '') + "/" + item[$scope.modelId])
                .then(function(response) {
                    $scope.model = response.data.data;
                    $scope.modelBackup = angular.copy($scope.model);
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

        function isModelSelected() {
            return !angular.equals({}, $scope.model);
        }

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
                loadItem($scope.selectedItem);
            }
        });
        $scope.$on('psfs.model.clean', () => {
            $scope.selectedItem = null;
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
