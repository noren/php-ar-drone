<?php
namespace jolicode\PhpARDrone;

use Evenement\EventEmitter;
use jolicode\PhpARDrone\Control\UdpControl;
use jolicode\PhpARDrone\Navdata\Frame;
use jolicode\PhpARDrone\Navdata\UdpNavdata;
use React\EventLoop\Factory AS LoopFactory;
use Datagram\Factory AS UdpFactory;
use jolicode\PhpARDrone\Config\Config;

class Client extends EventEmitter {

    private $udpFactory;
    private $udpControl;
    private $udpNavdata;
    private $timerOffset;
    public $disableEmergency;
    public $lastState;
    public $lastBattery;
    public $lastAltitude;
    private $loop;

    public function __construct()
    {
        $this->loop         = LoopFactory::create();

        $udpFactory         = new UdpFactory($this->loop);
        $this->udpFactory   = $udpFactory;
        $this->socket       = null;
        $this->timerOffset  = 0;
        $this->lastState    = 'CTRL_LANDED';
        $this->lastBattery  = 100;
        $this->lastAltitude = 0;
        $this->disableEmergency = false;

        $this->startUdpNavdata();
        $this->startUdpControl();
    }

    public function startUdpNavdata()
    {
        $this->udpNavdata = new UdpNavdata($this->loop);
        $self = $this;

        $this->udpNavdata->on('navdata', function(Frame $navdata) use (&$self) {

            if (count($navdata->getDroneState()) > 0) {
                $stateData = $navdata->getDroneState();
                if ($stateData['emergencyLanding'] && $self->disableEmergency) {
//                    this._ref.emergency = true;
                } else {
//                    this._ref.emergency    = false;
//                    this._disableEmergency = false;
                }
            }

            $options = $navdata->getOptions();

            if (count($navdata->getDroneState()) > 0 && isset($options['demo'])) {
                // Control drone state
                $optionDemo = $options['demo'];
                $demoData = $optionDemo->getData();

                $currentState = $demoData['controlState'];

                $self->emitState('landing', 'CTRL_TRANS_LANDING', $currentState);
                $self->emitState('landed', 'CTRL_LANDED', $currentState);
                $self->emitState('takeoff', 'CTRL_TRANS_TAKEOFF', $currentState);
                $self->emitState('hovering', 'CTRL_HOVERING', $currentState);
                $self->emitState('flying', 'CTRL_FLYING', $currentState);
                $self->lastState = $currentState;

                $battery = $demoData['batteryPercentage'];


                // battery events
                $stateData = $navdata->getDroneState();

                if ($stateData['lowBattery'] === 1) {
                    $self->emit('lowBattery', array($battery));
                }

                if ($battery !== $self->lastBattery) {
                    $self->emit('batteryChange', array($battery));
                    $self->lastBattery = $battery;
                }

                // altitude events
                $altitude = $demoData['altitudeMeters'];

                if ($altitude !== $self->lastAltitude) {
                    $self->emit('altitudeChange', array($altitude));
                    $self->lastAltitude = $altitude;
                }
            }

            $self->emit('navdata', array($navdata));
        });
    }

    public function emitState($e, $state, $currentState)
    {
        echo $currentState.PHP_EOL;
        if ($currentState === $state && $this->lastState !== $state) {
            $this->emit($e, array());
        }
    }

    private function startUdpControl()
    {
        $this->udpControl = new UdpControl($this->loop);
    }

    public function createRepl()
    {
        $repl = new Repl($this->loop);
        $repl->create();

        $udpControl = $this->udpControl;

        $repl->on('action', function($action) use (&$udpControl) {
            $udpControl->emit(array($action));
        });
    }

    public function after($duration, $fn)
    {
        $this->loop->addTimer(($this->timerOffset + $duration), $fn);
        $this->timerOffset += $duration;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getUdpNavdata()
    {
        return $this->udpNavdata;
    }

    /**
     * @return \React\EventLoop\LibEventLoop|\React\EventLoop\StreamSelectLoop
     */
    public function getLoop()
    {
        return $this->loop;
    }

    public function start()
    {
        $this->loop->run();
    }

    public function __call($name, $arguments)
    {
        if(in_array($name, Config::$commands)) {
            if ($name === 'takeoff' || $name === 'land') {
                // process callback function
                $callback  = (count($arguments) == 1) ? $arguments[0] : function() {};
                $eventName = ($name === 'takeoff') ? 'hovering' : 'landed';

                $this->once($eventName, $callback);

                $this->udpControl->emit($name);
            } else if ($name === 'stop') {
                $this->udpControl->emit($name);
            // Control commands
            } else {
                if (count($arguments) > 1) {
                    new \Exception('There are too many arguments');
                }
                $this->udpControl->emit($name, array($arguments[0]));
            }
        } else {
            new \Exception('Invalid function');
        }
    }

}