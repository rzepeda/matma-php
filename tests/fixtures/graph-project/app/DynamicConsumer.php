<?php
namespace App;
class DynamicConsumer { public function create(string $name): object { return new $name(); } }
