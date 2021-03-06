<?php namespace net\xp_framework\unittest\io\streams;

use io\streams\FileOutputStream;
use io\FileUtil;
use io\TempFile;
use io\IOException;
use unittest\PrerequisitesNotMetError;
use lang\IllegalArgumentException;

class FileOutputStreamTest extends \unittest\TestCase {
  private $file;

  /**
   * Sets up test case - creates temporary file
   *
   * @return void
   */
  public function setUp() {
    try {
      $this->file= new TempFile();
      FileUtil::setContents($this->file, 'Created by FileOutputStreamTest');
    } catch (IOException $e) {
      throw new PrerequisitesNotMetError('Cannot write temporary file', $e, [$this->file]);
    }
  }
  
  /**
   * Tear down this test case - removes temporary file
   *
   * @return void
   */
  public function tearDown() {
    try {
      $this->file->isOpen() && $this->file->close();
      $this->file->unlink();
    } catch (IOException $ignored) {
      // Can't really do anything about it...
    }
  }

  #[@test]
  public function writing() {
    with ($stream= new FileOutputStream($this->file), $buffer= 'Created by '.$this->name); {
      $stream->write($buffer);
      $this->file->close();
      $this->assertEquals($buffer, FileUtil::getContents($this->file));
    }
  }

  #[@test]
  public function appending() {
    with ($stream= new FileOutputStream($this->file, true)); {
      $stream->write('!');
      $this->file->close();
      $this->assertEquals('Created by FileOutputStreamTest!', FileUtil::getContents($this->file));
    }
  }

  #[@test]
  public function delete() {
    with ($stream= new FileOutputStream($this->file)); {
      $this->assertTrue($this->file->isOpen());
      unset($stream);
      $this->assertTrue($this->file->isOpen());
    }
  }

  #[@test, @expect(IllegalArgumentException::class)]
  public function given_an_invalid_file_an_exception_is_raised() {
    new FileOutputStream('');
  }

  #[@test, @expect(IOException::class)]
  public function cannot_write_after_closing() {
    with ($stream= new FileOutputStream($this->file)); {
      $stream->close();
      $stream->write('');
    }
  }

  #[@test]
  public function calling_close_twice_has_no_effect() {
    with ($stream= new FileOutputStream($this->file)); {
      $stream->close();
      $stream->close();
    }
  }
}
