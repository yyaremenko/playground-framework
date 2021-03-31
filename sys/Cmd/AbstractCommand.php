<?php

namespace Sys\Cmd;

abstract class AbstractCommand
{
    abstract public static function getName(): string;
    abstract public static function getDescription(): string;
    abstract public function run(): void;
}