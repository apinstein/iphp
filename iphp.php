<?php
// vim: set expandtab tabstop=4 shiftwidth=4:

require dirname(__FILE__).DIRECTORY_SEPARATOR.'iphp_commands.php';

/**
 * The iphp shell is an interactive PHP shell for working with your php applications.
 *
 * The shell includes readline support with tab-completion and history.
 *
 * To start the shell, simply run this script from your command line.
 *
 * Use ctl-d to exit the shell, or enter the command "exit".
 */
class iphp
{
    protected $inputPrompt = 'php> ';
    protected $outputPrompt = '=> ';
    protected $inReadline = false;
    protected $autocompleteList = array();
    protected $tmpFileShellCommand = null;
    protected $tmpFileShellCommandRequires = null;
    protected $tmpFileShellCommandState = null;
    protected $commandEscapeChar = '\\';
    protected $internalCommands = array();
    protected $options = array();

    const OPT_TAGS_FILE     = 'tags';
    const OPT_REQUIRE       = 'require';
    const OPT_TMP_DIR       = 'tmp_dir';
    const OPT_PROMPT_HEADER = 'prompt_header';
    const OPT_PHP_BIN       = 'php_bin';
    const OPT_COMMANDS      = 'commands';

    /**
     * Constructor
     *
     * @param array Options hash:
     *                  - OPT_TAGS_FILE: the path to a tags file produce with ctags for your project -- all tags will be used for autocomplete
     */
    public function __construct($options = array())
    {
        $this->initialize($options);
    }

    public function initialize($options = array())
    {
        $this->initializeOptions($options);
        $this->initializeTempFiles();
        $this->initializeRequires();
        $this->initializeCommands();
        $this->initializeAutocompletion();
        $this->initializeTags();
    }
    public function options()
    {
        return $this->options;
    }
    public function printHelp()
    {
        $uniqueArray = array(); // sadly array_unique doesn't work for arrays of objects. this works, but it slower, but hey it's plenty fast.
        $pad = 30;
        print str_pad('alias(es)', $pad, ' ', STR_PAD_RIGHT) . "<help>\n";
        print str_repeat('-', $pad * 3) . "\n";
        foreach ($this->internalCommands as $name => $command) {
            // only show commands once (due to command aliases)
            if (in_array($command, $uniqueArray)) continue;
            $uniqueArray[] = $command;

            $aliases = $command->name();
            if (!is_array($aliases))
            {
                $aliases = array($aliases);
            }
            $help = $command->help();
            if (!$help)
            {
                $help = "No help available.";
            }
            print str_pad($this->commandEscapeChar . join(",{$this->commandEscapeChar}", $aliases), $pad, ' ', STR_PAD_RIGHT) . "{$help}\n";
        }
    }

    private function initializeOptions($options = array())
    {
        // merge opts
        $this->options = array_merge(array(
                                            // default options
                                            self::OPT_TAGS_FILE     => NULL,
                                            self::OPT_REQUIRE       => NULL,
                                            self::OPT_TMP_DIR       => NULL,
                                            self::OPT_PROMPT_HEADER => $this->getPromptHeader(),
                                            self::OPT_PHP_BIN       => $this->getDefaultPhpBin(),
                                            self::OPT_COMMANDS      => array(),
                                          ), $options);
    }

    private function initializeTempFiles()
    {
        $this->tmpFileShellCommand = $this->tmpFileNamed('command');
        $this->tmpFileShellCommandRequires = $this->tmpFileNamed('requires');
        $this->tmpFileShellCommandState = $this->tmpFileNamed('state');
    }

    private function initializeAutocompletion()
    {
        $phpList = get_defined_functions();
        $this->autocompleteList = array_merge($this->autocompleteList, $phpList['internal']);
        $this->autocompleteList = array_merge($this->autocompleteList, get_defined_constants());
        $this->autocompleteList = array_merge($this->autocompleteList, get_declared_classes());
        $this->autocompleteList = array_merge($this->autocompleteList, get_declared_interfaces());
        $this->autocompleteList = array_merge($this->autocompleteList, array_keys($this->internalCommands));
    }

    private function initializeTags()
    {
        $tagsFile = $this->options[self::OPT_TAGS_FILE];
        if (file_exists($tagsFile))
        {
            $tags = array();
            $tagLines = file($tagsFile);
            foreach ($tagLines as $tag) {
                $matches = array();
                if (preg_match('/^([A-z0-9][^\W]*)\W.*/', $tag, $matches))
                {
                    $tags[] = $matches[1];
                }
            }
            $this->autocompleteList = array_merge($this->autocompleteList, $tags);
        }
    }
    
    private function initializeRequires()
    {
        if ($this->options[self::OPT_REQUIRE])
        {
            if (!is_array($this->options[self::OPT_REQUIRE]))
            {
                $this->options[self::OPT_REQUIRE] = array($this->options[self::OPT_REQUIRE]);
            }
            file_put_contents($this->tmpFileShellCommandRequires, serialize($this->options[self::OPT_REQUIRE]));
        }
    }

    private function initializeCommands()
    {
        $commandsToLoad = array('iphp_command_exit', 'iphp_command_reload', 'iphp_command_help');
        $commandsToLoad = array_merge($commandsToLoad, $this->options[self::OPT_COMMANDS]);
        $this->internalCommands = array();
        foreach ($commandsToLoad as $commandName) {
            $command = new $commandName;
            $names = $command->name();
            if (!is_array($names))
            {
                $names = array($names);
            }
            foreach ($names as $name) {
                if (isset($this->internalCommands[$name]))
                {
                    print "WARNING: internal command '{$name}' is already registered by " . get_class($this->internalCommands[$name]) . ". Skipping command for " . get_class($command) . ".";
                    continue;
                }
                $this->internalCommands[$name] = $command;
            }
        }
    }

    private function getDefaultPhpBin()
    {
        if(strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
        {
            $phpExecutableName = 'php.exe';
        }
        else
        {
            $phpExecutableName = 'php';
        }
        return PHP_BINDIR . DIRECTORY_SEPARATOR . $phpExecutableName . " -d include_path=" . escapeshellarg(".:" . ini_get('include_path'));
    }

    private function tmpFileNamed($name)
    {
        return tempnam($this->tmpDirName(), "iphp.{$name}.");
    }

    private function tmpDirName()
    {
        return empty($this->options['tmp_dir']) ? sys_get_temp_dir() : $this->options['tmp_dir'];
    }

    public function getPromptHeader()
    {
        return <<<END

Welcome to iphp, the interactive php shell!

Features include:
- autocomplete (tab key)
- readline support w/history
- require/include support
- extensible command system

Enter a php statement at the prompt, and it will be evaluated. The variable \$_ will contain the result.

Example:

{$this->inputPrompt}new ArrayObject(array(1,2))
{$this->outputPrompt}ArrayObject Object
(
    [0] => 1
    [1] => 2
)

{$this->inputPrompt}\$_[0] + 1
{$this->outputPrompt}2

To call an internal command, prefix the command with the \\ character.
{$this->inputPrompt}\\help


END;
    }

    public function historyFile()
    {
        return getenv('HOME') . '/.iphpHistory';
    }

    public function doCommand($command)
    {
        $this->inReadline = false;

        // detect ctl-d
        if ($command === NULL)
        {
            exit(0);
        }

        // no need to process empty commands
        if (trim($command) == '')
        {
            return;
        }

        // internal command parser
        $matches = array();
        if (preg_match("/\s*\\{$this->commandEscapeChar}([\w\?]+)\s?(.*)/", trim($command), $matches))
        {
            $internalCommand = $matches[1];
            $argsString = $matches[2];

            $args = array();
            if (preg_match_all("/(?:([\w]+)\s?)/", $argsString, $matches))
            {
                $args = $matches[1];
            }
            if (isset($this->internalCommands[$internalCommand]))
            {
                $this->internalCommands[$internalCommand]->run($this, $args);
            }
            else
            {
                print "Command '{$internalCommand}' does not exist.\n";
            }
            return;
        }

        // mutli-line detection (see if the code parses; if not, assume
        $tokenized = token_get_all('<?' . 'php ' . $command);
        array_shift($tokenized); // eat open tag
        while (count($tokenized) && $tokenized[0][0] === T_WHITESPACE) {
            array_shift($tokenized);
        }
        $firstTokenInCommand = $tokenized[0][0];

        // normal command
        if (!empty($command) and function_exists('readline_add_history'))
        {
            readline_add_history($command);
            readline_write_history($this->historyFile());
        }

        $command = preg_replace('/^\//', '$_', $command);  // "/" as a command will just output the last result.

        $requires = unserialize(file_get_contents($this->tmpFileShellCommandRequires));
        if (!is_array($requires))
        {
            $requires = array();
        }

        $saveLastResultCommand = "\$_ = NULL;";
        $savedLastResult = false;
        if (token_is_assignable($firstTokenInCommand))
        {
            $savedLastResult = true;
            $saveLastResultCommand = "\$_ = ";
        }

        $parsedCommand = "<?php
foreach (" . var_export($requires, true) . " as \$file) {
    require_once(\$file);
}
\$__commandState = unserialize(file_get_contents('{$this->tmpFileShellCommandState}'));
if (is_array(\$__commandState))
{
    extract(\$__commandState);
}
ob_start();
{$saveLastResultCommand}{$command};
\$__out = ob_get_contents();
ob_end_clean();
\$__allData = get_defined_vars();
unset(\$__allData['GLOBALS'], \$__allData['argv'], \$__allData['argc'], \$__allData['_POST'], \$__allData['_GET'], \$__allData['_COOKIE'], \$__allData['_FILES'], \$__allData['_SERVER']);
file_put_contents('{$this->tmpFileShellCommandRequires}', serialize(get_included_files()));
file_put_contents('{$this->tmpFileShellCommandState}', serialize(\$__allData));
";
        #echo "  $parsedCommand\n";
        try {
            file_put_contents($this->tmpFileShellCommand, $parsedCommand);

            $result = NULL;
            $output = array();

            $lastLine = exec("{$this->options[self::OPT_PHP_BIN]} {$this->tmpFileShellCommand} 2>&1", $output, $result);

            if ($result != 0) throw( new Exception("Fatal error executing php: " . join("\n", $output)) );

            // boostrap requires environment of command
            $requires = unserialize(file_get_contents($this->tmpFileShellCommandRequires));
            foreach ($requires as $require) {
                if ($require === $this->tmpFileShellCommand) continue;
                require_once($require);
            }

            $lastState = unserialize(file_get_contents($this->tmpFileShellCommandState));
            // echo output
            if ($lastState['__out'])
            {
                print $lastState['__out'] . "\n";
            }

            // print "result" if appropriate
            print $this->outputPrompt;
            if ($savedLastResult)
            {
                $lastResult = $lastState['_'];
                if (is_object($lastResult) && !is_callable(array($lastResult, '__toString')))
                {
                    print_r($lastResult) . "\n";
                }
                else
                {
                    print $lastResult . "\n";
                }
            }
            else
            {
                print "(no value)\n";
            }

            // after the eval, we might have new classes. Only update it if real readline is enabled
            if (!empty($this->autocompleteList)) $this->autocompleteList = array_merge($this->autocompleteList, get_declared_classes());
        } catch (Exception $e) {
            print "Uncaught exception with command:\n" . $e->getMessage() . "\n";
        }
    }

    public function fakeReadline()
    {
        $this->inReadline = true;

        if (function_exists('readline'))
        {
            $input = readline($this->inputPrompt);
        }
        else
        {
            print $this->inputPrompt;
            $input = fgets( STDIN );
        }

        // catch ctl-d or other errors
        if ($input === false)
        {
            exit(0);
        }
        $command = rtrim($input, "\n");
        $this->doCommand($command);
    }

    public function readlineCompleter($str)
    {
        return $this->autocompleteList;
    }

    private function realReadline()
    {
        $this->inReadline = true;
        while ($this->inReadline) {
            $w = NULL;
            $e = NULL;
            $r = array(STDIN);
            $n = @stream_select($r, $w, $e, NULL);       // @ to silence warning on ctl-c
            // detect ctl-c or other signal (causes stream_select to exit with FALSE)
            if ($n === false)
            {
                readline_callback_handler_remove();
                print "\n";
                readline_callback_handler_install($this->inputPrompt, array($this, 'doCommand'));
            }
            if (in_array(STDIN, $r))
            {
                readline_callback_read_char();
            }
        }
    }

    public function stop()
    {
        // no-op
    }

    public function runREPL()
    {
        // install signal handlers if possible
        declare(ticks = 1);
        if (function_exists('pcntl_signal'))
        {
            pcntl_signal(SIGINT, array($this, 'stop'));
        }

        print $this->options[self::OPT_PROMPT_HEADER];

        // readline history
        if (function_exists('readline_read_history'))
        {
            readline_read_history($this->historyFile());   // doesn't seem to work, even though readline_list_history() shows the read items!
        }
        // install tab-complete
        if (function_exists('readline_completion_function'))
        {
            readline_completion_function(array($this, 'readlineCompleter'));
        }

        // run repl loop. libedit defines readline but not callback handlers so check for both
        if (function_exists('readline') && function_exists('readline_callback_handler_install'))
        {
            // readline automatically re-prints the prompt after the callback runs, so the only way to prevent double-prompts is to do it this way until we figure out something better
            readline_callback_handler_install($this->inputPrompt, array($this, 'doCommand'));
            while (true)
            {
                $this->realReadline();
            }
        }
        else
        {
            while (true)
            {
                $this->fakeReadline();
            }
        }
    }

    public static function main($options = array())
    {
        $shell = new iphp($options);
        $shell->runREPL();
    }
}

function token_is_assignable($token)
{
    return in_array($token, array(
        T_ARRAY,
        T_ARRAY_CAST,
        T_BOOL_CAST,
        T_CLONE,
        T_CONST,
        T_CONSTANT_ENCAPSED_STRING,
        // T_DIR,
        T_DNUMBER,
        T_DOUBLE_CAST,
        T_EMPTY,
        T_EVAL,
        T_FILE,
        T_FUNC_C,
        T_INT_CAST,
        T_ISSET,
        T_LINE,
        T_LNUMBER,
        T_METHOD_C,
        T_NEW,
        // T_NS,
        T_NUM_STRING,
        T_OBJECT_CAST,
        T_START_HEREDOC,
        T_STATIC,
        T_STRING,
        T_STRING_CAST,
        T_STRING_VARNAME,
        T_VARIABLE,
        ));
}
