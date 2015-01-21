<?php
/**
 * Silktide Nibbler. Copyright 2013-2014 Silktide Ltd. All Rights Reserved.
 */
namespace Silktide\Teamster\Pool\Runner;

use Silktide\Teamster\Exception\ProcessException;
use Silktide\Teamster\Exception\RunnerException;
use Silktide\Teamster\Pool\Pid\PidFactoryInterface;

/**
 *
 */
class RunnerFactory
{

    protected $pidFactory;

    protected $consolePath;

    protected $descriptorSpec = [
        ProcessRunner::STDIN => ["pipe", "r"],
        ProcessRunner::STDOUT => ["pipe", "w"],
        ProcessRunner::STDERR => ["pipe", "w"]
    ];

    protected $processTimeout = ProcessRunner::DEFAULT_PROCESS_TIMEOUT;
    protected $waitTimeout = ProcessRunner::DEFAULT_WAIT_TIMEOUT;

    public function __construct(PidFactoryInterface $pidFactory, $consolePath)
    {
        $this->pidFactory = $pidFactory;
        $this->consolePath = $consolePath;
    }

    public function setDescriptorSpec(array $spec)
    {
        $this->descriptorSpec = $spec;
    }

    /**
     * modify the default descriptor spec
     * add new definitions, change the existing definition or remove definitions (by passing an empty array)
     *
     * @param $index
     * @param $definition
     * @throws RunnerException
     */
    public function modifyDescriptorSpec($index, $definition)
    {
        if (!$this->isInt($index)) {
            throw new RunnerException("Descriptor spec indecies must be integers");
        }
        if (!is_array($definition)) {
            throw new RunnerException("Descriptor spec definitions must be arrays");
        }

        $index = (int) $index;

        // empty definition array means "remove this element"
        if (empty($definition)) {
            unset($this->descriptorSpec[$index]);
        } else {
            $this->descriptorSpec[$index] = $definition;
        }
    }
    
    public function setProcessTimeout($timeout)
    {
        if (!$this->isInt($timeout) || $timeout <= 0) {
            throw new RunnerException("The process timeout must be a positive integer");
        }
        $this->processTimeout = $timeout;
    }

    public function setWaitTimeout($timeout)
    {
        if (!$this->isInt($timeout) || $timeout <= 0) {
            throw new RunnerException("The wait timeout must be a positive integer");
        }
        $this->waitTimeout = $timeout;
    }

    public function createRunner($type, $pidFile = "", $maxRunCount = 0)
    {
        switch ($type) {
            case "process":
                $runner = new ProcessRunner(
                    $this->pidFactory,
                    $this->descriptorSpec,
                    $pidFile,
                    $maxRunCount,
                    $this->processTimeout,
                    $this->waitTimeout
                );
                break;
            case "console":
                $runner = new ConsoleRunner(
                    $this->consolePath,
                    $this->pidFactory,
                    $this->descriptorSpec,
                    $pidFile,
                    $maxRunCount,
                    $this->processTimeout,
                    $this->waitTimeout
                );
                break;
            default:
                throw new RunnerException("The runner type '$type' is invalid");
        }
        return $runner;
    }

    protected function isInt($value)
    {
        return ($value == (string) (int) $value);
    }
    
} 