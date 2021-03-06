<?php

namespace Lexide\Teamster\Command;

use Lexide\Teamster\Exception\NotFoundException;
use Lexide\Teamster\Exception\PidException;
use Lexide\Teamster\Exception\ProcessException;
use Lexide\Teamster\Pool\Pid\Pid;
use Lexide\Teamster\Pool\Pid\PidFactoryInterface;
use Lexide\Teamster\Pool\Runner\RunnerFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *
 */
class PoolControlCommand extends Command
{

    const DEFAULT_WAIT_TIMEOUT = 20000000; // 20 seconds in microseconds

    /**
     * @var RunnerFactory
     */
    protected $runnerFactory;

    /**
     * @var PidFactoryInterface
     */
    protected $pidFactory;

    /**
     * @var string
     */
    protected $poolPidFile;

    /**
     * @var string
     */
    protected $poolCommand;

    /**
     * @var bool
     */
    protected $canRunAsRoot;

    /**
     * @var int
     */
    protected $waitTimeout;

    /**
     * @var Pid
     */
    private $pid;

    public function __construct(
        RunnerFactory $runnerFactory,
        PidFactoryInterface $pidFactory,
        $poolPidFile,
        $poolCommand,
        $canRunAsRoot = false,
        $waitTimeout = self::DEFAULT_WAIT_TIMEOUT
    ) {
        $this->runnerFactory = $runnerFactory;
        $this->pidFactory = $pidFactory;
        $this->poolPidFile = $poolPidFile;
        $this->poolCommand = $poolCommand;
        $this->canRunAsRoot = $canRunAsRoot;
        $this->waitTimeout = (int) $waitTimeout;
        parent::__construct();
    }

    public function configure()
    {
        $this->setName("lexide:teamster:control")
            ->setDescription("Control command for the teamster service")
            ->addArgument("action", InputArgument::REQUIRED, "service action");
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $action = $input->getArgument("action");

        switch ($action) {
            case "restart":
            case "stop":
                if ($this->isPoolRunning()) {
                    // KILL, KILL, KILL!!
                    $pid = $this->getPid();

                    // counter to prevent infinite loops
                    $maxCount = 1000;
                    $interval = $this->waitTimeout / $maxCount;
                    $count = 0;

                    // send the terminate signal
                    $result = posix_kill($pid, SIGUSR1);
                    if ($result === false) {
                        throw new ProcessException("Could not send the terminate command to the pool, $pid");
                    }

                    do {
                        usleep($interval);
                        ++$count;
                    } while ($this->isPoolRunning() && $count < $maxCount);

                    // check if we exited an infinite loop
                    if ($count >= $maxCount) {
                        throw new ProcessException("Could not stop the pool");
                    }
                    $output->writeln("<info>Pool stopped</info>");
                } else {
                    $output->writeln("<info>The pool was not running</info>");
                }
                if ($action == "stop") {
                    break;
                }
                // if "restart" then fall through
                // no break
            case "start":
                // check who we're running as
                if (posix_getuid() === 0 && !$this->canRunAsRoot) {
                    throw new ProcessException("Cannot run the pool as the root user");
                }
                // start the pool in a new process
                if ($this->isPoolRunning()) {
                    throw new ProcessException("Pool is already running");
                }
                $runner = $this->runnerFactory->createRunner("console", $this->poolPidFile, 1);
                $runner->execute($this->poolCommand, false);
                $output->writeln("<info>Pool started</info>");
                break;

        }

    }

    /**
     * @return bool
     */
    protected function isPoolRunning()
    {
        try {
            $pid = $this->getPid();
        } catch (PidException $e) {
            return false;
        } catch (NotFoundException $e) {
            return false;
        }

        // attempt to get the process exit status, if it hasn't exited this will return zero
        $pcntl = pcntl_waitpid($pid, $status, WNOHANG);
        $posix = false;
        // if we couldn't get the process, try the posix way
        if ($pcntl == -1) {
            $posix = posix_kill($pid, 0);
        }
        return $pcntl == 0 || $posix;
    }

    /**
     * gets the PID number from the PID file and caches it
     *
     * @param bool $skipCache
     * @return int
     * @throws PidException
     */
    protected function getPid($skipCache = false)
    {
        $skipCache = (bool) $skipCache;
        if (!$skipCache && !empty($this->pid) && $this->pid instanceof Pid) {
            return $this->pid->getPid();
        }
        $pid = $this->pidFactory->create($this->poolPidFile);
        $pidNum = $pid->getPid();
        $this->pid = $pid;
        return $pidNum;
    }

} 
