<?php namespace io\streams;

use lang\IllegalArgumentException;

/**
 * Represents the lines inside an input stream of bytes, delimited
 * by either Unix, Mac or Windows line endings.
 *
 * @see   xp://io.streams.TextReader#lines
 * @test  xp://net.xp_framework.unittest.io.streams.LinesInTest
 */
class LinesIn implements \IteratorAggregate {
  private $reader, $reset;

  /**
   * Creates a new lines instance
   *
   * @param  io.streams.TextReader|io.streams.InputStrean|io.Channel|string $arg Input
   * @param  string $charset Not taken into account when created by a TextReader
   * @param  bool $reset Whether to start from the beginning (default: true)
   * @throws lang.IllegalArgumentException
   */
  public function __construct($arg, $charset= \xp::ENCODING, $reset= true) {
    if ($arg instanceof TextReader) {
      $this->reader= $arg;
    } else {
      $this->reader= new TextReader($arg, $charset);
    }
    $this->reset= $reset;
  }

  /** @return php.Generator */
  public function getIterator() {
    if ($this->reset && !$this->reader->atBeginning()) {
      $this->reader->reset();
    }

    $number= 1;
    while (null !== ($line= $this->reader->readLine())) {
      yield $number++ => $line;
    }
  }
}