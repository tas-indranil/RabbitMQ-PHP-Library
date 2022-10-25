<?php

namespace tasindranil;
require_once __DIR__ . '..'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';

use Exception;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;


class RabbitMQ
{
    private static $instance = null;

    //------------------------------------------- RabbitMQ Values ----------------------------------------------------//
    private $RabbitHost = '';
    private $RabbitPort = '';
    private $RabbitUsername = '';
    private $RabbitPassword = '';

    private $connection = null;
    private $channel = null;
    private $message = null;
    private $exchange_list = ['direct', 'fanout', 'topic', 'headers'];

    //----------------------------------------------------------- Methods --------------------------------------------------------------------------//

    /**
     * A private constructor to set and initialize rabbitmq variables
     * @param $RabbitHost string RabbitMQ host name
     * @param $RabbitPort integer RabbitMQ port number
     * @param $RabbitUsername string RabbitMQ Username
     * @param $RabbitPassword string RabbitMQ Password
     */
    private function __construct($RabbitHost, $RabbitPort, $RabbitUsername , $RabbitPassword)
    {
        $this->RabbitHost = $RabbitHost;
        $this->RabbitPort = $RabbitPort;
        $this->RabbitUsername = $RabbitUsername;
        $this->RabbitPassword = $RabbitPassword;

        try {
            $this->connection = new AMQPStreamConnection($this->RabbitHost, $this->RabbitPort, $this->RabbitUsername, $this->RabbitPassword);
            $this->channel = $this->connection->channel();
        }catch(Exception $e)
        {
            return ErrorHandler::handleException($e);
        }

    }


    /**
     * Singleton instances for creating a rabbitmq class, please enter the required details to create a rabbitmq instance
     * @param $RabbitHost string RabbitMQ host name
     * @param $RabbitPort integer RabbitMQ port number
     * @param $RabbitUsername string RabbitMQ Username
     * @param $RabbitPassword string RabbitMQ Password
     * @return RabbitMQ|null
     */
    public static function Instance(string $RabbitHost = 'localhost', int $RabbitPort = 5672, string $RabbitUsername = 'admin', string $RabbitPassword = 'admin'): ?RabbitMQ
    {
        if(self::$instance == null)
        {
            self::$instance = new RabbitMQ($RabbitHost ,$RabbitPort,$RabbitUsername,$RabbitPassword);
        }
        return self::$instance;
    }


    /**
     * Create queue
     * @param string $queueName
     * @param bool $passive
     * @param bool $durable
     * @param bool $exclusive
     * @param bool $auto_delete
     * @return void
     */
    public function declareQueue(string $queueName, bool $passive = false, bool $durable = false, bool $exclusive = false, bool $auto_delete = false ): void
    {
        $this->channel->queue_declare($queueName, $passive, $durable, $exclusive, $auto_delete);;
    }


    /**
     * Set a message and send it to a specific queue
     * @param $message
     * @param $exchange string
     * @param $routing_key
     */
    public function setAndPublishBasicMessage($message, $exchange='', $routing_key)
    {
        $this->message = json_encode($message);
        $this->message = new AMQPMessage($this->message);
        try{
            $this->channel->basic_publish($this->message, $exchange, $routing_key);
        }catch (Exception $e)
        {
            return ErrorHandler::handleException($e);
        }

    }


    /**
     * Method for Basic Consume message from queue
     * @param string $queueName The Queue Name is a required field
     * @param string $consumertag
     * @param bool $noLocal
     * @param bool $noAck
     * @param bool $exclusive
     * @param bool $noWait
     * @param $callback
     * @return array
     */
    public function basicConsume(string $queueName, $consumertag='', $noLocal=false, $noAck=true, $exclusive=false, $noWait=false, $callback)
    {
        echo " [*] Waiting for messages. To exit press CTRL+C\n";
        try{
            $this->channel->basic_consume($queueName, $consumertag, $noLocal, $noAck, $exclusive, $noWait, $callback);
            while ($this->channel->is_open()) {
                $this->channel->wait();
            }
        }catch (Exception $exception)
        {
            return ErrorHandler::handleException($exception);
        }

    }


    /**
     * Declare the exchange
     * @param $exchangeName String Required The exchange Name
     * @param $exchangeType Required The type of exchange
     * @param bool $passive
     * @param bool $durable
     * @param bool $auto_delete
     * @return string|void
     */
    public function declareExchange(string $exchangeName, $exchangeType, $passive = false, $durable = false, $auto_delete = false)
    {
        if(!in_array(strtolower($exchangeName), $this->exchange_list))
        {
            return ErrorHandler::errorString("invalid_exchange");
        }
        $this->channel->exchange_declare(strtolower($exchangeName), $exchangeType, $passive, $durable, $auto_delete);
    }


    /**
     * @param $passive
     * @param $durable
     * @param $exclusive
     * @param $auto_delete
     * @return mixed
     */
    public function nonDurableQueueBind($passive = false, $durable = false, $exclusive = false, $auto_delete = false): mixed
    {
        list($queue_name, ,) = $this->channel->queue_declare("", $passive, $durable, $exclusive, $auto_delete);
        $this->channel->queue_bind($queue_name, 'logs');
        return $queue_name;
    }


    /**
     * Send file with message, use this method when you want to send a file with the message
     * @param string $filePath File path
     * @param array $data And the message to be sent (limit 20MB)
     * @return array|string
     */
    public function sendFile(string $filePath, array $data): array|string
    {
        if(file_exists($filePath))
        {
            $encodeData = base64_encode(file_get_contents($filePath));
            $data['fileName'] = basename(__DIR__.'/'.$filePath);
            $data['fileExtension'] = pathinfo($filePath, PATHINFO_EXTENSION);
            $data['fileData'] = $encodeData;
            return $data;
        }
        return ErrorHandler::errorString("file_not_found");
    }


    /**
     * This method will store the file which is sent by the produce and store it to the designated storage and will return
     * the new file name and the message as an array
     * @param string $storage
     * @param array $message
     * @return mixed
     */
    public function storeFileReturnMessage(string $storage, $message): mixed
    {
        $data = (json_decode($message, true));
        if(array_key_exists("fileName", $data) && array_key_exists("fileExtension", $data) &&array_key_exists("fileData", $data))
        {
            $newFilename = $this->nameGenerate().".".$data['fileExtension'];
            if($storage == '')
            {
                $file = fopen("../dosome".DIRECTORY_SEPARATOR.$newFilename, "w+");
            }else{
                $file = fopen($storage.DIRECTORY_SEPARATOR.$newFilename, 'w+');
            }
            fwrite($file, base64_decode($data['fileData']));
            fclose($file);
            unset($data['fileExtension']);
            unset($data['fileData']);
            $data['fileName'] = $newFilename;
            return $data;
        }
        return $data;
    }


    /**
     * Generate a random name
     * @return string
     */
    private function nameGenerate()
    {
        $data = uniqid().strtotime("now")."0123456789abcdefghijklmnopqrstuvwxyz";
        return substr(str_shuffle($data), 0, 32);
    }


    /**
     * Close all connections
     * @return void
     * @throws Exception
     */
    public function closeConnection()
    {
        $this->channel->close();
        $this->connection->close();
    }
}