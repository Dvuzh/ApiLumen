<?php

namespace App;

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;

class DynamoDbProvider
{
    protected static $tableTypes = [
        'key' => 'S',
        'type' => 'S',
        'question_id' => 'N',
        'result' => 'N',
        'time_limit' => 'N',
        'time_used' => 'N',
        'results' => 'L',
    ];

    protected static $instanse = null;

    public static function getInstanse()
    {
        if (is_null(self::$instanse)) {
            $dynamoDb = new DynamoDbClient([
                'region' => env('DYNAMODB_REGION'),
                'version' => env('AWS_VERSION') ,
                'credentials' => [
                    'key' => env('DYNAMODB_KEY'),
                    'secret' => env('DYNAMODB_SECRET'),
                ],
            ]);
            self::$instanse = $dynamoDb;
        }
        return self::$instanse;
    }

    public static function find($value)
    {
        $dynamoDb = self::getInstanse();
        $itemTable = $dynamoDb->getItem(['TableName' => env('TABLE_DB_NAME'), 'Key' => ['key' => [self::$tableTypes['key'] => $value]]]); //->has("Item");// ["Item"];
        
        if (isset($itemTable["Item"])) {
            return self::getValue($itemTable["Item"], "results");
        } else {
            return null;
        }
    }

    public static function delete($value)
    {
        $dynamoDb = self::getInstanse();
        $itemTable = $dynamoDb->deleteItem(['TableName' => env('TABLE_DB_NAME'), 'Key' => ['key' => [self::$tableTypes['key'] => $value]]]);
        return true;
    }

    public static function addItem($key, $value)
    {
        $dynamoDb = self::getInstanse();

        $arrayToDb = ['TableName' => env('TABLE_DB_NAME') ,
            'Item' => [
                'key' => [self::$tableTypes['key'] => $key],
                'results' => [self::$tableTypes['results'] => $value],
            ],
        ];
        $itemTable = $dynamoDb->putItem($arrayToDb);
        return true;
    }

    public static function getValue($item, $name)
    {
        if (isset($item[$name]['NULL'])) {
            return null;
        }
        if (isset(self::$tableTypes[$name])) {
            return isset($item[$name][self::$tableTypes[$name]]) ? $item[$name][self::$tableTypes[$name]] : null;
        } else {
            $res = array_shift($item);
            return count($item[$name]) > 0 ? array_shift($item[$name]) : null;
        }
    }

    public static function calculatedItem($itemDB)
    {
        $percentage = 0;
        $time_limit = 0;
        $time_used = 0;
        $count = 0;
        $countStudyNote = 0;
        $countArray = 0;
        $percentageForOnlyStudyNote = 0;
       
        foreach ($itemDB as $item) {
            if (self::getValue($item['M'], "type") != 'studyNote') {
                $percentage += self::getValue($item['M'], "result") == null ? 0 : self::getValue($item['M'], "result");
                $count++;
            }
            if (self::getValue($item['M'], "type") == 'studyNote') {
                $percentageForOnlyStudyNote += self::getValue($item['M'], "result") == null ? 0 : self::getValue($item['M'], "result");
                $countStudyNote++;
            } 
            $time_limit += self::getValue($item['M'], "time_limit") == null ? 0 : self::getValue($item['M'], "time_limit");
            $time_used += self::getValue($item['M'], "time_used") == null ? 0 : self::getValue($item['M'], "time_used");
            $countArray++;
        }

        return $results = [
            'percentage' => $countStudyNote == $countArray ? ($percentageForOnlyStudyNote == 0 ? 0 : $percentageForOnlyStudyNote /= $countArray) 
            :  ($percentage == 0 ? 0 : $percentage /= $count),
            'time_limit' => $time_limit,
            'time_used' => $time_used,
        ];
    }

    public static function updateItem($itemDB, $type, $array, $key)
    {
        $newArray = [];
        foreach ($itemDB as $item) {
            if (self::getValue($item['M'], "type") != $type || (self::getValue($item['M'], "type") == $type && self::getValue($item['M'], "question_id") != $array['M']["question_id"]['N'] )) {
                array_push($newArray, $item);
            } else {
                array_push($newArray,  $array);
            }
        }

        self::delete($key);
        self::addItem($key, $newArray);
        return true;
    }
    
    public static function setValue($type, $value)
    {
        if ($value === null) {
            return ["NULL" => true];
        }
        if (isset(self::$tableTypes[$type])) {
            return [self::$tableTypes[$type] => (string) $value];
        }
        return ['S' => (string) $value];
    }

    public static function findItem($question_id,$type)
    {
        $dynamoDb = self::getInstanse();
        $marshaler = new Marshaler();
        $mass = [
            ":type" => (int)$type,
            ":question_id" => $question_id
        ];
        $eav = $marshaler->marshalJson(json_encode($mass,true));

        $queryString = '(results[0].question_id = :question_id AND results[0].#yr = :type)';
        for ($i=1; $i<=19; $i++) {
            $queryString .= 'OR (results['.$i.'].question_id = :question_id AND results['.$i.'].#yr = :type)'; 
        }

        
        $params = [
            'TableName' => env('TABLE_DB_NAME'),
            'ExpressionAttributeNames' => ['#yr' => 'type'],
            'FilterExpression' => $queryString,
            'ExpressionAttributeValues' => $eav,
            ];

            $result = $dynamoDb->scan($params);

           if( $result["Count"] == 0){
                return false;
           }
        return true;
    }
}
