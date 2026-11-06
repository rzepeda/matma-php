<?php
namespace App;
class CycleA { public function __construct(private CycleB $next) {} }
