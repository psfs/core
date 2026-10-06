<?php

namespace PSFS\base\types\helpers;

use PSFS\base\types\traits\Generator\GeneratorDocumentRootTrait;
use PSFS\base\types\traits\Generator\GeneratorFilesystemTrait;
use PSFS\base\types\traits\Generator\GeneratorModulePathTrait;
use PSFS\base\types\traits\Generator\GeneratorNamespaceTrait;

/**
 * Backwards-compatible facade for module, filesystem, document-root and API helpers.
 *
 * @package PSFS\base\types\helpers
 */
class GeneratorHelper
{
    use GeneratorDocumentRootTrait;
    use GeneratorFilesystemTrait;
    use GeneratorModulePathTrait;
    use GeneratorNamespaceTrait;
}
