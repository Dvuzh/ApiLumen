<?php

namespace App;

use Aws\S3\S3Client;

class S3Provider
{

    protected static $instanse = null;

    public static function getInstanse()
    {
        if (is_null(self::$instanse)) {
        $client = new S3Client([
                'version' => env('AWS_VERSION'),
                'region' => env('AWS_REGION'),
                'credentials' => [
                    'key' => env('AWS_ACCESS_KEY_ID'),
                    'secret' => env('AWS_SECRET_ACCESS_KEY')
                ]
            ]);
            self::$instanse = $client;
        }
        return self::$instanse;
    }

    public static function putFile($pathToFile,$fileName){
        $client = self::getInstanse();

        // dd();

        $result = $client->putObject(array(
            'Bucket'     => env('AWS_S3_BUCKET'),
            'Key'        => $fileName,
            'SourceFile' => $pathToFile,
        ));

        
        return $client->getObjectUrl(env('AWS_S3_BUCKET'), $fileName);
    }
}