<?php
namespace Kwf\Lingohub\Output;

use Psr\Log\LoggerInterface;
use Composer\IO\IOInterface;

class ComposerOutput implements LoggerInterface
{
    protected $_io;
    public function __construct(IOInterface $io)
    {
        $this->_io;
    }

    public function emergency($message, array $context = array()) {}
    public function alert($message, array $context = array()) {}
    public function error($message, array $context = array()) {}
    public function warning($message, array $context = array()) {}
    public function notice($message, array $context = array()) {}
    public function debug($message, array $context = array()) {}
    public function log($level, $message, array $context = array()) {}

    public function critical($message, array $context = array())
    {
        $this->_io->write($message);
    }

    public function info($message, array $context = array())
    {
        $this->_io->write($message);
    }
}
