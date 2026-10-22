Device Parameters
This lists all the parameters that a device might send. Note: Not all devices send all the parameters.

winddir - instantaneous wind direction, 0-360º

windspeedmph - instantaneous wind speed, mph

windgustmph - max wind speed in the last 10 minutes, mph

maxdailygust - Maximum wind speed in last day, mph

windgustdir - Wind direction at which the wind gust occurred, 0-360º

windspdmph_avg2m - Average wind speed, 2 minute average, mph

winddir_avg2m - Average wind direction, 2 minute average, mph

windspdmph_avg10m - Average wind speed, 10 minute average, mph

winddir_avg10m - Average wind direction, 10 minute average, 0-360º

humidity - Outdoor Humidity, 0-100%

humidity1...humidity10 - humidity 1...10, 0-100%

humidityin - Indoor Humidity, 0-100%

tempf - Outdoor Temperature, ºF

temp1f...temp10f - Temperature 1...10, ºF

soiltemp1f...soiltemp10f - Temperature 1...10, ºF

soilhum1...soilhum10 - Temperature 1...10, %

leafwetness1...leafwetness8 - Leaf Wetness 1...8, Int, %

soiltens1...soiltens4 - Soil Tension 1...4, Float, cb

gdd - Growing Degree Days, Int, days

etos - Evapotranspiration short, Float, in/day

etrs - Evapotranspiration, tall, Float, in/day

tempinf - Indoor Temperature, ºF

leak1...leak4 - Leak Detection 1...4, 0=OK, 1=LEAK, 2=OFFLINEle

battout - Outdoor Battery - OK/Low indication, Int, 1=OK, 0=Low (Meteobridge Users 1=Low, 0=OK)

battin - Indoor Battery - OK/Low indication, Int, 1=OK, 0=Low (Meteobridge Users 1=Low, 0=OK)

batt1...batt10 - OK/Low indication, Int, 1=OK, 0=Low (Meteobridge Users 1=Low, 0=OK)

batt_25 - PM2.5 Air Quality Sensor Battery indication, OK/Low indication, Int, 1=OK, 0=Low (Meteobridge Users 1=Low, 0=OK)

batt_lightning - Lightning Detector Battery - 1=Low 0=OK

batleak1...batleak4 - Leak Detector Battery - 1=Low 0=OK

battsm1...battsm4 - Soil Moisture Battery - 1=OK, 0=Low

batt_co2 - CO2 battery - 1=OK, 0=Low

batt_cellgateway - Cellular Gateway - 1=OK, 0=Low

hourlyrainin - Hourly Rain Rate, in/hr

dailyrainin - Daily Rain, in

24hourrainin - 24 Hour Rain, in

weeklyrainin - Weekly Rain, in

monthlyrainin - Monthly Rain, in

yearlyrainin - Yearly Rain, in

eventrainin - Event Rain, in

totalrainin - Total Rain, in (since last factory reset)

baromrelin - Relative Pressure, inHg

baromabsin - Absolute Pressure, inHg

uv - Ultra-Violet Radiation Index, integer on all devices EXCEPT WS-8478.

solarradiation - Solar Radiation, W/m^2

co2 - CO2 Meter, ppm

relay1...relay10 - Relay 1...10, 0 or 1

pm25 - PM2.5 Air Quality, Float, µg/m^3

pm25_24h - PM2.5 Air Quality 24 hour average, Float, µg/m^3

pm25_in - PM2.5 Air Quality, Indoor, Float, µg/m^3

pm25_in_24h - PM2.5 Air Quality 24 hour average, Indoor, Float, µg/m^3

pm25_in_aqin - PM2.5 Air Quality Sensor indoor, AQIN sensor, Float, µg/m^3

pm25_in_24h_aqin - PM2.5 Air Quality Sensor indoor, 24 hour running average, AQIN sensor, Float, µg/m^3

pm10_in_aqin - PM10 Air Quality Sensor, Float, µg/m^3

pm10_in_24h_aqin - PM10 Air Quality Sensor, 24 hour running average, Float, µg/m^3

co2_in_aqin - Indoor CO2 from AQIN, Int, ppm

co2_in_24h_aqin - Indoor CO2 from AQIN, 24 hour running average, Int, ppm

pm_in_temp_aqin - Indoor PM sensor temperature, Float, ºF

pm_in_humidity_aqin - Indoor PM sensor humidity, Int, %

aqi_pm25_aqin - AQI derived from PM25, AQIN sensor, Int

aqi_pm25_24h_aqin - AQI derived from PM25 Indoor, 24 hour running average, AQIN sensor, Int

aqi_pm10_aqin - AQI derived from PM10 Indoor, AQIN sensor, Int

aqi_pm10_24h_aqin - AQI derived from PM10 Indoor, 24 hour running average, AQIN sensor, Int

aqi_pm25_in - AQI derived from PM25 IN, Int

aqi_pm25_in_24h - AQI derived from PM25 IN, 24 hour running average, Int

lightning_day - Lightning strikes per day, int

lightning_hour - Lightning strikes per hour, int

lightning_time - Last strike time, Datetime

lightning_distance - Distance of last lightning strike, Float, miles

tz - IANA Time Zone, String

dateutc - Datetime, int (milliseconds from 01-01-1970, rounded down to nearest minute on server)

Server calculated fields:

lastRain - Last time hourlyrainin > 0, date (calculated on server)
dewPoint - Dew Point, ºF (calculated on server)
feelsLike - if < 50ºF => Wind Chill, if > 68ºF => Heat Index (calculated on server)
date - Human Readable Date, string (converted on server from dateutc)
feelsLike1...feelsLike10 - feelsLike for sensors
dewPoint1...dewPoint10 - dewPoint for sensors
feelsLikein - Indoor Feels Like
dewPointin = Indoor dew Point
Data Timing
The devices returned from the /devices endpoint contain a lastData parameter that contains the most up-to-date data we have for the device. Most devices update every minute, some update less frequently. The timestamps are rounded to the nearest minute.

Queries for past data using the /devices/:macAddress endpoint are returned in 5 minute or 30 minute increments. There can be up to a 10 minute delay before the most recent data becomes available. If you need up-to-the-minute data please use the /devices endpoint or forthcoming realtime API.
