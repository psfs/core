(function() {
    app = app || angular.module(module || 'psfs', ['ngMaterial', 'ngSanitize', 'bw.paging']);

    var apiCtrl = ['$scope',
        function ($scope) {
            $scope.model = {};
            $scope.filters = {};
            $scope.list = [];
            $scope.selected = null;
            $scope.form = {};
            $scope.entity_form = null;
            $scope.itemLoading = true;
            $scope.i18N = i18N || {};
        }];
    app.controller('apiCtrl', apiCtrl);
})();