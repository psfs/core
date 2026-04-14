<?php

namespace PSFS\base\extension;

use Firebase\JWT\JWT;
use PSFS\base\Cache;
use PSFS\base\config\Config;
use PSFS\base\exception\GeneratorException;
use PSFS\base\Logger;
use PSFS\base\Request;
use PSFS\base\Router;
use PSFS\base\Security;
use PSFS\base\Template;
use PSFS\base\types\Form;
use PSFS\base\types\helpers\AssetsHelper;
use PSFS\base\types\helpers\AuthHelper;
use PSFS\base\types\helpers\FileHelper;
use PSFS\base\types\helpers\GeneratorHelper;
use PSFS\base\types\helpers\I18nHelper;
use PSFS\base\types\helpers\LocaleHelper;

/**
 * @package PSFS\base\extension
 */
class TemplateFunctions
{
    private const FALLBACK_ADMIN_LOCALES = 'en_US,es_ES';

    const ASSETS_FUNCTION = '\\PSFS\\base\\extension\\TemplateFunctions::asset';
    const ROUTE_FUNCTION = '\\PSFS\\base\\extension\\TemplateFunctions::route';
    const CONFIG_FUNCTION = '\\PSFS\\base\\extension\\TemplateFunctions::config';
    const BUTTON_FUNCTION = '\\PSFS\\base\\extension\\TemplateFunctions::button';
    const WIDGET_FUNCTION = '\\PSFS\\base\\extension\\TemplateFunctions::widget';
    const FORM_FUNCTION = '\\PSFS\\base\\extension\\TemplateFunctions::form';
    const RESOURCE_FUNCTION = '\\PSFS\\base\\extension\\TemplateFunctions::resource';
    const SESSION_FUNCTION = '\\PSFS\\base\\extension\\TemplateFunctions::session';
    const EXISTS_FLASH_FUNCTION = '\\PSFS\\base\\extension\\TemplateFunctions::existsFlash';
    const GET_FLASH_FUNCTION = '\\PSFS\\base\\extension\\TemplateFunctions::getFlash';
    const GET_QUERY_FUNCTION = '\\PSFS\\base\\extension\\TemplateFunctions::query';
    const AVAILABLE_LOCALES_FUNCTION = '\\PSFS\\base\\extension\\TemplateFunctions::availableLocales';
    const ENCRYPT_FUNCTION = '\PSFS\base\extension\TemplateFunctions::encrypt';
    const AUTH_TOKEN_FUNCTION = '\PSFS\base\extension\TemplateFunctions::generateAuthToken';
    const JWT_TOKEN_FUNCTION = '\PSFS\base\extension\TemplateFunctions::generateJWTToken';

    /**
     * @param string $string
     * @param string|null $name
     * @param bool $return
     * @return string|null
     * @throws \PSFS\base\exception\GeneratorException
     */
    public static function asset(string $string, string $name = null, bool $return = true): ?string
    {
        $filePath = $returnPath = '';
        if (!file_exists($filePath)) {
            $filePath = BASE_DIR . $string;
        }
        $filenamePath = AssetsHelper::findDomainPath($string, $filePath);
        if (!empty($filenamePath)) {
            $filePath = self::processAsset($string, $name, $return, $filenamePath);
            $basePath = Config::getParam('resources.cdn.url', Request::getInstance()->getRootUrl(false));
            $returnPath = empty($name) ? $basePath . '/' . $filePath : $name;
        }
        return $return ? $returnPath : '';
    }

    /**
     * @param string $path
     * @param bool $absolute
     * @param array $params
     *
     * @return string|null
     */
    public static function route(string $path = '', bool $absolute = false, array $params = []): ?string
    {
        $router = Router::getInstance();
        try {
            return $router->getRoute($path, $absolute, $params);
        } catch (\Exception $e) {
            Logger::log($e->getMessage());
            return $router->getRoute('', $absolute, $params);
        }
    }

    /**
     * @param string $param
     * @param string $default
     *
     * @return mixed|null
     */
    public static function config(string $param, string $default = ''): mixed
    {
        return Config::getInstance()->get($param) ?: $default;
    }

    /**
     * @param string $query
     *
     * @return string
     */
    public static function query(string $query): string
    {
        return Request::getInstance()->getQuery($query);
    }

    /**
     * Build an admin locale list from i18n.locales.
     * If the key is missing, keep the UX strict with English + Spanish only.
     */
    public static function availableLocales(): array
    {
        $configuredLocales = (string)Config::getParam('i18n.locales', '');
        $sessionLocale = Security::getInstance()->getSessionKey(I18nHelper::PSFS_SESSION_LOCALE_KEY);
        $defaultLocale = (string)Config::getParam('default.language', 'en_US');
        return LocaleHelper::buildAvailableLocales(
            $configuredLocales,
            is_string($sessionLocale) ? $sessionLocale : null,
            $defaultLocale,
            self::FALLBACK_ADMIN_LOCALES
        );
    }

    /**
     * @param array $button
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public static function button(array $button): void
    {
        Template::getInstance()->getTemplateEngine()->display('forms/button.html.twig', array(
            'button' => $button,
        ));
    }

    /**
     * @param array $field
     * @param string|null $label
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public static function widget(array $field, string $label = null): void
    {
        if (null !== $label) {
            $field['label'] = $label;
        }
        // Normalize required field defaults
        if (!isset($field['required'])) {
            $field['required'] = true;
        }
        if (isset($field['required']) && (bool)$field['required'] === false) {
            unset($field['required']);
        }
        Template::getInstance()->getTemplateEngine()->display('forms/field.html.twig', array(
            'field' => $field,
        ));
    }

    /**
     * @param Form $form
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public static function form(Form $form): void
    {
        Template::getInstance()->getTemplateEngine()->display('forms/base.html.twig', array(
            'form' => $form,
        ));
    }

    /**
     * @param string $path
     * @param string $dest
     * @param bool|bool $force
     *
     * @return string
     * @throws \PSFS\base\exception\GeneratorException
     */
    public static function resource(string $path, string $dest, bool $force = false): string
    {
        $debug = Config::getParam('debug');
        $domains = Template::getDomains(true);
        $filenamePath = self::extractPathname($path, $domains);
        // Check if resources has been copied to public folders
        if (!$debug) {
            $cacheFilename = Config::getParam('cache.var', '__initial__') . '.file.cache';
            $lockPath = CACHE_DIR . DIRECTORY_SEPARATOR . $cacheFilename . '.lock';
            FileHelper::withExclusiveLock(
                $lockPath,
                function () use ($cacheFilename, $filenamePath, &$force) {
                    $cachedFiles = Cache::getInstance()->readFromCache(
                        $cacheFilename,
                        1,
                        fn() => [],
                        Cache::JSON,
                        true
                    ) ?: [];
                    // Force the resource copy
                    if (!in_array($filenamePath, $cachedFiles, true) || $force) {
                        $force = true;
                        $cachedFiles[] = $filenamePath;
                        Cache::getInstance()->storeData($cacheFilename, $cachedFiles, Cache::JSON);
                    }
                }
            );
        }
        GeneratorHelper::copyResources($dest, $force, $filenamePath, $debug);
        return '';
    }

    /**
     * @param string $path
     * @param $domains
     *
     * @return string|array
     */
    private static function extractPathname(string $path, $domains): string|array
    {
        $filenamePath = $path;
        if (!empty($domains) && !file_exists($path)) {
            foreach ($domains as $domain => $paths) {
                $publicPath = $paths['public'] ?? null;
                if (!is_string($publicPath) || $publicPath === '') {
                    continue;
                }
                $domainFilename = str_replace($domain, $publicPath, $path);
                if (file_exists($domainFilename)) {
                    $filenamePath = $domainFilename;
                    break;
                }
            }
        }

        return $filenamePath;
    }

    /**
     * @param $filenamePath
     * @throws \PSFS\base\exception\GeneratorException
     */
    private static function processCssLines($filenamePath): void
    {
        $handle = @fopen($filenamePath, 'r');
        if ($handle) {
            while (!feof($handle)) {
                AssetsParser::extractCssLineResource($handle, $filenamePath);
            }
            fclose($handle);
        }
    }

    /**
     * @param string|null $name
     * @param string $filenamePath
     * @param string $base
     * @param string $filePath
     */
    private static function putResourceContent(
        string|null $name,
        string $filenamePath,
        string $base,
        string $filePath
    ): void {
        $data = file_get_contents($filenamePath);
        if (!empty($name)) {
            FileHelper::writeFileAtomic(WEB_DIR . DIRECTORY_SEPARATOR . $name, $data);
        } else {
            FileHelper::writeFileAtomic($base . $filePath, $data);
        }
    }

    /**
     * @param string $string
     * @param string|null $name
     * @param boolean $return
     * @param string $filenamePath
     * @return string
     * @throws GeneratorException
     */
    private static function processAsset(
        string $string,
        string|null $name = null,
        bool $return = true,
        string $filenamePath = ''
    ): string {
        $filePath = $filenamePath;
        if (file_exists($filenamePath)) {
            list($base, $htmlBase, $filePath) = AssetsHelper::calculateAssetPath(
                $string,
                $name,
                $return,
                $filenamePath
            );
            // Create directory when it does not exist.
            GeneratorHelper::createDir($base . $htmlBase);
            //Si se ha modificado
            if (!file_exists($base . $filePath) || filemtime($base . $filePath) < filemtime($filenamePath)) {
                if ($htmlBase === 'css') {
                    self::processCssLines($filenamePath);
                }
                self::putResourceContent($name, $filenamePath, $base, $filePath);
            }
        }

        return $filePath;
    }

    /**
     * @param string $key
     * @return mixed
     */
    public static function session(string $key): mixed
    {
        return Security::getInstance()->getSessionKey($key);
    }

    /**
     * @param string $key
     * @return bool
     */
    public static function existsFlash(string $key = ''): bool
    {
        return null !== Security::getInstance()->getFlash($key);
    }

    /**
     * @param string $key
     * @return mixed
     */
    public static function getFlash(string $key): mixed
    {
        $var = Security::getInstance()->getFlash($key);
        Security::getInstance()->setFlash($key, null);
        return $var;
    }

    public static function encrypt(string $string, string $key): string
    {
        return AuthHelper::encrypt($string, $key);
    }

    public static function generateAuthToken(string $user, string $password, $userAgent = null)
    {
        return AuthHelper::generateToken($user, $password, $userAgent);
    }

    public static function generateJWTToken(string $user, string $module, string $password)
    {
        return JWT::encode([
            'iss' => Config::getParam('platform.name', 'PSFS'),
            'sub' => $user,
            'aud' => $module,
            'iat' => time(),
            'exp' => time() + (int)Config::getParam('jwt.expiration_seconds', 3600),
        ], sha1($user . $password), Config::getParam('jwt.alg', 'HS256'));
    }

}
