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
     * @var  user
     */
    private $user;

    private $authorized = false;

    public function __construct()
    {
        $this->checkAdmin();
    }

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
        if(!empty($admins)) foreach($admins as &$admin)
        {
            if(isset($admin["profile"]))
            {
                $admin["class"] = $admin["profile"] == sha1("admin") ? 'primary' : "warning";
            }else{
                $admin["class"] = "primary";
            }
        }
        return Template::getInstance()->render('admin.html.twig', array(
            'admins' => $admins,
            'form' => $form,
            'profiles' => self::getProfiles(),
        ));
    }

    /**
     * Método estático que devuelve los perfiles de la plataforma
     * @return array
     */
    public static function getProfiles()
    {
        return array(
            sha1('superadmin') => _('Administrador'),
            sha1('admin') => _('Gestor'),
        );
    }

    /**
     * Método estático que devuelve los perfiles disponibles
     * @return array
     */
    public static function getCleanProfiles()
    {
        return array(
            "__SUPER_ADMIN__" => sha1("superadmin"),
            "__ADMIN__" => sha1("admin"),
        );
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
        $admins[$user['username']]['profile'] = $user['profile'];
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
        if(!$this->authorized)
        {
            $request = Request::getInstance();
            if(!file_exists(CONFIG_DIR . DIRECTORY_SEPARATOR . 'admins.json'))
            {
                //Si no hay fichero de usuarios redirigimos directamente al gestor
                return $request->redirect(Router::getInstance()->getRoute('setup-admin'));
            }
            $admins = $this->getAdmins();
            //Sacamos las credenciales de la petición
            $user = $user ?: $request->getServer('PHP_AUTH_USER');
            $pass = $pass ?: $request->getServer('PHP_AUTH_PW');
            if(empty($user) && empty($admins[$user]))
            {
                list($user, $pass) = $this->getAdminFromCookie();
            }
            if(!empty($user) && !empty($admins[$user]))
            {
                $auth = $admins[$user]["hash"];
                $this->authorized = ($auth == sha1($user.$pass));
                $this->user = array(
                    "alias" => $user,
                    "profile" => $admins[$user]["profile"],
                );
            }
        }
        return $this->authorized;
    }

    /**
     * Método que obtiene el usuario y contraseña de la cookie de sesión de administración
     * @return array
     */
    protected function getAdminFromCookie()
    {
        $auth_cookie = Request::getInstance()->getCookie($this->getHash());
        $user = $pass = array();
        if(!empty($auth_cookie))
        {
            list($user, $pass) = explode(":", base64_decode($auth_cookie));
        }
        return array($user, $pass);
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

    /**
     * Método que devuelve el usuario logado
     * @return user
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Servicio que devuelve una pantalla de error porque se necesita estar authenticado
     * @param $route
     *
     * @return mixed
     */
    public function notAuthorized($route)
    {
        return Template::getInstance()->render("notauthorized.html.twig", array(
            'route' => $route,
        ));
    }
}