<?php namespace net\xp_framework\unittest\tests\mock;
 
use unittest\mock\RecordState;
use util\Hashmap;

/**
 * Testcase for RecordState
 *
 * @see   xp://unittest.mock.RecordState
 */
class RecordStateTest extends \unittest\TestCase {
  private 
    $sut            = null,
    $expectationMap = null;
  
  /**
   * Creates the fixture
   */
  public function setUp() {
    $this->expectationMap= new Hashmap();
    $this->sut= new RecordState($this->expectationMap);
  }
    
  #[@test, @expect('lang.IllegalArgumentException')]
  public function expectationMapRequiredOnCreate() {
    new RecordState(null);
  }
  
  #[@test]
  public function canCreate() {
    new RecordState(new Hashmap());
  }

  #[@test]
  public function canHandleInvocation() {
    $this->sut->handleInvocation('methodName', null);
  }

  #[@test]
  public function newExpectationCreatedOnHandleInvocation() {
    $this->sut->handleInvocation('foo', null);
    $this->assertEquals(1, $this->expectationMap->size());
    $expectationList= $this->expectationMap->get('foo');
    $this->assertInstanceOf('unittest.mock.ExpectationList', $expectationList);
    $this->assertInstanceOf('unittest.mock.Expectation', $expectationList->getNext(array()));
  }

  #[@test]
  public function newExpectationCreatedOnHandleInvocation_twoDifferentMethods() {
    $this->sut->handleInvocation('foo', null);
    $this->sut->handleInvocation('bar', null);
    $this->assertInstanceOf('unittest.mock.Expectation', $this->expectationMap->get('foo')->getNext(array()));
    $this->assertInstanceOf('unittest.mock.Expectation', $this->expectationMap->get('bar')->getNext(array()));
  }

  #[@test]
  public function newExpectationCreatedOn_EACH_HandleInvocationCall() {
    $this->sut->handleInvocation('foo', null);
    $this->sut->handleInvocation('foo', null);
    $expectationList= $this->expectationMap->get('foo');

    $this->assertInstanceOf('unittest.mock.ExpectationList', $expectationList);
    $this->assertInstanceOf('unittest.mock.Expectation', $expectationList->getNext(array()));
    $this->assertInstanceOf('unittest.mock.Expectation', $expectationList->getNext(array()));
  }

  #[@test]
  public function method_call_should_set_arguments() {
    $args= array('1', 2, 3.0);
    $this->sut->handleInvocation('foo', $args);

    $expectationList= $this->expectationMap->get('foo');
    $expectedExpectaton= $expectationList->getNext($args);
    $this->assertInstanceOf('lang.Object', $expectedExpectaton);
  }
}
