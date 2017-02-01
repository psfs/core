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
                if('__name__' in item) return '__name__';
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

        function getPkField(fields) {
            var pk = null;
            fields = fields || {};
            for(var i in fields) {
                var field = fields[i];
                if(field.pk) {
                    pk = field.name;
                }
            }
            return pk;
        }

        function getId(item, fields)
        {
            var pk = getPkField(fields);
            if (null !== pk) {
                if (item[pk]) {
                    return item[pk];
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
            setEntity: setEntity,
            getPkField: getPkField
        };
    }];
    app.service('$apiSrv', entitySrv);
})();