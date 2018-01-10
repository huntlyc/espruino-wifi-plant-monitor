/**!
 *
 * Espruino WIFI Plant Monitoring System
 * =====================================
 * Created 2018-01-10 by Huntly Cameron <huntly.cameron@gmail.com>
 *
 * Simple soil moisture and ambient temperature monitoring system.
 *
 *
 *          DO WHAT THE FUCK YOU WANT TO PUBLIC LICENSE
 *                    Version 2, December 2004
 *
 * Copyright (C) 2004 Sam Hocevar <sam@hocevar.net>
 *
 * Everyone is permitted to copy and distribute verbatim or modified
 * copies of this license document, and changing it is allowed as long
 * as the name is changed.
 *
 *            DO WHAT THE FUCK YOU WANT TO PUBLIC LICENSE
 *   TERMS AND CONDITIONS FOR COPYING, DISTRIBUTION AND MODIFICATION
 *
 *  0. You just DO WHAT THE FUCK YOU WANT TO.
 *
 **/


function NetworkSave(data){
    this.data = data;
    var _self = this;

    var WIFI_NAME = "ESSID";
    var WIFI_OPTIONS = { password : "PWD" };

    var wifi = require("EspruinoWiFi");
    wifi.connect(WIFI_NAME, WIFI_OPTIONS, function(err) {
        if (err) {
            console.log("Connection error: "+err);
            return;
        }
        console.log("Connected!");
        _self.sendInfo(_self.data);
    });
}


NetworkSave.prototype.jsonToQueryString = function(json) {
    return '' +
        Object.keys(json).map(function(key) {
            return encodeURIComponent(key) + '=' +
                encodeURIComponent(json[key]);
        }).join('&');
};

NetworkSave.prototype.sendInfo = function(data){
    data.auth = 'YOUR_SUPER_SECRET_TOKEN';

    content = this.jsonToQueryString(data);


    var options = {
        host: 'server-name.tld',
        port: 80,
        protocol: 'http:',
        //port: 443,
        //protocol: 'https:',
        path: '/path/to/endpoint/index.php',
        method: 'POST',


        headers: {
            "Content-Type":"application/x-www-form-urlencoded",
            "Content-Length":content.length
        }
    };

    var req = require("http").request(options, function(res) {
        res.on('data', function(data) {
            console.log("HTTP> "+data);
        });
        res.on('close', function(data) {
            console.log("Connection closed");
        });
    }).end(content);
};

function PlantMonitor() {
    //Sensors
    this.ow = new OneWire(B1);
    this.tempSensor = require("DS18B20").connect(this.ow);

    //General config
    this.intervalVal = 300000; //5mins
    this.currentTimeoutID = undefined;

    //Start monitoring
    this.monitor();
}

PlantMonitor.prototype.getMoistureLevel = function(){
    var moisturePercentage = 0;
    var rawData = analogRead(B0); //spits back 0.0 - 1.0;

    if(!isNaN(rawData)){
        moisturePercentage = Math.round(rawData * 100);

        /*
         * This gives us a number between 0 - 100 where 100 is bone dry
         * I want it be the reverse so 100 is essentially a glass of water
         */
        moisturePercentage = 100 - moisturePercentage;
    }

    return moisturePercentage;
};


PlantMonitor.prototype.monitor = function(){
    this.getSensorJSON();
    this.setupNextCheckInterval();
};

PlantMonitor.prototype.getSensorJSON = function(){
    var _self = this;

    var plant = {
        moisture: -1,
        temperature: -1
    };

    plant.moisture = this.getMoistureLevel();


    //Get and echo current ambiant temperature
    this.tempSensor.getTemp(function (temp) {
        plant.temperature = temp;
        new NetworkSave(plant);
    });


};

PlantMonitor.prototype.getSensorInfo = function(){
    var _self = this;

    var moistureLevel = this.getMoistureLevel();

    //echo back moisture percentage
    console.log("Soil is "+moistureLevel+"% saturated");

    //Get and echo current ambiant temperature
    this.tempSensor.getTemp(function (temp) {
        console.log("amb tmp is "+temp+"°C");
    });
};

PlantMonitor.prototype.setupNextCheckInterval = function(){
    var _self = this;

    if(this.currentTimeoutID !== undefined){
        clearTimeout(this.currentTimeoutID);
    }

    this.currentTimeoutID = setTimeout(function(){
        _self.monitor();
    },this.intervalVal);
};



var pm = new PlantMonitor();
