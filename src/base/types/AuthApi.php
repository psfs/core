<?php
    namespace PSFS\base\types;

    use PSFS\base\config\Config;
    use PSFS\base\dto\JsonResponse;
    use PSFS\base\Request;
    use PSFS\base\Security;

    abstract class AuthApi extends Api
    {
        use SecureTrait;

        public function __construct()
        {
            parent::__construct();
            if (!$this->checkAuth()) {
                return $this->json(new JsonResponse(_('Not authorized'), FALSE), 401);
            }
        }

        /**
         * Check service authentication
         * @return bool
         */
        private function checkAuth()
        {
            $namespace = explode('\\', $this->getModelTableMap());
            $module = strtolower($namespace[0]);
            $secret = Config::getInstance()->get($module . '.api.secret');
            if (NULL === $secret) {
                $secret = Config::getInstance()->get("api.secret");
            }
            if (NULL === $secret) {
                $auth = TRUE;
            } else {
                $token = Request::getInstance()->getHeader('X-API-SEC-TOKEN');
                if (array_key_exists('API_TOKEN', $this->query)) {
                    $token = $this->query['API_TOKEN'];
                }
                $auth = Security::checkToken($token ?: '', $secret, $module);
            }

            return $auth;
        }
    }