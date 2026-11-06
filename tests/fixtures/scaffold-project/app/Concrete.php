<?php

namespace App;

class Concrete implements Contract
{
    public function run(): string
    {
        return 'real';
    }
}
