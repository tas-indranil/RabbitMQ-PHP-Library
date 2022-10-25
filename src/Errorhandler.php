<?php

namespace tasindranil;

use Exception;

class ErrorHandler
{
    private static array $errorList = [
        "file_not_found" => "File Do not exists. Please provide a valid file",
        "invalid_exchange"  =>  "Please provide a valid exchange name"
    ];

    public static function handleException(Exception $exception)
    {
        return [
            "error_code" => $exception->getCode(),
            "error_message" => $exception->getMessage(),
            "error_trace" => $exception->getTrace()
        ];
    }


    public static function errorString(string $error): string
    {
        return self::$errorList[$error];
    }

}