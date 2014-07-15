<?php
/**
 * Api de conexión a Google url sortener
 */
namespace PSFS\social;

use PSFS\base\Request;
use PSFS\base\Template;
use PSFS\base\Logger;
use PSFS\base\Router;
use PSFS\social\form\GoogleUrlShortenerForm;
use PSFS\social\form\GenerateShortUrlForm;

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
     * Servicio que configura la api key de Google Url Shortener
     * @route /admin/social/gus
     */
    public function configApiKey()
    {
        Logger::getInstance()->infoLog("Arranque del Config Loader al solicitar ".Request::getInstance()->getrequestUri());
        /* @var $form \PSFS\social\form\GoogleUrlShortenerForm */
        $form = new GoogleUrlShortenerForm;
        $form->build();
        $form->setData(array(
            "api_key" => $this->api_key,
        ));
        if(Request::getInstance()->getMethod() == 'POST')
        {
            $form->hydrate();
            if($form->isValid())
            {
                if($this->save($form->getData()))
                {
                    Logger::getInstance()->infoLog("Configuración guardada correctamente");
                    return Request::getInstance()->redirect();
                }
                throw new \HttpException('Error al guardar la configuración, prueba a cambiar los permisos', 403);
            }
        }
        return Template::getInstance()->render('welcome.html.twig', array(
            'text' => _("Bienvenido a PSFS"),
            'config' => $form,
            "routes" => Router::getInstance()->getAdminRoutes(),
        ));
    }

    /**
     * Método interno que actualiza la información de la ApiKey de Google Url Shortener
     * @param $data
     *
     * @return int
     */
    private function save($data)
    {
        if(file_exists(CONFIG_DIR . '/apis.json')) $config = json_decode(file_get_contents(CONFIG_DIR . '/apis.json'), true);
        else $config = array();
        $config["GoogleUrlShortener"] = $data["api_key"];
        return file_put_contents(CONFIG_DIR . '/apis.json', json_encode($config));
    }

    /**
     * Servicio que genera la url acortada de una dirección
     * @route /admin/social/gus/generate
     * @return mixed
     * @throws \HttpException
     */
    public function genShortUrl()
    {
        Logger::getInstance()->infoLog("Arranque del Config Loader al solicitar ".Request::getInstance()->getrequestUri());
        /* @var $form \PSFS\social\form\GenerateShortUrlForm */
        $form = new GenerateShortUrlForm;
        $form->build();
        if(Request::getInstance()->getMethod() == 'POST')
        {
            $form->hydrate();
            if($form->isValid())
            {
                $data = $form->getData();
                pre($this->shortUrl($data["url"]), true);
            }
        }
        return Template::getInstance()->render('welcome.html.twig', array(
            'text' => _("Bienvenido a PSFS"),
            'config' => $form,
            "routes" => Router::getInstance()->getAdminRoutes(),
        ));
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
            $shortUrl = $shortUrl["id"];
        }
        return $shortUrl;
    }

}