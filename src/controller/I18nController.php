<?php
namespace PSFS\controller;

use PSFS\base\config\Config;
use PSFS\controller\base\Admin;
use PSFS\Services\GeneratorService;

/**
 * Class I18nController
 * @package PSFS\controller
 */
class I18nController extends Admin
{

    /**
     * @GET
     * @route /admin/translations
     * @label Generador de locales
     * @return string
     */
    public function defaultTranslations()
    {
        return $this->getTranslations(Config::getParam('default.language', 'es_ES'));
    }

    /**
     * Method that regenerates the translations
     * @GET
     * @param $locale string
     * @route /admin/translations/{locale}
     * @label Generador de locales
     * @visible false
     * @return string HTML
     */
    public function getTranslations($locale)
    {
        //Default locale
        if (null === $locale) {
            $locale = Config::getParam('default.language', 'es_ES');
        }

        //Generating the templates translations
        $translations = $this->tpl->regenerateTemplates();

        $localePath = realpath(BASE_DIR . DIRECTORY_SEPARATOR . 'locale');
        $localePath .= DIRECTORY_SEPARATOR . $locale . DIRECTORY_SEPARATOR . 'LC_MESSAGES' . DIRECTORY_SEPARATOR;

        //xgettext localizations
        $translations = array_merge($translations, GeneratorService::findTranslations(SOURCE_DIR, $locale));
        $translations = array_merge($translations, GeneratorService::findTranslations(CORE_DIR, $locale));
        $translations = array_merge($translations, GeneratorService::findTranslations(CACHE_DIR, $locale));

        $translations[] = 'msgfmt {$locale_path}translations.po -o {$locale_path}translations.mo';
        $translations[] = shell_exec('export PATH=\$PATH:/opt/local/bin:/bin:/sbin; msgfmt '. $localePath . 'translations.po -o ' . $localePath . 'translations.mo');
        return $this->render('translations.html.twig', array(
            'translations' => $translations,
        ));
    }
}