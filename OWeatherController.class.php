<?php

namespace Budabot\User\Modules;

use Budabot\Core\xml;

/**
 * Authors: 
 *	- Nadyita (RK5)
 *
 * @Instance
 *
 * Commands this controller contains:
 *	@DefineCommand(
 *		command     = 'oweather', 
 *		accessLevel = 'all', 
 *		description = 'View Weather', 
 *		help        = 'oweather.txt'
 *	)
 */
class OWeatherController {

	/**
	 * Name of the module.
	 * Set automatically by module loader.
	 */
	public $moduleName;

	/** @Inject */
	public $text;

	/** @Inject */
	public $settingManager;

	/**
	 * @Setup
	 */
	public function setup() {
		$this->settingManager->add($this->moduleName, "oweather_api_key", "The OpenWeatherMap API key", "edit", "text", "None", "None", '', "mod");
	}

	/**
	 * Try to convert a wind degree into a wind direction
	 */
	public function degreeToDirection($degree) {
		$mapping = array(
			  0 => "N",
			 22 => "NNE",
			 45 => "NE",
			 67 => "ENE",
			 90 => "E",
			112 => "ESE",
			135 => "SE",
			157 => "SSE",
			180 => "S",
			202 => "SSW",
			225 => "SW",
			247 => "WSW",
			270 => "W",
			292 => "WNW",
			315 => "NW",
			337 => "NNW",
		);
		$current = "unknown";
		$currentDiff = 360;
		foreach ($mapping as $mapDeg => $mapDir) {
			if (abs($degree-$mapDeg) < $currentDiff) {
				$current = $mapDir;
				$currentDiff = abs($degree-$mapDeg);
			}
		}
		return $current;
	}
	/**
	 * @HandlesCommand("oweather")
	 * @Matches("/^oweather (.+)$/i")
	 */
	public function weatherCommand($message, $channel, $sender, $sendto, $args) {
		$location = $args[1];

		$apiKey = $this->settingManager->get('oweather_api_key');
		if (strlen($apiKey) != 32) {
			$sendto->reply("There is either no API key or an invalid one was set.");
			return;
		}
		$apiUrl = "http://api.openweathermap.org/data/2.5/weather?".http_build_query(array(
			"q"     => $location,
			"appid" => $apiKey,
			"units" => "metric",
			"mode"  => "json"
		));
		$response = file_get_contents($apiUrl);
		$data = json_decode($response, true);
		if (!is_array($data) || !array_key_exists("cod", $data)) {
			$sendto->reply("There was an error looking up the weather.");
			return;
		}
		if ($data["cod"] != 200) {
			if (!array_key_exists("message", $data)) {
				$sendto->reply("There was an error looking up the weather.");
			} else {
				$sendto->reply("There was an error looking up the weather: <highlight>".$data["message"]."<end>.");
			}
			return;
		}
		$latString = $data["coord"]["lat"] > 0 ? $data["coord"]["lat"]."N" : (-1 * $data["coord"]["lat"])."S";
		$lonString = $data["coord"]["lon"] > 0 ? $data["coord"]["lon"]."E" : (-1 * $data["coord"]["lon"])."W";
		$blob = "Last Updated:: <highlight>".date("D, Y-m-d H:i:s", $data["dt"])." UTC<end><br>".
		        "<br>".
		        "Location: <highlight>".$data["name"]."<end>, <highlight>".$data["sys"]["country"]."<end><br>".
		        "Lat/Lon: <highlight>$latString $lonString<end><br>".
		        "<br>".
		        "Currently: <highlight>".number_format($data["main"]["temp"], 1)."°C ".
		          "(".number_format($data["main"]["temp"] * 1.8 + 32, 1)."°F)<end>, <highlight>".
		          $data["weather"][0]["description"]."<end><br>".
		        "Clouds: <highlight>".$data["clouds"]["all"]."%<end><br>".
		        "Humidity: <highlight>".$data["main"]["humidity"]."%<end><br>".
		        "Visibility: <highlight>";
		if (array_key_exists("visibility", $data) && $data["visibility"] > 0) {
			$blob .= number_format($data["visibility"]/1000, 1)." km ".
			         "(".number_format($data["visibility"]/1609.3, 1)." miles)";
		} else {
			$blob .= "no data";
		}
		$blob .= "<end><br>".
		         "Pressure: <highlight>".$data["main"]["pressure"]." hPa<end><br>".
		         "Wind: <highlight>".$data["wind"]["speed"]." m/s<end> ".
		           "from the <highlight>".$this->degreeToDirection($data["wind"]["deg"])."<end><br>".
		         "<br>".
		         "Sunrise: <highlight>".date("H:i:s", $data["sys"]["sunrise"])." UTC<end><br>".
		         "Sunset: <highlight>".date("H:i:s", $data["sys"]["sunset"])." UTC<end>";

		$msg = $this->text->makeBlob('Weather for '.$data["name"], $blob);

		$sendto->reply($msg);
	}
}
