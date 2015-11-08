<?php
    namespace PSFS\base\types;

    use PSFS\base\config\Config;
    use PSFS\base\dto\JsonResponse;

    abstract class AuthApi extends Api
    {
        use SecureTrait;

        public function __construct()
        {
            parent::__construct();
            if (!$this->checkAuth()) {
                return $this->json(new JsonResponse(_('Not authorized'), false), 401);
            }
        }

        private function checkAuth()
        {
            $namespace = explode('\\', $this->getModelTableMap());
            $module = $namespace[0];
            $secret = Config::getInstance()->get($module. '_api_secret');
            $auth = false;
            if (null === $secret) {
                $secret = Config::getInstance()->get("api_secret");
            }
            if (null === $secret) {
                $auth = true;
            } else {
                if(array_key_exists('API_TOKEN', $this->query)) {
                }
            }
            return $auth;
        }
    }