<?php
error_reporting(E_ALL);
set_time_limit(0);
ini_set('memory_limit', '-1');
date_default_timezone_set('Africa/Brazzaville');




require_once __DIR__ . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

require_once 'SokaServices.php';
while (true) {
    $isError = false;
    try{
    $sokaServicesObj = new SokaServices("publisher");
    }
    catch(Exception $ex){
        echo "Error While connection to  Class";
        $isError = TRUE;
        
    }
    try{    
    $connection = new AMQPStreamConnection("localhost", 5672, "trivia_manage", "manage@CNu8Ut"); 
    $channel = $connection->channel();
    $channel->exchange_declare("trivia_exchange", "direct", false, true, false);
    $channel->queue_declare("trivia_queue", false, true, false, false);
    $channel->queue_bind('trivia_queue', 'trivia_exchange', 'tigochad.trivia.key');
}

    catch(Exception $ex){
        echo "Error While Connection to RMQ";
        $isError = TRUE;
        sleep(2);
    }
    if(!$isError){
    $messageArray = $sokaServicesObj->getMessageLogs();
    if(empty($messageArray)){
        $sokaServicesObj->closeDB();
        sleep(2);
    }
    else{
    foreach ($messageArray as $key => $value) {
        $id = $messageArray[$key]['id'];
        $sokaServicesObj->updateMessageLogStatus(0, $id);
        $mobile = $messageArray[$key]['sender'];
        $message = $messageArray[$key]['message'];
        try {

            $jsonArray = array("msisdn" => $mobile, "message" => $message, "id" => $id);
            $jsonMessage = new AMQPMessage(json_encode($jsonArray));
            $channel->basic_publish($jsonMessage, "trivia_exchange", "tigochad.trivia.key"); // it publishes the message to a new queue
        } catch (Exception $ex) {
            $ex->getCode();
            echo $ex;
        }
    }
    $channel->close();
    $connection->close();
    $sokaServicesObj->closeDB();
    sleep(2);
}
}
}


