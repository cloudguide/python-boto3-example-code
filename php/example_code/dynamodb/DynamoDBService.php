<?php

// Copyright Amazon.com, Inc. or its affiliates. All Rights Reserved.
// SPDX-License-Identifier: Apache-2.0

namespace DynamoDb;

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use AwsUtilities\AWSServiceClass;
use Exception;

class DynamoDBService extends AWSServiceClass
{
    public function __construct(
        DynamoDbClient $client = null,
        string $region = 'us-west-2',
        string $version = 'latest',
        string $profile = 'default'
    ) {
        if (gettype($client) == DynamoDbClient::class) {
            $this->dynamoDbClient = $client;
            return;
        }
        $this->dynamoDbClient = new DynamoDbClient([
            'region' => $region,
            'version' => $version,
            'profile' => $profile,
            'http' => [
                'verify' => false,
            ],
        ]);
        /* Inline declaration example
        # snippet-start:[php.example_code.ddb.basics.createClient]
        $dynamoDbClient = new DynamoDbClient(['region' => 'us-west-2', 'version' => 'latest', 'profile' => 'default']);
        # snippet-end:[php.example_code.ddb.basics.createClient]
        */
    }

    # snippet-start:[php.example_code.dynamodb.service.createTable]
    public function createTable(string $tableName, array $attributes)
    {
        $keySchema = [];
        $attributeDefinitions = [];
        foreach ($attributes as $attribute) {
            if (is_a($attribute, DynamoDBAttribute::class)) {
                $keySchema[] = ['AttributeName' => $attribute->AttributeName, 'KeyType' => $attribute->KeyType];
                $attributeDefinitions[] =
                    ['AttributeName' => $attribute->AttributeName, 'AttributeType' => $attribute->AttributeType];
            }
        }

        $this->dynamoDbClient->createTable([
            'TableName' => $tableName,
            'KeySchema' => $keySchema,
            'AttributeDefinitions' => $attributeDefinitions,
            'ProvisionedThroughput' => ['ReadCapacityUnits' => 10, 'WriteCapacityUnits' => 10],
        ]);
    }
    # snippet-end:[php.example_code.dynamodb.service.createTable]

    # snippet-start:[php.example_code.dynamodb.service.listTables]
    public function listTables($exclusiveStartTableName = "", $limit = 100)
    {
        $this->dynamoDbClient->listTables([
            'ExclusiveStartTableName' => $exclusiveStartTableName,
            'Limit' => $limit,
        ]);
    }
    # snippet-end:[php.example_code.dynamodb.service.listTables]

    # snippet-start:[php.example_code.dynamodb.service.deleteItem]
    public function deleteItemByKey(string $tableName, array $key)
    {
        $this->dynamoDbClient->deleteItem([
            'Key' => $key['Key'],
            'TableName' => $tableName,
        ]);
    }
    # snippet-end:[php.example_code.dynamodb.service.deleteItem]

    # snippet-start:[php.example_code.dynamodb.service.deleteTable]
    public function deleteTable(string $TableName)
    {
        $this->customWaiter(function () use ($TableName) {
            return $this->dynamoDbClient->deleteTable([
                'TableName' => $TableName,
            ]);
        });
    }
    # snippet-end:[php.example_code.dynamodb.service.deleteTable]

    # snippet-start:[php.example_code.dynamodb.service.getItem]
    public function getItemByKey(string $tableName, array $key)
    {
        return $this->dynamoDbClient->getItem([
            'Key' => $key['Key'],
            'TableName' => $tableName,
        ]);
    }
    #snippet-end:[php.example_code.dynamodb.service.getItem]

    #snippet-start:[php.example_code.dynamodb.service.putItem]
    public function putItem(array $array)
    {
        $this->dynamoDbClient->putItem($array);
    }
    #snippet-end:[php.example_code.dynamodb.service.putItem]

    #snippet-start:[php.example_code.dynamodb.service.query]
    public function query(string $tableName, $key)
    {
        $expressionAttributeValues = [];
        $expressionAttributeNames = [];
        $keyConditionExpression = "";
        $index = 1;
        foreach ($key as $name => $value) {
            $keyConditionExpression .= "#" . array_key_first($value) . " = :v$index,";
            $expressionAttributeNames["#" . array_key_first($value)] = array_key_first($value);
            $hold = array_pop($value);
            $expressionAttributeValues[":v$index"] = [
                array_key_first($hold) => array_pop($hold),
            ];
        }
        $keyConditionExpression = substr($keyConditionExpression, 0, -1);
        $query = [
            'ExpressionAttributeValues' => $expressionAttributeValues,
            'ExpressionAttributeNames' => $expressionAttributeNames,
            'KeyConditionExpression' => $keyConditionExpression,
            'TableName' => $tableName,
        ];
        return $this->dynamoDbClient->query($query);
    }
    #snippet-end:[php.example_code.dynamodb.service.query]

    #snippet-start:[php.example_code.dynamodb.service.scan]
    public function scan(string $tableName, array $key, string $filters)
    {
        $query = [
            'ExpressionAttributeNames' => ['#year' => 'year'],
            'ExpressionAttributeValues' => [
                ":min" => ['N' => '1990'],
                ":max" => ['N' => '1999'],
            ],
            'FilterExpression' => "#year between :min and :max",
            'TableName' => $tableName,
        ];
        return $this->dynamoDbClient->scan($query);
    }
    #snippet-end:[php.example_code.dynamodb.service.scan]

    #snippet-start:[php.example_code.dynamodb.service.updateItem]
    public function updateItemAttributeByKey(
        string $tableName,
        array $key,
        string $attributeName,
        string $attributeType,
        string $newValue
    ) {
        $this->dynamoDbClient->updateItem([
            'Key' => $key['Key'],
            'TableName' => $tableName,
            'UpdateExpression' => "set #NV=:NV",
            'ExpressionAttributeNames' => [
                '#NV' => $attributeName,
            ],
            'ExpressionAttributeValues' => [
                ':NV' => [
                    $attributeType => $newValue
                ]
            ],
        ]);
    }
    #snippet-end:[php.example_code.dynamodb.service.updateItem]

    public function updateItemAttributesByKey(string $tableName, array $key, array $attributes)
    {
        $updateExpression = "set ";
        $expressionAttributeNames = [];
        $expressionAttributeValues = [];
        $index = 1;
        foreach ($attributes as $attribute) {
            $updateExpression .= "#NV$index=:NV$index,";
            $expressionAttributeNames["#NV$index"] = $attribute['AttributeName'];
            $expressionAttributeValues[":NV$index"] = [$attribute['AttributeType'] => $attribute['Value']];
            ++$index;
        }
        $updateExpression = substr($updateExpression, 0, -1);
        $updateItem = [
            'Key' => $key['Key'],
            'TableName' => $tableName,
            'UpdateExpression' => $updateExpression,
            'ExpressionAttributeNames' => $expressionAttributeNames,
            'ExpressionAttributeValues' => $expressionAttributeValues,
        ];
        $this->dynamoDbClient->updateItem($updateItem);
    }

    #snippet-start:[php.example_code.dynamodb.service.batchWriteItem]
    public function writeBatch(string $TableName, array $Batch, int $depth = 2)
    {
        if (--$depth <= 0) {
            throw new Exception("Max depth exceeded. Please try with fewer batch items or increase depth.");
        }

        $marshal = new Marshaler();
        $total = 0;
        foreach (array_chunk($Batch, 25) as $Items) {
            foreach ($Items as $Item) {
                $BatchWrite['RequestItems'][$TableName][] = ['PutRequest' => ['Item' => $marshal->marshalItem($Item)]];
            }
            try {
                echo "Batching another " . count($Items) . " for a total of " . ($total += count($Items)) . " items!\n";
                $response = $this->dynamoDbClient->batchWriteItem($BatchWrite);
                $BatchWrite = [];
            } catch (Exception $e) {
                echo "uh oh...";
                echo $e->getMessage();
                die();
            }
            if ($total >= 250) {
                echo "250 movies is probably enough. Right? We can stop there.\n";
                break;
            }
        }
    }
    #snippet-end:[php.example_code.dynamodb.service.batchWriteItem]
}
