(function() {
    app = app || angular.module(module || 'psfs', ['ngMaterial', 'ngSanitize', 'bw.paging']);

    var formCtrl = ['$scope', '$http', '$msgSrv', '$log', '$apiSrv',
        function($scope, $http, $msgSrv, $log, $apiSrv) {
            $scope.method = 'POST';
            $scope.loading = false;

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

            $scope.$on('psfs.load.item', loadEntity);
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