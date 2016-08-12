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
        var entity;

        function getLabelField(item) {
            if (item) {
                if('label' in item) return 'label';
                if('Label' in item) return 'Label';
                if('name' in item) return 'name';
                if('Name' in item) return 'Name';
                if('title' in item) return 'title';
                if('Title' in item) return 'Title';
            }
            return '';
        }

        function getLabel(item)
        {
            if (item) {
                var label = getLabelField(item);
                if(label in item) {
                    return item[label];
                }
            }
            return '';
        }

        function getId(item, entity)
        {
            entity = entity || this.entity;
            if (item) {
                if (item.id || item.Id) {
                    return item.id || item.Id;
                } else if (item['id' + entity]) {
                    return item['id' + entity]
                } else if (item['Id' + entity]) {
                    return item['Id' + entity]
                } else if (item['Id' + entity.toLowerCase()]) {
                    return item['Id' + entity.toLowerCase()]
                } else if (item['id_' + entity.toLowerCase()]) {
                    return item['id_' + entity.toLowerCase()];
                } else if (item[entity + 'Id']) {
                    return item[entity + 'Id'];
                } else {
                    throw new Error('Unidentified element');
                }
            } else {
                throw new Error('Null object!!!');
            }
        }

        function setEntity(entity) {
            this.entity = entity;
        }

        return {
            getLabel: getLabel,
            getLabelField: getLabelField,
            getId: getId,
            setEntity: setEntity
        };
    }];
    app.service('$apiSrv', entitySrv);
})();