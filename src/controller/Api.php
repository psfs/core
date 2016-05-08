<?php
    namespace PSFS\controller;

    use PSFS\base\types\Controller;

    /**
     * Class Api
     * @package PSFS\controller
     */
    class Api extends Controller
    {
        const DOMAIN = 'ROOT';
        const PSFS_DOC = 'psfs';
        const SWAGGER_DOC = 'swagger';
        const POSTMAN_DOC = 'postman';

        /**
         * @Inyectable
         * @var \PSFS\services\DocumentorService $srv
         */
        protected $srv;

        /**
         * @GET
         * @route /api/__doc/{type}
         *
         * @param string $type
         *
         * @return string JSON
         */
        public function createApiDocs($type = 'psfs')
        {
            $doc = $endpoints = [];
            $modules = $this->srv->getModules();
            ini_set('memory_limit', -1);
            ini_set('max_execution_time', -1);
            if (count($modules)) {
                foreach ($modules as $module) {
                    $endpoints = array_merge($endpoints, $this->srv->extractApiEndpoints($module));
                }
            }
            ini_restore('max_execution_time');
            ini_restore('memory_limit');

            switch (strtolower($type)) {
                default:
                case self::PSFS_DOC:
                    $doc = $endpoints;
                    break;
            }

            return $this->json($doc, 200);
        }
    }