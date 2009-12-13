<?php

abstract class iphp_command
{
    abstract public function run($shell, $args);
    abstract public function name();
    public function help() { return ''; }
}

class iphp_command_help extends iphp_command
{
    function run($shell, $args)
    {
        $shell->printHelp();
    }
    function name()
    {
        return array('help', '?');
    }
    function help() { return 'View a list of all installed commands.'; }
}

class iphp_command_exit extends iphp_command
{
    function run($shell, $args)
    {
        exit(0);
    }
    function name()
    {
        return array('exit', 'die', 'bye', 'quit');
    }
    function help() { return 'Quit the shell.'; }
}

class iphp_command_reload extends iphp_command
{
    function run($shell, $args)
    {
        $shell->initialize($shell->options());
    }
    function name()
    {
        return 'reload';
    }
    function help()
    {
        return "Re-initialize the iphp state so it's just as if you quit and re-started.";
    }
}

