<?php
    /**
     * Api de conexión a Google url sortener
     */
    namespace PSFS\base\social;

    use PSFS\base\Request;
    use PSFS\base\Template;
    use PSFS\base\Logger;
    use PSFS\base\Router;
    use PSFS\base\social\form\GoogleUrlShortenerForm;
    use PSFS\base\social\form\GenerateShortUrlForm;

    class GoogleUrlShortener{

        const URL_INSERT = "https://www.googleapis.com/urlshortener/v1/url?key={key}";
        const URL_GET = "https://www.googleapis.com/urlshortener/v1/url?key={key}&shortUrl={shrotUrl}";

        private $api_key;

        public function __construct()
        {
            if(file_exists(CONFIG_DIR . '/apis.json'))
            {
                $config = json_decode(file_get_contents(CONFIG_DIR . '/apis.json'), true);
                if(!empty($config["GoogleUrlShortener"]))
                {
                    $this->api_key = $config["GoogleUrlShortener"];
                }
            }
        }

        /**
         * Método que devuelveel apy key de Google
         * @return mixed
         */
        public function getApyKey(){ return $this->api_key; }

        /**
         * Método interno que actualiza la información de la ApiKey de Google Url Shortener
         * @param $data
         *
         * @return int
         */
        public function save($data)
        {
            if(file_exists(CONFIG_DIR . '/apis.json')) $config = json_decode(file_get_contents(CONFIG_DIR . '/apis.json'), true);
            else $config = array();
            $config["GoogleUrlShortener"] = $data["api_key"];
            return file_put_contents(CONFIG_DIR . '/apis.json', json_encode($config));
        }

        /**
         * Método que utilizada la api de Google Url Shortener para acortar una url
         * @param $url
         *
         * @return string
         */
        public function shortUrl($url)
        {
            $shortUrl = $url;
            $ch = curl_init();
            $post = json_encode(array("longUrl" => $url));
            curl_setopt($ch, CURLOPT_URL, str_replace("{key}", $this->api_key, self::URL_INSERT));
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT ,3);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($post))
            );
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
            $response = curl_exec($ch);
            curl_close($ch);
            if(!empty($response))
            {
                $shortUrl = json_decode($response, true);
                if(isset($shortUrl["id"])) $shortUrl = $shortUrl["id"];
                else $shortUrl= null;
            }
            return $shortUrl;
        }

    }