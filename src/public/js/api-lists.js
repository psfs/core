(function(){
    app = app || angular.module(module || 'psfs', ['ngMaterial', 'ngSanitize', 'bw.paging']);

    var messageService = ['$rootScope', '$log', function($rootScope, $log) {
        return {
            'send': function(message, data) {
                $log.debug('Event: ' + message);
                $log.debug(data);
                $rootScope.$broadcast(message, data);
            }
        };
    }];
    var listCtrl = ['$scope', '$log', '$http', '$mdDialog', '$msgSrv',
    function($scope, $log, $http, $mdDialog, $msgSrv){
        $scope.list = [];
        $scope.loading = false;
        $scope.limit = globalLimit || 10;
        $scope.actualPage = 1;
        $scope.filters = {};
        $scope.count = 0;

        function catchError(response)
        {
            $mdDialog.show(
                $mdDialog.alert()
                    .clickOutsideToClose(true)
                    .title('An error ocurred')
                    .content(response)
                    .ariaLabel('Alert Error Dialog')
                    .ok('Close')
            );
            $scope.loading = false;
        }

        function paginate(page)
        {
            $scope.actualPage = page || 1;
            loadData();
        }

        function loadData()
        {
            var queryParams = {
                '__limit': $scope.limit,
                '__page': $scope.actualPage
            };
            $scope.loading = true;
            try {
                $http.get($scope.url, {params: queryParams})
                    .then(function(result) {
                        $log.info(result);
                        $scope.list = result.data.data;
                        $scope.loading = false;
                        $scope.count = result.data.total;
                    }, catchError);
            } catch(err) {
                $log.error(err.message);
            }
        }

        function getLabel(item)
        {
            if (item) {
                if (item.label || item.Label) {
                    return item.label || item.Label;
                } else if (item.name || item.Name) {
                    return item.name || item.Name;
                } else if (item.title || item.Title) {
                    return item.title || item.Title;
                } else {
                    return '';
                }
            } else {
                return '';
            }
        }

        function getId(item)
        {
            if (item) {
                if (item.id || item.Id) {
                    return item.id || item.Id;
                } else if (item['id' + $scope.api]) {
                    return item['id' + $scope.api]
                } else if (item['id_' + $scope.api.toLowerCase]) {
                    return item['id_' + $scope.api.toLowerCase];
                } else if (item[$scope.api + 'Id']) {
                    return item[$scope.api + 'Id'];
                } else {
                    throw new Error('Unidentified element');
                }
            } else {
                throw new Error('Null object!!!');
            }
        }

        function deleteItem(item)
        {
            $scope.loading = true;
            var confirm = $mdDialog.confirm()
                .title('Would you like to delete ' + getLabel(item) + '?')
                .content('If you delete this element, maybe lost some related data')
                .ariaLabel('Delete Element')
                .ok('Delete')
                .cancel('Cancel');
            $mdDialog.show(confirm).then(function() {
                $http.delete($scope.url + "/" + getId(item))
                    .then(loadData);
            }, function() {
                $scope.loading = false;
            });
        }

        function loadItem(item)
        {
            $msgSrv.send('psfs.load.item', item);
        }

        $scope.getLabel = getLabel;
        $scope.loadData = loadData;
        $scope.loadItem = loadItem;
        $scope.deleteItem = deleteItem;
        $scope.paginate = paginate;

        loadData();
    }];

    app
    .service('$msgSrv', messageService)
    .directive('apiLists', function() {
        return {
            restrict: 'E',
            scope: {
                'api': '@',
                'url': '@'
            },
            templateUrl: '/js/templates/api-list.html',
            controller: listCtrl
        };
    });
})();