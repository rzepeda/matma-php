<?php
namespace App;
function makeAnonymous(): object { return new class { public function execute(): bool { return true; } }; }
