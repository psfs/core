<?php

namespace PSFS\controller;

use PSFS\base\config\Config;
use PSFS\base\types\helpers\attributes\HttpMethod;
use PSFS\base\types\helpers\attributes\Icon;
use PSFS\base\types\helpers\attributes\Label;
use PSFS\base\types\helpers\attributes\Route;
use PSFS\base\types\helpers\attributes\Visible;
use PSFS\base\types\helpers\I18nHelper;
use PSFS\controller\base\Admin;

/**
 * Class I18nController
 * @package PSFS\controller
 */
class I18nController extends Admin
{

    /**
     * @GET
     * @route /admin/translations
     * @icon fa-language
     * @label Locale generator
     * @return string
     */
    #[HttpMethod('GET')]
    #[Route('/admin/translations')]
    #[Icon('fa-language')]
    #[Label('Locale generator')]
    public function defaultTranslations()
    {
        return $this->getTranslations(Config::getParam('default.language', 'en_US'));
    }

    /**
     * Method that regenerates the translations
     * @GET
     * @param $locale string
     * @route /admin/translations/{locale}
     * @label Locale generator
     * @visible false
     * @return string HTML
     */
    #[HttpMethod('GET')]
    #[Route('/admin/translations/{locale}')]
    #[Label('Locale generator')]
    #[Visible(false)]
    public function getTranslations($locale)
    {
        //Default locale
        if (null === $locale) {
            $locale = Config::getParam('default.language', 'en_US');
        }
        if (!I18nHelper::isValidLocale($locale)) {
            $locale = Config::getParam('default.language', 'en_US');
        }

        //Generating the templates translations
        $translations = $this->tpl->regenerateTemplates();

        $localePath = realpath(BASE_DIR . DIRECTORY_SEPARATOR . 'locale');
        $localePath .= DIRECTORY_SEPARATOR . $locale . DIRECTORY_SEPARATOR . 'LC_MESSAGES' . DIRECTORY_SEPARATOR;

        //xgettext localizations
        $translations = array_merge($translations, I18nHelper::findTranslations(SOURCE_DIR, $locale));
        $translations = array_merge($translations, I18nHelper::findTranslations(CORE_DIR, $locale));
        $translations = array_merge($translations, I18nHelper::findTranslations(CACHE_DIR, $locale));

        $translations[] = "msgfmt {$localePath}translations.po -o {$localePath}translations.mo";
        $translations[] = I18nHelper::compileTranslations($localePath);
        return $this->render('translations.html.twig', array(
            'translations' => $translations,
        ));
    }
}
