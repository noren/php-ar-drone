<?php
namespace jolicode\PhpARDrone;

use Evenement\EventEmitter;

class Repl extends EventEmitter {

    private $loop;
    public $prompt;
    public $commands = array(
        'takeoff',
        'land',
        'clockwise',
        'counterClockwise',
        'front',
        'back',
        'right',
        'left',
        'up',
        'down',
        'stop',
        'exit',
    );

    public function __construct($loop)
    {
        $this->loop   = $loop;
        $this->prompt = 'drone> ';
    }

    public function create()
    {
        $that = $this;

        //todo: speed variable
        $this->loop->addReadStream(STDIN, function ($stdin) use ($that) {
            $input = trim(fgets($stdin));

            if(in_array($input, $that->commands)) {
                if($input === 'exit') {
                    exit;
                } else {
                    $that->emit('action', array($input));
                }
            } else {
                echo 'Unknown command' . PHP_EOL;
            }

            echo $that->prompt;
        });

        echo $this->getAsciiArt();
        echo PHP_EOL;
        echo $this->prompt;
    }

    private function getAsciiArt() {
        return "
     _ __ | |__  _ __         __ _ _ __       __| |_ __ ___  _ __   ___
    | '_ \| '_ \| '_ \ _____ / _` | '__|____ / _` | '__/ _ \| '_ \ / _ \
    | |_) | | | | |_) |_____| (_| | | |_____| (_| | | | (_) | | | |  __/
    | .__/|_| |_| .__/       \__,_|_|        \__,_|_|  \___/|_| |_|\___|
    |_|         |_|
    ";
    }
}