<?php

namespace PSFS\base;

use PSFS\base\Singleton;
use PSFS\base\Request;
use PSFS\base\Logger;
use PSFS\base\Router;
use PSFS\config\AdminForm;
use PSFS\config\Config;
use PSFS\config\LoginForm;

/**
 * Class Security
 * @package PSFS
 */
class Security extends Singleton{

    /**
     * Método que gestiona los usuarios administradores de la plataforma
     * @route /setup-admin
     * @return mixed
     */
    public function adminers()
    {
        $admins = $this->getAdmins();
        if(!empty($admins))
        {
            if(!$this->checkAdmin())
            {
                if("login" === Config::getInstance()->get("admin_login")) return Security::getInstance()->adminLogin("/setup-admin");
                header('HTTP/1.1 401 Unauthorized');
                header('WWW-Authenticate: Basic Realm="PSFS"');
                echo _("Es necesario ser administrador para ver ésta zona");
                exit();
            }
        }
        $form = new AdminForm();
        $form->build();
        if(Request::getInstance()->getMethod() == 'POST')
        {
            $form->hydrate();
            if($form->isValid())
            {
                if(self::save($form->getData()))
                {
                    Logger::getInstance()->infoLog("Configuración guardada correctamente");
                    return Request::getInstance()->redirect();
                }
                throw new \HttpException('Error al guardar los administradores, prueba a cambiar los permisos', 403);
            }
        }
        return Template::getInstance()->render('admin.html.twig', array(
            'admins' => $admins,
            'form' => $form,
        ));
    }

    /**
     * Método que guarda los administradores
     * @param $user
     *
     * @return bool
     */
    public static function save($user)
    {
        $admins = array();
        if(file_exists(CONFIG_DIR . DIRECTORY_SEPARATOR . 'admins.json'))
        {
            $admins = json_decode(file_get_contents(CONFIG_DIR . DIRECTORY_SEPARATOR . 'admins.json'), true);
        }
        $admins[$user['username']]['hash'] = sha1($user['username'].$user['password']);
        return (false !== file_put_contents(CONFIG_DIR . DIRECTORY_SEPARATOR . 'admins.json', json_encode($admins)));;
    }

    /**
     * Método que devuelve los administradores de una plataforma
     * @return array|mixed
     */
    public function getAdmins()
    {
        $admins = array();
        if(file_exists(CONFIG_DIR . DIRECTORY_SEPARATOR . 'admins.json'))
        {
            $admins = json_decode(file_get_contents(CONFIG_DIR . DIRECTORY_SEPARATOR . 'admins.json'), true);
        }
        return $admins;
    }

    /**
     * Método que devuelve si un usuario tiene privilegios para acceder a la zona de administración
     * @return bool
     */
    public function checkAdmin($user = null, $pass = null)
    {
        $authorized = false;
        $request = Request::getInstance();
        if(!file_exists(CONFIG_DIR . DIRECTORY_SEPARATOR . 'admins.json'))
        {
            return $request->redirect(Router::getInstance()->getRoute('setup-admin'));
        }
        $admins = $this->getAdmins();
        //Sacamos las credenciales de la petición
        $user = $user ?: $request->getServer('PHP_AUTH_USER');
        $pass = $pass ?: $request->getServer('PHP_AUTH_PW');
        if(empty($user) && empty($admins[$user]))
        {
            $auth_cookie = $request->getCookie($this->getHash());
            if(!empty($auth_cookie))
            {
                list($user, $pass) = explode(":", base64_decode($auth_cookie));
            }
        }
        if(!empty($user) && !empty($admins[$user]))
        {
            $auth = $admins[$user]["hash"];
            $authorized = ($auth == sha1($user.$pass));
        }
        return $authorized;
    }

    /**
     * Método privado para la generación del hash de la cookie de administración
     * @return string
     */
    private function getHash(){ return substr(md5("admin"), 0, 8); }

    /**
     * Acción que pinta un formulario genérico de login pra la zona restringida
     * @params string $route
     * @route /admin/login
     * @return html
     */
    public function adminLogin($route = null)
    {
        $form = new LoginForm();
        if(Request::getInstance()->getMethod() == "GET") $form->setData(array("route" => $route));
        $form->build();
        if(Request::getInstance()->getMethod() == 'POST')
        {
            $form->hydrate();
            if($form->isValid())
            {
                if($this->checkAdmin($form->getFieldValue("user"), $form->getFieldValue("pass")))
                {
                    $cookies = array(
                        array(
                            "name" => $this->getHash(),
                            "value" => base64_encode($form->getFieldValue("user") . ":" . $form->getFieldValue("pass")),
                            "expire" => time() + 3600,
                            "http" => true,
                        )
                    );
                    return Template::getInstance()->render("redirect.html.twig", array(
                        'route' => $form->getFieldValue("route"),
                    ), $cookies);
                }else{
                    $form->setError("user", "El usuario no tiene acceso a la web");
                }
            }
        }
        return Template::getInstance()->render("login.html.twig", array(
            'form' => $form,
        ));
    }
}