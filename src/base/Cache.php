<?php
    namespace PSFS\base;

    use PSFS\base\config\Config;
    use PSFS\base\exception\ConfigException;
    use PSFS\base\types\SingletonTrait;

    /**
     * Class Cache
     * @package PSFS\base
     * Gestión de los ficheros de cache
     */
    class Cache {

        const JSON = 1;
        const TEXT = 2;
        const ZIP = 3;

        use SingletonTrait;

        /**
         * Método que guarda un text en un fichero
         * @param string $data
         * @param string $path
         */
        private function saveTextToFile($data, $path) {
            $filename = basename($path);
            Config::createDir(CACHE_DIR.DIRECTORY_SEPARATOR.str_replace($filename, "", $path));
            if (false === file_put_contents(CACHE_DIR.DIRECTORY_SEPARATOR.$path, $data)) {
                throw new ConfigException(_("No se tienen los permisos suficientes para escribir en el fichero ").$path);
            }
        }

        /**
         * Método que extrae el texto de un fichero
         * @param string $path
         * @param int $transform
         * @return string
         */
        public function getDataFromFile($path, $transform = Cache::TEXT) {
            $data = null;
            if (file_exists(CACHE_DIR.DIRECTORY_SEPARATOR.$path)) {
                $data = file_get_contents(CACHE_DIR.DIRECTORY_SEPARATOR.$path);
            }
            return Cache::extractDataWithFormat($data, $transform);
        }

        /**
         * Método que verifica si un fichero tiene la cache expirada
         * @param string $path
         * @param int $expires
         * @return bool
         */
        private function hasExpiredCache($path, $expires = 300) {
            $lasModificationDate = filemtime(CACHE_DIR.DIRECTORY_SEPARATOR.$path);
            return ($lasModificationDate + $expires <= time());
        }

        /**
         * Método que transforma los datos de salida
         * @param string $data
         * @param int $transform
         * @return string
         */
        public static function extractDataWithFormat($data, $transform = Cache::TEXT) {
            switch ($transform) {
                case Cache::JSON:
                    $data = json_decode($data, true);
                    break;
                case Cache::ZIP:
                    // TODO implementar
                case Cache::TEXT:
                default:
                    //do nothing
                    break;
            }
            return $data;
        }

        /**
         * Método que transforma los datos de entrada del fichero
         * @param string $data
         * @param int $transform
         * @return string
         */
        public static function transformData($data, $transform = Cache::TEXT) {
            switch ($transform) {
                case Cache::JSON:
                    $data = json_encode($data, JSON_PRETTY_PRINT);
                    break;
                case Cache::ZIP:
                    // TODO implementar
                case Cache::TEXT:
                default:
                    //do nothing
                    break;
            }
            return $data;
        }

        /**
         * Método que guarda en fichero los datos pasados
         * @param $path
         * @param $data
         * @param int $transform
         */
        public function storeData($path, $data, $transform = Cache::TEXT) {
            $data = Cache::transformData($data, $transform);
            $this->saveTextToFile($data, $path);
        }

        /**
         * Método que verifica si tiene que leer o no un fichero de cache
         * @param $path
         * @param int $expires
         * @param callable $function
         * @param int $transform
         * @return string|null
         */
        public function readFromCache($path, $expires = 300, callable $function, $transform = Cache::TEXT) {
            $data = null;
            if (file_exists(CACHE_DIR.DIRECTORY_SEPARATOR.$path)) {
                if (null !== $function && $this->hasExpiredCache($path, $expires)) {
                    $data = call_user_func($function);
                    $this->storeData($path, $data, $transform);
                }else {
                    $data = $this->getDataFromFile($path, $transform);
                }
            }
            return $data;
        }
    }
