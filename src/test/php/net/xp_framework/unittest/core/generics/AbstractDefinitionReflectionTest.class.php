<?php namespace net\xp_framework\unittest\core\generics;

use lang\XPClass;
use lang\Primitive;
use lang\IllegalArgumentException;

/**
 * TestCase for definition reflection
 *
 * @see   xp://net.xp_framework.unittest.core.generics.Lookup
 */
abstract class AbstractDefinitionReflectionTest extends \unittest\TestCase {
  protected $fixture= null;

  /**
   * Creates fixture, a Lookup class
   *
   * @return  lang.XPClass
   */  
  protected abstract function fixtureClass();

  /**
   * Creates fixture instance
   *
   * @return  var
   */
  protected abstract function fixtureInstance();

  /**
   * Creates fixture, a Lookup class
   */  
  public function setUp() {
    $this->fixture= $this->fixtureClass();
  }

  #[@test]
  public function isAGenericDefinition() {
    $this->assertTrue($this->fixture->isGenericDefinition());
  }

  #[@test]
  public function isNotAGeneric() {
    $this->assertFalse($this->fixture->isGeneric());
  }

  #[@test]
  public function components() {
    $this->assertEquals(['K', 'V'], $this->fixture->genericComponents());
  }

  #[@test]
  public function newGenericTypeIsGeneric() {
    $t= $this->fixture->newGenericType([
      Primitive::$STRING, 
      XPClass::forName('unittest.TestCase')
    ]);
    $this->assertTrue($t->isGeneric());
  }

  #[@test]
  public function newLookupWithStringAndTestCase() {
    $arguments= [Primitive::$STRING, XPClass::forName('unittest.TestCase')];
    $this->assertEquals(
      $arguments, 
      $this->fixture->newGenericType($arguments)->genericArguments()
    );
  }

  #[@test]
  public function newLookupWithStringAndObject() {
    $arguments= [Primitive::$STRING, XPClass::forName('lang.Object')];
    $this->assertEquals(
      $arguments, 
      $this->fixture->newGenericType($arguments)->genericArguments()
    );
  }

  #[@test]
  public function classesFromReflectionAndCreateAreEqual() {
    $this->assertEquals(
      $this->fixtureInstance()->getClass(),
      $this->fixture->newGenericType([
        Primitive::$STRING, 
        XPClass::forName('unittest.TestCase')
      ])
    );
  }

  #[@test]
  public function classesCreatedWithDifferentTypesAreNotEqual() {
    $this->assertNotEquals(
      $this->fixture->newGenericType([Primitive::$STRING, XPClass::forName('lang.Object')]),
      $this->fixture->newGenericType([Primitive::$STRING, XPClass::forName('unittest.TestCase')])
    );
  }

  #[@test, @expect(IllegalArgumentException::class)]
  public function missingArguments() {
    $this->fixture->newGenericType([]);
  }

  #[@test, @expect(IllegalArgumentException::class)]
  public function missingArgument() {
    $this->fixture->newGenericType([$this->getClass()]);
  }

  #[@test, @expect(IllegalArgumentException::class)]
  public function tooManyArguments() {
    $c= $this->getClass();
    $this->fixture->newGenericType([$c, $c, $c]);
  }

  #[@test]
  public function abstractMethod() {
    $abstractMethod= XPClass::forName('net.xp_framework.unittest.core.generics.ArrayFilter')
      ->newGenericType([$this->fixture])
      ->getMethod('accept')
    ;
    $this->assertEquals(
      $this->fixture,
      $abstractMethod->getParameter(0)->getType()
    );
  }
}
