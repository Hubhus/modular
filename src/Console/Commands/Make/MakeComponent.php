<?php

namespace InterNACHI\Modular\Console\Commands\Make;

use Illuminate\Foundation\Console\ComponentMakeCommand;
use Illuminate\Foundation\Inspiring;

class MakeComponent extends ComponentMakeCommand
{
    use Modularize;

    protected function viewPath($path = '')
    {
        if ($module = $this->module()) {
            return $module->path("resources/views/{$path}");
        }

        return parent::viewPath($path);
    }

    protected function buildClass($name)
    {
        $default = parent::buildClass($name);
        if ($module = $this->module()) {
            return str_replace('components.', "$module->name::components.", $default);
        }

        return $default;
    }
}
