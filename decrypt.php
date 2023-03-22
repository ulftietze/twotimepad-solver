<?php
declare(strict_types = 1);

############################## BEGIN ASCIIXOR.PHP ######################################################################
class AsciiXOR
{
    public AsciiChar $left;

    public AsciiChar $right;

    public string $hexXOR;

    public function __construct(AsciiChar $left, AsciiChar $right, string $hexXOR)
    {
        $this->left   = $left;
        $this->right  = $right;
        $this->hexXOR = $hexXOR;
    }
}

############################## FINISH ASCIIXOR.PHP #####################################################################

############################## BEGIN ASCIICHAR.PHP #####################################################################
class AsciiChar
{
    public string $char;

    public string $hex;

    public int $dec;

    /** @var AsciiXOR[] */
    public array $xor;

    public function __construct(string $char, string $hex, int $no, array $xor = [])
    {
        $this->char = $char;
        $this->hex  = $hex;
        $this->dec  = $no;
        $this->xor  = $xor;
    }

    public function addXor(string $char, AsciiXOR $xor): void
    {
        $this->xor[$char] = $xor;
    }

    /**
     * @return AsciiXOR[]
     */
    public function getReadableXor(): array
    {
        $returnXor = [];

        foreach ($this->xor as $char => $xor) {
            if (!Hex::isReadable([$xor->hexXOR])) {
                continue;
            }

            $returnXor[$char] = $xor;
        }

        return $returnXor;
    }
}

############################## FINISH ASCIICHAR.PHP ####################################################################

############################## BEGIN ASCIITABLE.PHP ####################################################################
class AsciiTable
{
    private const ASCII_OUTPUT_DETECT_REGEX = "/^'(.)'\s=\s{1,2}(\d{2,3}).*0x([a-z\d]{2})\s.*$/";

    private array $charMapping = [];

    private array $hexMapping = [];

    private array $decMapping = [];

    /** @var AsciiXOR[][] */
    private array $xorMapping = [];

    private static self $instance;

    public static function instance(): self
    {
        return self::$instance;
    }

    public function __construct()
    {
        for ($i = 32; $i <= 126; $i++) {
            $match = new AsciiChar(chr($i), Hex::dechex($i), $i);

            $this->charMapping[$match->char] = $match;
            $this->hexMapping[$match->hex]   = $match;
            $this->decMapping[$match->dec]   = $match;
        }

        // Build XOR
        foreach ($this->getAll() as $sourceChar) {
            foreach ($this->getAll() as $targetChar) {
                $xor = new AsciiXOR($sourceChar, $targetChar, Hex::dechex($sourceChar->dec ^ $targetChar->dec));

                $this->xorMapping[$xor->hexXOR][] = $xor;
                $sourceChar->addXor($targetChar->char, $xor);
            }
        }

        self::$instance = $this;
    }

    /**
     * @param string $hex
     *
     * @return AsciiXOR[]
     */
    public function getReadableXorForHex(string $hex): array
    {
        return $this->xorMapping[$hex] ?? [];
    }

    /**
     * @return AsciiChar[]
     */
    public function getXorPossibleSolutions(string $hex): array
    {
        $possibleChars = [];

        foreach (($this->xorMapping[$hex] ?? []) as $xor) {
            if (!isset($possibleChars[$xor->left->hex])) {
                $possibleChars[$xor->left->hex] = $xor->left;
            }

            if (!isset($possibleChars[$xor->right->hex])) {
                $possibleChars[$xor->right->hex] = $xor->right;
            }
        }

        return $possibleChars;
    }

    public function getAsciiCharByDecimal(int $decimal): ?AsciiChar
    {
        return $this->decMapping[$decimal] ?? null;
    }

    public function getAsciiCharByHex(string $hex): ?AsciiChar
    {
        return $this->hexMapping[$hex] ?? null;
    }

    public function getAsciiCharByString(string $char): ?AsciiChar
    {
        return $this->charMapping[$char] ?? null;
    }

    /**
     * @param string[] $hexMsg
     *
     * @return AsciiChar[]
     */
    public function getAsciiCharsByHex(array $hexMsg): array
    {
        $asciiHexMsg = [];

        foreach ($hexMsg as $hexMsgChar) {
            $asciiHexMsg[] = $this->getAsciiCharByHex($hexMsgChar);
        }

        return $asciiHexMsg;
    }

    /**
     * @return AsciiChar[]
     */
    public function getAll(): array
    {
        return array_values($this->charMapping);
    }

    public function __toString(): string
    {
        $string = "   ";

        foreach ($this->getAll() as $asciiMatch) {
            $string .= $asciiMatch->char . "  ";
        }

        $string .= PHP_EOL;

        foreach ($this->getAll() as $asciiMatch) {
            $string .= $asciiMatch->char . " ";

            foreach ($asciiMatch->xor as $xor) {
                $string .= $xor->hexXOR . ' ';
            }

            $string .= PHP_EOL;
        }

        return $string;
    }
}

############################## ASCIITABLE HEX.PHP ######################################################################

############################## BEGIN HEX.PHP ###########################################################################
class Hex
{
    /**
     * @param string $toHex
     *
     * @return AsciiChar[]
     */
    public static function strToHexArray(string $toHex): array
    {
        $fn = static fn(string $char) => AsciiTable::instance()->getAsciiCharByString($char)?->hex;
        return array_map($fn, str_split($toHex));
    }

    public static function hexArrayToStr(array $toString): string
    {
        if (!$toString) {
            return '';
        }

        return hex2bin(implode('', $toString));
    }

    public static function xorStrings(array $encryptedMessage, array $hexArray, bool $print = false): array
    {
        $stringsCombined = [];

        for ($i = 0, $iMax = count($encryptedMessage); $i < $iMax; $i++) {
            $hexTmp = [];
            $j      = $i;

            foreach ($hexArray as $hex) {
                if (isset($encryptedMessage[$j])) {
                    $hexTmp[] = self::dechex(hexdec($hex) ^ hexdec($encryptedMessage[$j++]));
                } else {
                    break;
                }
            }

            $stringsCombined[$i] = ['string' => self::hexArrayToStr($hexTmp), 'hex' => $hexTmp];

            if ($print && self::isReadable($hexTmp)) {
                print_r($i . " => " . self::hexArrayToStr($hexTmp) . PHP_EOL);
            }
        }

        return $stringsCombined;
    }

    public static function isReadable(array $hexArray): bool
    {
        foreach (str_split(self::hexArrayToStr($hexArray)) as $char) {
            if (
                ($char < 'a' || $char > 'z')
                && ($char < 'A' || $char > 'Z')
                && !is_numeric(($char))
                && $char !== ' '
                && $char !== ','
                && $char !== '.'
                && $char !== '!'
                && $char !== '?'
            ) {
                return false;
            }
        }

        return true;
    }

    public static function dechex(int $decimal): string
    {
        return sprintf("%02x", $decimal);
    }
}

############################## FINISH HEX.PHP ##########################################################################

############################## BEGIN ENCRYPT.PHP #######################################################################
class Encrypted
{
    private array $decryptedChars;

    private array $combinedEncryptedMessage;

    private array $charsAtIndex;

    private array $encryptedMessage;

    public function __construct(array $decryptedChars, array $combinedEncryptedMessage, array $encryptedMessage)
    {
        $this->decryptedChars           = $decryptedChars;
        $this->combinedEncryptedMessage = $combinedEncryptedMessage;
        $this->charsAtIndex             = [];
        $this->encryptedMessage         = $encryptedMessage;

        foreach ($this->decryptedChars as $hex => $i) {
            $this->decryptedChars[$hex] = '';
        }
    }

    public function add(string $cryptHex, string $correctHex, int $index): void
    {
        if ($cryptHex === '00') {
            $this->addAtIndex($index, $correctHex);
            return;
        }

        $this->decryptedChars[$cryptHex] = $correctHex;
    }

    public function addAtIndex(int $index, string $correctHex): void
    {
        $this->charsAtIndex[$index] = $correctHex;
    }

    public function deleteAtIndex(int $index, int $length = 1): void
    {
        for ($i = 0; $i < $length; $i++) {
            unset($this->charsAtIndex[$index + $i]);
        }
    }

    public function addHexAtIndex(array $hexMsg, int $index): void
    {
        for ($i = 0, $iMax = count($hexMsg); $i < $iMax; $i++) {
            $this->addAtIndex($index + $i, $hexMsg[$i]);
        }
    }

    public function getDecryptedChar(string $hex): string
    {
        return $this->decryptedChars[$hex] ?? '';
    }

    public function getKey(): string
    {
        $string = '';
        $index  = 0;

        foreach ($this->encryptedMessage as $char) {
            if (isset($this->charsAtIndex[$index])) {
                $string .= Hex::dechex(hexdec($char) ^ hexdec($this->charsAtIndex[$index])) . ' ';
            } else {
                $string .= '.';
            }

            $index++;
        }

        return $string;
    }

    public function __toString(): string
    {
        $string = '';
        $index  = 0;

        foreach ($this->combinedEncryptedMessage as $char) {
            //print_r($index++ . ": " . $char . ' (' . ($this->decryptedChars[$char] ?? '') . ')' . PHP_EOL);

            if (isset($this->charsAtIndex[$index])) {
                $string .= Hex::hexArrayToStr([$this->charsAtIndex[$index]]);
            } else {
                $string .= ($this->decryptedChars[$char] ?? '') ? Hex::hexArrayToStr([$this->decryptedChars[$char]])
                    : '.';
            }

            $index++;
        }

        return $string . PHP_EOL;
    }
}

############################## FINISH ENCRYPT.PHP ######################################################################

## Begin programm decrypt.php
$encryptedFile1 = $argv[1] ?? '';
$encryptedFile2 = $argv[2] ?? '';

if (!$encryptedFile1 || !$encryptedFile2) {
    die('No or not all encrypted files specified.' . PHP_EOL);
}

$asciiTable = new AsciiTable();

$combinedEncryptedMessage = [];
$encryptedMessage1        = str_split(current(unpack("H*", file_get_contents($encryptedFile1))), 2);
$encryptedMessage2        = str_split(current(unpack("H*", file_get_contents($encryptedFile2))), 2);

for ($i = 0, $iMax = count($encryptedMessage1); $i < $iMax; $i++) {
    $combinedEncryptedMessage[] = Hex::dechex(hexdec($encryptedMessage1[$i]) ^ hexdec($encryptedMessage2[$i]));
}

$msg1Encrypted = new Encrypted(
    array_flip(array_values($combinedEncryptedMessage)),
    $combinedEncryptedMessage,
    $encryptedMessage1
);
$msg2Encrypted = new Encrypted(
    array_flip(array_values($combinedEncryptedMessage)),
    $combinedEncryptedMessage,
    $encryptedMessage2
);

function printMessages(Encrypted $msg1, Encrypted $msg2, bool $withKey = false): void
{
    print_r(PHP_EOL);
    print_r("Message 1: " . PHP_EOL . $msg1 . PHP_EOL);
    print_r("Message 2: " . PHP_EOL . $msg2 . PHP_EOL);

    if ($withKey) {
        print_r("Key: " . PHP_EOL . $msg1->getKey() . PHP_EOL);
    }
}

$help = <<<EOD
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


EOD;
printMessages($msg1Encrypted, $msg2Encrypted);
print_r(PHP_EOL);
print_r($help);

$stringsCombined = null;
$run             = true;

$matchSearch   = '/\/search\s(.*)/';
$matchIndex    = '/\/index\s(msg1|msg2)\s(\d+)/';
$matchLookup   = '/\/lookup\s(\d+)/';
$matchSetMsg   = '/\/set\s(msg1|msg2)\s(\d{1,4})\s(.+)/';
$matchUnsetMsg = '/\/unset\s(\d+)\s(\d+)/';
$matchPrint    = '/\/print\s*(key|)/';

// Prepare shell to react on each key input directly
ini_set('output_buffering', 'off');
ob_implicit_flush(true);
ignore_user_abort(true);
set_time_limit(0);
stream_set_blocking(STDIN, false);
system('stty cbreak -echo');

// TODO: This whole command history thing is bit buggy. Fixme if the time is there.
$commandHistory = ["", ""];
$commandIndex   = 1;

while ($run) {
    echo "\033[0;36m$>\033[0m \r";

    $input = "";
    echo "\033[" . strlen($input) + 3 . "C";

    do {
        $char  = fgets(STDIN);
        $valid = true;

        if ($char !== false && ord($char) >= 32 && ord($char) <= 126) {
            $input                         .= $char;
            $commandIndex                  = count($commandHistory) - 1;
            $commandHistory[$commandIndex] = $input;
        } else {
            switch ($char) {
                case "\033[A": // Pressed the "UP"-Arrow key
                    $commandHistory[$commandIndex] = $input;
                    $commandIndex                  = $commandIndex > 0 ? --$commandIndex : $commandIndex;
                    $input                         = $commandHistory[$commandIndex];
                    break;
                case "\033[B": // Pressed the "DOWN"-Arrow key
                    $commandIndex = $commandIndex < (count($commandHistory) - 1) ? ++$commandIndex : $commandIndex;
                    $input        = $commandHistory[$commandIndex];
                    break;
                case "\010": // Backspace / Delete
                case "\177": // Backspace / Delete
                    $input = substr($input, 0, -1);
                    break;
                default:
                    $valid = false;
                    break;
            }
        }

        if ($valid) {
            // TODO: Clear max the cli row size or this breaks the output haha
            $sizeToClear = max(array_map('strlen', $commandHistory));
            #echo str_repeat(' ', $sizeToClear + 5) . "\r";
            echo "\33[2K\r";
            echo "\033[0;36m$>\033[0m " . $input . "\r";
            echo "\033[" . strlen($input) + 3 . "C";
        }
    } while ($char !== PHP_EOL);

    echo PHP_EOL;

    if (preg_match($matchSearch, $input, $matches)) {
        $searchAsHex     = Hex::strToHexArray($matches[1]);
        $stringsCombined = Hex::xorStrings($combinedEncryptedMessage, $searchAsHex, true);
    } elseif (preg_match($matchIndex, $input, $matches)) {
        $target = $matches[1];
        $index  = (int)$matches[2];

        if (!$stringsCombined || !in_array($target, ['msg1', 'msg2'])) {
            print_r('Incompatible target.' . PHP_EOL);
            print_r($help);
            continue;
        }

        $stringsMsg1 = Hex::xorStrings($combinedEncryptedMessage, $stringsCombined[$index]['hex']);
        $stringsMsg2 = Hex::xorStrings($combinedEncryptedMessage, $stringsMsg1[$index]['hex']);

        if ($target === 'msg1') {
            $msg1Encrypted->addHexAtIndex($stringsMsg2[$index]['hex'], $index);
            $msg2Encrypted->addHexAtIndex($stringsMsg1[$index]['hex'], $index);
        } else {
            $msg1Encrypted->addHexAtIndex($stringsMsg1[$index]['hex'], $index);
            $msg2Encrypted->addHexAtIndex($stringsMsg2[$index]['hex'], $index);
        }

        printMessages($msg1Encrypted, $msg2Encrypted);
    } elseif (preg_match($matchLookup, $input, $matches)) {
        $index       = $matches[1];
        $combinedHex = $combinedEncryptedMessage[$index] ?? null;

        if (!$combinedHex) {
            print_r('No char at index found!' . PHP_EOL);
            continue;
        }

        print_r("Possible matching Ascii Chars for [$index] => $combinedHex: " . PHP_EOL);
        foreach ($asciiTable->getXorPossibleSolutions($combinedHex) as $asciiChar) {
            $reverseHex = Hex::dechex(hexdec($combinedHex) ^ $asciiChar->dec);
            printf(
                '[%s]=[%s⊕%s] %s ⊕ %s' . PHP_EOL,
                $combinedHex,
                $reverseHex,
                $asciiChar->hex,
                hex2bin($reverseHex),
                $asciiChar->char
            );
        }
    } elseif (preg_match($matchUnsetMsg, $input, $matches)) {
        $index  = (int)$matches[1];
        $length = (int)$matches[2];

        $msg1Encrypted->deleteAtIndex($index, $length);
        $msg2Encrypted->deleteAtIndex($index, $length);

        printMessages($msg1Encrypted, $msg2Encrypted);
    } elseif (preg_match($matchSetMsg, $input, $matches)) {
        $target = $matches[1];
        $index  = (int)$matches[2];
        $msg    = $matches[3];

        if (!in_array($target, ['msg1', 'msg2'])) {
            print_r('Incompatible target.' . PHP_EOL);
            continue;
        }

        $asciiHexMsg = Hex::xorStrings($combinedEncryptedMessage, Hex::strToHexArray($msg));
        $otherTarget = Hex::xorStrings($combinedEncryptedMessage, $asciiHexMsg[$index]['hex']);

        if ($target === 'msg1') {
            $msg1Encrypted->addHexAtIndex($otherTarget[$index]['hex'], $index);
            $msg2Encrypted->addHexAtIndex($asciiHexMsg[$index]['hex'], $index);
        } else {
            $msg1Encrypted->addHexAtIndex($asciiHexMsg[$index]['hex'], $index);
            $msg2Encrypted->addHexAtIndex($otherTarget[$index]['hex'], $index);
        }

        printMessages($msg1Encrypted, $msg2Encrypted);
    } elseif (str_contains($input, '/ascii')) {
        echo $asciiTable;
    } elseif (preg_match($matchPrint, $input, $matches)) {
        printMessages($msg1Encrypted, $msg2Encrypted, ($matches[1] ?? '') === 'key');
    } elseif (str_contains($input, '/end')) {
        $run = false;
        print_r('Goodbye' . PHP_EOL);
    } elseif (str_contains($input, '/clear')) {
        echo "\033[2J"; // Clear the screen, move to (0,0).
    } else {
        print_r($help);
    }

    if ($input !== '' && ($commandHistory[count($commandHistory) - 2] !== $input)) {
        $commandHistory[count($commandHistory) - 1] = $input;
        $commandHistory[]                           = "";
        $commandIndex                               = count($commandHistory) - 1;
    }

    echo PHP_EOL;
}
