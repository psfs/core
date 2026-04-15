<?php

namespace PSFS\base\dto;

use PSFS\base\types\helpers\attributes\CsrfField;
use PSFS\base\types\helpers\attributes\CsrfProtected;
use PSFS\base\types\helpers\attributes\Length;
use PSFS\base\types\helpers\attributes\Pattern;
use PSFS\base\types\helpers\attributes\Required;
use PSFS\base\types\helpers\attributes\VarType;

#[CsrfProtected(formKey: 'admin_setup')]
#[CsrfField('admin_setup_token', 'admin_setup_token_key')]
class DeleteUserRequestDto extends Dto
{
    #[Required]
    #[VarType('string')]
    #[Length(min: 1, max: 120)]
    #[Pattern('/^[A-Za-z0-9._@\-]+$/')]
    public ?string $user = null;
}
