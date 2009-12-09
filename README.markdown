iphp - Interactive PHP Shell
============================
iphp is an interactive php shell that solves a number of painful problems with normal php shells:

* Fatal Error handling - iphp doesn't die even if your code does.
* readline support
* autocomplete support (tab key)
* history support across runs
* support ctags *tags* files
* implemented as a class for integration with your framework
* require support (supports dynamic includes)
* autoload support

Example:

    > new ArrayObject(array(1,2))
    
    ArrayObject Object
    (
        [0] => 1
        [1] => 2
    )
    
    > $_[0] + 1
    2

**NOTE:** You may need to edit the #! line on the php executable to be appropriate for your system.
