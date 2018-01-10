Espruino WIFI Plant Monitoring System
=====================================

Using the Espruino WIFI chip to log soil moisture and ambiant temperature.


## Required components/Shopping list:

- [Espruino Wifi](https://www.espruino.com/WiFi)
- [One Wire Digital Temperature Sensor - DS18B20](https://www.sparkfun.com/products/245)
- [Soil Moisture Sensor](https://www.bitsbox.co.uk/index.php?main_page=product_info&cPath=302_306&products_id=2816)
- 4.7K resistor - If you don't have one, get a [resistor pack](https://coolcomponents.co.uk/products/resistor-kit-1-4w-500-total)

## Basic Setup
There are only 2 sensors in this project, both are run off a shared 3.3v rail and shared ground rail.

### Soil Sensor

* VCC - 3.3v rail
* GND - Ground rail
* DO - _not used_
* AO - straight to pin B0

### Temperature Sensor
With the rounded side facing ***away*** from left to right:

* 0 - Ground rail
* 1 - pin B1
* 2 - 3.3v rail

***Note:*** You need to put the 4.7K resistor between pins 1 & 2 (data and power)

## Server software setup
I've included a PHP example server application that takes this post data and saves it into a file - overwriting the file each time new data arrives.

The Espruino code sends a standard POST with form data so any server side software that can read this respone can be used. The post data key value pairs are:

* __auth__: "YOUR_SUPER_SECRET_TOKEN"
* __moisture__: integer value
* __temperature__: double value
