<?php
    class RSSFeed{
        public $stub =<<<EOXML
<?xml version="1.0" encoding="UTF-8" ?>
<rss version="2.0">
<channel>
 <title>Plant Issues</title>
 <description>Shows any issues with any plants</description>
 <link></link>
 <lastBuildDate></lastBuildDate>
 <pubDate></pubDate>
 <ttl>1800</ttl>

 <item>
  <title>Plant Issue</title>
  <description></description>
  <link></link>
  <guid isPermaLink="false"></guid>
  <pubDate></pubDate>
 </item>

</channel>
</rss>

EOXML;
        public function __construct($description){
            $config = json_decode(file_get_contents(realpath(dirname(__FILE__)) . '/config.json'));
            $link =  $config->endpoint . '?auth=' . $config->token;
            $date = date(DATE_RSS);

            $xml = simplexml_load_string($this->stub);

            $xml->channel->link = $link;
            $xml->channel->lastBuildDate = $date;
            $xml->channel->pubDate = $date;

            $xml->channel->item->description = $description;
            $xml->channel->item->link = $link;
            $xml->channel->item->guid = time();
            $xml->channel->item->pubDate = $date;

            file_put_contents(realpath(dirname(__FILE__)) . '/feed.rss', $xml->asXML());
        }
    }

    class PlantNurse{

        protected $config = null;

        public function __construct(){
            $this->config = json_decode(file_get_contents(realpath(dirname(__FILE__)) . '/config.json'));
        }

        /**
         * diagnose(int $t, float $m)
         *
         * Checks paramaters to make sure they're ok
         *
         * Possible responses:
         * "All OK"
         * "Temperature (n) out of range"
         * "Moisture (n) out of range"
         * "Temperature (n) and moisture (m) out of range)"
         *
         * @param int $t - temperature
         * @param float $m - moisture
         * @return str $message - blank if nothing wrong
         **/
        public function diagnose($t,$m){
            $msg = '';
            $roundTemp = round($t) . "&#8451;";

            if(!$this->isTempWithinRange($t)){
                $msg .= "Temperature ({$roundTemp}) out of range";
            }

            if(!$this->isMoistureWithinRange($m)){
                if(!empty($msg)){
                    $msg = "Temperature ({$roundTemp}) and moisture ({$m}) out of range";
                }else{
                    $msg .= "Moisture ({$m}) out of range";
                }
            }

            if(empty($msg)){
                $msg = "All OK";
            }

            return $msg;
        }

        /**
         * isTempWithinRange(int $t)
         *
         * checks if temperature within config range
         * @param int $t
         * @return boolean - true if within range
         **/
        protected function isTempWithinRange($t){
            //echo "($t > {$this->config->tempRange[0]} && $t < {$this->config->tempRange[1]});\n";
            return ($t > $this->config->tempRange[0] && $t < $this->config->tempRange[1]);
        }

        /**
         * isMoistureWithinRange(float $m)
         *
         * checks if moisture within config range
         * @param float $m
         * @return boolean - true if within range
         **/
        protected function isMoistureWithinRange($m){
            //echo "($m > {$this->config->moistureRange[0]} && $m < {$this->config->moistureRange[1]});\n";
            return ($m > $this->config->moistureRange[0] && $m < $this->config->moistureRange[1]);
        }
    }

    class PlantNetworkResponse{
        public $status = array(
            'badReq' => 400,
            'ok' => 200,
            'unauth' => 401,
            'err' => 520,
            'teapot' => 418
        );

        protected $config = null;

        public function __construct(){

            $this->config = json_decode(file_get_contents(realpath(dirname(__FILE__)) . '/config.json'));


            if(isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST'){

                if(isset($_POST['auth'],$_POST['moisture'],$_POST['temperature'])){

                    if($this->authenticateRequest($_POST['auth'])){

                        $t = $_POST['temperature'];
                        $t = doubleval($t);

                        $m = $_POST['moisture'];
                        $m = intval($m);

                        if(is_numeric($t) && is_numeric($m) && $m >= 0){
                            $plantNurse = new PlantNurse();

                            $currentData = json_decode($this->getPlantData());

                            $oldStatus = $plantNurse->diagnose( $currentData->t, $currentData->m );
                            $newStatus = $plantNurse->diagnose( $t, $m );


                            if($oldStatus !== $newStatus){
                                new RSSFeed($newStatus);
                            }


                            if($this->savePlantData($t,$m)){
                                $this->sendResponse(
                                    $msg = "Data received",
                                    $respCode = $this->status['ok']
                                );
                            }else{
                                $this->sendResponse(
                                    $msg = "Couldnt save data",
                                    $respCode = $this->status['err']
                                );
                            }
                        }else{
                            $this->sendResponse(
                                $msg = "Invalid data - temp and moisture must be numeric and moisture must be greater than or equal to 0",
                                $respCode = $this->status['badReq']
                            );
                        }
                    }else{ //invalid token
                        $this->sendUnauth();
                    }
                }else{ //Missing data
                    $this->sendResponse(
                        $msg = "Missing required data",
                        $respCode = $this->status['badReq']
                    );
                }
            }else if(isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'GET'){
                if(isset($_GET['auth']) && $this->authenticateRequest($_GET['auth'])){

                    $jsonData = $this->getPlantData();

                    if(isset($_GET['type']) && $_GET['type'] === 'json'){
                        $status = $jsonData;
                    }else{
                        $plantNurse = new PlantNurse();
                        $currentData = json_decode($jsonData);
                        $status = $plantNurse->diagnose( $currentData->t, $currentData->m );
                    }


                    $this->sendResponse(
                        $msg = $status,
                        $respCode = $this->status['ok']
                    );
                }else{
                    $this->sendUnauth();
                }

            }else{ //just send back nonsense
                $this->sendResponse(
                    $msg = "I'm a teapot!",
                    $respCode = $this->status['teapot']
                );
            }

        }


        /**
         * sendUnauth() - sends unauthorized msg and response code via sendResponse()
         **/
        public function sendUnauth(){
            $this->sendResponse('Not Authorized', $this->status['unauth']);
        }

        /**
         * sendResponse($msd, $httpRespCode)
         *
         * Sends the response code and message back to the requestee
         *
         * @param str $msg - what to echo back
         * @param int $httpRespCode - the HTTP response code to send
         * @return void
         **/
        public function sendResponse($msg, $httpRespCode){
            http_response_code($httpRespCode);
            echo "{$msg}\n";
        }


        /**
         * authenticateRequest($token)
         *
         * checks if $token is valid
         *
         * @param str $token - token to be authenticated
         * @return book $isValid
         **/
        public function authenticateRequest($token){
            $isValid = false;
            if($token === $this->config->token){
                $isValid = true;
            }

            return $isValid;
        }


        /**
         * savePlantData($t, $m)
         *
         * Saves data to a file called 'sensor_data'
         *
         * @param $t - temperature data
         * @param $m - moisture data
         * @reutn void;
         **/
        public function savePlantData($t, $m){
            $saved = true;
            $data = json_encode(array('date' => date('Y-m-d H:i'), 't' => $t, 'm' => $m));
            $saveStatus = file_put_contents(realpath(dirname(__FILE__)) . '/sensor_data', $data);
            if($saveStatus === FALSE){
                $saved = FALSE;
                $err = 'ERR: could not save plant info';
                error_log($err);
                echo "\n\n{err}\n\n";
            }
            return $saved;
        }

        /**
         * getPlantData()
         *
         * @return str $data - json string of data
         **/
        public function getPlantData(){
            $ret = 'no data available';

            if(file_exists(realpath(dirname(__FILE__)) . '/sensor_data')){
                $jsonStr = file_get_contents(realpath(dirname(__FILE__)) . '/sensor_data');

                if(!empty($jsonStr)){
                    $ret = $jsonStr;
                }
            }

            return $ret;
        }
    }

    try{
        $ps = new PlantNetworkResponse();
    }catch(Exception $e){
        echo "Error: {$e}";
    }


    exit;
