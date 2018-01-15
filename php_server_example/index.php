<?php
    class AlertEmail{
        public function __construct($description){
            $config = json_decode(file_get_contents(realpath(dirname(__FILE__)) . '/config.json'));
            $link =  $config->endpoint . '?auth=' . $config->token;
            $date = date('d/m/Y @ H:i');

            @mail(
                $recipient = $config->email_settings->recipint,
                $subject   = "Plant Problem - {$date}",
                $body      = $description,
                $headers   = "FROM: {$config->email_settings->from}\r\n"
            );
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
         * "Temperature out of range"
         * "Moisture out of range"
         * "Temperature and moisture (m) out of range)"
         *
         * @param int $t - temperature
         * @param float $m - moisture
         * @return str $message - blank if nothing wrong
         **/
        public function diagnose($t,$m){
            $msg = '';
            $roundTemp = round($t) . "&#8451;";

            if(!$this->isTempWithinRange($t)){
                $msg .= "Temperature out of range";
            }

            if(!$this->isMoistureWithinRange($m)){
                if(!empty($msg)){
                    $msg = "Temperature and moisture out of range";
                }else{
                    $msg .= "Moisture out of range";
                }
            }

            if(empty($msg)){
                $msg = "All OK";
            }

            return $msg;
        }

        /**
         * detailedDiagnosis(int $t, float $m)
         *
         * Checks paramaters to make sure they're ok
         *
         * Possible responses:
         * "Your plants are happy. Good job!"
         * "Your plants are too cold. They're currently at {$t} degrees celsius."
         * "Your plants are too hot. They're currently at {$t} degrees celsius."
         * "Your plants could use a drink... The moisture sensor recorded a value of {$m}"
         * "Your plants are drunk! The moisture sensor recorded a value of {$m}"
         *
         * @param int $t - temperature
         * @param float $m - moisture
         * @return str $message - blank if nothing wrong
         **/
        public function detailedDiagnosis($t,$m){
            $msg = '';
            $roundTemp = round($t);

            if(!$this->isTempWithinRange($t)){
                if($t < $this->config->tempRange[0]){
                    $msg .= "Your plants are too cold ";
                }else if($t > $this->config->tempRange[1]){
                    $msg .= "Your plants are too hot ";
                }
                $msg .= ". They're currently at {$roundTemp} degrees celsius.";
            }

            if(!$this->isMoistureWithinRange($m)){
                if($m < $this->config->moistureRange[0]){
                    $msg .= "Your plants could " . (!empty($msg) ? 'also' : '') . " use a drink...";
                }else if($m > $this->config->moistureRange[1]){
                    $msg .= "Your plants are " . (!empty($msg) ? 'also' : '') . " drunk!";
                }
                $msg .= " The moisture sensor recorded a value of {$m}";
            }

            if(empty($msg)){
                $msg = "Your plants are happy. Good job!";
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
                                new AlertEmail($plantNurse->detailedDiagnosis( $t, $m ));
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
                        $status = $plantNurse->detailedDiagnosis( $currentData->t, $currentData->m );
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
