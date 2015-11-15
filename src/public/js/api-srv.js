(function(){
    app = app || angular.module(module || 'psfs', ['ngMaterial', 'ngSanitize', 'bw.paging']);
    /**
     * Message Service
     * @type {*[]}
     */
    var messageService = ['$rootScope', '$log', function($rootScope, $log) {
        return {
            'send': function(message, data) {
                $log.debug('Event: ' + message);
                $log.debug(data);
                $rootScope.$broadcast(message, data);
            }
        };
    }];
    app.service('$msgSrv', messageService);
    var entitySrv = ['$log', function($log) {
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
        return {
            getLabel: getLabel,
            getId: getId
        };
    }];
    app.service('$apiSrv', entitySrv);
})();