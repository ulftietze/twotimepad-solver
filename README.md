# twotimepad-solver

A small interactive solver for an encrypted twotimepad. This tool is written in PHP.

> This tool was originally created during one of my college projects, but was 
> rewritten afterwards.

## Start the tool

Just start the programm via PHP. I know it works with PHP8.2, but
probably also in 7.4, 8.0 and 8.1.

```shell
php decrypt.php ../examples/msg*.asc
```

or

```shell
php decrypt.php ./examples/msg1.asc ./examples/msg2.asc
```

## Use the tool

This should be pretty self-explanatory. If not, feel free to message me :)

```
Usage:
 /search  [needleString]                Search for string in combined encrypted message. (fe. "/search hello")
 /index   [msg1|msg2] [index]           Set index from last '/search' result as to msg1 or msg2
 /lookup  [index]                       Lookup ASCII possibilities at index
 /set     [msg1|msg2] [index] [string]  Set a string at a specific position
 /unset   [index] [length]              Unset character/string at a position with the given length
 /print   [?key]                        Print messages (use "key" for printing also the key)
 /ascii                                 Print ASCII XOR Table (careful, big output)
 /clear                                 Clear the screen
 /help                                  Print this message
 /end                                   Finish and end program
```

This tool has some minor bugs, but afaik only visibility bugs. So, yeah, i'm not
gonna fix those.