<?php
// SPDX-License-Identifier: GPL-2.0-only

namespace LokalSo {

	use Exception;
	use JsonSerializable;

	const LOKAL_SERVER_MIN_VERSION = "v0.6.0";
	const LOKAL_SO_BANNER = <<<BANNER
    __       _         _             
   / /  ___ | | ____ _| |  ___  ___  
  / /  / _ \| |/ / _  | | / __|/ _ \ 
 / /__| (_) |   < (_| | |_\__ \ (_) |
 \____/\___/|_|\_\__,_|_(_)___/\___/ 
BANNER;

	class Options implements JsonSerializable
	{
		/**
		 * @var array
		 */
		public array $basic_auth = [];

		/**
		 * @var array
		 */
		public array $cidr_allow = [];

		/**
		 * @var array
		 */
		public array $cidr_deny = [];

		/**
		 * @var array
		 */
		public array $request_header_add = [];

		/**
		 * @var array
		 */
		public array $request_header_remove = [];

		/**
		 * @var array
		 */
		public array $response_header_add = [];

		/**
		 * @var array
		 */
		public array $response_header_remove = [];

		/**
		 * @var array
		 */
		public array $header_key = [];

		public function __construct() {}

		public function setBasicAuth(string $username, string $password): self
		{
			$auth = "{$username}:{$password}";
			if (!in_array($auth, $this->basic_auth)) {
				$this->basic_auth[] = $auth;
			}
			return $this;
		}

		public function setCIDRAllow(string $cidr): self
		{
			if (!in_array($cidr, $this->cidr_allow)) {
				$this->cidr_allow[] = $cidr;
			}
			return $this;
		}

		public function setCIDRDeny(string $cidr): self
		{
			if (!in_array($cidr, $this->cidr_deny)) {
				$this->cidr_deny[] = $cidr;
			}
			return $this;
		}

		public function addRequestHeader(string $key, string $value): self
		{
			$header = "{$key}:{$value}";
			if (!in_array($header, $this->request_header_add)) {
				$this->request_header_add[] = $header;
			}
			return $this;
		}

		public function removeRequestHeader(string $header): self
		{
			if (!in_array($header, $this->request_header_remove)) {
				$this->request_header_remove[] = $header;
			}
			return $this;
		}

		public function addResponseHeader(string $key, string $value): self
		{
			$header = "{$key}:{$value}";
			if (!in_array($header, $this->response_header_add)) {
				$this->response_header_add[] = $header;
			}
			return $this;
		}

		public function removeResponseHeader(string $header): self
		{
			if (!in_array($header, $this->response_header_remove)) {
				$this->response_header_remove[] = $header;
			}
			return $this;
		}

		public function setHeaderKey(string $key, string $value): self
		{
			$header = "{$key}:{$value}";
			if (!in_array($header, $this->header_key)) {
				$this->header_key[] = $header;
			}
			return $this;
		}

		public function jsonSerialize(): array
		{
			return get_object_vars($this);
		}
	};

	class Tunnel implements JsonSerializable
	{

		/**
		 * @var string
		 */
		private string $name = "";

		/**
		 * @var string
		 */
		private string $tunnel_type = "";

		/**
		 * @var string
		 */
		private string $local_address = "";

		/**
		 * @var string
		 */
		private string $server_id = "";

		/**
		 * @var string
		 */
		private string $address_tunnel = "";

		/**
		 * @var int
		 */
		private int $address_tunnel_port = 0;

		/**
		 * @var string
		 */
		private string $address_public = "";

		/**
		 * @var string
		 */
		private string $address_mdns = "";

		/**
		 * @var bool
		 */
		private bool $inspect = false;

		/**
		 * @var \LokalSo\Options
		 */
		private Options $options;

		/**
		 * @var string
		 */
		private string $description = "";

		/**
		 * @var bool
		 */
		private bool $ignore_duplicate = false;

		/**
		 * @var bool
		 */
		private bool $startup_banner = false;

		/**
		 * @var \LokalSo\Lokal
		 */
		private Lokal $lokal;

		public function __construct(Lokal $lokal)
		{
			$this->lokal = $lokal;
			$this->options = new Options();
			$this->inspect = false;
			$this->ignore_duplicate = false;
			$this->startup_banner = false;
		}

		public function setLocalAddress(string $local_address): Tunnel
		{
			$this->local_address = $local_address;
			return $this;
		}

		public function setOptions(Options $options): Tunnel
		{
			$this->options = $options;
			return $this;
		}

		public function setTunnelType(string $tunnel_type): Tunnel
		{
			$this->tunnel_type = $tunnel_type;
			return $this;
		}

		public function setInpsection(bool $inspect): Tunnel
		{
			$this->inspect = $inspect;
			return $this;
		}

		public function setLANAddress(string $lan_address): Tunnel
		{
			$lan_address = preg_replace('/\.local$/', '', $lan_address);
			$this->address_mdns = $lan_address;
			return $this;
		}

		public function setPublicAddress(string $public_address): Tunnel
		{
			$this->address_public = $public_address;
			return $this;
		}

		public function setName(string $name): Tunnel
		{
			$this->name = $name;
			return $this;
		}

		public function getLANAddress(): string
		{
			if (!str_ends_with($this->address_mdns, '.local')) {
				$this->address_mdns = $this->address_mdns . ".local";
			}
			return $this->address_mdns;
		}

		public function getPublicAddress(): string
		{
			// TODO: implement port fallback like in golang library
			return $this->address_public;
		}

		public function ignoreDuplicate(bool $ignore_duplicate = true): Tunnel
		{
			$this->ignore_duplicate = $ignore_duplicate;
			return $this;
		}

		public function showStartupBanner(bool $startup_banner = true): Tunnel
		{
			$this->startup_banner = $startup_banner;
			return $this;
		}

		public function create(): ?array
		{
			if (empty($this->address_mdns) && empty($this->address_public)) {
				throw new Exception("Either LAN or Public address must be set");
			}

			$body = json_encode($this);
			$resp = $this->lokal->postJson("/api/tunnel/start", $body);

			if ($this->ignore_duplicate) {
				if (isset($resp['success']) && $resp['success'] === false) {
					if (isset($resp['message']) && stripos($resp['message'], 'address is already being used') !== false) {
						$this->__showStartupBanner();
						return $resp;
					}
					throw new Exception($resp['message']);
				}
			}

			if ($this->startup_banner)
				$this->__showStartupBanner();

			return $resp;
		}

		public function jsonSerialize(): array
		{
			return [
				"name" => $this->name,
				"tunnel_type" => $this->tunnel_type,
				"local_address" => $this->local_address,
				"server_id" => $this->server_id,
				"address_tunnel" => $this->address_tunnel,
				"address_tunnel_port" => $this->address_tunnel_port,
				"address_public" => $this->address_public,
				"address_mdns" => $this->address_mdns,
				"inspect" => $this->inspect,
				"options" => $this->options,
				"description" => $this->description,
			];
		}

		private function __showStartupBanner(): void
		{
			if (!$this->startup_banner) {
				return;
			}

			$colors = [
				'magenta' => "\033[95m",
				'blue' => "\033[94m",
				'cyan' => "\033[96m",
				'green' => "\033[92m",
				'red' => "\033[91m",
				'reset' => "\033[0m"
			];

			$randomColor = array_rand(array_slice($colors, 0, -1)); // Exclude 'reset'
			printf("%s%s%s\n\n", $colors[$randomColor], LOKAL_SO_BANNER, $colors['reset']);

			printf("%sMinimum Lokal Client%s\t%s\n", $colors['red'], $colors['reset'], LOKAL_SERVER_MIN_VERSION);

			if (!empty($this->address_public)) {
				printf("%sPublic Address%s\t\thttps://%s\n", $colors['cyan'], $colors['reset'], $this->getPublicAddress());
			}

			if (!empty($this->getLANAddress())) {
				printf("%sLAN Address%s\t\thttps://%s\n", $colors['green'], $colors['reset'], $this->getLANAddress());
			}

			printf("\n");
		}
	};

	class Lokal
	{

		public const TunnelTypeHTTP = "HTTP";

		/**
		 * @var string
		 */
		private string $base_url;

		public function __construct(string $base_url = "http://127.0.0.1:6174")
		{
			$this->base_url = $base_url;
		}

		public function setBaseUrl(string $base_url): Lokal
		{
			$this->base_url = $base_url;
			return $this;
		}

		public function newTunnel(): Tunnel
		{
			return new Tunnel($this);
		}

		private static function outJson(string $data)
		{
			$ret = json_decode($data, true);
			if (json_last_error() !== JSON_ERROR_NONE) {
				throw new Exception("Failed to decode JSON response: {$data}");
			}

			return $ret;
		}

		public function postJson(string $path, string $body): array
		{
			$hdr = ["Content-Type: application/json"];
			$opt = [CURLOPT_POSTFIELDS => $body];
			return self::outJson($this->curl("POST", $path, $opt, $hdr));
		}

		public function getJson(string $path): array
		{
			return self::outJson($this->curl("GET", $path));
		}

		private static function curlHeaderCheck(string $hdr): void
		{
			if (strpos($hdr, ":") === false)
				return;

			list($key, $val) = explode(":", $hdr, 2);

			$key = strtolower(trim($key));
			$val = trim($val);

			if ($key !== "lokal-server-version")
				return;

			if (version_compare($val, substr(LOKAL_SERVER_MIN_VERSION, 1), "<")) {
				$err = sprintf("Outdated software version, server version: %s, server version required (minimal): %s", $val, LOKAL_SERVER_MIN_VERSION);
				throw new Exception($err);
			}
		}

		public function curl(string $method, string $path, array $opt = [], array $hdr = []): string
		{
			$hdr_chk_func = function ($ch, $hdr) {
				self::curlHeaderCheck($hdr);
				return strlen($hdr);
			};

			$ch = curl_init();
			$default_opts = [
				CURLOPT_URL => $this->base_url . $path,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HEADER => false,
				CURLOPT_USERAGENT => "Lokal PHP - github.com/lokal-so/lokal-php",
				CURLOPT_HTTPHEADER => $hdr,
				CURLOPT_CUSTOMREQUEST => $method,
				CURLOPT_HEADERFUNCTION => $hdr_chk_func
			];

			foreach ($opt as $key => $value)
				$default_opts[$key] = $value;

			curl_setopt_array($ch, $default_opts);
			$res = curl_exec($ch);
			$err = curl_error($ch);
			$ern = curl_errno($ch);
			curl_close($ch);

			if ($ern !== 0) {
				if (str_contains($err, "Couldn't connect to server")) {
					throw new Exception("Curl error ({$ern}): {$err} - You may need to install Lokal Client at Lokal.so/download");
				}
				throw new Exception("Curl error ({$ern}): {$err}");
			}

			return $res;
		}
	};
} /* namespace LokalSo */
