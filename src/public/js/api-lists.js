(function(){
    app = app || angular.module(module || 'psfs', ['ngMaterial', 'ngSanitize', 'bw.paging']);

    var listCtrl = ['$scope', '$log', '$http', '$mdDialog', '$msgSrv', '$apiSrv', '$timeout',
    function($scope, $log, $http, $mdDialog, $msgSrv, $apiSrv, $timeout){
        $scope.loading = false;
        $scope.limit = globalLimit || 25;
        $scope.actualPage = 1;
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

        function deleteItem(item)
        {
            $scope.loading = true;
            var confirm = $mdDialog.confirm()
                .title('Would you like to delete ' + $apiSrv.getLabel(item) + '?')
                .content('If you delete this element, maybe lost some related data')
                .ariaLabel('Delete Element')
                .ok('Delete')
                .cancel('Cancel');
            $mdDialog.show(confirm).then(function() {
                $http.delete($scope.url + "/" + $apiSrv.getId(item, $scope.form.fields))
                    .then(function() {
                        $timeout(function() {
                            if(checkItem(item)) {
                                addNewItem();
                            }
                            loadData();
                        }, 250);
                    }, function(err, statu){
                        $mdDialog.show(
                            $mdDialog.alert()
                                .clickOutsideToClose(true)
                                .title($scope.entity + ' Error ' + status)
                                .content(err)
                                .ariaLabel('Delete error')
                                .ok('Close')
                        );

                    });
            }, function() {
                $scope.loading = false;
            });
        }

        function loadItem(item)
        {
            $scope.model = angular.copy(item);
            $msgSrv.send('populate_combos');
        }

        function isModelSelected() {
            return !angular.equals({}, $scope.model);
        }

        function cleanFormStatus(element) {
            if(typeof element === 'object' && '$setDirty' in element) {
                element.$setDirty(false);
                element.$setPristine(false);
            }
        }

        function addNewItem() {
            $scope.model = {};
            cleanFormStatus($scope.entity_form);
            for(var i in $scope.entity_form) {
                var _field = $scope.entity_form[i];
                cleanFormStatus(_field);
            }
            $msgSrv.send('populate_combos');
        }

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