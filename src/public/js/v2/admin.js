app.controller('AdminCtrl', ['$msgSrv', '$scope', '$log', '$mdSidenav', '$timeout', '$mdToast', '$window',
    function($msgSrv, $scope, $log, $mdSidenav, $timeout, $mdToast, $window) {

        $scope.toggleMenu = buildToggler('left');
        $scope.toogleActions = buildToggler('right');
        $scope.menus = {};
        $scope.loadingPage = true;
        var last = {
            bottom: false,
            top: true,
            left: false,
            right: true
        };

        $scope.toastPosition = angular.extend({},last);

        $scope.getToastPosition = function() {
            sanitizePosition();

            return Object.keys($scope.toastPosition)
                .filter(function(pos) { return $scope.toastPosition[pos]; })
                .join(' ');
        };

        function sanitizePosition() {
            var current = $scope.toastPosition;

            if ( current.bottom && last.top ) current.top = false;
            if ( current.top && last.bottom ) current.bottom = false;
            if ( current.right && last.left ) current.left = false;
            if ( current.left && last.right ) current.right = false;

            last = angular.extend({},current);
        }

        function buildToggler(componentId) {
            return function() {
                try {
                    switch(componentId) {
                        default:
                        case 'left':
                            // Do nothing
                            //if($mdSidenav('right').isOpen()) {
                            //    $mdSidenav('right').close();
                            //}
                            break;
                        case 'right':
                            if($mdSidenav('left').isOpen()) {
                                $mdSidenav('left').close();
                            }
                            break;
                    }
                    if(componentId === 'left') {
                        $mdSidenav(componentId, true).toggle();
                    }
                } catch(err) {
                    $log.warn(err.message);
                }

            };
        }

        $scope.toggleModuleMenu = function(module) {
            if(module in $scope.menus) {
                $scope.menus[module] = !$scope.menus[module];
            } else {
                $scope.menus[module] = true;
            }
        };

        $scope.showSimpleToast = function(message) {
            var pinTo = $scope.getToastPosition();

            $mdToast.show(
                $mdToast.simple()
                    .textContent(message)
                    .position(pinTo )
                    .hideDelay(3000)
            );
        };

        $scope.goTo = function(path) {
            $scope.toggleMenu();
            $msgSrv.send('page.load.start');
            $window.location.href = path;
        };

        $scope.$on('page.load.finished', function() {
            $scope.loadingPage = false;
        });

        $scope.$on('page.load.start', function() {
            $scope.loadingPage = true;
        });

        $scope.$on('admin.message', function(ev, message) {
            if(typeof message === 'string') {
                $timeout(function() {
                    $scope.showSimpleToast(message);
                }, 500);
            }
        });

        $timeout(function(){
            $msgSrv.send('page.load.finished');
        }, 500);
    }
]);