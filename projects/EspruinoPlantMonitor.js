/**!
 *
 * Espurino Plant Monitoring System
 * ========================
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

var CONFIG;
var CODE_DEBUG = false;

function NetworkSave(data){
    var _self = this;

    this.data = data;

    if(CODE_DEBUG){
        console.log('Starting network request');
    }

    this.wifi = require("EspruinoWiFi");
    this.wifi.connect(CONFIG.wifi.name, CONFIG.wifi.options, function(err) {
        if (err) {
            if(CODE_DEBUG){
                console.log("Connection error: "+err);
            }
            return;
        }else if(CODE_DEBUG){
            console.log("Connected!");
        }

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
    var _self = this;

    data.auth = CONFIG.plantEndpoint.secret_token;
    content = this.jsonToQueryString(data);
    var endpoint = CONFIG.plantEndpoint;
    endpoint.headers['Content-Length'] = content.length;

    var req = require("http").request(endpoint, function(res) {
        res.on('data', function(data) {
            if(CODE_DEBUG){
                console.log("HTTP> "+data);
            }
        });
        res.on('close', function(data) {
            if(CODE_DEBUG){
                console.log("HTTP Request Closed");
            }
            _self.wifi.disconnect();
        });
    }).end(content);
};

function PlantMonitor() {
    //Sensors
    this.ow = new OneWire(B1);
    this.tempSensor = require("DS18B20").connect(this.ow);

    //General config
    this.intervalVal = 60000; //1min
    this.currentTimeoutID = undefined;

    //setup moisture power pin and make sure it's off
    this.tempSensor.powerPin = B9;
    digitalWrite(this.tempSensor.powerPin, 0);

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

    var plantData = {
        moisture: -1,
        temperature: -1
    };

    //Turn on soil sensor and wait for a second
    if(CODE_DEBUG){
        console.log('Turning on soil sensor');
    }
    digitalWrite(this.tempSensor.powerPin, 1);

    setTimeout(function(){
        if(CODE_DEBUG){
            console.log('Gathering info');
        }
        plantData.moisture = _self.getMoistureLevel();
        digitalWrite(_self.tempSensor.powerPin, 0);

        //Get and echo current ambiant temperature
        _self.tempSensor.getTemp(function (temp) {
            plantData.temperature = temp;

            if(CODE_DEBUG){
                console.log('Plant data: {"moisture":' + plantData.moisture + ', "temperature":' + plantData.temperature + '}');
            }

            new NetworkSave(plantData);
        });
    },1000);
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

setTimeout(function(){
    if(CODE_DEBUG){
      console.log('starting');
    }
    CONFIG = require('projectConfig').config;
    var pm = new PlantMonitor();
},1000);
