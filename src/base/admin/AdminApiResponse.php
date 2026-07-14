<?php

namespace PSFS\base\admin;

class AdminApiResponse
{
    /** @return array{ok:true,message:?string,data:array,errors:object} */
    public static function success(array $data, ?string $message = null): array
    {
        return [
            'ok' => true,
            'message' => $message,
            'data' => $data,
            'errors' => (object) [],
        ];
    }

    /** @return array{ok:false,message:string,data:null,errors:object} */
    public static function failure(string $message, array $errors = []): array
    {
        $normalizedErrors = [];
        foreach ($errors as $field => $fieldErrors) {
            $normalizedErrors[(string) $field] = is_array($fieldErrors)
                ? array_map(static fn ($error): string => (string) $error, $fieldErrors)
                : [(string) $fieldErrors];
        }

        return [
            'ok' => false,
            'message' => $message,
            'data' => null,
            'errors' => (object) $normalizedErrors,
        ];
    }
}
