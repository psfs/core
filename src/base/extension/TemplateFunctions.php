<?php
    namespace PSFS\base\extension;

    use PSFS\base\config\Config;
    use PSFS\base\exception\ConfigException;
    use PSFS\base\Request;
    use PSFS\base\Router;
    use PSFS\base\Security;
    use PSFS\base\Template;
    use PSFS\base\types\Form;
    use PSFS\base\types\helpers\GeneratorHelper;
    use PSFS\Services\GeneratorService;
    use Symfony\Component\Translation\Tests\StringClass;

    class TemplateFunctions {

        const ASSETS_FUNCTION       = "\\PSFS\\base\\extension\\TemplateFunctions::asset";
        const ROUTE_FUNCTION        = "\\PSFS\\base\\extension\\TemplateFunctions::route";
        const CONFIG_FUNCTION       = "\\PSFS\\base\\extension\\TemplateFunctions::config";
        const BUTTON_FUNCTION       = "\\PSFS\\base\\extension\\TemplateFunctions::button";
        const WIDGET_FUNCTION       = "\\PSFS\\base\\extension\\TemplateFunctions::widget";
        const FORM_FUNCTION         = "\\PSFS\\base\\extension\\TemplateFunctions::form";
        const RESOURCE_FUNCTION     = "\\PSFS\\base\\extension\\TemplateFunctions::resource";
        const SESSION_FUNCTION      = "\\PSFS\\base\\extension\\TemplateFunctions::session";
        const EXISTS_FLASH_FUNCTION = "\\PSFS\\base\\extension\\TemplateFunctions::existsFlash";
        const GET_FLASH_FUNCTION    = "\\PSFS\\base\\extension\\TemplateFunctions::getFlash";

        /**
         * Función que copia los recursos de las carpetas Public al DocumentRoot
         * @param $string
         * @param null $name
         * @param bool|TRUE $return
         *
         * @return string
         */
        public static function asset($string, $name = null, $return = true) {

            $file_path = "";
            if (!file_exists($file_path)) {
                $file_path = BASE_DIR.$string;
            }
            $filename_path = AssetsParser::findDomainPath($string, $file_path);

            $file_path = self::processAsset($string, $name, $return, $filename_path);
            $return_path = (empty($name)) ? Request::getInstance()->getRootUrl().'/'.$file_path : $name;
            return ($return) ? $return_path : '';
        }

        /**
         * Función que devuelve una url correspondiente a una ruta
         * @param string $path
         * @param bool|FALSE $absolute
         * @param array $params
         *
         * @return string|null
         */
        public static function route($path = '', $absolute = false, array $params = null) {
            $router = Router::getInstance();
            try {
                return $router->getRoute($path, $absolute, $params);
            } catch (\Exception $e)
            {
                return $router->getRoute('', $absolute, $params);
            }
        }

        /**
         * Función que devuelve un parámetro de la configuración
         * @param $param
         * @param string $default
         *
         * @return string
         */
        public static function config($param, $default = '') {
            return Config::getInstance()->get($param) ?: $default;
        }

        /**
         * Método que devuelve un botón en html para la plantilla de formularios
         * @param array $button
         */
        public static function button(array $button) {
            Template::getInstance()->getTemplateEngine()->display('forms/button.html.twig', array(
                'button' => $button,
            ));
        }

        /**
         * Función que pinta parte de un formulario
         * @param array $field
         * @param StringClass $label
         */
        public static function widget(array $field, StringClass $label = null) {
            if (!empty($label)) {
                $field["label"] = $label;
            }
            //Limpiamos los campos obligatorios
            if (!isset($field["required"])) {
                $field["required"] = true;
            } elseif (isset($field["required"]) && (bool)$field["required"] === false) {
                unset($field["required"]);
            }
            Template::getInstance()->getTemplateEngine()->display('forms/field.html.twig', array(
                'field' => $field,
            ));
        }

        /**
         * Función que deveulve un formulario en html
         * @param Form $form
         */
        public static function form(Form $form) {
            Template::getInstance()->getTemplateEngine()->display('forms/base.html.twig', array(
                'form' => $form,
            ));
        }

        /**
         * Función que copia un recurso directamente en el DocumentRoot
         * @param string $path
         * @param string $dest
         * @param bool|FALSE $force
         *
         * @return string
         * @throws ConfigException
         */
        public static function resource($path, $dest, $force = false) {
            $debug = Config::getInstance()->getDebugMode();
            $domains = Template::getDomains(true);
            $filename_path = self::extractPathname($path, $domains);
            GeneratorService::copyResources($dest, $force, $filename_path, $debug);
            return '';
        }

        /**
         * Método que extrae el pathname para un dominio
         * @param string $path
         * @param $domains
         *
         * @return mixed
         */
        private static function extractPathname($path, $domains)
        {
            $filename_path = $path;
            if (!file_exists($path) && !empty($domains)) {
                foreach ($domains as $domain => $paths) {
                    $domain_filename = str_replace($domain, $paths["public"], $path);
                    if (file_exists($domain_filename)) {
                        $filename_path = $domain_filename;
                        continue;
                    }
                }

            }

            return $filename_path;
        }

        /**
         * @param string $filename_path
         */
        private static function processCssLines($filename_path)
        {
            $handle = @fopen($filename_path, 'r');
            if ($handle) {
                while (!feof($handle)) {
                    AssetsParser::extractCssLineResource($handle, $filename_path);
                }
                fclose($handle);
            }
        }

        /**
         * Método que copia el contenido de un recurso en su destino correspondiente
         * @param string $name
         * @param string $filename_path
         * @param string $base
         * @param string $file_path
         */
        private static function putResourceContent($name, $filename_path, $base, $file_path)
        {
            $data = file_get_contents($filename_path);
            if (!empty($name)) file_put_contents(WEB_DIR.DIRECTORY_SEPARATOR.$name, $data);
            else file_put_contents($base.$file_path, $data);
        }

        /**
         * Método que procesa un recurso para su copia en el DocumentRoot
         * @param string $string
         * @param string $name
         * @param boolean $return
         * @param string $filename_path
         *
         * @return string
         */
        private static function processAsset($string, $name, $return, $filename_path)
        {
            $file_path = $filename_path;
            if (file_exists($filename_path)) {
                list($base, $html_base, $file_path) = AssetsParser::calculateAssetPath($string, $name, $return, $filename_path);
                //Creamos el directorio si no existe
                GeneratorHelper::createDir($base.$html_base);
                //Si se ha modificado
                if (!file_exists($base.$file_path) || filemtime($base.$file_path) < filemtime($filename_path)) {
                    if ($html_base == 'css') {
                        self::processCssLines($filename_path);
                    }
                    self::putResourceContent($name, $filename_path, $base, $file_path);
                }
            }

            return $file_path;
        }

        /**
         * Template function for get a session var
         * @param string $key
         * @return mixed
         */
        public static function session($key) {
            return Security::getInstance()->getSessionKey($key);
        }

        /**
         * Template function that check if exists any flash session var
         * @param string $key
         * @return bool
         */
        public static function existsFlash($key = '') {
            return null !== Security::getInstance()->getFlash($key);
        }

        /**
         * Template function that get a flash session var
         * @param string $key
         * @return mixed
         */
        public static function getFlash($key) {
            $var = Security::getInstance()->getFlash($key);
            Security::getInstance()->setFlash($key, null);
            return $var;
        }
    }