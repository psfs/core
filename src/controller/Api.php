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

        /**
         * @Inyectable
         * @var \PSFS\Services\DocumentorService srv
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
        public function createApiDocs($type = 'swagger')
        {
            $doc = $endpoints = [];
            $modules = $this->srv->getModules();
            if (count($modules)) {
                foreach ($modules as $module) {
                    $endpoints = array_merge($doc, $this->srv->extractApiEndpoints($module));
                }
            }

            return $this->json($endpoints, 200);
        }
    }