<?php
    namespace PSFS\Services;

    use PSFS\base\config\Config;
    use PSFS\base\Service;

    class GeneratorService extends Service{
        /**
         * @Inyectable
         * @var \PSFS\base\config\Config Servicio de configuración
         */
        protected $config;
        /**
         * @Inyectable
         * @var \PSFS\base\Security Servicio de autenticación
         */
        protected $security;
        /**
         * @Inyectable
         * @var \PSFS\base\Template Servicio de gestión de plantillas
         */
        protected $tpl;

        /**
         * Método que revisa las traducciones directorio a directorio
         * @param $path
         * @param $locale
         * @return boolean
         */
        public static function findTranslations($path, $locale)
        {
            $locale_path = realpath(BASE_DIR . DIRECTORY_SEPARATOR . 'locale');
            $locale_path .= DIRECTORY_SEPARATOR . $locale . DIRECTORY_SEPARATOR . 'LC_MESSAGES' . DIRECTORY_SEPARATOR;

            $translations = false;
            if(file_exists($path))
            {
                $d = dir($path);
                while(false !== ($dir = $d->read()))
                {
                    Config::createDir($locale_path);
                    if(!file_exists($locale_path . 'translations.po')) file_put_contents($locale_path . 'translations.po', '');
                    $inspect_path = realpath($path.DIRECTORY_SEPARATOR.$dir);
                    $cmd_php = "export PATH=\$PATH:/opt/local/bin; xgettext ". $inspect_path . DIRECTORY_SEPARATOR ."*.php --from-code=UTF-8 -j -L PHP --debug --force-po -o {$locale_path}translations.po";
                    if(is_dir($path.DIRECTORY_SEPARATOR.$dir) && preg_match('/^\./',$dir) == 0)
                    {
                        echo "<li>" . _('Revisando directorio: ') . $inspect_path;
                        echo "<li>" . _('Comando ejecutado: '). $cmd_php;
                        shell_exec($cmd_php);// . " >> " . __DIR__ . DIRECTORY_SEPARATOR . "debug.log 2>&1");
                        usleep(10);
                        $translations = self::findTranslations($inspect_path, $locale);
                    }
                }
            }
            return $translations;
        }

        /**
         * Servicio que genera la estructura de un módulo o lo actualiza en caso de ser necesario
         * @param $module
         * @param $logger
         * @param $pb
         *
         * @return mixed
         */
        public function createStructureModule($module, $logger, $pb = null)
        {
            $mod_path = CORE_DIR . DIRECTORY_SEPARATOR;
            $module = ucfirst($module);
            $this->createModulePath($module, $mod_path);
            $this->createModulePathTree($module, $logger, $mod_path);
            $this->createModuleBaseFiles($module, $logger, $mod_path);
            $this->createModuleModels($module, $logger);
            //Redireccionamos al home definido
            $logger->infoLog("Módulo generado correctamente");
        }

        /**
         * Servicio que genera el path base del módulo
         * @param $module
         * @param $mod_path
         */
        private function createModulePath($module, $mod_path)
        {
            //Creamos el directorio base de los módulos
            Config::createDir($mod_path);
            //Creamos la carpeta del módulo
            Config::createDir($mod_path . $module);
        }

        /**
         * Servicio que genera la estructura base
         * @param $module
         * @param $logger
         * @param $mod_path
         */
        private function createModulePathTree($module, $logger, $mod_path)
        {
            //Creamos las carpetas CORE del módulo
            $logger->infoLog("Generamos la estructura");
            $paths = array("Config", "Controller", "Form", "Models", "Public", "Templates", "Services", "Test");
            foreach($paths as $path) {
                Config::createDir($mod_path . $module . DIRECTORY_SEPARATOR . $path);
            }
            //Creamos las carpetas de los assets
            $htmlPaths = array("css", "js", "img", "media", "font");
            foreach($htmlPaths as $path) {
                Config::createDir($mod_path . $module . DIRECTORY_SEPARATOR . "Public" . DIRECTORY_SEPARATOR . $path);
            }
        }

        /**
         * Servicio que genera las plantillas básicas de ficheros del módulo
         * @param $module
         * @param $logger
         * @param $mod_path
         */
        private function createModuleBaseFiles($module, $logger, $mod_path)
        {
            $this->generateControllerTemplate($module, $logger, $mod_path);
            $this->generateServiceTemplate($module, $logger, $mod_path);
            $this->genereateAutoloaderTemplate($module, $logger, $mod_path);
            $this->generateSchemaTemplate($module, $logger, $mod_path);
            $this->generatePropertiesTemplate($module, $logger, $mod_path);
            $this->generateIndexTemplate($module, $logger, $mod_path);
        }

        /**
         * Servicio que ejecuta Propel y genera el modelo de datos
         * @param $module
         * @param $logger
         */
        private function createModuleModels($module, $logger)
        {
            //Generamos las clases de propel y la configuración
            $exec = "export PATH=\$PATH:/opt/local/bin; " . BASE_DIR . DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR . "bin" . DIRECTORY_SEPARATOR . "propel ";
            $schemaOpt = " --schema-dir=" . CORE_DIR . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . "Config";
            $opt = " --config-dir=" . CORE_DIR . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . "Config --output-dir=" . CORE_DIR . " --verbose";
            $ret = shell_exec($exec . "build" . $opt . $schemaOpt);

            $logger->infoLog("Generamos clases invocando a propel:\n $ret");
            $ret = shell_exec($exec . "sql:build" . $opt . " --output-dir=" . CORE_DIR . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . "Config" . $schemaOpt);
            $logger->infoLog("Generamos sql invocando a propel:\n $ret");
            $ret = shell_exec($exec . "config:convert" . $opt . " --output-dir=" . CORE_DIR . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . "Config");
            $logger->infoLog("Generamos configuración invocando a propel:\n $ret");
        }

        /**
         * @param $module
         * @param $logger
         * @param $mod_path
         */
        private function generateControllerTemplate($module, $logger, $mod_path)
        {
            //Generamos el controlador base
            $logger->infoLog("Generamos el controlador BASE");
            $controller = $this->tpl->dump("generator/controller.template.twig", array(
                "module" => $module,
            ));
            $this->writeTemplateToFile($controller, $mod_path . $module . DIRECTORY_SEPARATOR . "Controller" . DIRECTORY_SEPARATOR . "{$module}Controller.php");
        }

        /**
         * @param $module
         * @param $logger
         * @param $mod_path
         */
        private function generateServiceTemplate($module, $logger, $mod_path)
        {
            //Generamos el controlador base
            $logger->infoLog("Generamos el servicio BASE");
            $controller = $this->tpl->dump("generator/service.template.twig", array(
                "module" => $module,
            ));
            $this->writeTemplateToFile($controller, $mod_path . $module . DIRECTORY_SEPARATOR . "Services" . DIRECTORY_SEPARATOR . "{$module}Service.php");
        }

        /**
         * @param $module
         * @param $logger
         * @param $mod_path
         */
        private function genereateAutoloaderTemplate($module, $logger, $mod_path)
        {
            //Generamos el autoloader del módulo
            $logger->infoLog("Generamos el autoloader");
            $autoloader = $this->tpl->dump("generator/autoloader.template.twig", array(
                "module" => $module,
            ));
            $this->writeTemplateToFile($autoloader, $mod_path . $module . DIRECTORY_SEPARATOR . "autoload.php");
        }

        /**
         * @param $module
         * @param $logger
         * @param $mod_path
         */
        private function generateSchemaTemplate($module, $logger, $mod_path)
        {
            //Generamos el autoloader del módulo
            $logger->infoLog("Generamos el schema");
            $schema = $this->tpl->dump("generator/schema.propel.twig", array(
                "module" => $module,
                "db"     => $this->config->get("db_name"),
            ));
            $this->writeTemplateToFile($schema, $mod_path . $module . DIRECTORY_SEPARATOR . "Config" . DIRECTORY_SEPARATOR . "schema.xml");
        }

        /**
         * @param $module
         * @param $logger
         * @param $mod_path
         */
        private function generatePropertiesTemplate($module, $logger, $mod_path)
        {
            $logger->infoLog("Generamos la configuración de Propel");
            $build_properties = $this->tpl->dump("generator/build.properties.twig", array(
                "module" => $module,
                "host"   => $this->config->get("db_host"),
                "port"   => $this->config->get("db_port"),
                "user"   => $this->config->get("db_user"),
                "pass"   => $this->config->get("db_password"),
                "db"     => $this->config->get("db_name"),
            ));
            $this->writeTemplateToFile($build_properties, $mod_path . $module . DIRECTORY_SEPARATOR . "Config" . DIRECTORY_SEPARATOR . "propel.yml");
        }

        /**
         * @param $module
         * @param $logger
         * @param $mod_path
         */
        private function generateIndexTemplate($module, $logger, $mod_path)
        {
            //Generamos la plantilla de index
            $index = $this->tpl->dump("generator/index.template.twig");
            $logger->infoLog("Generamos una plantilla base por defecto");
            $this->writeTemplateToFile($index, $mod_path . $module . DIRECTORY_SEPARATOR . "Templates" . DIRECTORY_SEPARATOR . "index.html.twig");
        }

        /**
         * Método que graba el contenido de una plantilla en un fichero
         * @param $fileContent
         * @param $filename
         */
        private function writeTemplateToFile($fileContent, $filename) {
            if (!file_exists($filename)) {
                file_put_contents($filename, $fileContent);
            }else{
                $this->log->errorLog($filename . " not exists or cant write");
            }
        }
    }