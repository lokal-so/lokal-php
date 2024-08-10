<?php

require __DIR__."/vendor/autoload.php";

use LokalSo\Lokal;

$address = "127.0.0.1:8080";

$lokal  = new Lokal();
$tunnel = $lokal->newTunnel()
	->setName("Gin test")
	->setTunnelType(Lokal::TunnelTypeHTTP)
	// ->setPublicAddress("cacb.k.lokal-so.site") // tunnel server required
	->setLANAddress("backend.local")
	->setLocalAddress($address)
	->showStartupBanner()
	->ignoreDuplicate();

$ret = $tunnel->create();
