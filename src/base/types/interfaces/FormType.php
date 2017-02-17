<?php
namespace PSFS\base\types\interfaces;

interface FormType
{
    /**
     * Constantes de uso general
     */
    const SEPARATOR = '__SEPARATOR__';
    const VALID_NUMBER = '^[0-9]+$';
    const VALID_ALPHANUMERIC = '[A-Za-z0-9-_\","\s]+';
    const VALID_DATETIME = '/([0-2][0-9]{3})\-([0-1][0-9])\-([0-3][0-9])T([0-5][0-9])\:([0-5][0-9])\:([0-5][0-9])(Z|([\-\+]([0-1][0-9])\:00))/';
    const VALID_DATE = '(?:19|20)[0-9]{2}-(?:(?:0[1-9]|1[0-2])-(?:0[1-9]|1[0-9]|2[0-9])|(?:(?!02)(?:0[1-9]|1[0-2])-(?:30))|(?:(?:0[13578]|1[02])-31))'; //YYYY-MM-DD
    const VALID_COLOR = '^#?([a-fA-F0-9]{6}|[a-fA-F0-9]{3})$';
    const VALID_IPV4 = '((^|\.)((25[0-5])|(2[0-4]\d)|(1\d\d)|([1-9]?\d))){4}$';
    const VALID_IPV6 = '((^|:)([0-9a-fA-F]{0,4})){1,8}$';
    const VALID_GEO = '-?\d{1,3}\.\d+';
    const VALID_PASSWORD = '^(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?!.*\s).*$';
    const VALID_PASSWORD_STRONG = '(?=^.{8,}$)((?=.*\d)|(?=.*\W+))(?![.\n])(?=.*[A-Z])(?=.*[a-z]).*$';

    public function getName();
}