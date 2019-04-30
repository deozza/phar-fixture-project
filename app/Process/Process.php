<?php

namespace Process;

use GuzzleHttp\Client;
use Faker\Factory as FakerFactory;

class Process{

	const AUTH_URL = "";
	const STUDY_KIND = "checking";
	const COMPONENT_KIND = "email";
	const COMPONENT_FILE = "email.to_assign";
	const SYSTEM = "galian";
	const STEP_KIND = "received";

	private $guzzleClient;
	
	private $route;
	private $login;
	private $password;
	private $nbEmail;
	private $debug = false;
	private $token;

	private $faker;

	public function __construct(array $arguments)
	{
		if(count($arguments) < 5)
		{
			throw new \Exception("Usage : \n command url login password nb_to_generate [debug]");
		}

		$this->route    = $arguments[1];
		$this->login    = $arguments[2];
		$this->password = $arguments[3];
		$this->nbEmail  = $arguments[4];

		if(isset($arguments[5]))
		{
			$this->debug = fopen(uniqid().".txt", "w");
		}

		$this->guzzleClient = new Client(
			[
				"base_uri" => $this->route,
				"timeout" => 180
			]);

		$this->faker = FakerFactory::create("fr_FR");
        $this->faker->seed(time());
	}

	public function run()
	{
		for($i = 0; $i < $this->nbEmail ; $i++)
		{
			$this->getUserToken();
			$study = $this->postStudy();
			$component = $this->postComponent($study);

			echo "Le nombre de fichier : ".$component->data->file."\n";
			for($j = 0; $j < $component->data->file; $j++)
			{
				$this->postFile($component->uuid);
			}

			$this->postStep($study);

			echo "Study created \n";			
		}
	}

	private function getUserToken()
	{
		$body = [
			"login" => $this->login,
			"password" => $this->password
		];

		$response = $this->guzzleClient->request("POST", "tokens/jwt", $this->getOption($body));
		
		if($response->getStatusCode() !== 201)
		{
			throw new \Exception("Login or password is invalid on ".$this->route."token/jwt");
		}

		$this->token = json_decode($response->getBody())->jwt;
	}

	private function postStudy(): string
	{
		$body = [
			"kind" => self::STUDY_KIND,
			"metadata" => [
				"from" => $this->faker->name,
				"worker" => $this->faker->name
			]
		];

		$response = $this->guzzleClient->request("POST", self::SYSTEM."/study", $this->getOption($body));
		
		if($response->getStatusCode() !== 201)
		{
			$this->handleError($response);
		}

		$uuid = json_decode($response->getBody())->uuid;

		return $uuid;
	}

	private function postComponent(string $studyUuid)
	{
		$body = [
			"kind" => self::COMPONENT_KIND,
			"key" => 0,
			"data" => [
				"file" => $this->faker->randomDigitNotNull
			]
		];

		$response = $this->guzzleClient->request("POST", "/studies/$studyUuid/component", $this->getOption($body));
		
		if($response->getStatusCode() !== 201)
		{
			$this->handleError($response);
		}

		return json_decode($response->getBody());
	}

	private function postStep(string $studyUuid)
	{
		$body = [
			"kind" => self::STEP_KIND,
		];

		$response = $this->guzzleClient->request("POST", "/studies/$studyUuid/step", $this->getOption($body));
		
		if($response->getStatusCode() !== 201)
		{
			$this->handleError($response);
		}
	}

	private function postFile(string $componentUuid)
	{
		$body = "";
		$file = null;
		$orientation = $this->faker->boolean(90);
		$i = 0;

		do
		{
			$file = $this->faker->image($dir = '/tmp', $orientation?210:297, $orientation?297:210);
			$body = file_get_contents($file);
			if(strlen($body) == 0)
			{
				unlink($file);
			}
		}
		while(strlen($body) == 0 && $i++ < 50);

		if(strlen($body) == 0)
		{
			throw new \Exception("http://lorempixel.com does not respond");
		}
		
		$response = $this->guzzleClient->request("POST", "/studies/components/$componentUuid/file/".self::COMPONENT_FILE, $this->getOption($body, false));

		unlink($file);
		if($response->getStatusCode() !== 201)
		{
			$this->handleError($response);
		}
	}

	private function handleError($response)
	{
		if($response->hasHeader("X-Debug-Token-Link"))
		{
			throw new \Exception("Error happened. See more at ".$response->getHeader("X-Debug-Token-Link"));
		}
		throw new \Exception("Error happened : ".$response->getStatusCode()."\n Enable debug to see more.");
	}


	private function getOption($body, $json = true)
	{
		return [
			"body" => $json?json_encode($body):$body,
			"debug" => $this->debug,
			"headers" => [
				'Content-Type' => 'application/json',
				"X-Auth-Token" => $this->token
			]			
		];
	}
}